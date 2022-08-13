<?php

namespace Leantab\Sherpa\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Leantab\Sherpa\Models\Game;

class StageProcessed
{
    use Dispatchable, SerializesModels;

    public $game;
    public $stage;

    public function __construct(Game $game, $stage)
    {

        $this->game = $game;
        $this->stage = $stage;
    }
}
