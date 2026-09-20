<?php
/* Last update: 2023-10-16
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\customgame;

use \heart\core\error as e;
use \heart\core\helper as h;


class player {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			// Queries: custom_game_player
			's_game_player'						=> 'SELECT * FROM `custom_game_player` WHERE `game_playerID` = ? LIMIT 1',

			'l_game_player'						=> 'SELECT cgp.*, cg.gameDate, cg.duration
													FROM `custom_game_player` cgp
													INNER JOIN `custom_game` cg ON cg.gameID = cgp.gameID
													',

			'l_game_player_by_gameID'			=> 'SELECT cgp.*, cg.gameDate, cg.duration
													FROM `custom_game_player` cgp
													INNER JOIN `custom_game` cg ON cg.gameID = cgp.gameID
													WHERE cgp.gameID = ?
													',

			'l_game_player_by_playerID'			=> 'SELECT cgp.*, cg.gameDate, cg.duration
													FROM `custom_game_player` cgp
													INNER JOIN `custom_game` cg ON cg.gameID = cgp.gameID
													WHERE `playerID` = ?
													',

			'i_game_player'						=> 'INSERT INTO `custom_game_player` (`gameID`, `playerID`, `teamID`, `championID`, `champion`, `summoner_one`, `summoner_two`, `level`, `kills`, `deaths`, `assists`, `creep_score`, `gold`, `position`, `won`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',

			'u_game_player'						=> 'UPDATE `custom_game_player` SET WHERE `game_playerID` = ?',

			]];
		}

	protected static $lvl1_cache = [];

	public static function get_game_player($req = []){

		// alternative
		$alt = h::eX($req, [
			'game_playerID'	=> '~1,4294967295/i',
			'gameID'		=> '~1,4294967295/i',
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert closure
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// convert to bool
				$entry->won = (bool) $entry->won;
				}
			};


		// param order 1: game_playerID
		if(isset($alt['game_playerID'])){

			// load entry
			$entry = self::pdo('s_game_player', [$alt['game_playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: playerID
		if(isset($alt['playerID'])){

			// define cache key
			$cache_key = 'customstats:league_customgame_by_playerID:'.$alt['playerID'];

			// if cached
			if(isset(self::$lvl1_cache[$cache_key])){

				// directly return from cache
				return self::response(200, self::$lvl1_cache[$cache_key]);
				}

			// load list
			$list = self::pdo('l_game_player_by_playerID', [$alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// cache list
			self::$lvl1_cache[$cache_key] = $list;

			// return list
			return self::response(200, $list);
			}

		// param order 3: gameID
		if(isset($alt['gameID'])){

			// load list
			$list = self::pdo('l_game_player_by_gameID', [$alt['gameID']]);

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 4: no param
		if(empty($alt)){

			// define cache key
			$cache_key = 'customstats:league_customgame:game_player:list_complete';

			// if cached
			if(isset(self::$lvl1_cache[$cache_key])){

				// directly return from cache
				return self::response(200, self::$lvl1_cache[$cache_key]);
				}

			// load list
			$list = self::pdo('l_game_player');

			// on error
			if($list === false) return self::response(560);

			// cache list
			self::$lvl1_cache[$cache_key] = $list;

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting game_playerID|playerID|gameID or no param');
		}

	public static function create_game_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'gameID'		=> '~1,4294967295/i',
			'playerID'		=> '~1,4294967295/i',
			'teamID'		=> '~1,2/i',
			'championID'	=> '~1,4294967295/i',
			'champion'		=> '~1,255/s',
			'summoner_one'	=> '~1,255/s',
			'summoner_two'	=> '~1,255/s',
			'level'			=> '~1,20/i',
			'kills'			=> '~0,100/i',
			'deaths'		=> '~0,100/i',
			'assists'		=> '~0,100/i',
			'creep_score'	=> '~0,65535/i',
			'gold'			=> '~0,65535/i',
			'position'		=> '~^(?:top|jungle|mid|adc|support)$',
			'won'			=> '~/b',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// insert
		$game_playerID = self::pdo('i_game_player', [$mand['gameID'], $mand['playerID'], $mand['teamID'], $mand['championID'], $mand['champion'], $mand['summoner_one'], $mand['summoner_two'], $mand['level'], $mand['kills'], $mand['deaths'], $mand['assists'], $mand['creep_score'], $mand['gold'], $mand['position'], $mand['won'] ? 1 : 0]);

		// on error
		if($game_playerID === false) return self::response(560);

		// on success
		return self::response(201, (object)['game_playerID' => $game_playerID]);
		}

	public static function update_game_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'game_playerID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			'teamID'		=> '~1,2/i',
			'championID'	=> '~1,4294967295/i',
			'champion'		=> '~1,255/s',
			'summoner_one'	=> '~1,255/s',
			'summoner_two'	=> '~1,255/s',
			'level'			=> '~1,20/i',
			'kills'			=> '~0,100/i',
			'deaths'		=> '~0,100/i',
			'assists'		=> '~0,100/i',
			'creep_score'	=> '~0,65535/i',
			'gold'			=> '~0,65535/i',
			'position'		=> '~^(?:top|jungle|mid|adc|support)$',
			'won'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_game_player([
			'game_playerID'	=> $mand['game_playerID'],
			]);

		// on unexpected error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

        // default update
        $update = [];

        // check for each opt
        foreach($opt as $k => $v){

            // if value does not match push into update
            if($entry->{$k} !== $v) $update[$k] = $v;
            }

        // if there is nothing to update
        if(!$update) return self::response(204);

        // set query
        $query = self::pdo_extract('u_game_player', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->game_playerID]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
