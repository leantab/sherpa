<?php

namespace Leantab\Sherpa\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $user_id
 * @property int $game_id
 * @property string $company_name
 * @property int $avatar
 * @property bool $bankrupt
 * @property bool $dismissed
 * @property array $ceo_parameters
 * @property array $results
 * @property \Carbon\Carbon $created_at
 * @property bool $is_funded
 * @property int $final_position
 * @property bool $is_winner
 * @property bool $is_bot
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\User $user
 */
class GameUser extends Pivot
{
  protected $table = "game_user";

  protected $foreignKey = ["game_id", "user_id"];

  protected $guarded = [];

  protected $casts = [
    'ceo_parameters' => 'array',
    'results' => 'array',
  ];
}
