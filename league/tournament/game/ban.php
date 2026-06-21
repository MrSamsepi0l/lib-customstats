<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class game_ban {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_game_ban'						=> 'SELECT * FROM `tournament_game_ban` WHERE `game_banID` = ? LIMIT 1',

			'l_game_ban'						=> 'SELECT * FROM `tournament_game_ban`',
			'l_game_ban_by_gameID'				=> 'SELECT * FROM `tournament_game_ban` WHERE `gameID` = ?',
			'l_game_ban_by_championID'			=> 'SELECT * FROM `tournament_game_ban` WHERE `championID` = ?',
			'l_game_ban_by_game_team'			=> 'SELECT * FROM `tournament_game_ban` WHERE `gameID` = ? AND `team` = ?',

			'i_game_ban'						=> 'INSERT INTO `tournament_game_ban` (`gameID`, `team`, `championID`, `champion`) VALUES (?,?,?,?)',

			]];
		}


	public static function get_game_ban($req = []){

		// alternative
		$alt = h::eX($req, [
			'game_banID'	=> '~1,4294967295/i',
			'gameID'		=> '~1,4294967295/i',
			'team'			=> '~1,4294967295/i',
			'championID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: game_banID
		if(isset($alt['game_banID'])){

			// load entry
			$entry = self::pdo('s_game_ban', [$alt['game_banID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: gameID + team
		if(isset($alt['gameID']) and isset($alt['team'])){

			// load list
			$list = self::pdo('l_game_ban_by_game_team', [$alt['gameID'], $alt['team']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 2: gameID
		if(isset($alt['gameID'])){

			// load list
			$list = self::pdo('l_game_ban_by_gameID', [$alt['gameID']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 4: championID
		if(isset($alt['championID'])){

			// load list
			$list = self::pdo('l_game_ban_by_championID', [$alt['championID']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_game_ban');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting game_banID|gameID+team|gameID|championID or no parameter');
		}

	public static function create_game_ban($req = []){

		// mandatory
		$mand = h::eX($req, [
			'gameID'	=> '~1,4294967295/i',
			'team'		=> '~1,4294967295/i',
			'championID'=> '~1,4294967295/i',
			'champion'	=> '~1,255/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// insert
		$game_banID = self::pdo('i_game_ban', [$mand['gameID'], $mand['team'], $mand['championID'], $mand['champion']]);

		// on error
		if($game_banID === false) return self::response(560);

		// return primary key
		return self::response(201, (object)['game_banID' => $game_banID]);
		}

	}
