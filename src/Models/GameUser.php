<?php

namespace Leantab\Sherpa\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

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
