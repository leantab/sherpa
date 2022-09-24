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
 * @method static getSchema()
 * @method static getVariables()
 * @method static getGovermentVariables()
 * @method static getCeoVariables()
 * @method static industry()
 * @method static Collection|Games[] getGames($user_id, $segment_id)
 * @method static Game createGame($version, $game_parameters, $creator_id, $segment_id)
 * @method static addGoverment($game_id, $user_id)
 * @method static addCeo($game_id, $user_id, $company_name, $avatar)
 * @method static addSimpleCeo($game_id, $user_id, $company_name, $avatar)
 * @method static getGovermentParameters($game_id, $stage)
 * @method static setGovermentParameters($game_id, $goverment_parameters)
 * @method static getCeoParameters($game_id, $stage, $user_id)
 * @method static setCeoParameters($game_id, $ceo_parameters, $user_id)
 * @method static Game getGame($game_id)
 * @method static getGameRanking($game_id, $stage)
 * @method static deleteGame($game_id)
 * @method static deleteCeo($game_id, $user_id)
 * @method static reprocessGame($game_id, $stage)
 * @method static processGame($game_id)
 * @method static forceProcessGame($game_id)
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
