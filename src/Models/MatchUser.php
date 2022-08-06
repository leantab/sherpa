<?php

namespace CompanyHike\Sherpa\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MatchUser extends Pivot
{
  protected $casts = [
    'ceo_parameters' => 'array',
    'results' => 'array',
  ];
}
