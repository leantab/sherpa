<?php

namespace Leantab\Sherpa;

class Tips
{

  protected $game;
  protected $global;
  protected $company;
  public $selected_tips;
  protected $ceo;
  protected $public = [];

  public function __construct($game, $global, $company, $ceo)
  {

    $this->game = $game;
    $this->global = $global;
    $this->company = $company;
    $this->ceo = $ceo;
    $this->selected_tips = [];

    $available_tips = collect(get_class_methods($this))->except(0);
    foreach ($available_tips as $tip) {
      $this->$tip();
    }
    
    return $this->selected_tips;
  }

  /* ************************************************************************** */



  /* COST */

  public function cost1()
  {
    if (
      ($this->company['total_cost'] < $this->ceo->pivot->results['stage_' . ($this->game->current_stage - 1)]['total_cost'])
      && ($this->company['price'] < $this->ceo->pivot->results['stage_' . ($this->game->current_stage - 1)]['price'])
      && ($this->company['demand_u'] > $this->ceo->pivot->results['stage_' . ($this->game->current_stage - 1)]['demand_u'])
    ) {
      $this->selected_tips['production']['company'][] = 'cost1';
    }
  }

  public function cost2()
  {
    if (
      ($this->global['average_price'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
      && ($this->global['average_cbu'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_cbu'])
    ) {
      $this->selected_tips['finance']['industry'][] = 'cost2';
    }
  }

  public function cost3()
  {
    if (
      ($this->global['average_price'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
      && ($this->global['average_total_cost'] < $this->global['average_price'] / 2)
    ) {
      $this->selected_tips['finance']['industry'][] = 'cost3';
    }
  }

  /* INV */

  public function inv1()
  {
    if (
      ($this->global['ppe_industry'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['ppe_industry'])
      && ($this->company['capex'] < 0)
    ) {
      $this->selected_tips['finance']['company'][] = 'inv1';
    }
  }

  public function inv2()
  {
    if (
      ($this->global['ppe_industry'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['ppe_industry'])
    ) {
      $this->selected_tips['finance']['industry'][] = 'inv2';
    }
  }

  /* PRICE */

  public function price1()
  {
    if (
      ($this->global['average_price'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
      && ($this->company['total_cost'] < $this->ceo->pivot->results['stage_' . ($this->game->current_stage - 1)]['total_cost'])
    ) {
      $this->selected_tips['marketing']['company'][] = 'price1';
    }
  }

  public function price2()
  {
    if (
      ($this->global['u_dem_industry'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['u_dem_industry'])
      && ($this->global['average_price'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
    ) {
      $this->selected_tips['marketing']['industry'][] = 'price2';
    }
  }

  public function price3()
  {
    if (
      ($this->global['average_price'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
      &&  ($this->global['final_stock_industry'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['final_stock_industry'])
    ) {
      $this->selected_tips['marketing']['industry'][] = 'price3';
    }
  }

  public function price4()
  {
    if (
      ($this->global['average_price'] < $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
      &&  ($this->global['average_total_cost'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_total_cost'])
    ) {
      $this->selected_tips['finance']['industry'][] = 'price4';
    }
  }

  public function price5()
  {
    if (
      ($this->global['average_price'] < $this->game->results['stage_' . ($this->game->current_stage - 1)]['average_price'])
      &&  ($this->global['u_dem_industry'] < $this->game->results['stage_' . ($this->game->current_stage - 1)]['u_dem_industry'])
      &&  ($this->global['final_stock_industry'] > $this->game->results['stage_' . ($this->game->current_stage - 1)]['final_stock_industry'])
    ) {
      $this->selected_tips['finance']['industry'][] = 'price5';
    }
  }
}
