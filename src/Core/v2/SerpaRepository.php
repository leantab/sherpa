<?php

namespace Core\v2;

use Leantab\Sherpa\Models\Game;

class SerpaRepository
{
    private Game $game;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function getMinPrice($game)
    {
        $min_price =  -3 * $game->results['stage_' . ($game->current_stage - 1)]['sd_price'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
        return ($min_price < 0) ? 10 : $min_price;
    }

    function getMaxPrice($game)
    {
        return 4 * $game->results['stage_' . ($game->current_stage - 1)]['sd_price'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
    }

    public function getMinCorpDebToPay($game, $ceo)
    {
        return 0;
    }

    public function getMaxCorpDebToPay($game, $ceo)
    {
        return round(($ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'] < 0) ? 0 : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'], 0);
    }

    public function getMaxCorpDebt($game, $ceo)
    {
        return ($ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'] < 0) ? 0 : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
    }
}