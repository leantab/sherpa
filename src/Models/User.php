<?php

namespace Leantab\Sherpa\Models;


use Illuminate\Database\Eloquent\Model;

class User extends Model
{

  public function goverment_games()
  {
    return $this->hasMany('Leantab\Sherpa\Models\Game', 'goverment_id');
  }

  public function ceo_games()
  {
    return $this->belongsToMany('Leantab\Sherpa\Models\Game')->using('\Leantab\Sherpa\Models\GameUser')->withPivot(['company_name', 'avatar', 'bankrupt', 'dismissed', 'ceo_parameters', 'results', 'created_at']);
  }
}
