<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\customgame;

use \heart\core\error as e;
use \heart\core\helper as h;

class ban {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			// Queries: custom_game_ban
			's_game_ban'						=> 'SELECT * FROM `custom_game_ban` WHERE `game_banID` = ? LIMIT 1',
			's_game_ban_game_champion'			=> 'SELECT * FROM `custom_game_ban` WHERE `gameID` = ? AND `champion` = ? LIMIT 1',
			's_game_ban_game_championID'		=> 'SELECT * FROM `custom_game_ban` WHERE `gameID` = ? AND `championID` = ? LIMIT 1',

			'l_game_ban'						=> 'SELECT * FROM `custom_game_ban`',
			'l_game_ban_by_gameID'				=> 'SELECT * FROM `custom_game_ban` WHERE `gameID` = ?',
			'l_game_ban_by_champion'			=> 'SELECT * FROM `custom_game_ban` WHERE `champion` = ?',
			'l_game_ban_by_championID'			=> 'SELECT * FROM `custom_game_ban` WHERE `championID` = ?',

			'i_game_ban'						=> 'INSERT INTO `custom_game_ban` (`gameID`, `teamID`, `championID`, `champion`) VALUES (?,?,?,?)',

			'u_game_ban'						=> 'UPDATE `custom_game_ban` SET WHERE `game_banID` = ?',
			]];
		}


	public static function get_game_ban($req = []){

		// alternative
		$alt = h::eX($req, [
			'game_banID'	=> '~1,4294967295/i',
			'gameID'		=> '~1,4294967295/i',
			'teamID'		=> '~1,4294967295/i',
			'champion'		=> '~1,255/s',
			'championID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure function
		$convert = function($list){

			// for each champion
			foreach($list as $entry){

				// remove all whitespace and convert to lowercase
				$entry->name_converted = strtolower(str_replace(' ', '', $entry->champion));
				}
			};


		// param order 1: game_banID
		if(isset($alt['game_banID'])){

			// load entry
			$entry = self::pdo('s_game_ban', [$alt['game_banID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: gameID & champion
		if(isset($alt['gameID']) and isset($alt['champion'])){

			// load entry
			$entry = self::pdo('s_game_ban_game_champion', [$alt['gameID'], [$alt['champion']]]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: gameID & championID
		if(isset($alt['gameID']) and isset($alt['championID'])){

			// load entry
			$entry = self::pdo('s_game_ban_game_championID', [$alt['gameID'], [$alt['championID']]]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 4: gameID
		if(isset($alt['gameID'])){

			// load list
			$list = self::pdo('l_game_ban_by_gameID', [$alt['gameID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 5: champion
		if(isset($alt['champion'])){

			// load list
			$list = self::pdo('l_game_ban_by_champion', [$alt['champion']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 6: championID
		if(isset($alt['championID'])){

			// load list
			$list = self::pdo('l_game_ban_by_championID', [$alt['championID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 7: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_game_ban');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting game_banID|gameID+champion|gameID+championID|gameID|champion|championID or no parameter');
		}

	public static function create_game_ban($req = []){

		// mandatory
		$mand = h::eX($req, [
			'gameID'	=> '~1,4294967295/i',
			'teamID'	=> '~1,4294967295/i',
			'championID'=> '~1,4294967295/i',
			'champion'	=> '~1,255/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// insert
		$ins = self::pdo('i_game_ban', [$mand['gameID'], $mand['teamID'], $mand['championID'], $mand['champion']]);

		// on error
		if($ins === false) return self::response(560);

		// return success (unique index)
		return self::response(201, (object)['gameID' => $mand['gameID'], 'championID' => $mand['championID']]);
		}

	public static function update_game_ban($req = []){

		// mandatory
		$mand = h::eX($req, [
			'game_banID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'gameID'		=> '~1,4294967295/i',
			'teamID'		=> '~1,2/i',
			'championID'	=> '~1,4294967295/i',
			'champion'		=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_game_ban([
			'game_banID'	=> $mand['game_banID'],
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
        $query = self::pdo_extract('u_game_ban', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->game_banID]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
