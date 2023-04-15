<?php

namespace Leantab\Sherpa\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Leantab\Sherpa\Models\Game;
use Leantab\Sherpa\Events\StageProcessed;

use Log;
use DB;

class ProcessStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $core;
    public $version;

    public function __construct(Game $game)
    {
        $this->version = $game->version;

        include(__DIR__ . '/../Core/' . $this->version . '/functions.php');

        if (!$game->hasAllCeoDecisions() && $game->current_stage > 0) {
            Log::error('[Sherpa->ProcessStage] - Error: No estan definidas las decisiones de los CEO');
            exit;
        }
        if (!$game->hasGovermentDecisions() && $game->current_stage > 0) {
            Log::error('[Sherpa->ProcessStage] - Error: No estan definidas las decisiones de Gobierno');
            exit;
        }

        if ($this->version == 'v1') {
            $this->core = new \Leantab\Sherpa\Core\v1\Core($game);
        }elseif ($this->version == 'v2') {
            $this->core = new \Leantab\Sherpa\Core\v2\Core($game);
        }
    }

    public function handle()
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
