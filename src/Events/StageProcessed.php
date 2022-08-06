<?php

namespace CompanyHike\Sherpa\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use CompanyHike\Sherpa\Models\Game;

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
