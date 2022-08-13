<?php

namespace Leantab\Sherpa\Facades;

use Illuminate\Support\Facades\Facade;

class Sherpa extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'sherpa';
    }
}
