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
 * @method static \Leantab\Sherpa\Sherpa getGames($user_id, $segment_id)
 * @method static \Leantab\Sherpa\Sherpa createGame($version, $game_parameters, $creator_id, $segment_id)
 * @method static \Leantab\Sherpa\Sherpa addGoverment($game_id, $user_id)
 * @method static \Leantab\Sherpa\Sherpa addCeo($game_id, $user_id, $company_name, $avatar)
 * @method static \Leantab\Sherpa\Sherpa addSimpleCeo($game_id, $user_id, $company_name, $avatar)
 * @method static \Leantab\Sherpa\Sherpa getGovermentParameters($game_id, $stage)
 * @method static \Leantab\Sherpa\Sherpa setGovermentParameters($game_id, $goverment_parameters)
 * @method static \Leantab\Sherpa\Sherpa getCeoParameters($game_id, $stage, $user_id)
 * @method static \Leantab\Sherpa\Sherpa setCeoParameters($game_id, $ceo_parameters, $user_id)
 * @method static \Leantab\Sherpa\Sherpa getGame(@param int $game_id) @return \Leantab\Sherpa\Models\Game
 * @method static \Leantab\Sherpa\Sherpa getGameRanking($game_id, $stage)
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
