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
        if ($game->current_stage == 0 || !array_key_exists('average_price', $game->results['stage_' . ($game->current_stage - 1)]) ) {
            return 10;
        }
        // $min_price =  -3 * $game->results['stage_' . ($game->current_stage - 1)]['sd_price'] + $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
        $min_price = 0.2 * $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
        return ($min_price < 0) ? 10 : $min_price;
    } 
}

if (!function_exists('getMaxPrice')) {
    function getMaxPrice($game)
    {
        if ($game->current_stage == 0 || !array_key_exists('average_price', $game->results['stage_' . ($game->current_stage - 1)]) ) {
            return 50000;
        }
        return 5 * $game->results['stage_' . ($game->current_stage - 1)]['average_price'];
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

if (!function_exists('getFinalCash')) {
    function getFinalCash($game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['final_cash'];
    }
}

if (!function_exists('getLineCredit')) {
    function getLineCredit($game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['line_credit'];
    }
}

if (!function_exists('getFinancialDebt')) {
    function getFinancialDebt($game, $ceo)
    {
        return $ceo->pivot->results['stage_' . ($game->current_stage - 1)]['financial_debt'];
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
        if ($game->current_stage > 1) {
            return $ceo->pivot->ceo_parameters['stage_' . ($game->current_stage - 1)];
        } else {
            return [
                'capital_inv' => 1,
                'new_debt' => 1,
                "taken_debt" => 1,
                "payed_debt" => 0,
                'design' => 1,
                'survey' => 1,
                'ibk' => 1,
                'mkt' => 1,
                'price' => 1,
                'production' => 1,
                'quality_control' => 'qc_start_up',
                'recycle' => 'recycle_sub_saharian_standards',
                'safety' => 'safety_1',
            ];
        }
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

if (!function_exists('getMinNewDebt')) {
    function getMinNewDebt($game, $ceo)
    {
        return round((getLineCredit($game, $ceo) + getFinancialDebt($game, $ceo)) * -1, 0);
    }
}
