<?php

namespace CompanyHike\Sherpa\Models;


use Illuminate\Database\Eloquent\Model;

class User extends Model
{

  public function goverment_games()
  {
    return $this->hasMany('CompanyHike\Sherpa\Models\Game', 'goverment_id');
  }

  public function ceo_games()
  {
    return $this->belongsToMany('CompanyHike\Sherpa\Models\Game')->using('\CompanyHike\Sherpa\Models\GameUser')->withPivot(['company_name', 'avatar', 'bankrupt', 'dismissed', 'ceo_parameters', 'results', 'created_at']);
  }
}
