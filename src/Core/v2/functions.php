<?php

if (!function_exists('sd_square')) {
    function sd_square($x, $mean)
    {
        return pow($x - $mean, 2);
    }
}

if (!function_exists('sd')) {
    function sd($array)
    {
        // square root of sum of squares devided by N-1
        return sqrt(array_sum(array_map("sd_square", $array, array_fill(0, count($array), (array_sum($array) / count($array))))) / (count($array)));
    }
}

if (!function_exists('getMinPrice')) {
    function getMinPrice($game)
    {
        $min_price =  -3 * $game->results['stage_' . ($game->current_stage - 1)]['sd'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
        return ($min_price < 0) ? 10 : $min_price;
    }
}

if (!function_exists('getMaxPrice')) {
    function getMaxPrice($game)
    {
        return 4 * $game->results['stage_' . ($game->current_stage - 1)]['sd'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
    }
}

if (!function_exists('getMinCorpDebToPay')) {
    function getMinCorpDebToPay($game, $ceo)
    {
        return 0;
    }
}

if (!function_exists('getMaxCorpDebToPay')) {
    function getMaxCorpDebToPay($game, $ceo)
    {
        return round(($ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'] < 0) ? 0 : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'], 0);
    }
}

if (!function_exists('getMaxCorpDebt')) {
    function getMaxCorpDebt($game, $ceo)
    {
        return ($ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'] < 0) ? 0 : $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
    }
}

if (!function_exists('getMaxCapitalInv')) {
    function getMaxCapitalInv($game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['final_cash'] - $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
    }
}

if (!function_exists('getTotalFunds')) {
    function getTotalFunds($game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['total_funds'];
    }
}

if (!function_exists('checkTotalFunds')) {
    function checkTotalFunds($game, $ceo, $input)
    {
        $total_funds = $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['total_funds'];
        return (($input['mkt'] ?? 0) + ($input['survey'] ?? 0) + ($input['design'] ?? 0) + ($input['ibk'] ?? 0) + ($input['capital_inv'] ?? 0) + ($input['corp_debt'] ?? 0) <= $total_funds);
    }
}

if (!function_exists('forceStageCopyCeoDecisions')) {
    function forceStageCopyCeoDecisions($game, $ceo)
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
}


if (!function_exists('checkDebtsFunds')) {
    function checkDebtsFunds($game, $ceo, $input)
    {

        $debt = getMaxCorpDebToPay($game, $ceo);
        if ($input['capital_inv'] > 0 && $debt > 0 && $input['corp_debt_topay'] < $debt) {
            return false;
        } else {
            return true;
        }
    }
}
