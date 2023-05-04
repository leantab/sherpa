<?php

namespace Leantab\Sherpa;

use Leantab\Sherpa\Models\Game;
use Leantab\Sherpa\Models\User;
use Leantab\Sherpa\Models\GameUser;
use Leantab\Sherpa\Jobs\ProcessStage;
use Leantab\Sherpa\Services\ProcessStageService;

class Sherpa
{
    // Public API

    /*
    *
    *   Retorna el listado de versiones en orden descendiente
    *   @params
    *   $include_dev boolean (false): incluye las versiones no productivas (beta)
    */
    public function getVersions($include_dev = false)
    {
        $versions = scandir(__DIR__ . '/Core', SCANDIR_SORT_DESCENDING);
        foreach ($versions as $index => $v) {
            if ($v == '.' || $v == '..') {
                unset($versions[$index]);
            }
            if (!$include_dev && substr_count($v, 'beta')) {
                unset($versions[$index]);
            }
        }

        return $versions;
    }

    /*
    *
    *   Retorna el schema json de la version seleccionada (o la ultima version si no se especifica ninguna)
    *
    */
    public function getSchema(string $version = 'current'): array
    {
        if ($version == 'current') {
            $versions = $this->getVersions();
            $version = array_shift($versions);
        }

        $schema = $this->parseSchema($version);
        return $schema;
    }

    /*
    *   Retorna el schema json de la version seleccionada (o la ultima version si no se especifica ninguna)
    */
    public function getVariables($version = 'current')
    {
        if ($version == 'current') {
            $versions = $this->getVersions();
            $version = array_shift($versions);
        }

        if (!file_exists(__DIR__ . '/Core/' . $version . '/schema.json')) {
            return false;
        }
        $variables = json_decode(file_get_contents(__DIR__ . '/Core/' . $version . '/variables.json'), true);

        return $variables;
    }

    /*
    *   Retorna el schema json de variables de gobierno para la partida indicada
    */
    public function getGovermentVariables($game_id): array
    {
        $game = Game::findOrFail($game_id);
        $schema = $this->getSchema($game->version);
        $goverment_parameters = [];

        foreach ($schema['goverment_parameters'] as $index => $field) {

            // Reemplaza las variables dinamicas
            if (isset($field['max']) && is_string($field['max']) && substr($field['max'], 0, 9) == '$industry') {
                $field['max'] = $this->industry($game, substr($field['max'], 10));
            }
            if (isset($field['min']) && is_string($field['min']) && substr($field['min'], 0, 9) == '$industry') {
                $field['min'] = $this->industry($game, substr($field['min'], 10));
            }


            if (isset($field['required_if_game'])) {
                $explode = explode(':', $field['required_if_game']);
                $game_field = $explode[0];
                $values = explode(',', $explode[1]);
                if (isset($game['game_parameters'][$game_field]) && in_array($game['game_parameters'][$game_field], $values)) {
                    $field['required'] = true;
                    unset($field['required_if_game']);
                    $goverment_parameters[$index] = $field;
                }
            } else {
                $goverment_parameters[$index] = $field;
            }
        }
        return $goverment_parameters;
    }

    public function getCeoVariables($game_id, $user_id)
    {
        $game = Game::findOrFail($game_id);
        $user = $game->ceos()->where('user_id', $user_id)->first();
        
        $schema = $this->getSchema($game->version);

        $ceo_parameters = [];

        include(__DIR__ . '/Core/' . $game->version . '/functions.php');

        foreach ($schema['ceo_parameters'] as $index => $field) {

            // Reemplaza las variables dinamicas
            if (isset($field['max']) && is_string($field['max'])) {
                $function = substr($field['max'], 0, strlen($field['max']) - 2);
                $field['max'] = $function($game, $user);
            }
            if (isset($field['min']) && is_string($field['min'])) {
                $function = substr($field['min'], 0, strlen($field['min']) - 2);
                $field['min'] = $function($game, $user);
            }
            if (isset($field['value']) && is_string($field['value'])) {
                $function = substr($field['value'], 0, strlen($field['value']) - 2);
                $field['value'] = $function($game, $user);
            }

            if (isset($field['required_if_game'])) {
                $explode = explode(':', $field['required_if_game']);
                $game_field = $explode[0];
                $values = explode(',', $explode[1]);
                if (isset($game['game_parameters'][$game_field]) && in_array($game['game_parameters'][$game_field], $values)) {
                    $field['required'] = true;
                    unset($field['required_if_game']);
                    $ceo_parameters[$index] = $field;
                }
            } else {
                $ceo_parameters[$index] = $field;
            }
        }
        return $ceo_parameters;
    }

    public function industry($game, $var): bool
    {
        try {
            $industry = $game->game_parameters['industry'];
            $vars = json_decode(file_get_contents(__DIR__ . '/Core/' . $game->version . '/industries/' . $industry . '.json'), true);
            if ($vars) {
                return $vars[$var];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }


    public function getGames($user_id, $segment_id): array
    {
        $gamees = [];
        $user = User::findOrFail($user_id);
        $govs = $user->goverment_games()->where('segment_id', $segment_id)->latest()->get();

        foreach ($govs as $item) {
            $gamees[] = [
                'player_type' => 'goverment',
                'id' => $item->id,
                'name' => $item->name,
                'current_stage' => $item->current_stage,
                'type' => $item->game_parameters['type'] ?? '',
                'players' => $item->game_parameters['players'] ?? '',
                'industry' => $item->game_parameters['industry'] ?? '',
                'stages' => $item->game_parameters['stages'] ?? '',
                'country' => $item->game_parameters['country'] ?? '',
                'scenario' => $item->game_parameters['scenario'] ?? '',
                'active' => ($item->status_id == 2) ? true : false
            ];
        }
        $ceos = $user->ceo_games()->where('segment_id', $segment_id)->latest()->get();
        foreach ($ceos as $item) {
            $gamees[] = [
                'player_type' => 'ceo',
                'id' => $item->id,
                'name' => $item->name,
                'current_stage' => $item->current_stage,
                'type' => $item->game_parameters['type'] ?? '',
                'players' => $item->game_parameters['players'] ?? '',
                'industry' => $item->game_parameters['industry'] ?? '',
                'players' => $item->game_parameters['players'] ?? '',
                'stages' => $item->game_parameters['stages'] ?? '',
                'country' => $item->game_parameters['country'] ?? '',
                'scenario' => $item->game_parameters['scenario'] ?? '',
                'active' => ($item->status_id == 2) ? true : false
            ];
        }

        return $gamees;
    }


    public function createGame($version, $game_parameters, $creator_id, $segment_id)
    {
        $schema = $this->getSchema($version);

        $res = $this->validateJsonData($schema['game_parameters'], $game_parameters);

        if ($res->status === true) {

            if ($res->parameters['type'] == 'scenario') {
                // Merge variables de escenario
                $scenarioGameParameters = json_decode(file_get_contents(__DIR__ . '/Core/' . $version . '/scenarios/' . $game_parameters['scenario'] . '.json'), true);
                $res->parameters = array_merge($res->parameters, $scenarioGameParameters['game_parameters']);
            } elseif ($res->parameters['type'] == 'country') {
                // Merge variables de pais
                $countryGameParameters = json_decode(file_get_contents(__DIR__ . '/Core/' . $version . '/countries/' . $game_parameters['country'] . '.json'), true);
                $res->parameters = array_merge($res->parameters, $countryGameParameters['game_parameters']);
            } else {
                // Merge variables de tipo de gobierno
                if ($res->parameters['goverment_side'] != 'custom') {
                    $govermentSideGameParameters = json_decode(file_get_contents(__DIR__ . '/Core/' . $version . '/data/goverment_sides.json'), true);
                    $res->parameters = array_merge($res->parameters, $govermentSideGameParameters[$res->parameters['goverment_side']]);
                }
            }

            $game_data = [
                'version' => $version,
                'status_id' => 1,
                'segment_id' => $segment_id,
                'game_parameters' => $res->parameters,
                'creator_id' => $creator_id
            ];

            if ($res->parameters['type'] == 'scenario') {
                $game_data['goverment_parameters'] = $scenarioGameParameters['goverment_parameters'];
            }


            $game = Game::create($game_data);
            $return = new \StdClass();
            $return->status = true;
            $return->id = $game->id;
            return $return;
        } else {
            return $res;
        }
    }

    public function createTestGameScenario($version)
    {
        $schema = $this->getSchema($version);

        $schema['game_parameters']['name'] = 'Test Game ' . rand(1, 1000);
        $schema['game_parameters']['type'] = 'scenario';
        $schema['game_parameters']['players'] = 8;
        $schema['game_parameters']['industry'] = 'cars';
        $schema['game_parameters']['scenario'] = 'argentina_crisis_2001';
        $schema['game_parameters']['proficiency_rate'] = 'proficiency_senior';
        $schema['game_parameters']['company_type'] = 'company_type_corporate';
        $schema['game_parameters']['goverment_side'] = 'goverment_side_liberal';
        $schema['game_parameters']['country'] = 'arg';
        $schema['game_parameters']["stages"] = 4;
        $schema['game_parameters']["country_income_level"] = "country_income_low_income";
        $schema['game_parameters']["industry_status"] = "industry_status_constant_development";
        $schema['game_parameters']["accounting_period"] = 6;
        $schema['game_parameters']["positive_random_events"] = "positive_random_events_few";
        $schema['game_parameters']["risk_limit_min"] = 20;
        $schema['game_parameters']["risk_limit_max"] = 80;
        $schema['game_parameters']["initial_eq"]  = rand(-10, 10);
        $schema['game_parameters']["profit_tax"] = rand(1, 60);
        $schema['game_parameters']["vat_tax"] = rand(1, 28);
        $schema['game_parameters']["labor_tax"] = 2;
        $schema['game_parameters']["easy_business_score"] = "easy_business_full";
        $schema['game_parameters']["compensation_cost"] = rand(1, 5);
        $schema['game_parameters']["interest_rate"] = rand(1, 30);
        $schema['game_parameters']["financial_cost"] = 3;

        $geme_params = $schema['game_parameters'];

        $scenarioGameParameters = json_decode(file_get_contents(__DIR__ . '/Core/'.$version.'/scenarios/argentina_crisis_2001.json'), true);

        //foreach ($scenarioGameParameters['game_parameters'] as $key => $value) {
            //$geme_params[$key] = $value;
        //}

        $game_data = [
            'version' => $version,
            'status_id' => 1,
            'segment_id' => 1,
            'game_parameters' => $geme_params,
            'creator_id' => 1
        ];

        $game_data['goverment_parameters'] = $scenarioGameParameters['goverment_parameters'];

        $game = Game::create($game_data);

        for ($i=1; $i <= $schema['game_parameters']['players']; $i++) { 
            $this->addSimpleCeo($game->id, $i, 'Test Company '.$i, $i, true);
            $this->addCeo($game->id, $i, 'Test Company '.$i, $i, true);
        }        

        $service = new ProcessStageService($game);
        $service->processStage();

        return $game;
    }

    public function processNextStepTest($game_id)
    {
        $game = Game::findOrFail($game_id);
        $game->status_id = 2;
        $game->save();

        foreach ($game->ceos as $ceo) {
            $this->generateRandomDesitions($game_id, $ceo->pivot->user_id);
        }

        $service = new ProcessStageService($game);
        $service->processStage();

        return $game;
    }

    public function generateRandomDesitions(int $game_id, int $user_id)
    {
        $game = Game::findOrFail($game_id);
        $ceo = $game->ceos()->where('user_id', $user_id)->first();

        $schema = $this->getCeoVariables($game_id, $user_id);

        $randomFactor = rand(1, 5);
        $financialRand = (14 - $randomFactor) / 100;
        $marketingRand = (22 - $randomFactor) / 100;
        
        $desitions = [];
        foreach ($schema as $key => $value) {
            if ($value['type'] == 'options') {
                $desitions[$key] = $value['options'][array_rand($value['options'])];
            } elseif ($value['type'] == 'integer') {
                if ($key == 'corp_debt' || $key == 'ibk' || $key == 'capital_inv') {
                    $desitions[$key] = round($value['max'] * $financialRand);
                } elseif ($key == 'desing' || $key == 'survey' || $key == 'mkt') {
                    $desitions[$key] = round($value['max'] * $marketingRand );
                } else {
                    if (array_key_exists('min', $value) && array_key_exists('max', $value)) {
                        $desitions[$key] = rand($value['min'], $value['max']);
                    }
                }
            }
        }

        $ceoParameters = $ceo->pivot->ceo_parameters;
        $ceoParameters['stage_' . $game->current_stage] = $desitions;
        $ceo->pivot->ceo_parameters = $ceoParameters;
        $ceo->pivot->save();
    }

    public function getVariablesTypes()
    {
        return file_get_contents(__DIR__ . '/Core/v2/variables.json');
    }

    public function addGoverment($game_id, $user_id)
    {
        $game = Game::findOrFail($game_id);
        $game->goverment_id = $user_id;
        $game->save();
        return true;
    }

    public function addCeo($game_id, $user_id, $company_name, $avatar, $is_funded = false)
    {
        $game = Game::findOrFail($game_id);

        // Update the existing game_user data with the incoming parameters, or create new ceo
        $game_user = GameUser::where([['game_id', $game_id], ['user_id', $user_id]])->first();
        if (null !== $game_user) {
            $game_user->company_name = $company_name;
            $game_user->avatar = $avatar;
            $game_user->is_funded = true;
            $game_user->save();
        } else {
            // if ($game->ceos()->count() >= $game->players) {
            //     throw new \Exception("No hay Lugares disponibles en esta partida");
            // }
            
            // $game->ceos()->attach($user_id, [
            //     'company_name' => $company_name,
            //     'avatar' => $avatar,
            //     'is_funded' => $is_funded
            // ]);
            return false;
        }

        //check if all ceos are funded
        $funded = 0;
        foreach ($game->ceos as $ceo) {
            if ($ceo->pivot->is_funded) {
                $funded++;
            }
        }

        if ($funded === $game->players) {
            $game->status_id = 2;
            $game->save();
            ProcessStage::dispatch($game);
        }
        return true;
    }
    
    public function addSimpleCeo($game_id, $user_id, $company_name, $avatar)
    {
        $game = Game::findOrFail($game_id);
        if ($game->ceos()->count() >= $game->players) {
            throw new \Exception("No hay slots disponibles en esta partida");
        }
        
        $game->ceos()->attach($user_id, [
            'company_name' => $company_name,
            'avatar' => $avatar,
            'is_funded' => false
        ]);
        return true;
    }

    public function getGovermentParameters($game_id, $stage)
    {
        $game = Game::findOrFail($game_id);
        if (isset($game->goverment_parameters['stage_' . $stage])) {
            return $game->goverment_parameters['stage_' . $stage];
        } else {
            return [];
        }
    }

    public function setGovermentParameters($game_id, $goverment_parameters)
    {
        $game = Game::findOrFail($game_id);
        $schema = $this->getGovermentVariables($game_id);
        $res = $this->validateJsonData($schema, $goverment_parameters);

        if ($res->status === true) {
            $goverment_parameters = $game->goverment_parameters;
            $goverment_parameters['stage_' . $game->current_stage] = $res->parameters;

            $game->update([
                'goverment_parameters' => $goverment_parameters
            ]);
            $this->processGame($game_id);
            $return = new \StdClass();
            $return->status = true;
            return $return;
        } else {
            return $res;
        }
    }

    public function setCeoParameters($game_id, $ceo_parameters, $user_id)
    {
        $game = Game::findOrFail($game_id);
        $user = $game->ceos()->where('user_id', $user_id)->first();
        $pivot = $user->pivot;

        if (!$game->isActive()) {
            $return = new \StdClass();
            $return->errors = 'inactive_game';
            $return->status = false;
            return $return;
        }

        if ($pivot->bankrupt == true) {
            $return = new \StdClass();
            $return->errors = 'company_bankrupt';
            $return->status = false;
            return $return;
        }

        if ($pivot->dismissed == true) {
            $return = new \StdClass();
            $return->errors = 'ceo_dismissed';
            $return->status = false;
            return $return;
        }


        $schema = $this->getCeoVariables($game_id, $user_id);

        $res = $this->validateJsonData($schema, $ceo_parameters, $game, $user);

        if ($res->status === true) {
            $ceo_parameters = $pivot->ceo_parameters;
            $ceo_parameters['stage_' . $game->current_stage] = $res->parameters;

            $pivot->update([
                'ceo_parameters' => $ceo_parameters
            ]);
            $this->processGame($game_id);
            $return = new \StdClass();
            $return->status = true;
            return $return;
        } else {
            return $res;
        }
    }

    public function getCeoParameters($game_id, $stage, $user_id)
    {
        $game = Game::findOrFail($game_id);
        $user = $game->ceos()->where('user_id', $user_id)->first();
        $pivot = $user->pivot;

        if (isset($pivot->ceo_parameters['stage_' . $stage])) {
            return $pivot->ceo_parameters['stage_' . $stage];
        } else {
            return ['no se encontró la información'];
        }
    }

    public function getGame(int $game_id): ?Game
    {
        $game = Game::findOrFail($game_id);
        return $game;
    }

    public function getGameRanking($game_id, $stage)
    {

        $game = Game::findOrFail($game_id);
        if ($stage >= $game->current_stage && !$game->isCompleted()) {
            return [];
        }

        $ceos = $game->ceos()->orderBy('game_user.results->stage_' . $stage . '->company_ranking')->get();
        $ranking = [];
        foreach ($ceos as $c) {
            $ranking[] = [
                'user_id' => $c->id,
                'company_name' => $c->pivot->company_name,
                'position' => $c->pivot->results['stage_' . $stage]['company_ranking']
            ];
        }
        return $ranking;
    }

    public function deleteGame($game_id)
    {
        $game = Game::findOrFail($game_id);
        $game->ceos()->detach();
        $game->delete();
        return true;
    }
    
    public function deleteCeo($game_id, $user_id)
    {
        $game = Game::findOrFail($game_id);
        $game->ceos()->detach($user_id);
        return true;
    }

    public function reprocessGame($game_id, $stage)
    {
        $game = Game::findOrFail($game_id);
        if (!$game->isActive() || (int)($game->current_stage) == 0) {
            return false;
        }
        if (($game->current_stage - 1) > $stage) {
            $results = [];
            for ($i = 0; $i < $stage; $i++) {
                $results[] = $game->results['stage_' . $i];
            }
            $game->update([
                'results' => $results
            ]);
        }
        $game->current_stage = $stage;
        $game->save();
        if ($stage > 0) {
            foreach ($game->ceos as $ceo) {
                if (isset($ceo->pivot->results['stage_' . ($stage - 1)]['bankrupt']) && $ceo->pivot->results['stage_' . ($stage - 1)]['bankrupt'] == true) {
                    $ceo->pivot->update([
                        'bankrupt' => false,
                    ]);
                    $ceo->save();
                }
                if (isset($ceo->pivot->results['stage_' . ($stage - 1)]['dismissed']) && $ceo->pivot->results['stage_' . ($stage - 1)]['dismissed'] == true) {
                    $ceo->pivot->update([
                        'dismissed' => false,
                    ]);
                    $ceo->save();
                }
            }
        }
        ProcessStage::dispatch($game);
        return true;
    }

    public function processGame($game_id)
    {
        $game = Game::findOrFail($game_id);
        if ($game->hasAllCeoDecisions() && $game->hasGovermentDecisions()) {
            ProcessStage::dispatch($game);
            return true;
        } else {
            return false;
        }
    }

    public function forceProcessGame($game_id)
    {

        $game = Game::findOrFail($game_id);
        if ($game->hasGovermentDecisions()) {
            include(__DIR__ . '/Core/' . $game->version . '/functions.php');
            foreach ($game->ceos as $ceo) {
                if (!isset($ceo->pivot->ceo_parameters['stage_' . $game->current_stage])) {
                    if ($ceo->pivot->ceo_parameters) {
                        $ceo_parameters = $ceo->pivot->ceo_parameters;
                    } else {
                        $ceo_parameters = [];
                    }

                    $ceo_parameters['stage_' . $game->current_stage] = forceStageCopyCeoDecisions($game, $ceo);
                    $ceo_parameters['stage_' . $game->current_stage]['copy_force_stage'] = true;

                    $ceo->pivot->update([
                        'ceo_parameters' => $ceo_parameters
                    ]);
                }
            }
            ProcessStage::dispatch($game);
            return true;
        } else {
            return false;
        }
    }




    /* */
    /*
    *
    *   Valida estructura y datos ingresados contra el schema
    *
    */
    private function validateJsonData($gameParams, $input, $game = null, $user = null)
    {
        $return = new \StdClass();
        $return->status = true;
        $return->parameters = [];

        foreach ($gameParams as $param => $definition) {

            if (isset($definition['required']) && $definition['required']) {
                if (!isset($input[$param]) || $input[$param] == '') {
                    $return->status = false;
                    $return->errors[$param] = 'required';
                }
            }


            if (isset($definition['required_if'])) {

                $required_if_explode = explode(':', $definition['required_if']);
                $required_if_field = $required_if_explode[0];
                $required_if_values = explode(',', $required_if_explode[1]);

                if (isset($input[$required_if_field]) && in_array($input[$required_if_field], $required_if_values)) {
                    if (!isset($input[$param]) || $input[$param] == '') {
                        $return->status = false;
                        $return->errors[$param] = 'required_if';
                    }
                }
            }

            if ($definition['type'] == 'validation') {
                $function = substr($definition['function'], 0, strlen($definition['function']) - 2);
                if (!$function($game, $user, $input)) {
                    $return->status = false;
                    $return->errors[$param] = $definition['function'];
                }
            }

            if (isset($input[$param]) && $return->status == true) {

                $value = $input[$param];


                // Valida el tipo de dato
                if ($definition['type'] == 'string') {

                    if (isset($definition['min_length'])) {
                        if (strlen($value) < $definition['min_length']) {
                            $return->status = false;
                            $return->errors[$param] = 'min_length';
                        }
                    }

                    if (isset($definition['max_length'])) {
                        if (strlen($value) > $definition['max_length']) {
                            $return->status = false;
                            $return->errors[$param] = 'max_length';
                        }
                    }

                    if (isset($definition['options'])) {
                        if (!in_array($value, $definition['options'])) {
                            $return->status = false;
                            $return->errors[$param] = 'options';
                        }
                    }
                } elseif ($definition['type'] == 'integer') {

                    $value = (int)($value);

                    if (!is_int($value)) {
                        $return->status = false;
                        $return->errors[$param] = 'integer';
                    }

                    if (isset($definition['min'])) {
                        if ($value < $definition['min']) {
                            $return->status = false;
                            $return->errors[$param] = 'min';
                        }
                    }

                    if (isset($definition['max'])) {
                        if ($value > $definition['max']) {
                            $return->status = false;
                            $return->errors[$param] = 'max';
                        }
                    }
                } elseif ($definition['type'] == 'options') {

                    if (isset($definition['options'])) {
                        try {
                            if (!in_array($value, $definition['options'])) {
                                $return->status = false;
                                $return->errors[$param] = 'options';
                            }
                        } catch (\Exception $e) {
                        }
                    } else {
                        $return->status = false;
                        $return->errors[$param] = 'options no set';
                    }
                }

                // Reglas de validacion dinamica
                if (isset($definition['rule'])) {

                    foreach ($definition['rule'] as $rule) {

                        if ($rule[0] == 'self') {
                            $field1 = $param;
                        } else {
                            $field1 = $rule[0];
                        }

                        if ($rule[2] == 'self') {
                            $field2 = $param;
                        } else {
                            $field2 = $rule[2];
                        }


                        switch ($rule[1]) {
                            case '>':
                                $rule_valid = ($input[$field1] > $input[$field2]);
                                break;
                            case '>=':
                                $rule_valid = ($input[$field1] >= $input[$field2]);
                                break;
                            case '<':
                                $rule_valid = ($input[$field1] < $input[$field2]);
                                break;
                            case '<=':
                                $rule_valid = ($input[$field1] <= $input[$field2]);
                                break;
                        }

                        if (!$rule_valid) {
                            $return->status = false;
                            $return->errors[$param] = 'rule invalid';
                        }
                    }
                }

                if ($return->status == true) {
                    $return->parameters[$param] = $input[$param];
                }
            }
        }

        return $return;
    }



    /*
        Retorna el listado de archivos de un directorio
    */
    private function getDirectoryFiles($folder)
    {
        $files = scandir(__DIR__ . '/Core/' . $folder, SCANDIR_SORT_DESCENDING);
        foreach ($files as $index => $f) {
            if ($f == '.' || $f == '..') {
                unset($files[$index]);
            }
        }
        return $files;
    }

    /*
        Retorn el schema parseado (agrega los items dinamicos)
    */

    private function parseSchema(string $version): array
    {
        if (!file_exists(__DIR__ . '/Core/' . $version . '/schema.json')) {
            return false;
        }

        /** @var array $schema */
        $schema = json_decode(file_get_contents(__DIR__ . '/Core/' . $version . '/schema.json'), true);

        $schema['game_parameters']['scenario']['options'] = collect($this->getDirectoryFiles($version . '/scenarios'))->map(function ($str) {
            return str_replace('.json', '', $str);
        })->toArray();

        $schema['game_parameters']['country']['options'] = collect($this->getDirectoryFiles($version . '/countries'))->map(function ($str) {
            return str_replace('.json', '', $str);
        })->toArray();

        $schema['game_parameters']['industry']['options'] = collect($this->getDirectoryFiles($version . '/industries'))->map(function ($str) {
            return str_replace('.json', '', $str);
        })->toArray();


        return $schema;
    }
}
