<?php

namespace Leantab\Sherpa\Services;

use Leantab\Sherpa\Models\Game;
use Leantab\Sherpa\Events\StageProcessed;
use Log;
use DB;
use Leantab\Sherpa\Jobs\ProcessUserStats;

class ProcessStageService
{
    public $core;
    public $version;

    public function __construct(Game $game)
    {
        $this->version = $game->version;

        include(__DIR__ . '/../Core/' . $this->version . '/functions.php');

        if (!$game->hasAllCeoDecisions() && $game->current_stage > 0) {
            Log::error('[Sherpa->ProcessStage] - Error: No estan definidas las decisiones de los CEO');
            dd('Not all CEO decisions are defined');
        }
        if (!$game->hasGovermentDecisions() && $game->current_stage > 0) {
            Log::error('[Sherpa->ProcessStage] - Error: No estan definidas las decisiones de Gobierno');
            dd('Not all Goverment decisions are defined');
        }

        if ($this->version == 'v1') {
            $this->core = new \Leantab\Sherpa\Core\v1\CoreV1($game);
        }elseif ($this->version == 'v2') {
            $this->core = new \Leantab\Sherpa\Core\v2\Core($game);
        }
    }

    public function processStage()
    {
        try {
            $this->core->process();
            $this->save();
        } catch (\Exception $e) {
            Log::error('[Sherpa->ProcessStage] - ' . $e->getMessage(), [
                'game_id' => $this->core->game->id
            ]);

            dd($e->getMessage());
        }
    }

    private function save()
    {
        try {
            DB::beginTransaction();

            if ($this->core->game->results) {
                $results = $this->core->game->results;
            } else {
                $results = [];
            }

            $results['stage_' . $this->core->stage] = $this->core->global;


            $this->core->game->update([
                'results' => $results
            ]);

            if ($this->core->game->stages == $this->core->stage) {
                $this->core->game->status_id = 3;
            } else {
                $this->core->game->current_stage = $this->core->stage + 1;
            }
            $this->core->game->save();

            foreach ($this->core->game->ceos as $ceo) {

                if ($this->core->game->results) {
                    $results = $ceo->pivot->results;
                } else {
                    $results = [];
                }

                $results['stage_' . $this->core->stage] = $this->core->company[$ceo->id];

                $pivot_data_update = [
                    'results' => $results
                ];

                if (isset($this->core->company[$ceo->id]['bankrupt']) && $this->core->company[$ceo->id]['bankrupt'] === true) {
                    $pivot_data_update['bankrupt'] = true;
                }

                if (isset($this->core->company[$ceo->id]['dismissed']) && $this->core->company[$ceo->id]['dismissed'] === true) {
                    $pivot_data_update['dismissed'] = true;
                }

                $ceo->pivot->update($pivot_data_update);

                ProcessUserStats::dispatch($ceo, $this->core->game);

                $ceo->save();
            }

            DB::commit();

            event(new StageProcessed($this->core->game, ($this->core->stage + 1)));
        } catch (\Exception $e) {
            DB::rollBack();

            dd("[Sherpa - ProcessStage]", [
                'game_id' => $this->core->game->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}