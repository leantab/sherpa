<?php

namespace CompanyHike\Sherpa\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use CompanyHike\Sherpa\Models\Match;
use CompanyHike\Sherpa\Events\StageProcessed;

use Log;
use DB;

class ProcessStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $core;
        
    public function __construct(Match $match)
    {

        include(__DIR__ . '/../Core/' . $match->version . '/core.php');
        include(__DIR__ . '/../Core/' . $match->version . '/functions.php');
        include(__DIR__ . '/../Core/' . $match->version . '/tips.php');

        if (!$match->hasAllCeoDecisions() && $match->current_stage > 0) {
            Log::error('[Sherpa->ProcessStage] - Error: No estan definidas las decisiones de los CEO');
            exit;
        } 
        if (!$match->hasGovermentDecisions() && $match->current_stage > 0) {
            Log::error('[Sherpa->ProcessStage] - Error: No estan definidas las decisiones de Gobierno');
            exit;
        }

        $this->core = new \CompanyHike\Sherpa\Core($match);

        $this->core->process();

    }

    public function handle()
    {
        
        try{
            $this->core->process();
            $this->save();
           
        }catch(\Exception $e){
            Log::error('[Sherpa->ProcessStage] - '. $e->getMessage(), [
                'match_id' => $this->core->match->id                
                ]);

            dd($e->getMessage());
        }
    }

    private function save(){

        try{
            DB::beginTransaction();

            if ($this->core->match->results) {
                $results = $this->core->match->results;
            } else {
                $results = [];
            }
    
            $results['stage_'.$this->core->stage] = $this->core->global;
    
    
            $this->core->match->update([
                'results' => $results
            ]);

            if ($this->core->match->stages == $this->core->stage) {
                $this->core->match->status_id = 3;
            } else {
                $this->core->match->current_stage = $this->core->stage + 1;
            }
            $this->core->match->save();
            
            foreach ($this->core->match->ceos as $ceo) {
    
                if ($this->core->match->results) {
                    $results = $ceo->pivot->results;
                } else {
                    $results = [];
                }
                
                $results['stage_'.$this->core->stage] = $this->core->company[$ceo->id];

                $pivot_data_update = [
                    'results' => $results
                ];

                if(isset($this->core->company[$ceo->id]['bankrupt']) && $this->core->company[$ceo->id]['bankrupt'] === true){
                    $pivot_data_update['bankrupt'] = true;
                }                    

                if(isset($this->core->company[$ceo->id]['dismissed']) && $this->core->company[$ceo->id]['dismissed'] === true){
                    $pivot_data_update['dismissed'] = true;
                }                    

                $ceo->pivot->update($pivot_data_update);

                
                $ceo->save();
    
            }

            

            DB::commit();

            event(new StageProcessed($this->core->match, ($this->core->stage + 1)));
        }catch(\Exception $e){
            DB::rollBack();

            dd("[Sherpa - ProcessStage]", [
                'match_id' => $this->core->match->id,
                'error' => $e->getMessage()
            ]);
        }
    }

}
