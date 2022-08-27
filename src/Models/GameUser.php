<?php

namespace Leantab\Sherpa\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GameUser extends Pivot
{
  protected $table = "match_user";

  protected $guarded = [];
  
  protected $casts = [
    'ceo_parameters' => 'array',
    'results' => 'array',
  ];
}
