<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class game {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_tournament_game'								=> 'SELECT * FROM `tournament_game` WHERE `gameID` = ? LIMIT 1',

			'l_tournament_game_by_tournamentID'				=> 'SELECT * FROM `tournament_game` WHERE `tournamentID` = ?',
			'l_tournament_game_by_teamID'					=> 'SELECT * FROM `tournament_game` WHERE `team_oneID` = ? OR `team_twoID` = ?',
			'l_tournament_game'								=> 'SELECT * FROM `tournament_game`',

			'i_tournament_game'								=> 'INSERT INTO `tournament_game` (`tournamentID`, `team_oneID`, `team_twoID`, `winner_teamID`, `type`, `gameDate`, `duration`, `scheduleID`, `canceled`, `vod_link`, `createTime`) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
			]];
		}


	public static function get_game($req = []){

		// alternative
		$alt = h::eX($req, [
			'gameID'		=> '~1,4294967295/i',
			'tournamentID'	=> '~1,4294967295/i',
			'teamID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert function
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// convert to bool
				$entry->canceled = (bool) $entry->canceled;
				}
			};


		// param order 1: gameID
		if(isset($alt['gameID'])){

			// load entry
			$entry = self::pdo('s_tournament_game', [$alt['gameID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_game_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 3: teamID
		if(isset($alt['teamID'])){

			// load list
			$list = self::pdo('l_tournament_game_by_teamID', [$alt['teamID'], $alt['teamID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 4: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_game');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting gameID|tournamentID|teamID or no parameter');
		}

	public static function create_game($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			'team_oneID'	=> '~1,4294967295/i',
			'team_twoID'	=> '~1,4294967295/i',
			'winner_teamID'	=> '~1,4294967295/i',
			'type'			=> '~^(?:regular_season|knockout)$',
			'gameDate'		=> '~Y-m-d/d',
			'duration'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'scheduleID'		=> '~0,4294967295/i',
			'createTime'		=> '~Y-m-d/d',
			'canceled'			=> '~/b',
			'vod_link'			=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'scheduleID'	=> 0,
			'createTime'	=> h::dtstr('now'),
			'canceled'		=> false,
			'vod_link'		=> '',
			];

		// create entry
		$gameID = self::pdo('i_tournament_game', [$mand['tournamentID'], $mand['team_oneID'], $mand['team_twoID'], $mand['winner_teamID'], $mand['type'], $mand['gameDate'], $mand['duration'], $opt['scheduleID'], $opt['canceled'] ? 1 : 0, $opt['vod_link'], $opt['createTime']]);

		// on error
		if($gameID === false) return self::response(560);

		// return success
		return self::response(201, (object)['gameID' => $gameID]);
		}

	public static function update_game($req = []){

		}

	}