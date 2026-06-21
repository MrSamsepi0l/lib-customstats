<?php
/* Last update: 2021-11-06
	Jeremiah Meyer - MobileTrendGmbH
	*/
namespace customstats\league;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\league\customgame;
use \customstats\league\player;

class timeserie {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			'l_timeserie_bestof_analysis_by_period'			=> 'SELECT `gameDate`
																FROM `custom_game`
																WHERE `gameDate` BETWEEN ? AND ?
																	AND `canceled` = 0
																ORDER BY `gameID` ASC',

			's_timeserie_dashboard_avg_kills'				=> 'SELECT AVG(`kills`) AS `sum`
																FROM `custom_game_player`
																WHERE `gameID` = ?
																LIMIT 1
																',

			's_timeserie_dashboard_avg_assists'				=> 'SELECT AVG(`assists`) AS `sum`
																FROM `custom_game_player`
																WHERE `gameID` = ?
																LIMIT 1
																',

			's_timeserie_dashboard_avg_cs'					=> 'SELECT AVG(`creep_score`) AS `sum`
																FROM `custom_game_player`
																WHERE `gameID` = ? AND `position` != "support"
																LIMIT 1
																',

			'l_timeserie_dashboard_champ_banned'			=> 'SELECT `champion`, COUNT(`championID`) AS `sum`, (COUNT(`championID`) * 100 / ?)
																FROM `custom_game_ban`
																WHERE `gameID` IN ?
																GROUP BY `championID`
																ORDER BY `sum` DESC
																LIMIT 5
																',

			'l_timeserie_dashboard_champ_played'			=> 'SELECT `champion`, COUNT(`championID`) AS `sum`, (COUNT(`championID`) * 100 / ?)
																FROM `custom_game_player`
																WHERE `gameID` IN ?
																GROUP BY `championID`
																ORDER BY `sum` DESC
																LIMIT 5
																',
			]];
		}

	/* Object: timeserie */
	public static function get_timeserie($req = []){

	    // mandatory
	    $mand = h::eX($req, [
	        'category'       => '~^(?:date|champion|champion_only_with_date)$',
	        'type'           => '~^(?:winrate|playrate|times_played|kills|deaths|assists|cs|gold|duration|times_banned)$',
	        'range_num'      => '~1,1000/i', // DEPRECATED, MAKE ALTERNATIVE
	        'run'            => '~^(?:champion|player|bestof|dashboard)$',
	        ], $error);

	    // alternative
	    $alt = h::eX($req, [
	    	'playerlist'	=> '~/l',
	        'mode'          => '~^(?:preset|manual|all)$',
	        'range_unit'    => '~^(?:day|week|month|year|game)$',
	        'step'          => '~^(\+[0-9]{1,2} (month|week|day|hour|min|game))$',
	        'from'          => '~Y-m-d H:i:s/d',
	        'to'            => '~Y-m-d H:i:s/d',
	        ], $error, true);

	    // optional
	    $opt = h::eX($req, [
	        'champion'     	=> '~/s',
	        ], $error, true);

	    // on error
	    if($error) return self::response(400, $error);

	    // default alt
	    $alt += [
	        'mode'          => 'all',
	        'range_unit'    => 'game',
	        'playerlist'    => [],
	        ];

	    // timeserie bestof
	    if($mand['run'] == 'bestof'){

	    	// get timeserie for bestof
	    	return self::get_timeserie_bestof([
	    		'type'		=> $mand['type'],
	    		'step'		=> $alt['step'][0] ?? '+1 month',
	    		'from'		=> $alt['from'] ?? null,
	    		'to'		=> $alt['to'] ?? 'now',
	    		]);
	    	}

	    // timeserie bestof
	    if($mand['run'] == 'dashboard'){

	    	// get timeserie for bestof
	    	return self::get_timeserie_dashboard([
	    		'type'		=> $mand['type'],
	    		'step'		=> $alt['step'][0] ?? '+1 month',
	    		'from'		=> $alt['from'] ?? null,
	    		'to'		=> $alt['to'] ?? 'now',
	    		'range_num'	=> $mand['range_num']
	    		]);
	    	}

	    // if no playerlist given
	    if(!isset($alt['playerlist'])){

	        // load all player from DB
	        $res = player::get_player();

	        // on error
	        if($res->status != 200) return $res;

	        // take player list
	        $alt['playerlist'] = $res->data;
	        }

	    // define timeserie run
	    $run = (object) [
			'category'		=> $mand['category'],
			'type'          => $mand['type'],
			'range_num'     => $mand['range_num'],
			'playerlist'	=> $alt['playerlist'],
			'player'		=> count($alt['playerlist']) == 1 ? $alt['playerlist'][0] : null,
			'run'           => $mand['run'],
			'mode'          => ($alt['mode'] == 'all' or ($alt['mode'] == 'preset' and $alt['range_unit'] == 'game')) ? 'preset_game' : 'preset_date',
			'range_unit'    => $alt['range_unit'],
			'step'          => $alt['step'] ?? ['+30 game', 'game'],
			'from'          => $alt['from'] ?? '2018-01-01',
			'to'            => $alt['to'] ?? '2030-12-31',
			'champion'      => $opt['champion'] ?? null,
			'one_player'    => count($alt['playerlist']) == 1 ? true : false,
			'validate'      => !in_array($mand['type'], ['playrate', 'times_played']),
			'quant_games'   => 0,
	        ];


	    // load converted game data
	    $res = game::get_game([
	        'converted'     => true,
	        ]);

	    // on error
	    if($res->status != 200) return $res;

	    // take games
	    $gamedata = $res->data;


	    // if only one player and type equals 'times_played' or 'playrate'
	    if($run->one_player and !in_array($run->type, ['playrate', 'times_played'])){

	        // validate gamedata (all games needed for this)
	        $gamedata = self::validate_single_player($gamedata, $run->player->playerID, $run->champion);
	        }

	    // define quantity of games
	    $run->quant_games = count($gamedata);


	    // on mode: preset game
	    if($run->mode == 'preset_game'){

	        // take minimum possible range number
	        $run->range_num = min($run->quant_games, $mand['range_num']);

	        // and compress games by this number
	        $gamedata = array_slice($gamedata, - $run->range_num, $run->range_num);
	        }

	    // on mode: preset_date or manual
	    if(in_array($run->mode, ['preset_date', 'manual'])){

	        // format list
	        $format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d', 'game'=>'Y-m-d'];

	        // define times
	        $step_format = $format_list[$run->step[1]];

	        // copy actual gamedata
	        $temp_gamedata = $gamedata;

	        // clear it
	        $gamedata = [];

	        // and run through each game
	        foreach($temp_gamedata as $entry){

	            // define gamedata
	            $date = $entry->gameDate;

	            // if gamedate isn't in defined interval, skip game
	            if($date < $run->from or $date > $run->to) continue;

	            // take game
	            $gamedata[] = $entry;
	            }
	        }


	    // timeserie player
	    if($run->run == 'player'){

	        // get timeserie for player
	        return self::get_timeserie_player([
				'gamedata'		=> $gamedata,
				'type'          => $run->type,
				'playerlist'    => $run->playerlist,
				'one_player'    => $run->one_player,
				'mode'          => $run->mode,
				'step'          => $run->step[0],
				'champion'      => $run->champion,
				'validate'      => $run->validate,
	            ]);
	        }

	    // timeserie champion
	    elseif($run->run == 'champion'){

	        // get timeserie for champion
	        return self::get_timeserie_champion([
	            'gamedata'      => $gamedata,
	            'type'          => $run->type,
	            'champion'      => $run->champion,
	            'step'          => $run->step[0],
	            ]);
	        }

	    // return not implemented
	    return self::response(501);
	    }


	/* Object: timeserie (player) */
	protected static function get_timeserie_player($req = []){

	    // mandatory
	    $mand = h::eX($req, [
	        'type'         	=> '~^(?:winrate|playrate|times_played|kills|deaths|assists|cs|gold)$',
	        'gamedata'      => '~/l',
	        'playerlist'	=> '~/l',
	        ], $error);

	    // optional
	    $opt = h::eX($req, [
	        'one_player'    => '~/b',
	        'champion'      => '~/s',
	        'step'          => '~^(\+[0-9]{1,2} (month|week|day|hour|min|game))$',
	        'validate'      => '~/b',
	        ], $error, true);

	    // on error
	    if($error) return self::response(400, $error);

	    // default opt
	    $opt += [
			'step'			=> '+1 week',
			'one_player'    => false,
			'validate'     	=> true,
	        ];

	    // define run
	    $run = (object) [
	        'type'			=> $mand['type'],
	        'gamedata'      => $mand['gamedata'],
	        'playerlist'    => $mand['playerlist'],
	        'one_player'    => $opt['one_player'],
	        'champion'      => $opt['champion'] ?? null,
	        'step'          => $opt['step'],
	        'validate'      => $opt['validate']
	        ];

	    // if validation needed, only one player and champ given
	    if($run->validate and $run->one_player and $run->champion){

	        // validate gamedata again for this player
	        $run->gamedata = self::validate_single_player($run->gamedata, $run->playerlist[0]->playerID, $run->champion);
	        }


	    // define timeline array
	    $timeline = [];

	    // format list and step
	    $format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d', 'game'=>'Y-m'];
	    $step_format = $format_list[$opt['step'][1]];

	    // for each game
	    foreach($run->gamedata as $entry){

	        // define object
	        $step = (object) [
	            'name'      => h::dtstr($entry->gameDate, $step_format),
	            'time'      => h::dtstr($entry->gameDate, 'Y-m-d H:i:s'),
	            'result'	=> [],
	            ];

	        // take step
	        $timeline[] = $step;
	        }


	    // for each player
	    foreach($run->playerlist as $player){

	        // define stat object
	        $stat = (object) [
				'times_played'	=> 0,
				'times_won'     => 0,
				'kills'         => 0,
				'deaths'        => 0,
				'assists'       => 0,
				'cs'            => 0,
				'gold'          => 0,
				'quant_game'    => 0,
				'value'         => null,
				'timeline_point'=> []
	            ];

	        // for each game
	        foreach($run->gamedata as $game){

	            // define default object
	            $object = (object) [
	                'date'        => $game->gameDate,
	                $run->type    => - 1
	                ];

	            // increment game count
	            $stat->quant_game++;

	            // if player didn't play this game and is more than one player and type doesn't equal winrate, playrate, times_played
	            if(!in_array($run->type, ['winrate', 'playrate', 'times_played']) and !in_array($player->playerID, $game->playerlist) and !$run->one_player){

	                // take default
	                $stat->timeline_point[] = $object;

	                // next game
	                continue;
	                }

	            // increment times_played (maybe multiple for random players) or other not random players
	            if(in_array($player->playerID, $game->playerlist)) $stat->times_played += ($player->playerID == 48) ? array_count_values($game->playerlist)[48] : 1;

	            // increment times_won (maybe multiple for random players) or other not random players
	            if(in_array($player->playerID, $game->player_winner_list)) $stat->times_won += ($player->playerID == 48) ? array_count_values($game->player_winner_list)[48] : 1;

	            // if played at least one
	            if($stat->times_played > 0){

	                // on type kills, deaths, assists, cs, gold
	                if(in_array($run->type, ['kills', 'deaths', 'assists', 'cs', 'gold'])){

	                    // if player didn't play, skip this
	                    if(!in_array($player->playerID, $game->playerlist)) continue;

	                    // for each players game data
	                    foreach($game->playerdata as $data){

	                        // if player doesn't match, skip this
	                        if($player->playerID != $data->playerID) continue;

	                        // increment type value
	                        $stat->{$run->type} += $data->{$run->type};

	                        // increment value
	                        $stat->value = $data->{$run->type};
	                        }
	                    }

	                // on type winrate
	                elseif($run->type == 'winrate') $stat->value = ($stat->times_won / $stat->times_played) * 100;

	                // on type playrate
	                elseif($run->type == 'playrate') $stat->value = ($stat->times_played / $stat->quant_game) * 100;

	                // on type times_played
	                elseif($run->type == 'times_played') $stat->value = $stat->times_played;
	                }

	            // change object value
	            $object->{$run->type} = $stat->value;

	            // take it
	            $stat->timeline_point[] = $object;
	            }

	        // take step object for player
	        $player->data['result'] = $stat;
	        }


	    // for each player
	    foreach($run->playerlist as $player){

	        // for each timeline step
	        foreach($timeline as $key => $category){

	            // if key not given, skip category
				if(!isset($player->data['result']->timeline_point[$key])) continue;

	            // final object
	            $final = (object) [
	                'type'    => $player->ingame_name,
	                'sum'    => round($player->data['result']->timeline_point[$key]->{$run->type} ?? 0, 2)
	                ];

	            // add it to ingame_name index
	            $category->result[$player->ingame_name] = $final;
	            }
	        }

	    // return timeserie
	    return self::response(200, $timeline);
	    }

	/* Object: timeserie (champion) */
	protected static function get_timeserie_champion($req = []){

	    // mandatory
	    $mand = h::eX($req, [
	        'type'          => '~^(?:winrate|playrate|times_played)$',
	        'step'          => '~^(\+[0-9]{1,2} (month|week|day|hour|min|game))$',
	        'gamedata'      => '~/l',
	        'champion'      => '~/s',
	        ], $error);

	    // on error
	    if($error) return self::response(400, $error);

	    // define run
	    $run = (object) [
	        'champion'      => $mand['champion'],
	        'gamedata'      => $mand['gamedata'],
	        'type'          => $mand['type'],
	        'step'          => $mand['step'],
	        'quant_game'	=> count($mand['gamedata']),
	        'times_played'  => 0,
	        'times_won'     => 0,
	        ];

	    // default timeline array
	    $timeline = [];

	    // format list and step
	    $format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d', 'game'=>'Y-m-d'];
	    $step_format = $format_list[$mand['step'][1]];

	    // for each game
	    foreach($run->gamedata as $game){

	        // if champ wasn't in this game, skip game
	        if(!in_array($run->champion, $game->championlist)) continue;

	        // if champ won this game, increment times_won
	        if(in_array($run->champion, $game->champion_winner_list)) $run->times_won++;

	        // for each data of this game
	        foreach($game->playerdata as $data){

	            // increment times_played
	            $run->times_played++;

	            // calculate winrate
	            if($run->type == 'winrate') $value = ($run->times_won / $run->times_played) * 100;

	            // calculate playrate
	            elseif($run->type == 'playrate') $value = ($run->times_played / $run->quant_game) * 100;

	            // simply take times_played value
	            elseif($run->type == 'times_played') $value = $run->times_played;

	            // define step object
	            $step = (object) [
	                'name'			=> h::dtstr($game->gameDate, $step_format),
	                'time'          => h::dtstr($game->gameDate, $step_format),
	                'ingame_name'   => $data->ingame_name,
	                'gameID'        => $game->gameID,
	                'result'        => []
	                ];

	            // define timeline object
	            $timeline_obj = (object) [
	                'type'      => $data->ingame_name,
	                'sum'		=> round($value ?? 0, 2),
	                ];

	            // take timeline object at champions name index
	            $step->result[$run->champion] = $timeline_obj;

	            // take step
	            $timeline[] = $step;

	            // next game
	            continue 2;
	            }
	        }

	    // return timeserie
	    return self::response(200, $timeline);
	    }

	public static function get_timeserie_bestof($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'      => '~^(?:times_played)$',
			'step'      => '~^(\+[0-9]{1,2} (month|week|day|hour))$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// optional
		$opt = h::ex($req, [
			'from'		=> '~Y-m-d/d',
			'to'		=> '~Y-m-d/d',
			], $error, true);

		// default opt
		$opt += [

			// first custom game tracked is in this year/month
			'from'		=> '2019-01-01',
			'to'		=> h::dtstr('now', 'Y-m-d'),
			];

		// define from and to
		$from = h::dtstr($opt['from'], 'Y-m-01');
		$to = $end = h::dtstr($opt['to'], 'Y-m-31');

	    // format list and step
	    $format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d'];
	    $step_format = $format_list[$mand['step'][1]];

		// default timeline
		$timeline = [];

		// start week counter
		$week_count = 0;

		// run
		do {

			// increment week
			$week_count++;

			// define new to (if too large, simply take end date)
			$to = h::date($from, $mand['step'][0], 'Y-m-d');
			$to = ($to > $end) ? $end : $to;

            // create step for timeline
            $timeline_step = (object) [
                'name'		=> h::dtstr($from, $step_format),
                'time'      => h::dtstr($from, $step_format),
                'result'    => []
                ];

           	// load games played in period
			$list = self::pdo('l_timeserie_bestof_analysis_by_period', [$from, $to]);

			// on error
			if($list === false) return self::response(560);

            // create timeline point
            $point = (object) [
                'type'      => $mand['type'],
                'sum'		=> count($list),
                ];

            // add point to step result
            $timeline_step->result[] = $point;

            // add step to timeline
            $timeline[] = $timeline_step;

			// take to as new from
			$from = $to;

			// while end time is not reached
			} while($to < $end);

		// return timeline
		return self::response(200, $timeline);
		}


	/* Dashboard */
	public static function get_timeserie_dashboard($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'      => '~^(?:kills|deaths|assists|cs|times_banned|times_played|duration)$',
			'step'      => '~^(\+[0-9]{1,2} (month|week|day|hour))$',
			], $error);

		// optional
		$opt = h::ex($req, [
			'from'		=> '~Y-m-d/d',
			'to'		=> '~Y-m-d/d',
			'range_num'	=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// special types
		if(in_array($mand['type'], ['times_played', 'times_banned'])){

			// process fn
			return self::get_timeserie_dashboard_champion($mand + $opt);
			}

		// special types
		if($mand['type'] == 'duration'){

			// process fn
			return self::get_timeserie_dashboard_duration($mand + $opt);
			}

		// default opt
		$opt += [

			// first custom game tracked is in this year/month
			'from'		=> '2019-01-01',
			'to'		=> h::dtstr('now', 'Y-m-d'),
			'range_num'	=> null,
			];

		// define from and to
		$from = $opt['from'];
		$to = $end = $opt['to'];

	    // format list and step
	    $format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d'];
	    $step_format = $format_list[$mand['step'][1]];

		// default timeline
		$timeline = [];

		// if game range number given
		if($opt['range_num']){

			// load list of games
			$res = customgame::get_custom_game([
				'limit'		=> $opt['range_num'],
				]);

			// on error
			if($res->status != 200) return $res;

			// take custom game list
			$gamelist = $res->data;

			// for each game in list
			foreach($gamelist as $game){

	            // create step for timeline
	            $timeline_step = (object) [
	                'name'		=> $game->gameDate,
	                'time'      => h::dtstr($game->gameDate),
	                'result'    => []
	                ];

	           	// load games played in period
				$entry = self::pdo('s_timeserie_dashboard_avg_'.$mand['type'], [$game->gameID]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

	            // create timeline point
	            $point = (object) [
	                'type'      => $mand['type'],
	                'sum'		=> (float) $entry->sum,
	                ];

	            // add point to step result
	            $timeline_step->result[] = $point;

	            // add step to timeline
	            $timeline[] = $timeline_step;
				}

			// return
			return self::response(200, $timeline);
			}

		// run
		do {

			// define new to (if too large, simply take end date)
			$to = h::date($from, $mand['step'][0], 'Y-m-d');
			$to = ($to > $end) ? $end : $to;

            // create step for timeline
            $timeline_step = (object) [
                'name'		=> h::dtstr($from, $step_format),
                'time'      => h::dtstr($from, $step_format),
                'result'    => []
                ];

           	// load games played in period
			$entry = self::pdo('s_timeserie_dashboard_avg_kills', [$from, $to]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

            // create timeline point
            $point = (object) [
                'type'      => $mand['type'],
                'sum'		=> (float) $entry->avg_kills,
                ];

            // add point to step result
            $timeline_step->result[] = $point;

            // add step to timeline
            $timeline[] = $timeline_step;

			// take to as new from
			$from = $to;

			// while end time is not reached
			} while($to < $end);

		// return timeline
		return self::response(200, $timeline);
		}

	public static function get_timeserie_dashboard_champion($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'      => '~^(?:times_banned|times_played)$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// optional
		$opt = h::ex($req, [
			'range_num'	=> '~1,10000/i',
			], $error, true);

		// load list of games
		$res = customgame::get_custom_game([
			'limit'		=> $opt['range_num'] ?? 100,
			]);

		// on error
		if($res->status != 200) return $res;

		// take custom game list
		$gamelist = $res->data;

		// get list of game IDs to search in and build query
		$gameID_list = array_column($gamelist, 'gameID');

		// define query by type
		$query = $mand['type'] == 'times_played' ? 'l_timeserie_dashboard_champ_played' : 'l_timeserie_dashboard_champ_banned';

		// load list
		$list = self::pdo(self::pdo_extract($query, ['WHERE `gameID` IN ?' => 'WHERE `gameID` IN ('.implode(",", $gameID_list).")"]), [$opt['range_num']]);

		// on error
		if($list === false) return self::response(560);

		// for each banned champion
		foreach($list as $entry){

            // create step for timeline
            $timeline_step = (object) [
                'name'		=> $entry->champion,
                'result'    => []
                ];

            // create timeline point
            $point = (object) [
                'type'      => $entry->champion,
                'sum'		=> $entry->sum,
                ];

            // add point to step result
            $timeline_step->result[] = $point;

            // add step to timeline
            $timeline[] = $timeline_step;
			}

		// return timeline
		return self::response(200, $timeline);
		}


	public static function get_timeserie_dashboard_duration($req = []){

		// optional
		$opt = h::ex($req, [
			'range_num'	=> '~1,10000/i',
			], $error, true);

		// load list of games
		$res = customgame::get_custom_game([
			'limit'		=> $opt['range_num'] ?? 100,
			]);

		// on error
		if($res->status != 200) return $res;

		// take custom game list
		$list = $res->data;

		// for each banned champion
		foreach($list as $entry){

			// add float duration value to list
			$timeline[] = (float) $entry->duration;
			}

		// return timeline
		return self::response(200, $timeline);
		}


	/* Helper */
	protected static function validate_single_player($list = [], $playerID = 0, $champion = null){

	    // define result
	    $result = [];

	    // for each game
	    foreach($list as $entry){

	        // if player didn't play this game, skip this
	        if(!in_array($playerID, $entry->playerlist)) continue;

	        // if champion
	        if($champion){

	            // for each player data of this game
	            foreach($entry->playerdata as $data){

	                // if player doesn't match, skip data
	                if($playerID != $data->playerID) continue;

	                // if player didn't play this champ, skip game
	                if($champion != $data->champion) continue 2;
	                }
	            }

	        // if random player
	        if($playerID == 48){

	            // count how many randoms are in this game
	            $count_value = array_count_values($entry->playerlist);

	            // and get value for times_played
	            $counter_times_played_random_players = $count_value[$playerID];

	            // if 'Random' was multiple in the same game, get the quantity of it and loop through
	            for($i = 0; $i < $counter_times_played_random_players; $i++){

	                // so games for random will be pushed maybe multiple times
	                $result[] = $entry;
	                }

	            // go on
	            continue;
	            }

	        // take game
	        $result[] = $entry;
	        }

	    // return result
	    return $result;
	    }

	}
