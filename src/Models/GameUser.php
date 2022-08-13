<?php

namespace Leantab\Sherpa\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GameUser extends Pivot
{
  protected $casts = [
    'ceo_parameters' => 'array',
    'results' => 'array',
  ];
}
