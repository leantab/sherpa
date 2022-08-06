<?php

namespace CompanyHike\Sherpa\Models;


use Illuminate\Database\Eloquent\Model;

class User extends Model 
{

  public function goverment_matches()
  {
    return $this->hasMany('CompanyHike\Sherpa\Models\Match', 'goverment_id');
  }

  public function ceo_matches()
  {
    return $this->belongsToMany('CompanyHike\Sherpa\Models\Match')->using('\CompanyHike\Sherpa\Models\MatchUser')->withPivot(['company_name', 'avatar', 'bankrupt', 'dismissed', 'ceo_parameters', 'results', 'created_at']);
  }


  

}
