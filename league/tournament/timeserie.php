<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\league\tournament\game as tournament_game;
use \customstats\league\tournament\player as tournament_player;

class timeserie {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: tournament_game */
			'l_timeserie_dashboard_game_duration_winrate'	=> 'SELECT
																	FLOOR(g.duration) + ROUND((g.duration - FLOOR(g.duration)) * 100) / 60.0 AS duration,
																	MAX(CASE WHEN gp.team = 1 THEN gp.won END) AS blueWon
																FROM tournament_game g
																JOIN tournament_game_player gp ON gp.gameID = g.gameID
																WHERE g.canceled = 0
																	AND g.tournamentID = ?
																AND g.winner_teamID IS NOT NULL
																GROUP BY g.gameID, g.duration',

			]];
		}

	/* Object: timeserie */
	public static function get_timeserie($req = []){

	    // mandatory
	    $mand = h::eX($req, [
	        'type'           => '~^(?:duration)$',
	        'range_num'      => '~1,1000/i', // DEPRECATED, MAKE ALTERNATIVE
	        'run'            => '~^(?:dashboard)$',
	        ], $error);

	    // alternative
	    $alt = h::eX($req, [
	        'step'          => '~^(\+[0-9]{1,2} (month|week|day|hour|min|game))$',
	        'from'          => '~Y-m-d H:i:s/d',
	        'to'            => '~Y-m-d H:i:s/d',
	        ], $error, true);

	    // optional
	    $opt = h::eX($req, [
	    	'tournamentID'	=> '~1,4294967295/i',
	        ], $error, true);

	    // on error
	    if($error) return self::response(400, $error);

	    // default alt
	    $alt += [

	        ];

	    // timeserie dashboard
	    if($mand['run'] == 'dashboard'){

	    	// get timeserie for dashboard
	    	return self::get_timeserie_dashboard([
	    		'type'			=> $mand['type'],
	    		'step'			=> $alt['step'][0] ?? '+1 month',
	    		'from'			=> $alt['from'] ?? null,
	    		'to'			=> $alt['to'] ?? 'now',
	    		'range_num'		=> $mand['range_num'],
	    		'tournamentID'	=> $opt['tournamentID'] ?? null,
	    		]);
	    	}

	    // return not implemented
	    return self::response(501);
	    }


	/* Dashboard */
	public static function get_timeserie_dashboard($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'      	=> '~^(?:duration)$',
			'step'      	=> '~^(\+[0-9]{1,2} (month|week|day|hour))$',
			], $error);

		// optional
		$opt = h::ex($req, [
			'from'			=> '~Y-m-d/d',
			'to'			=> '~Y-m-d/d',
			'range_num'		=> '~1,10000/i',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// special types
		if($mand['type'] == 'duration'){

			// process fn
			return self::get_timeserie_dashboard_duration($mand + $opt);
			}

		// set default
		$opt += [
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

		// return timeline
		return self::response(200, $timeline);
		}


	public static function get_timeserie_dashboard_duration($req = []){

		// optional
		$opt = h::ex($req, [
			'range_num'		=> '~1,10000/i',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// set default
		$opt += [
			'range_num'	=> 100,
			];

		// load list of games
		$res = tournament_game::get_game([
			'limit'			=> $opt['range_num'],
			'tournamentID'	=> $opt['tournamentID'] ?? null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take custom game list
		$list = $res->data;

		// for each game
		foreach($list as $entry){

			// add duration value as float to list
			$timeline[] = (float) $entry->duration;
			}

		// default list
		$list = [];

		// if tournamentID given
		if(isset($opt['tournamentID'])){

			// load stats
			$list = self::pdo('l_timeserie_dashboard_game_duration_winrate', [$opt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);
			}

		// return timeline
		return self::response(200, (object)['timeline' => $timeline, 'stats' => $list]);
		}

	}
