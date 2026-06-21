<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class game_player {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			// Queries: tournament_game_player
			's_tournament_game_player'						=> 'SELECT * FROM `tournament_game_player` WHERE `game_playerID` = ? LIMIT 1',

			'l_tournament_game_player_by_gameID'			=> 'SELECT * FROM `tournament_game_player` WHERE `gameID` = ?',
			'l_tournament_game_player_by_playerID'			=> 'SELECT * FROM `tournament_game_player` WHERE `playerID` = ?',

			'i_tournament_game_player'						=> 'INSERT INTO `tournament_game_player` (`tournamentID`, `gameID`, `playerID`, `team`, `championID`, `champion`, `summoner_one`, `summoner_two`, `level`, `kills`, `deaths`, `assists`, `creep_score`, `gold`, `position`, `won`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', // 16 values


			// Queries: tournament_game_player | tournament_game
			'l_tournament_game_player'						=> 'SELECT tgp.*, tg.gameDate, tg.duration
																FROM `tournament_game_player` tgp
																INNER JOIN `tournament_game` tg ON tg.gameID = tgp.gameID
																',

			'l_tournament_game_player_by_tournamentID'		=> 'SELECT tgp.*, tg.gameDate, tg.duration
																FROM `tournament_game_player` tgp
																INNER JOIN `tournament_game` tg ON tg.gameID = tgp.gameID
																WHERE tgp.tournamentID = ?
																',

			]];
		}


	public static function get_game_player($req = []){

		// alternative
		$alt = h::eX($req, [
			'game_playerID'		=> '~1,4294967295/i',
			'gameID'			=> '~1,4294967295/i',
			'playerID'			=> '~1,4294967295/i',
			'tournamentID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define closure function
		$alter_list = function($list){

			// foreach entry
			foreach($list as $entry){

				// convert to boolean
				$entry->won = (bool) $entry->won;
				}
			};


		// param order 1: game_playerID
		if(isset($alt['game_playerID'])){

			// load entry
			$entry = self::pdo('s_tournament_game_player', [$alt['game_playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// alter list
			$alter_list([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: gameID
		if(isset($alt['gameID'])){

			// load list
			$list = self::pdo('l_tournament_game_player_by_gameID', [$alt['gameID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 3: playerID
		if(isset($alt['playerID'])){

			// load list
			$list = self::pdo('l_tournament_game_player_by_playerID', [$alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 4: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_game_player_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_game_player');

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting game_playerID|gameID|playerID or no parameter');
		}

	public static function create_game_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'		=> '~1,4294967295/i',
			'gameID'			=> '~1,4294967295/i',
			'playerID'			=> '~1,4294967295/i',
			'team'				=> '~1,2/i',
			'championID'		=> '~1,4294967295/i',
			'champion'			=> '~1,255/s',
			'summoner_one'		=> '~1,255/s',
			'summoner_two'		=> '~1,255/s',
			'level'				=> '~1,20/i',
			'kills'				=> '~/i',
			'deaths'			=> '~/i',
			'assists'			=> '~/i',
			'creep_score'		=> '~/i',
			'gold'				=> '~/i',
			'position'			=> '~1,255/s',
			'won'				=> '~/b',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$game_playerID = self::pdo('i_tournament_game_player', [$mand['tournamentID'], $mand['gameID'], $mand['playerID'], $mand['team'], $mand['championID'], $mand['champion'], $mand['summoner_one'], $mand['summoner_two'], $mand['level'], $mand['kills'], $mand['deaths'], $mand['assists'], $mand['creep_score'], $mand['gold'], $mand['position'], $mand['won'] ? 1 : 0]);

		// on error
		if($game_playerID === false) return self::response(560);

		// return success
		return self::response(201, (object)['game_playerID' => $game_playerID]);
		}

	public static function update_game_player($req = []){

		}

	}