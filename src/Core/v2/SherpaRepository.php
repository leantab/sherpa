<?php

namespace Core\v2;

use Leantab\Sherpa\Models\Game;

class SherpaRepository
{
    public static function getMinPrice(Game $game): float|int
    {
        $min_price =  -3 * $game->results['stage_' . ($game->current_stage - 1)]['sd_price'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
        return ($min_price < 0) ? 10 : $min_price;
    }

    public static function getMaxPrice(Game $game): float|int
    {
        return 4 * $game->results['stage_' . ($game->current_stage - 1)]['sd_price'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
    }

    public static function getMinCorpDebToPay(Game $game, $ceo): float|int
    {
        return 0;
    }

    public static function getMaxCorpDebToPay(Game $game, $ceo): float|int
    {
        return round(($ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'] < 0) ? 0 : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'], 0);
    }

    public static function getMaxCorpDebt($game, $ceo): float|int
    {
        return ($ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'] < 0) ? 0 : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
    }

    public static function getMaxCapitalInv(Game $game, $ceo): float|int
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['final_cash'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
    }

    public static function getFinalCash(Game $game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['final_cash'];
    }

    public static function getLineCredit(Game $game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'];
    }

    public static function getFinancialDebt(Game $game, $ceo): float|int
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
    }

    public static function getTotalFunds(Game $game, $ceo): float|int
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['total_funds'];
    }

    public static function checkTotalFunds(Game $game, $ceo, array $input): bool
    {
        $total_funds = $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['total_funds'];
        return (($input['mkt'] ?? 0) + ($input['survey'] ?? 0) + ($input['design'] ?? 0) + ($input['ibk'] ?? 0) + ($input['capital_inv'] ?? 0) + ($input['corp_debt'] ?? 0) <= $total_funds);
    }

    public static function forceStageCopyCeoDecisions(Game $game, $ceo): array
    {
        return [
            'capital_inv' => ($game->current_stage > 1) ? $ceo->pivot->ceo_parameters['stage_' . ($game->current_stage - 1)]['capital_inv'] : 0,
            'corp_debt' => ($game->current_stage > 1) ? $ceo->pivot->ceo_parameters['stage_' . ($game->current_stage - 1)]['corp_debt'] : 0,
            'corp_debt_topay' => ($game->current_stage > 1) ? $ceo->pivot->ceo_parameters['stage_' . ($game->current_stage - 1)]['corp_debt_topay'] : 0,
            'design' => ($game->current_stage > 1) ? $ceo->pivot->ceo_parameters['stage_' . ($game->current_stage - 1)]['design'] : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['id'] / 2,
            'survey' => ($game->current_stage > 1) ? $ceo->pivot->ceo_parameters['stage_' . ($game->current_stage - 1)]['survey'] : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['id'] / 2,
            'ibk' => $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['ibk'],
            'mkt' => $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['mkt'],
            'price' => $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['price'],
            'production' => $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['production'],
            'quality_control' => ($game->current_stage > 1) ? $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['quality_control'] : 'qc_start_up',
            'recycle' => ($game->current_stage > 1) ? $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['recycle'] : 'recycle_sub_saharian_standards',
            'safety' => ($game->current_stage > 1) ? $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['safety'] : 'safety_1',
        ];
    }

    public static function checkDebtsFunds(Game $game, $ceo, array $input): bool
    {

        $debt = getMaxCorpDebToPay($game, $ceo);
        if ($input['capital_inv'] > 0 && $debt > 0 && $input['corp_debt_topay'] < $debt) {
            return false;
        } else {
            return true;
        }
    }
}