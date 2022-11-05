<?php

namespace Leantab\Sherpa\Models;


use Illuminate\Database\Eloquent\Model;

class User extends Model
{

    protected $guarded = [];
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $hidden = ['password'];

    public function goverment_games()
    {
        return $this->hasMany(Game::class, 'goverment_id');
    }

    public function ceo_games()
    {
        return $this->belongsToMany(Game::class, 'game_user', 'user_id', 'game_id')
            ->using('\Leantab\Sherpa\Models\GameUser')
            ->withPivot(['company_name', 'avatar', 'bankrupt', 'dismissed', 'ceo_parameters', 'results', 'created_at', 'is_funded']);
    }
}
