<?php

namespace Leantab\Sherpa\Facades;

use Illuminate\Support\Facades\Facade;
use \Leantab\Sherpa\Models\Game;
use \Leantab\Sherpa\Models\GameUser;
use Illuminate\Support\Collection;

/**
 * Facade for the Sherpa service.
 *
 * @method static getVersions()
 * @method static array getSchema()
 * @method static array getVariables()
 * @method static array getGovermentVariables()
 * @method static array getCeoVariables()
 * @method static bool industry()
 * @method static array|Games[] getGames($user_id, $segment_id)
 * @method static Game createGame($version, $game_parameters, $creator_id, $segment_id)
 * @method static Game createTestGameScenario()
 * @method static Game createTestGameConqueror()
 * @method static bool addGoverment($game_id, $user_id)
 * @method static bool addCeo($game_id, $user_id, $company_name, $avatar, $is_funded = false)
 * @method static bool addSimpleCeo($game_id, $user_id, $company_name, $avatar)
 * @method static array getGovermentParameters($game_id, $stage)
 * @method static bool setGovermentParameters($game_id, $goverment_parameters)
 * @method static array getCeoParameters($game_id, $stage, $user_id)
 * @method static bool setCeoParameters($game_id, $ceo_parameters, $user_id)
 * @method static Game getGame($game_id)
 * @method static array getGameRanking($game_id, $stage)
 * @method static bool deleteGame($game_id)
 * @method static bool deleteCeo($game_id, $user_id)
 * @method static void reprocessGame($game_id, $stage)
 * @method static void processGame($game_id)
 * @method static void forceProcessGame($game_id)
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
