<?php

namespace Leantab\Sherpa\Core\v2;

use Leantab\Sherpa\Models\Game;
use Exception;

class Core
{
    public $ceo;
    public Game $game;
    public $num_ceos;
    public $stage;
    public $global;
    public $industry;
    public $company;
    public $production_t0;
    public $random_ibk_t0;
    public $random_mkt_t0;
    public $risk_score_t0;
    public $safety_t0 = 1;
    public $recycle_t0 = 1;
    public $quality_control_t0 = 1;
    public $random_id_t0;
    public $random_new_debt_t0;
    public $new_debt_t0;

    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->stage = (int)($this->game->current_stage);
        $this->global = [];
        $this->company = [];
        $this->num_ceos = $this->game->ceos->count();

        if ($this->stage == 0) {
            $this->init_t0();
        }
        $this->industry = $this->getIndustryParameters();
        $this->ceo = $this->getCeoParameters();
    }

    public function process(): void
    {

        try {

            $this->global['risk_slope'] = 20;
            $this->global['random_ibk_t0'] = $this->random_ibk_t0;
            $this->global['random_id_t0'] = $this->random_id_t0;
            $this->global['reference_cost'] = $this->industry['reference_cost'];
            $this->global['annual_rounds'] = 12 / $this->game->game_parameters['accounting_period'];

            $this->global['positive_random_events'] = $this->industry['positive_random_events'];

            $this->global['price_sum'] = 0;
            $this->global['production_sum'] = 0;
            $this->global['cbu_sum'] = 0;
            $this->global['max_u_industry'] = 0;
            $this->global['employees_industry'] = 0;
            $this->global['ppe_industry'] = 0;
            $this->global['hirschman_index'] = 0;

            $this->global['final_price_points_sum'] = 0;
            $this->global['final_id_points_sum'] = 0;
            $this->global['final_mkt_points_sum'] = 0;

            if ($this->stage == 0) {
                $this->global['interest_rate'] = $this->game->game_parameters['interest_rate'];
            } else {
                $this->global['interest_rate'] = $this->game->goverment_parameters['stage_' . $this->stage]['interest_rate'];
                $this->global['delta_interest_rate'] = $this->global['interest_rate'] - $this->game->results['stage_' . ($this->stage - 1)]['interest_rate'];
            }

            $this->global['mkt_industry'] = 0;
            $this->global['ibk_industry'] = 0;
            $this->global['id_industry'] = 0;

            $arr_price = [];
            $arr_risk_scores = [1];
            $this->global['output_sum'] = 0;
            $this->global['industry'] = $this->industry;

            // Loop 1
            foreach ($this->game->ceos as $ceo) {
                if ($this->stage == 0) {
                    $this->company[$ceo->id]['ppe'] = $this->industry['ppe_t0'];
                } else {
                    $this->company[$ceo->id]['ppe'] = round($ceo->pivot->results['stage_' . ($this->stage - 1)]['ppe_next']);
                }
                $this->global['ppe_industry'] += $this->company[$ceo->id]['ppe'];

                if ($this->global['reference_cost'] < 1000) {
                    $this->company[$ceo->id]['worker_productivity'] = round(60 / log($this->global['reference_cost'] / 3), 2);
                } else {
                    $this->company[$ceo->id]['worker_productivity'] = round((40 / log($this->global['reference_cost'])) - ($this->global['reference_cost'] / 1500), 2);
                }

                $this->company[$ceo->id]['production'] = $this->ceo[$ceo->id]['production'];
                $this->global['production_sum'] += $this->company[$ceo->id]['production'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['risk_score'] = $this->risk_score_t0;
                } else {
                    $this->company[$ceo->id]['risk_score'] = log($this->ceo[$ceo->id]['recycle_value'] * $this->ceo[$ceo->id]['quality_control_value'] * $this->ceo[$ceo->id]['safety_value']);
                }
                $arr_risk_scores[] = $this->company[$ceo->id]['risk_score'];

                $this->company[$ceo->id]['risk_level'] = round(min(
                    $this->game->game_parameters['risk_limit_max'] / 100,
                    max(
                        1 - $this->company[$ceo->id]['risk_score'] / 3.3,
                        $this->game->game_parameters['risk_limit_min'] / 100
                    )
                ), 2);

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['event_prob'] = 0.001;
                } else {
                    //MAX(0.001%; {[(risk_slope^(2*production)) / (1+2*production)-1)] *risk_level } / 132)
                    $this->company[$ceo->id]['event_prob'] = max(
                        pow($this->global['risk_slope'], (2 * ($this->company[$ceo->id]['production'] / 100)))
                            / ((1 + 2 * ($this->company[$ceo->id]['production'] / 100)))
                            * $this->company[$ceo->id]['risk_level']
                            / 132,
                        0.001
                    );
                }

                $this->company[$ceo->id]['top_limit'] = round($this->num_ceos / $this->company[$ceo->id]['event_prob']);

                if ($ceo->pivot->bankrupt || $ceo->pivot->dismissed) {
                    $this->company[$ceo->id]['cbu'] = 1;
                } else {
                    //(reference_cost * (8^7) / (ppe_t * production)*(1-event_prob/2))
                    $this->company[$ceo->id]['cbu'] = round(
                        (
                            ($this->global['reference_cost'] * pow(8, 7)) /
                            ($this->company[$ceo->id]['ppe'] * ($this->company[$ceo->id]['production'] / 100)) * (1 - ($this->company[$ceo->id]['event_prob']  / 2))
                        ),
                        2
                    );
                }

                $this->global['cbu_sum'] += $this->company[$ceo->id]['cbu'];

                //worker_productivity * ((ppe_t ^ alfa))
                $this->company[$ceo->id]['max_u'] = round(
                    $this->company[$ceo->id]['worker_productivity'] * pow($this->company[$ceo->id]['ppe'], $this->industry['alfa']),
                    0
                );
                $this->global['max_u_industry'] += $this->company[$ceo->id]['max_u'];

                $this->company[$ceo->id]['u_prod'] = round(($this->company[$ceo->id]['production'] / 100) * $this->company[$ceo->id]['max_u'], 0);
                $this->company[$ceo->id]['output'] = $this->company[$ceo->id]['cbu'] * $this->company[$ceo->id]['u_prod'];
                $this->global['output_sum'] += $this->company[$ceo->id]['output'];

                // En el turno cero completo las decisiones simuladas de los CEO 
                if ($this->stage == 0) {
                    $this->ceo[$ceo->id]['price'] = round($this->game->game_parameters['price_t0_leverage'] * $this->industry['reference_cost']);
                    $this->ceo[$ceo->id]['mkt'] = round($this->random_mkt_t0 * $this->company[$ceo->id]['output'] / 100);
                    $this->ceo[$ceo->id]['id'] = round(($this->random_id_t0 / 100) * $this->company[$ceo->id]['output']);
                } else {
                    // La decision ID es la suma de las variables survey y design
                    $this->ceo[$ceo->id]['id'] = $this->ceo[$ceo->id]['survey'] + $this->ceo[$ceo->id]['design'];
                }
                $this->company[$ceo->id]['price'] = $this->ceo[$ceo->id]['price'];
                $this->company[$ceo->id]['mkt'] = $this->ceo[$ceo->id]['mkt'];
                $this->company[$ceo->id]['id'] = $this->ceo[$ceo->id]['id'];
                $this->company[$ceo->id]['recycle'] = $this->ceo[$ceo->id]['recycle'];
                $this->company[$ceo->id]['quality_control'] = $this->ceo[$ceo->id]['quality_control'];
                $this->company[$ceo->id]['safety'] = $this->ceo[$ceo->id]['safety'];
                $this->company[$ceo->id]['new_debt'] = $this->ceo[$ceo->id]['new_debt'];

                $this->global['price_sum'] += $this->ceo[$ceo->id]['price'];
                $arr_price[] = $this->ceo[$ceo->id]['price'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['current_stock'] = 0;
                } else {
                    $this->company[$ceo->id]['current_stock'] = $ceo->pivot->results['stage_' . ($this->stage - 1)]['final_stock'];
                }

                $this->company[$ceo->id]['offered_u'] = round($this->company[$ceo->id]['current_stock'] + $this->company[$ceo->id]['u_prod']);
                $this->company[$ceo->id]['vpe'] = round($this->company[$ceo->id]['cbu'] * $this->company[$ceo->id]['worker_productivity'], 2);
                // $this->company[$ceo->id]['max_w'] = $this->company[$ceo->id]['ppe'] / $this->company[$ceo->id]['vpe'] * log(10);
                $this->company[$ceo->id]['max_e'] = round($this->company[$ceo->id]['ppe'] / $this->company[$ceo->id]['vpe'], 2);
                $this->company[$ceo->id]['employees'] = round(($this->company[$ceo->id]['production'] / 100) * $this->company[$ceo->id]['max_e']);

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['price_change'] = $this->company[$ceo->id]['price'];
                    $this->company[$ceo->id]['delta_employees'] = $this->company[$ceo->id]['employees'];
                    $this->company[$ceo->id]['ibk'] = ($this->random_ibk_t0 / 100) * $this->company[$ceo->id]['output'];
                    $this->company[$ceo->id]['id'] = ($this->random_id_t0 / 100) * $this->company[$ceo->id]['output'];
                    $this->company[$ceo->id]['active_id'] = $this->company[$ceo->id]['id'] / 2;
                } else {
                    $this->company[$ceo->id]['price_change'] = $this->company[$ceo->id]['price'] - $ceo->pivot->results['stage_' . ($this->stage - 1)]['price'];
                    $this->company[$ceo->id]['delta_employees'] = $this->company[$ceo->id]['employees'] - $ceo->pivot->results['stage_' . ($this->stage - 1)]['employees'];
                    $this->company[$ceo->id]['ibk'] = $this->ceo[$ceo->id]['ibk'];
                    $this->company[$ceo->id]['id'] = $this->ceo[$ceo->id]['id'];

                    if ($this->stage === 0) {
                        $this->company[$ceo->id]['active_id'] = $this->company[$ceo->id]['id'] / 2;
                    } elseif ($this->stage >= 1 && $this->stage < 3) {
                        $this->company[$ceo->id]['active_id'] = max(0, $this->company[$ceo->id]['id'] * 0.5 + $ceo->pivot->results['stage_' . ($this->stage - 1)]['active_id']);
                    } else {
                        // MAX(id*0,5 + active_id_(t-1) - 0.5*active_id_(t-3) ; 0)
                        $this->company[$ceo->id]['active_id'] = max(0, $this->company[$ceo->id]['id'] * 0.5 + $ceo->pivot->results['stage_' . ($this->stage - 1)]['active_id'] - 0.5 * $ceo->pivot->results['stage_' . ($this->stage -  3)]['active_id']);
                    }
                }

                $this->global['mkt_industry'] += $this->company[$ceo->id]['mkt'];
                $this->global['id_industry'] += $this->company[$ceo->id]['id'];
                $this->global['ibk_industry'] += $this->company[$ceo->id]['ibk'];

                $this->global['employees_industry'] += $this->company[$ceo->id]['employees'];
                $this->company[$ceo->id]['salaries_expense'] = $this->game->game_parameters['salary'] * $this->company[$ceo->id]['employees'] * $this->game->game_parameters['accounting_period'];

                $this->company[$ceo->id]['necesary_funds'] = $this->company[$ceo->id]['ibk'] + $this->company[$ceo->id]['id'] + $this->company[$ceo->id]['mkt'] + $this->company[$ceo->id]['salaries_expense'] + $this->company[$ceo->id]['output'];
                $this->company[$ceo->id]['target'] = $this->company[$ceo->id]['offered_u'] * $this->ceo[$ceo->id]['price'];
            } // end foreach

            $this->global['average_production'] = $this->global['production_sum'] / $this->num_ceos;
            $this->global['average_cbu'] = $this->global['cbu_sum'] / $this->num_ceos;

            $this->global['average_price'] = $this->global['price_sum'] / $this->num_ceos;

            if ($this->stage == 0) {
                $this->global['sd_price'] = $this->global['average_price'] / 2;
            } else {
                $this->global['sd_price'] = sd($arr_price);
            }

            $this->global['output_industry'] = 0;
            $this->global['current_stock_industry'] = 0;
            $this->global['u_prod_industry'] = 0;
            $this->global['active_id_industry'] = 0;
            $this->global['target_industry'] = 0;
            $this->global['offered_u_industry'] = 0;

            // Loop 2
            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['ibk_industry'] = $this->global['ibk_industry'];
                $this->company[$ceo->id]['id_industry'] = $this->global['id_industry'];
                $this->company[$ceo->id]['mkt_industry'] = $this->global['mkt_industry'];

                $this->company[$ceo->id]['risk_profile'] = ($this->company[$ceo->id]['risk_score'] / max($arr_risk_scores)) * 100;

                $this->global['output_industry'] += $this->company[$ceo->id]['output'];
                $this->global['current_stock_industry'] += $this->company[$ceo->id]['current_stock'];
                $this->global['u_prod_industry'] += $this->company[$ceo->id]['u_prod'];
                $this->global['active_id_industry'] += $this->company[$ceo->id]['active_id'];
                $this->global['target_industry'] += $this->company[$ceo->id]['target'];
                $this->global['offered_u_industry'] += $this->company[$ceo->id]['offered_u'];
            }

            if ($this->global['active_id_industry'] == 0) {
                $this->global['active_id_industry'] = 1;
            }
            if ($this->global['offered_u_industry'] == 0) {
                $this->global['offered_u_industry'] = 1;
            }
            if ($this->global['target_industry'] == 0) {
                $this->global['target_industry'] = 1;
            }
            if ($this->global['output_industry'] == 0) {
                $this->global['output_industry'] = 1;
            }
            $this->global['added_offer'] = $this->global['output_industry'];

            $this->global['id_index_industry'] = 0;
            // loop 3
            foreach ($this->game->ceos as $ceo) {

                $this->company[$ceo->id]['share_target'] = $this->company[$ceo->id]['target'] / $this->global['target_industry'];
                $this->company[$ceo->id]['depreciation'] = $this->company[$ceo->id]['ppe'] * ($this->industry['depreciation_rate'] / 100) / $this->global['annual_rounds'];
                $this->company[$ceo->id]['offer_ratio'] = ($this->company[$ceo->id]['offered_u'] / $this->global['offered_u_industry']) * 100;

                $this->company[$ceo->id]['capex'] = round($this->company[$ceo->id]['ibk'] - $this->company[$ceo->id]['depreciation']);
                $this->company[$ceo->id]['ppe_next'] = round($this->company[$ceo->id]['ppe'] + $this->company[$ceo->id]['capex']);

                $this->global['id_sensibility'] = $this->industry['id_sensibility'];
                $this->global['price_sensibility'] = $this->industry['price_sensibility'];
                $this->company[$ceo->id]['pond_share_id'] = ($this->company[$ceo->id]['active_id'] / $this->global['active_id_industry']) * $this->industry['id_sensibility'];

                $this->company[$ceo->id]['id_index'] = (is_nan(log($this->company[$ceo->id]['pond_share_id'])) || log($this->company[$ceo->id]['pond_share_id']) < 0) ? 0 : log($this->company[$ceo->id]['pond_share_id']);
                $this->global['id_index_industry'] += $this->company[$ceo->id]['id_index'];
            }

            if ($this->global['id_index_industry'] == 0) {
                $this->global['id_index_industry'] = 1;
            }

            // loop 4
            $arr_corrected_prices = [];
            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['final_id_points'] = $this->company[$ceo->id]['id_index'] / $this->global['id_index_industry'] * $this->industry['p_id'];
                $this->global['final_id_points_sum'] += $this->company[$ceo->id]['final_id_points'];

                $this->company[$ceo->id]['corrected_price'] = max(0.5, log($this->ceo[$ceo->id]['price'] / $this->industry['price_sensibility']));
                $arr_corrected_prices[] = $this->company[$ceo->id]['corrected_price'];
            }

            $this->global['max_corrected_price'] = max($arr_corrected_prices);

            // loop 5
            $arr_corrected_prices = [];
            $this->global['price_index_industry'] = 0;
            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['price_index_user'] = $this->global['max_corrected_price'] / $this->company[$ceo->id]['corrected_price'];
                $this->global['price_index_industry'] += $this->company[$ceo->id]['price_index_user'];
            }

            // loop 6
            $this->global['active_mkt_industry'] = 0;
            $this->global['final_price_points_sum'] = 0;
            foreach ($this->game->ceos as $ceo) {
                //average_price(t-1) * (1 + price_control)
                // $this->company[$ceo->id]['limit_price'] =  ;

                $this->company[$ceo->id]['final_price_points'] = $this->company[$ceo->id]['price_index_user'] / $this->global['price_index_industry'] * $this->industry['p_price'];
                $this->global['final_price_points_sum'] += $this->company[$ceo->id]['final_price_points'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['active_mkt'] = $this->company[$ceo->id]['mkt'];
                } else {
                    //mkt + mkt(t-1)*0,25 + active_mkt_ind(t-1)*0,1
                    $this->company[$ceo->id]['active_mkt'] = $this->company[$ceo->id]['mkt'] + ($ceo->pivot->results['stage_' . ($this->stage - 1)]['mkt'] * 0.25) + ($this->game->results['stage_' . ($this->stage - 1)]['active_mkt_industry'] * 0.1);
                }

                $this->global['active_mkt_industry'] += $this->company[$ceo->id]['active_mkt'];

                $this->company[$ceo->id]['active_investements'] = $this->company[$ceo->id]['active_id'] + $this->company[$ceo->id]['active_mkt'];
            }

            if ($this->global['active_mkt_industry'] == 0) {
                $this->global['active_mkt_industry'] = 1;
            }

            $this->global['mkt_index_industry'] = 0;
            // loop 7
            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['active_mkt_share'] = $this->company[$ceo->id]['active_mkt'] / $this->global['active_mkt_industry'];
                $this->company[$ceo->id]['delta_mkt_share'] = $this->company[$ceo->id]['active_mkt_share'] - $this->company[$ceo->id]['share_target'];
                //MAX(LN((pMKT/n_players) + pMKT * delta_share_mkt); 0)
                $this->company[$ceo->id]['mkt_index'] = max(
                    log(($this->industry['p_mkt'] / $this->game->game_parameters['players']) + ($this->industry['p_mkt'] * $this->company[$ceo->id]['delta_mkt_share'])),
                    0
                );
                $this->global['mkt_index_industry'] += $this->company[$ceo->id]['mkt_index'];
            }

            // loop 8
            $this->global['total_points_industry'] = 0;
            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['final_mkt_points'] = $this->company[$ceo->id]['mkt_index'] / ($this->global['mkt_index_industry'] == 0 ? 0.1 : $this->global['mkt_index_industry']) * $this->industry['p_mkt'];
                $this->global['final_mkt_points_sum'] += $this->company[$ceo->id]['final_mkt_points'];

                if (!$ceo->pivot->bankrupt && !$ceo->pivot->dismissed) {
                    $this->company[$ceo->id]['total_points_player'] = $this->company[$ceo->id]['final_price_points'] + $this->company[$ceo->id]['final_id_points'] + $this->company[$ceo->id]['final_mkt_points'];
                } else {
                    $this->company[$ceo->id]['total_points_player'] = 0.001;
                }
                $this->global['total_points_industry'] += $this->company[$ceo->id]['total_points_player'];
            }

            $this->global['pms'] = $this->game->game_parameters['pms'];

            if ($this->stage == 0) {
                $this->global['delta_interest_rate'] = $this->global['interest_rate'];
                $this->global['added_demand'] = $this->global['output_industry'] * (1 + ($this->game->game_parameters['initial_eq'] / 100));
                $this->global['real_added_demand'] = $this->game->game_parameters['initial_eq'];
            } else {
                $this->global['delta_interest_rate'] = $this->global['interest_rate'] - $this->game->results['stage_' . ($this->stage - 1)]['interest_rate'];
                //added_demand (t-1) * (1+Δda) * (1- (Δi * pms))
                $this->global['added_demand'] = $this->game->results['stage_' . ($this->stage - 1)]['added_demand'] * (1 + $this->game->goverment_parameters['stage_' . $this->stage]['added_demand_variation'] / 100) * (1 - ($this->global['delta_interest_rate'] / 100 * $this->global['pms']));

                $this->global['real_added_demand'] = (($this->global['added_demand'] / $this->game->results['stage_' . ($this->stage - 1)]['added_demand']) - 1) * 100;
            }

            $this->global['pda'] = $this->global['added_demand'] / $this->global['added_offer'] * 100;
            $this->global['poa'] = $this->global['total_points_industry'];
            $this->global['u_dem_industry'] = $this->global['pda'] / $this->global['poa'] * $this->global['u_prod_industry'];
            $this->global['eoa'] = $this->global['u_prod_industry'] - $this->global['u_dem_industry'];


            // loop 9
            $this->global['sold_u_industry'] = 0;
            $this->global['total_revenue_industry'] = 0;
            $this->global['sum_total_revenue'] = 0;
            foreach ($this->game->ceos as $ceo) {
                //MAX(0, total_points_player / total_points_industry * u_dem_industry)
                $this->company[$ceo->id]['demand_u'] = max(round($this->company[$ceo->id]['total_points_player'] / $this->global['total_points_industry'] * $this->global['u_dem_industry'], 0), 0);
                $this->company[$ceo->id]['offer_surplus'] = $this->company[$ceo->id]['u_prod'] - $this->company[$ceo->id]['demand_u'];
                $this->company[$ceo->id]['sold_u'] = round(min($this->company[$ceo->id]['demand_u'], $this->company[$ceo->id]['offered_u']), 0);
                $this->global['sold_u_industry'] += $this->company[$ceo->id]['sold_u'];
                $this->company[$ceo->id]['unrealized_sales'] = ($this->company[$ceo->id]['demand_u'] - $this->company[$ceo->id]['sold_u']) * $this->ceo[$ceo->id]['price'];
                $this->company[$ceo->id]['total_revenue'] = $this->company[$ceo->id]['sold_u'] * $this->ceo[$ceo->id]['price'];
                $this->global['total_revenue_industry'] += $this->company[$ceo->id]['total_revenue'];
                $this->global['sum_total_revenue'] += $this->company[$ceo->id]['total_revenue'];
                $this->company[$ceo->id]['achieved_target'] = ($this->company[$ceo->id]['total_revenue'] / $this->company[$ceo->id]['target']) * 100;
                $this->company[$ceo->id]['cost_sold_goods'] = $this->company[$ceo->id]['sold_u'] * $this->company[$ceo->id]['cbu'];
                $this->company[$ceo->id]['gross_profit'] = $this->company[$ceo->id]['total_revenue'] - $this->company[$ceo->id]['cost_sold_goods'];
                $this->company[$ceo->id]['demand_surplus'] = ((($this->company[$ceo->id]['demand_u'] / $this->company[$ceo->id]['offered_u']) - 1) * 100);
                $this->company[$ceo->id]['offer_precision'] = (100 - abs($this->company[$ceo->id]['demand_surplus']));
             }

            $this->global['revenue_employees'] = $this->global['total_revenue_industry'] / (($this->global['employees_industry'] == 0) ? 1 : $this->global['employees_industry']);
            $this->global['sold_u_employees'] = $this->global['sold_u_industry'] / (($this->global['employees_industry'] == 0) ? 1 : $this->global['employees_industry']);


            // loop 10
            $this->global['final_stock_industry'] = 0;
            $this->global['inventories_sum'] = 0;
            $this->global['hirschman_sum'] = 0;

            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['market_share'] = ($this->company[$ceo->id]['sold_u'] / $this->global['sold_u_industry']) * 100;
                //Hirschman_index=(Σmarket_share^2(n)/ 2500) * 100             
                $this->global['hirschman_sum'] += pow($this->company[$ceo->id]['market_share'], 2);
                $this->company[$ceo->id]['final_stock'] = max(round($this->company[$ceo->id]['offer_surplus'] + $this->company[$ceo->id]['current_stock']), 0);
                $this->global['final_stock_industry'] += $this->company[$ceo->id]['final_stock'];
                $this->company[$ceo->id]['inventories'] = $this->company[$ceo->id]['final_stock'] * $this->company[$ceo->id]['cbu'];
                $this->global['inventories_sum'] += $this->company[$ceo->id]['inventories'];

                $this->company[$ceo->id]['loan_ratio'] = $this->game->game_parameters['loan_ratio'];
                
                $this->company[$ceo->id]['top_loan'] = ($this->company[$ceo->id]['loan_ratio'] * ($this->global['total_revenue_industry'] / $this->num_ceos)) / 100;
                
            }

            $this->global['inventories_industry'] = ($this->global['final_stock_industry'] == 0) ? '-' : $this->global['average_cbu'] / $this->global['final_stock_industry'];
            $this->global['hirschman_index'] = ($this->global['hirschman_sum'] / 2500) * 100;

            // loop 11
            $this->global['taxes_sum'] = 0;
            $this->global['total_cost_sum'] = 0;
            $arr_earnings = [];
            $arr_full_earnings = [];
            $arr_total_funds = [];
            foreach ($this->game->ceos as $ceo) {
                $this->company[$ceo->id]['stock_share'] = ($this->global['final_stock_industry'] == 0) ? '-' : ($this->company[$ceo->id]['final_stock'] / $this->global['final_stock_industry']) * 100;
                $this->company[$ceo->id]['pipeline_status'] = ($this->company[$ceo->id]['max_u'] == 0) ? '-' : ($this->company[$ceo->id]['final_stock'] / $this->company[$ceo->id]['max_u']) * 100;
                $this->company[$ceo->id]['stock_variation_u'] = $this->company[$ceo->id]['final_stock'] - $this->company[$ceo->id]['current_stock'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['opening_cash'] = $this->game->game_parameters['opening_cash_ratio'] * $this->company[$ceo->id]['ppe'];
                    $this->company[$ceo->id]['inventory_change'] = $this->company[$ceo->id]['inventories'] * -1;

                    // EVENTO RANDOM
                    $this->company[$ceo->id]['event'] = false;
                    $this->company[$ceo->id]['event_cost'] = 0;

                    //$this->company[$ceo->id]['new_debt'] = $this->new_debt_t0 * $this->company[$ceo->id]['output'];
                    $this->company[$ceo->id]['new_debt'] = min($this->company[$ceo->id]['necesary_funds'] - $this->company[$ceo->id]['opening_cash'], $this->company[$ceo->id]['top_loan']);
                    $this->company[$ceo->id]['financial_debt'] = $this->company[$ceo->id]['new_debt'];
                } else {
                    $this->company[$ceo->id]['opening_cash'] = $ceo->pivot->results['stage_' . ($this->stage - 1)]['final_cash'];

                    if ($this->company[$ceo->id]['event_prob'] > 0) {
                        $this->company[$ceo->id]['top_limit'] = round($this->game->game_parameters['players'] / ($this->company[$ceo->id]['event_prob'] / 100));
                        $this->company[$ceo->id]['trigger'] = rand(1, $this->company[$ceo->id]['top_limit']);
                    } else {
                        $this->company[$ceo->id]['top_limit'] = 0;
                        $this->company[$ceo->id]['trigger'] = 0;
                    }

                    if ($this->company[$ceo->id]['trigger'] <= $this->num_ceos) {
                        $this->company[$ceo->id]['event_id'] = 'event' . rand(1, 10);
                    } else {
                        $this->company[$ceo->id]['event_id'] = 'no_event';
                    }

                    if ($this->company[$ceo->id]['trigger'] > $this->game->game_parameters['players']) {
                        // Determina el tipo de evento tomando todas los items del array con menor valor
                        $arr_event_prob_factors = [
                            'recycle' => $this->company[$ceo->id]['recycle'],
                            'quality_control' => $this->company[$ceo->id]['quality_control'],
                            'safety' => $this->company[$ceo->id]['safety']
                        ];

                        $labels = array_keys($arr_event_prob_factors, min($arr_event_prob_factors));
                        $this->company[$ceo->id]['negative_event'] = $this->getRandomEvent($labels);
                        $this->company[$ceo->id]['event_cost'] = $this->company[$ceo->id]['cost_sold_goods'] * ($this->company[$ceo->id]['negative_event']['impact_cbv'] / 100);
                    } else {
                        $this->company[$ceo->id]['negative_event'] = false;
                        $this->company[$ceo->id]['event_cost'] = 0;
                    }

                    //financial_debt	= MAX (0; financial_debt (t-1) - financial_inv (t-1)+ new_debt)
                    $this->company[$ceo->id]['financial_debt'] = max(0, $ceo->pivot->results['stage_' . ($this->stage - 1)]['financial_debt'] - $ceo->pivot->results['stage_' . ($this->stage - 1)]['financial_inv'] + $this->company[$ceo->id]['new_debt']);
                    $this->company[$ceo->id]['inventory_change'] = $ceo->pivot->results['stage_' . ($this->stage - 1)]['inventories'] - $this->company[$ceo->id]['inventories'];
                    
                    if ($this->company[$ceo->id]['new_debt'] < 0 && abs($this->company[$ceo->id]['new_debt']) > $this->company[$ceo->id]['financial_debt']) {
                        $this->company[$ceo->id]['new_financial_inv'] = (-1 * $this->company[$ceo->id]['new_debt'] - $this->company[$ceo->id]['financial_debt']);
                    }
                }

                //MULTIPLICA POR TURNO + 1
                if ($this->stage == 0) {
                    $this->company[$ceo->id]['subsidy'] = ($this->company[$ceo->id]['delta_employees'] < 0)
                        ? $this->company[$ceo->id]['delta_employees'] * $this->game->game_parameters['compensation_cost'] * $this->game->game_parameters['salary'] * $this->stage
                        : $this->company[$ceo->id]['delta_employees'] * $this->game->game_parameters['compensation_cost'] * $this->game->game_parameters['salary'];
                } else {
                    $this->company[$ceo->id]['subsidy'] = ($this->company[$ceo->id]['delta_employees'] < 0)
                        ? $this->company[$ceo->id]['delta_employees'] * $this->game->goverment_parameters['stage_' . $this->stage]['compensation_cost'] * $this->game->game_parameters['salary'] * $this->stage
                        : $this->company[$ceo->id]['delta_employees'] * $this->game->goverment_parameters['stage_' . $this->stage]['compensation_cost'] * $this->game->game_parameters['salary'];
                }

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['financial_inv'] = ($this->company[$ceo->id]['financial_debt'] < 0) ? -1 * $this->company[$ceo->id]['financial_debt'] : 0;
                } else {
                    $this->company[$ceo->id]['financial_inv'] = -1 * min(0, $ceo->pivot->results['stage_' . ($this->stage - 1)]['financial_debt'] - $ceo->pivot->results['stage_' . ($this->stage - 1)]['financial_inv'] + $this->company[$ceo->id]['new_debt']);
                }

                //-financial_debt * SI (financial_debt>0; interest_rate; interest_rate - financial_cost) / (12/accounting_period)
                $this->company[$ceo->id]['financial_result_multiplier'] = ($this->company[$ceo->id]['financial_debt'] > 0)
                    ? ($this->global['interest_rate'] / 100)
                    : ($this->global['interest_rate'] - $this->game->game_parameters['financial_cost']) / 100;
                $neg_finacial_debt = -1 * $this->company[$ceo->id]['financial_debt'];

                //SI(financial_debt>0,-1*financial_debt*interest_rate, financial_inv*(interest_rate-financial_cost))/(12/accounting_period)
                if ($this->company[$ceo->id]['financial_debt'] > 0) {
                    $this->company[$ceo->id]['financial_result'] = $neg_finacial_debt * $this->global['interest_rate'] / 100;
                } else {
                    $this->company[$ceo->id]['financial_result'] = $this->company[$ceo->id]['financial_inv'] * (($this->global['interest_rate'] - $this->game->game_parameters['financial_cost']) / 100) / $this->global['annual_rounds'];
                }

                //output + salaries_expense + mkt + id + depreciation + event_cost - financial_result - subsidy
                $this->company[$ceo->id]['total_cost'] = $this->company[$ceo->id]['output']
                    + $this->company[$ceo->id]['salaries_expense']
                    + $this->company[$ceo->id]['mkt']
                    + $this->company[$ceo->id]['id']
                    + $this->company[$ceo->id]['depreciation']
                    + $this->company[$ceo->id]['event_cost']
                    - $this->company[$ceo->id]['financial_result']
                    - $this->company[$ceo->id]['subsidy'];
                $this->global['total_cost_sum'] += $this->company[$ceo->id]['total_cost'];

                $this->company[$ceo->id]['break_even'] = round($this->company[$ceo->id]['total_cost'] / $this->ceo[$ceo->id]['price']);
                $this->company[$ceo->id]['total_cost_u'] = $this->company[$ceo->id]['total_cost'] / $this->company[$ceo->id]['u_prod'];
                $this->company[$ceo->id]['margin_u'] = $this->ceo[$ceo->id]['price'] - $this->company[$ceo->id]['total_cost_u'];

                //gross_profit + subsidy - salaries_expense - mkt - id  - event_cost
                $this->company[$ceo->id]['ebitda'] = round($this->company[$ceo->id]['gross_profit']
                    + $this->company[$ceo->id]['subsidy']
                    - $this->company[$ceo->id]['salaries_expense']
                    - $this->ceo[$ceo->id]['mkt']
                    - $this->ceo[$ceo->id]['id']
                    - $this->company[$ceo->id]['event_cost'], 2);
                $this->company[$ceo->id]['ebit'] = round($this->company[$ceo->id]['ebitda'] - $this->company[$ceo->id]['depreciation'], 2);
                $this->company[$ceo->id]['uai'] = $this->company[$ceo->id]['ebit'] + $this->company[$ceo->id]['financial_result'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['profit_tax_amount'] = ($this->company[$ceo->id]['uai'] <= 0) ? 0 : $this->company[$ceo->id]['uai'] * ($this->game->game_parameters['profit_tax'] / 100);
                    $this->company[$ceo->id]['vat_tax_amount'] = ($this->company[$ceo->id]['uai'] <= 0) ? 0 : ($this->company[$ceo->id]['total_revenue'] - $this->company[$ceo->id]['total_cost']) * ($this->game->game_parameters['vat_tax'] / 100);
                } else {
                    $this->company[$ceo->id]['profit_tax_amount'] = ($this->company[$ceo->id]['uai'] <= 0) ? 0 : ($this->company[$ceo->id]['uai']) * ($this->game->goverment_parameters['stage_' . $this->stage]['profit_tax'] / 100);
                    $this->company[$ceo->id]['vat_tax_amount'] = ($this->company[$ceo->id]['uai'] <= 0) ? 0 : - ($this->company[$ceo->id]['total_revenue'] - $this->company[$ceo->id]['total_cost']) * ($this->game->goverment_parameters['stage_' . $this->stage]['vat_tax'] / 100);
                }

                $this->company[$ceo->id]['other_tax_amount'] = ($this->company[$ceo->id]['uai'] <= 0) ? 0 : ($this->company[$ceo->id]['uai']) * ($this->game->game_parameters['easy_business_score_tax'] / 100) * ($this->company[$ceo->id]['market_share'] / 100);
                $this->company[$ceo->id]['labour_tax'] = 1; // TODO
                $this->company[$ceo->id]['taxes'] = $this->company[$ceo->id]['profit_tax_amount'] + $this->company[$ceo->id]['vat_tax_amount'] + $this->company[$ceo->id]['labour_tax'] + $this->company[$ceo->id]['other_tax_amount'];
                $this->global['taxes_sum'] += $this->company[$ceo->id]['taxes'];
                $this->company[$ceo->id]['un'] = $this->company[$ceo->id]['uai'] - $this->company[$ceo->id]['taxes'];

                if ($ceo->pivot->bankrupt || $ceo->pivot->dismissed) {
                    $this->company[$ceo->id]['earnings'] = 1;
                } else {
                    if ($this->stage == 0) {
                        $this->company[$ceo->id]['earnings'] = $this->company[$ceo->id]['un'];
                    } else {
                        $this->company[$ceo->id]['earnings'] = $ceo->pivot->results['stage_' . ($this->stage - 1)]['earnings'] + $this->company[$ceo->id]['un'];
                    }
                }
                $arr_earnings[] = $this->company[$ceo->id]['earnings'];
                $arr_full_earnings[] = [$ceo->id => $this->company[$ceo->id]['earnings']];

                //opening_cash + un - capex + inventory_change + new_debt
                $this->company[$ceo->id]['final_cash'] = $this->company[$ceo->id]['opening_cash']
                    + $this->company[$ceo->id]['un']
                    - $this->company[$ceo->id]['capex']
                    + $this->company[$ceo->id]['inventory_change']
                    + $this->company[$ceo->id]['new_debt'];

                $this->company[$ceo->id]['current_assets'] = $this->company[$ceo->id]['final_cash'] + $this->company[$ceo->id]['financial_inv'] + $this->company[$ceo->id]['inventories'];
                $this->company[$ceo->id]['non_current_assets'] = $this->company[$ceo->id]['ppe_next'];
                $this->company[$ceo->id]['total_assets'] = $this->company[$ceo->id]['current_assets'] + $this->company[$ceo->id]['non_current_assets'];
                $this->company[$ceo->id]['fix_assets'] = $this->company[$ceo->id]['ppe_next'] + $this->company[$ceo->id]['inventories'];
                $this->company[$ceo->id]['net_assets'] = $this->company[$ceo->id]['total_assets'] - $this->company[$ceo->id]['financial_debt'];

                $this->company[$ceo->id]['non_current_liabilities'] = $this->company[$ceo->id]['financial_debt'];
                $this->company[$ceo->id]['current_liabilities'] = 1;
                $this->company[$ceo->id]['liabilities'] = $this->company[$ceo->id]['non_current_liabilities'] + $this->company[$ceo->id]['current_liabilities'];

                $this->company[$ceo->id]['current_liquity'] = $this->company[$ceo->id]['current_assets'] / $this->company[$ceo->id]['current_liabilities'];
                $this->company[$ceo->id]['dry_liquity'] = ($this->company[$ceo->id]['current_assets'] - $this->company[$ceo->id]['inventories']) / $this->company[$ceo->id]['current_liabilities'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['shareholders_equity'] = $this->company[$ceo->id]['opening_cash'] + $this->company[$ceo->id]['ppe'];
                } else {
                    $this->company[$ceo->id]['shareholders_equity'] = $ceo->pivot->results['stage_0']['opening_cash'] + $ceo->pivot->results['stage_0']['ppe'];
                }

                $this->company[$ceo->id]['equity'] = $this->company[$ceo->id]['shareholders_equity'] + $this->company[$ceo->id]['earnings'];
                $this->company[$ceo->id]['total_equity'] = $this->company[$ceo->id]['liabilities'] + $this->company[$ceo->id]['equity'];

                $this->company[$ceo->id]['line_credit'] = max($this->company[$ceo->id]['top_loan'] - $this->company[$ceo->id]['financial_debt'], 0);
                $this->company[$ceo->id]['total_funds'] = $this->company[$ceo->id]['final_cash'] + $this->company[$ceo->id]['line_credit'];
                $arr_total_funds[] = $this->company[$ceo->id]['total_funds'];
                $this->company[$ceo->id]['asset_indebtedness'] = ($this->company[$ceo->id]['liabilities'] <= 0) ? 0 : round($this->company[$ceo->id]['liabilities'] / $this->company[$ceo->id]['total_assets'], 2);
                $this->company[$ceo->id]['equity_indebtedness'] = ($this->company[$ceo->id]['liabilities'] <= 0) ? 0 : round($this->company[$ceo->id]['liabilities'] / $this->company[$ceo->id]['equity'], 2);
                $this->company[$ceo->id]['fix_assets_indebtedness'] = ($this->company[$ceo->id]['liabilities'] <= 0) ? 0 : round($this->company[$ceo->id]['liabilities'] / $this->company[$ceo->id]['fix_assets'], 2);
                
                if ($this->stage == 0 && $this->company[$ceo->id]['final_cash'] < 0) {
                    $this->company[$ceo->id]['line_credit'] = $this->company[$ceo->id]['final_cash'] * -3;
                    $this->company[$ceo->id]['total_funds'] = $this->company[$ceo->id]['final_cash'] + $this->company[$ceo->id]['line_credit'];
                }

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['demand_change'] = $this->company[$ceo->id]['demand_u'];
                    $this->company[$ceo->id]['demand_elasticity'] = 'N/A';
                } else {
                    $demand_u_t_ant = $ceo->pivot->results['stage_' . ($this->stage - 1)]['demand_u'] ?? 0;
                    $price_t_ant = $ceo->pivot->results['stage_' . ($this->stage - 1)]['price'] ?? 0;
                    $this->company[$ceo->id]['demand_change'] = round($this->company[$ceo->id]['demand_u'] - $demand_u_t_ant, 0);
                    if ($this->company[$ceo->id]['price_change'] == 0 || $this->company[$ceo->id]['demand_change'] == 0 || $demand_u_t_ant == 0 || $price_t_ant == 0) {
                        $this->company[$ceo->id]['demand_elasticity'] = 0.5;
                    } else {
                        $this->company[$ceo->id]['demand_elasticity'] = abs(round(($this->company[$ceo->id]['demand_change'] / $demand_u_t_ant) / ($this->company[$ceo->id]['price_change'] / $price_t_ant), 2));
                    }
                }

                $this->company[$ceo->id]['demand_type'] = ($this->company[$ceo->id]['demand_elasticity'] < 1) ? 'inelastic_demand' : 'elastic_demand';
                $this->company[$ceo->id]['fix_asset_rotation'] = round($this->company[$ceo->id]['total_revenue'] / $this->company[$ceo->id]['fix_assets'], 2);
                $this->company[$ceo->id]['total_asset_rotation'] = round($this->company[$ceo->id]['total_revenue'] / $this->company[$ceo->id]['total_assets'], 2);

                if (!$ceo->pivot->bankrupt && !$ceo->pivot->dismissed) {
                    $this->company[$ceo->id]['financial_impact'] = abs(round(($this->company[$ceo->id]['financial_result'] / $this->company[$ceo->id]['total_revenue']) * 100, 2));
                    $this->company[$ceo->id]['asset_net_profitability'] = round(($this->company[$ceo->id]['un'] / $this->company[$ceo->id]['total_assets']) * 100, 2);
                    $this->company[$ceo->id]['gross_profitability'] = round(($this->company[$ceo->id]['gross_profit'] / $this->company[$ceo->id]['total_revenue']) * 100, 2);
                    $this->company[$ceo->id]['sales_net_profitability'] = round(($this->company[$ceo->id]['un'] / $this->company[$ceo->id]['total_revenue']) * 100, 2);
                } else {
                    $this->company[$ceo->id]['financial_impact'] = 0;
                    $this->company[$ceo->id]['asset_net_profitability'] = 0;
                    $this->company[$ceo->id]['gross_profitability'] = 0;
                    $this->company[$ceo->id]['sales_net_profitability'] = 0;
                }

                $this->company[$ceo->id]['roe'] = round(($this->company[$ceo->id]['un'] / $this->company[$ceo->id]['equity']), 3);
                $this->company[$ceo->id]['roa'] = round(($this->company[$ceo->id]['ebit'] / $this->company[$ceo->id]['total_assets']), 3);

                if ($this->ceo[$ceo->id]['production'] > 0) {
                    //{[(risk_slope ^ (2*production)) / (1 + 2*production)-1)] * risk_limit_min } / 132
                    $this->company[$ceo->id]['event_prob_min'] = (
                        (pow($this->global['risk_slope'], (2 * ($this->ceo[$ceo->id]['production'] / 100)))
                            / ((1 + 2 * ($this->ceo[$ceo->id]['production'] / 100)) - 1))
                        * $this->game->game_parameters['risk_limit_min']
                    ) / 132;
                    //(reference_cost * (8^7) / (ppe_t * production)*(1-event_prob_min/2))) * production
                    $this->company[$ceo->id]['risk_free_output'] = (
                        ($this->global['reference_cost'] * pow(8, 7)
                            / ($this->company[$ceo->id]['ppe'] * ($this->company[$ceo->id]['production'] / 100))
                            * (1 - ($this->company[$ceo->id]['event_prob'] / 100) / 2))
                    ) * ($this->company[$ceo->id]['production'] / 100);
                } else {
                    $this->company[$ceo->id]['event_prob_min'] = 0;
                    $this->company[$ceo->id]['risk_free_output'] = 0;
                }

                $this->company[$ceo->id]['risk_cost'] = $this->company[$ceo->id]['risk_free_output'] - $this->company[$ceo->id]['output'];

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['growth_rate'] = 0;
                } else {
                    $this->company[$ceo->id]['growth_rate'] = (
                        ($this->company[$ceo->id]['earnings'] / $ceo->pivot->results['stage_0']['earnings']) - 1
                    ) * 100;
                }
            }

            $this->global['total_cost_industry'] = $this->global['total_cost_sum'];
            $this->global['average_total_cost'] = $this->global['total_cost_sum'] / $this->num_ceos;
            $this->global['average_payed_taxes'] = -1 * ($this->global['taxes_sum'] / $this->num_ceos);

            sort($arr_earnings, SORT_NUMERIC);
            $arr_earnings = array_reverse($arr_earnings);

            arsort($arr_full_earnings, SORT_NUMERIC);
            $arr_full_earnings = array_reverse($arr_full_earnings);
            $this->global['ranking'] = $arr_full_earnings;

            // loop 12
            foreach ($this->game->ceos as $ceo) {

                if ($this->stage == 0) {
                    $this->company[$ceo->id]['min_funds'] = 0;
                } else {
                    $this->company[$ceo->id]['min_funds']  = $ceo->pivot->results['stage_' . ($this->stage - 1)]['output'] / 2;
                }

                $this->company[$ceo->id]['company_ranking'] = array_search($this->company[$ceo->id]['earnings'], $arr_earnings) + 1;
                $this->company[$ceo->id]['total_funds_ratio'] = ($this->company[$ceo->id]['total_funds'] / max($arr_total_funds)) * 100;

                $this->company[$ceo->id]['status_company'] = 'OK';
                if (!$ceo->pivot->bankrupt && !$ceo->pivot->dismissed && $this->stage > 0) {
                    // if ($this->company[$ceo->id]['total_funds_ratio'] < $this->game->game_parameters['out_zone']) {
                    //     $this->company[$ceo->id]['dismissed'] = true;
                    //     $this->company[$ceo->id]['status_company'] = 'DISMISSED_CEO';
                    // // } else if ($this->company[$ceo->id]['total_funds'] < $this->company[$ceo->id]['min_funds']) {
                    // } else if ($this->company[$ceo->id]['total_funds'] < $this->company[$ceo->id]['depreciation']) {
                    if ($this->company[$ceo->id]['total_funds'] < $this->company[$ceo->id]['depreciation']) {
                        $this->company[$ceo->id]['bankrupt'] = true;
                        $this->company[$ceo->id]['status_company'] = 'BANKRUPT';
                    }
                }
            }

            $this->global['best_price'] = min($arr_price);
            $this->global['industry_pipeline'] = round(($this->global['final_stock_industry'] / $this->global['u_dem_industry']) * 100);

            $this->global['positive_top_limit'] = round($this->num_ceos / ($this->global['positive_random_events'] / 100));
            $this->global['positive_trigger'] = rand(1, $this->global['positive_top_limit']);

            $ceos_array = $this->game->ceos->toArray();
            if ($this->global['positive_trigger'] <= $this->num_ceos) {
                $this->global['lucky_player'] = $ceos_array[$this->global['positive_trigger'] - 1]['id'];
            } else {
                $this->global['lucky_player'] = 'None';
            }

            $this->global['success_id'] = rand(1, 3);
            if ($this->global['lucky_player'] == 'None') {
                $this->global['strength'] = 1;
            } else {
                $this->global['strength'] = 1 + (rand(-100, 200) / 100);
            }

            if ($this->global['success_id'] == 1) {
                $this->global['decision_success'] = 'MKT';
            } else if ($this->global['success_id'] == 2) {
                $this->global['decision_success'] = 'I&d';
            } else {
                $this->global['decision_success'] = 'IBK';
            }

            $this->global['avg_sales'] = round($this->global['total_revenue_industry'] / $this->num_ceos);
            $this->global['avg_total_cost'] = round($this->global['total_cost_industry'] / $this->num_ceos);


            // loop 13
            foreach ($this->game->ceos as $ceo) {
                if ($this->stage == 0) {
                    $this->company[$ceo->id]['company_ranking_delta'] = 0;
                } else {
                    // company_ranking(t-1) - company_ranking(t)
                    $this->company[$ceo->id]['company_ranking_delta']  = $ceo->pivot->results['stage_' . ($this->stage - 1)]['company_ranking'] - $this->company[$ceo->id]['company_ranking'];
                }

                if ($this->company[$ceo->id]['total_revenue'] == 0) {
                    $this->company[$ceo->id]['total_revenue'] = 1;
                }

                $this->company[$ceo->id]['udr']  = round(($this->company[$ceo->id]['demand_u'] / $this->company[$ceo->id]['active_investements']) * 100000);

                $this->company[$ceo->id]['salaries_budget']  = round(($this->company[$ceo->id]['salaries_expense'] / $this->company[$ceo->id]['total_revenue']) * 100);
                $this->company[$ceo->id]['mkt_budget']  = round(($this->company[$ceo->id]['mkt'] / $this->company[$ceo->id]['total_revenue']) * 100);
                $this->company[$ceo->id]['id_budget']  = round(($this->company[$ceo->id]['id'] / $this->company[$ceo->id]['total_revenue']) * 100);

                $this->company[$ceo->id]['corrected_demand']  = round(($this->company[$ceo->id]['demand_u'] / $this->company[$ceo->id]['active_investements']) * 100000);

                $this->company[$ceo->id]['global_leverage'] = round(($this->company[$ceo->id]['total_assets'] / $this->company[$ceo->id]['equity']), 2);
                //(uai/equity) / (ebit/total_assets)
                $this->company[$ceo->id]['financial_leverage'] = round(
                    ($this->company[$ceo->id]['uai'] / $this->company[$ceo->id]['equity'])
                        / ($this->company[$ceo->id]['ebit'] / $this->company[$ceo->id]['total_assets']),
                    2
                );
            }

            $this->global['price_checkpoint'] = ($this->global['final_price_points_sum'] == $this->industry['p_price']) ? 'OK' : 'ERROR - ' . (string) $this->global['final_price_points_sum'] . ' != ' . (string) $this->industry['p_price'];
            $this->global['id_checkpoint'] = ($this->global['final_id_points_sum'] == $this->industry['p_id']) ? 'OK' : 'ERROR - ' . (string) $this->global['final_id_points_sum'] . ' != ' . (string) $this->industry['p_id'];
            $this->global['mkt_checkpoint'] = ($this->global['final_mkt_points_sum'] == $this->industry['p_mkt']) ? 'OK' : 'ERROR - ' . (string) $this->global['final_mkt_points_sum'] . ' != ' . (string) $this->industry['p_mkt'];


            // CALCULO DE TIPS (solo se muestran a partir del turno 2)
            if ($this->stage > 0) {
                foreach ($this->game->ceos as $ceo) {
                    $tips = new Tips($this->game, $this->global, $this->company[$ceo->id], $ceo);
                    $this->company[$ceo->id]['tips'] = $tips->selected_tips;
                }
            }
        } catch (\Exception $e) {
            dump($this->global);
            dump($this->company);
            dump($this->industry);
            dump($e->getMessage());
            dd($e->getTraceAsString());
        }
    }


    /********************************************************************/
    /********************************************************************/
    /********************************************************************/
    /********************************************************************/
    /********************************************************************/
    /********************************************************************/

    private function init_t0(): void
    {
        // Calcula variables random de creacion de partida
        $game_parameters = $this->game->game_parameters;

        // country_income_level
        $vars_income_level = json_decode(file_get_contents(__DIR__ . '/data/countries_income_level.json'), true);

        $income_level = $this->game->game_parameters['country_income_level'];

        // Salary y loan_ratio no se calculan en modo pais
        if ($this->game->game_parameters['type'] != 'country') {
            $game_parameters['salary'] = rand(
                $vars_income_level[$income_level]['salary_min'],
                $vars_income_level[$income_level]['salary_max']
            );
            $game_parameters['loan_ratio'] = rand(
                $vars_income_level[$income_level]['loan_ratio_min'] * 1000,
                $vars_income_level[$income_level]['loan_ratio_max'] * 1000
            ) / 1000;
        }

        $game_parameters['pms'] = rand(
            $vars_income_level[$income_level]['pms_min'] * 1000,
            $vars_income_level[$income_level]['pms_max'] * 1000
        ) / 1000;

        $vars_easy_business_score = json_decode(file_get_contents(__DIR__ . '/data/easy_business_score.json'), true);
        $game_parameters['easy_business_score_tax'] = rand($vars_easy_business_score[$this->game->game_parameters['easy_business_score']]['min'] * 100, $vars_easy_business_score[$this->game->game_parameters['easy_business_score']]['max'] * 100) / 100;

        $game_parameters['business_promotion'] = rand(0, 66) / 100;

        $vars_proficiency_rate = json_decode(file_get_contents(__DIR__ . '/data/proficiency_rates.json'), true);
        $game_parameters['out_zone'] = $vars_proficiency_rate[$this->game->game_parameters['proficiency_rate']]['out_zone'];
        $game_parameters['opening_cash_ratio'] = rand(
            $vars_proficiency_rate[$this->game->game_parameters['proficiency_rate']]['opening_cash_min'],
            $vars_proficiency_rate[$this->game->game_parameters['proficiency_rate']]['opening_cash_max']
        ) / 100;
        $game_parameters['price_t0_leverage'] = rand(
            $vars_proficiency_rate[$this->game->game_parameters['proficiency_rate']]['price_t0_min'],
            $vars_proficiency_rate[$this->game->game_parameters['proficiency_rate']]['price_t0_max']
        ) / 100;

        $this->game->update([
            'game_parameters' => $game_parameters
        ]);
        $this->game->save();

        $this->production_t0 = rand(60, 90);
        $this->random_ibk_t0 = rand(1, 15);
        $this->random_mkt_t0 = rand(1, 15);
        $this->risk_score_t0 = rand(1, 33) / 10;
        $this->random_id_t0 = rand(1, 10);

        // variable temporal para calcular el porcentaje para el calculo de new debt en T0 
        //MIN(necesary_funds - opening_cash, top_loan)
        $this->random_new_debt_t0 = rand(-20, 20);
    }

    private function getRandomEvent($labels): array
    {
        $events = json_decode(file_get_contents(__DIR__ . '/data/events.json'), true);
        $selectable_events = [];
        foreach ($events["events"] as $e) {
            $has_label = false;
            foreach ($labels as $l) {
                if (in_array($l, $e['labels'])) {
                    $has_label = true;
                }
            }
            if ($has_label) {
                $selectable_events[] = $e;
            }
        }
        shuffle($selectable_events);
        return $selectable_events[0];
    }

    public function getCeoParameters(): array
    {
        $params = [];
        if ($this->stage == 0) {
            foreach ($this->game->ceos as $ceo) {
                $params[$ceo->id] = [
                    'production' => $this->production_t0,
                    'recycle' => $this->recycle_t0,
                    'safety' => $this->safety_t0,
                    'quality_control' => $this->quality_control_t0,
                    'survey' => 0,
                    'design' => 0,
                    'new_debt' => rand(1000, 5000),
                ];
            }
        } else {
            $recycle_vars = json_decode(file_get_contents(__DIR__ . '/data/recycle.json'), true);
            $quality_control_vars = json_decode(file_get_contents(__DIR__ . '/data/quality_control.json'), true);
            $safety_vars = json_decode(file_get_contents(__DIR__ . '/data/safety.json'), true);
            if (!is_array($recycle_vars) || !is_array($quality_control_vars) || !is_array($safety_vars)) {
                throw new \Exception('Could not read vars from files. recycle = ' . $recycle_vars . ', quality_control = ' . $quality_control_vars . ', safety = ' . $safety_vars);
            }
            foreach ($this->game->ceos as $ceo) {
                if ($ceo->pivot->bankrupt || $ceo->pivot->dismissed) {

                    $params[$ceo->id]['new_debt'] = 5000;
                    $params[$ceo->id]['design'] = 1;
                    $params[$ceo->id]['survey'] = 1;
                    $params[$ceo->id]['ibk'] = 1;
                    $params[$ceo->id]['mkt'] = 1;

                    $params[$ceo->id]['price'] = getMaxPrice($this->game);

                    $params[$ceo->id]['production'] = 1;
                    $params[$ceo->id]['quality_control'] = 'qc_start_up';
                    $params[$ceo->id]['quality_control_value'] = 1;
                    $params[$ceo->id]['recycle'] = 'recycle_sub_saharian_standards';
                    $params[$ceo->id]['recycle_value'] = 1;
                    $params[$ceo->id]['safety'] = 'safety_1';
                    $params[$ceo->id]['safety_value'] = 1;
                } else {
                    $parameters = $ceo->pivot->ceo_parameters['stage_' . $this->stage];
                    if (!is_array($parameters) && json_decode($parameters, true) !== false) {
                        $parameters = json_decode($parameters, true);
                    }
                    $params[$ceo->id] = $parameters;
                    $params[$ceo->id]['recycle_value'] = $recycle_vars[$parameters['recycle']];
                    $params[$ceo->id]['quality_control_value'] = $quality_control_vars[$parameters['quality_control']];
                    $params[$ceo->id]['safety_value'] = $safety_vars[$parameters['safety']];
                }
                if (!array_key_exists('new_debt', $params[$ceo->id])) {
                    throw new \Exception('new_debt not found in ceo_parameters');
                }
                if (!array_key_exists('design', $params[$ceo->id])) {
                    throw new \Exception('design not found in ceo_parameters');
                }
                if (!array_key_exists('survey', $params[$ceo->id])) {
                    throw new \Exception('survey not found in ceo_parameters');
                }
                if (!array_key_exists('ibk', $params[$ceo->id])) {
                    throw new \Exception('ibk not found in ceo_parameters');
                }
                if (!array_key_exists('mkt', $params[$ceo->id])) {
                    throw new \Exception('mkt not found in ceo_parameters');
                }
                if (!array_key_exists('price', $params[$ceo->id])) {
                    throw new \Exception('price not found in ceo_parameters');
                }
                if (!array_key_exists('production', $params[$ceo->id])) {
                    throw new \Exception('production not found in ceo_parameters');
                }
            }
        }
        return $params;
    }

    public function getIndustryParameters(): array
    {
        $industry = $this->game->game_parameters['industry'];
        $vars = json_decode(file_get_contents(__DIR__ . '/industries/' . $industry . '.json'), true);

        $industry_status = $this->game->game_parameters['industry_status'];
        $status_vars = json_decode(file_get_contents(__DIR__ . '/data/industry_statuses.json'), true);

        if ($industry_status === 'random') {
            $industry_status = array_rand([
                "industry_status_war_prices",
                "industry_status_demanding_customers",
                "industry_status_constant_development",
                "industry_status_faithful_clients"
            ]);
        }

        $vars['id_sensibility'] = rand(
            $status_vars[$industry_status]['id_sensibility']['min'],
            $status_vars[$industry_status]['id_sensibility']['max'],
        );
        
        $vars['price_sensibility'] = rand(
            $status_vars[$industry_status]['price_sensibility']['min'],
            $status_vars[$industry_status]['price_sensibility']['max'],
        );

        $company_type = $this->game->game_parameters['company_type'];
        $company_vars = json_decode(file_get_contents(__DIR__ . '/data/company_type.json'), true);

        $vars['ppe_t0'] = rand(
            $company_vars[$company_type]['ppe_t0']['min'],
            $company_vars[$company_type]['ppe_t0']['max'],
        );

        $positive_random_events = $this->game->game_parameters['positive_random_events'];
        // $positive_random_events
        $pre_vars = json_decode(file_get_contents(__DIR__ . '/data/positive_random_events.json'), true);
        if ($positive_random_events === 'positive_random_events_random') {
            $vars['positive_random_events'] = rand(
                $pre_vars[$positive_random_events]['min'],
                $pre_vars[$positive_random_events]['max'],
            );
        } else {
            $vars['positive_random_events'] = $pre_vars[$positive_random_events];
        }

        return $vars;
    }
}
