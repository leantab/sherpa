<?php

namespace Leantab\Sherpa\Models;

use Arr;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Game
 * @package Leantab\Sherpa\Models
 * @property int $id
 * @property int $goverment_id
 * @property int $status_id
 * @property int $current_stage
 * @property array $game_parameters
 * @property array $goverment_parameters
 * @property array $results
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $goverment
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $ceos
 * @method bool hasAllCeoDecisions()
 * @method bool hasGovermentDecisions()
 * @method bool isGoverment($user_id)
 * @method bool isCeo($user_id)
 * @method bool isActive()
 * @method bool isCompleted()
 * @method string getPlayerPosition($user_id)
 */
class Game extends Model
{

    protected $table = 'games';

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
        return $this->belongsToMany(User::class, 'game_user', 'game_id', 'user_id')
            ->using('\Leantab\Sherpa\Models\GameUser')
            ->withPivot(['company_name', 'avatar', 'bankrupt', 'dismissed', 'ceo_parameters', 'results', 'created_at', 'is_funded']);
            /* ->withPivot([
                'company_name', 
                'avatar', 
                'bankrupt',
                'dismissed', 
                'ceo_parameters', 
                'results', 
                'created_at', 
                'is_funded',
                'final_position',
                'is_winner',
                'is_bot'
            ]);*/
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
        if ($this->current_stage == 0) {
            return true;
        }

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
            if ($this->current_stage == 0) {
                return true;
            }
            
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
