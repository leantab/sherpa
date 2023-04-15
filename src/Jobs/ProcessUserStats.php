<?php

namespace Leantab\Sherpa\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Leantab\Sherpa\Models\Game;
use Leantab\Sherpa\Events\StageProcessed;

class ProcessStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*	                    
        games	                n de partidas que completo el usuario	    COUNTIF(n,">0")
        league	                SI(games>9,SI(games>29,"sherpa","hiker"),"tourist")	> 29 sherpa, 10-29 Hiker < 10 tourist
        game_index	            ln(2*games)	
        historic_position_avg	average (company_ranking)	
        sd_position	            sd (company_ranking)	
        cv_position	            min(1;(sd_position / position_avg))
        user_best_position	    MIN (company_ranking)	
        user_index	            (100 / position_avg) * (1 - cv_position/2)	
        ranking_points	        user_index * game_index	
        user_ranking	        JERARQUIA(ranking_points)	
        user_avg_price	        promedio(price t, price t-1, price t-n)	
    */

    public function handle()
    {
        //
    }
}