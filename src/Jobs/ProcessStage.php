<?php

namespace Leantab\Sherpa\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Leantab\Sherpa\Models\Game;
use Leantab\Sherpa\Services\ProcessStageService;

class ProcessStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Game $game;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function handle()
    {
        $service = new ProcessStageService($this->game);
        $service->processStage();
    }

}
