<?php

namespace Leantab\Sherpa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Sherpa service.
 * 
 * @method static \Leantab\Sherpa\Sherpa getVersions()
 * @method static \Leantab\Sherpa\Sherpa getSchema()
 * @method static \Leantab\Sherpa\Sherpa getVariables()
 * @method static \Leantab\Sherpa\Sherpa getGovermentVariables()
 * @method static \Leantab\Sherpa\Sherpa getCeoVariables()
 * @method static \Leantab\Sherpa\Sherpa industry()
 * @method static \Leantab\Sherpa\Sherpa getGamees()
 * @method static \Leantab\Sherpa\Sherpa createGame()
 * @method static \Leantab\Sherpa\Sherpa addGoverment()
 * @method static \Leantab\Sherpa\Sherpa addCeo()
 * @method static \Leantab\Sherpa\Sherpa getGovermentParameters()
 * @method static \Leantab\Sherpa\Sherpa setGovermentParameters()
 * @method static \Leantab\Sherpa\Sherpa getCeoParameters()
 * @method static \Leantab\Sherpa\Sherpa setCeoParameters()
 * @method static \Leantab\Sherpa\Sherpa getGame()
 * @method static \Leantab\Sherpa\Sherpa getGameRanking()
 * @method static \Leantab\Sherpa\Sherpa deleteGame($game_id)
 * @method static \Leantab\Sherpa\Sherpa deleteCeo($game_id, $user_id)
 * @method static \Leantab\Sherpa\Sherpa reprocessGame($game_id, $stage)
 * @method static \Leantab\Sherpa\Sherpa processGame($game_id)
 * @method static \Leantab\Sherpa\Sherpa forceProcessGame($game_id)
 */
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
