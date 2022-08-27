<?php

namespace Leantab\Sherpa\Models;

use Arr;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{

    protected $table = 'matches';

    protected $guarded = [];

    protected $casts = [
        'game_parameters' => 'array',
        'goverment_parameters' => 'array',
        'results' => 'array',
    ];

    protected $appends = ['players', 'stages', 'name'];


    public function goverment()
    {
        return $this->hasOne('App\Models\User');
    }

    public function ceos()
    {
        return $this->belongsToMany('Leantab\Sherpa\Models\User')
            ->using('\Leantab\Sherpa\Models\GameUser')
            ->withPivot(['company_name', 'avatar', 'bankrupt', 'dismissed', 'ceo_parameters', 'results', 'created_at']);
    }

    public function getPlayersAttribute()
    {
        return Arr::get($this->game_parameters, 'players', null);
    }

    public function getNameAttribute()
    {
        return Arr::get($this->game_parameters, 'name', null);
    }

    public function getStagesAttribute()
    {
        return Arr::get($this->game_parameters, 'stages', null);
    }

    public function getTypeAttribute()
    {
        return Arr::get($this->game_parameters, 'type', null);
    }

    public function hasAllCeoDecisions()
    {
        $totalDecisions = 0;
        foreach ($this->ceos as $c) {
            if (isset($c->pivot->ceo_parameters['stage_' . $this->current_stage]) || $c->pivot->bankrupt || $c->pivot->dismissed) {
                $totalDecisions++;
            }
        }

        if ($totalDecisions == $this->players) {
            return true;
        } else {
            return false;
        }
    }

    public function hasGovermentDecisions()
    {
        if ($this->game_parameters['type'] == 'scenario') {
            return true;
        } else {
            if (isset($this->goverment_parameters['stage_' . $this->current_stage])) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function isGoverment($user_id)
    {
        return ($this->goverment_id == $user_id);
    }

    public function isCeo($user_id)
    {
        $user = $this->ceos()->where('user_id', $user_id)->first();
        if ($user) {
            return true;
        } else {
            return false;
        }
    }

    public function isActive()
    {
        return ($this->status_id == 2);
    }

    public function isCompleted()
    {
        return ($this->status_id == 3);
    }

    public function getPlayerPosition($user_id)
    {
        if (!$this->isCeo($user_id) || $this->current_stage == 0) {
            return '-';
        } else {
            $user = $this->ceos()->where('user_id', $user_id)->first();
            if ($this->status_id == 3) {
                return $user->results['stage_' . $this->current_stage]['company_ranking'];
            } else {
                return $user->results['stage_' . ($this->current_stage - 1)]['company_ranking'];
            }
        }
    }
}
