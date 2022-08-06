<?php

namespace CompanyHike\Sherpa\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use CompanyHike\Sherpa\Models\Match;

class StageProcessed
{
    use Dispatchable, SerializesModels;

    public $match;
    public $stage;

    public function __construct(Match $match, $stage)
    {

        $this->match = $match;
        $this->stage = $stage;
    }
}