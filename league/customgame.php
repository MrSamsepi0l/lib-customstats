<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\league\player as league_player;

class customgame {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\customstats\extension\filecache_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: custom_game */
			's_custom_game'						=> 'SELECT `gameID`, `gameDate`, `duration`, `sourceID`, `canceled`, `createTime`
													FROM `custom_game`
													WHERE `gameID` = ?
													LIMIT 1',

			'l_custom_game'						=> 'SELECT `gameID`, `gameDate`, `duration`, `sourceID`, `canceled`, `createTime`
													FROM `custom_game`
													ORDER BY `gameDate` ASC, `gameID`',

			'l_custom_game_by_date_range'		=> 'SELECT `gameID`, `gameDate`, `duration`, `sourceID`, `canceled`, `createTime`
													FROM `custom_game`
													WHERE `gameDate` BETWEEN ? AND ?
													ORDER BY `gameDate` ASC, `gameID`',

			'l_custom_game_limited'				=> 'SELECT `gameID`, `gameDate`, `duration`, `sourceID`, `canceled`, `createTime`
													FROM `custom_game`
													ORDER BY `gameDate` DESC, `gameID` DESC
													LIMIT ?
													',

			'i_custom_game'						=> 'INSERT INTO `custom_game` (`gameDate`, `duration`, `sourceID`, `canceled`, `createTime`) VALUES (?,?,?,?,?)',

			'u_custom_game'						=> 'UPDATE `custom_game` SET WHERE `gameID` = ?',

			]];
		}

	public static function get_custom_game($req = []){

		// alternative
		$alt = h::eX($req, [
			'gameID'	=> '~1,4294967295/i',
			'gameDate'	=> '~Y-m-d/d',
			'from'		=> '~Y-m-d/d',
			'to'		=> '~Y-m-d/d',
			'limit'		=> '~1,100000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: gameID
		if(isset($alt['gameID'])){

			// load entry from DB
			$entry = self::pdo('s_custom_game', $alt['gameID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// on success
			return self::response(200, $entry);
			}

		// param order 2: gameDate
		if(isset($alt['gameDate'])){

			// load list from DB
			$list = self::pdo('l_custom_game');

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 3: limit
		if(isset($alt['limit'])){

			// load list from DB
			$list = self::pdo('l_custom_game_limited', [$alt['limit']]);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 4: from & to
		if(isset($alt['from']) and isset($alt['to'])){

			// load list from DB
			$list = self::pdo('l_custom_game_by_date_range', [$alt['from'], $alt['to']]);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order: 4 empty
		if(empty($alt)){

			// load list from DB
			$list = self::pdo('l_custom_game');

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting gameID|gameDate|from+to or no parameter');
		}

	public static function create_custom_game($req = []){

		// mandatory
		$mand = h::eX($req, [
			'gameDate'		=> '~Y-m-d/d',
			'duration'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'sourceID'		=> '~0,4294967295/i',
			'canceled'		=> '~/b',
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'sourceID'		=> 0,
			'canceled'		=> false,
			'createTime'	=> h::dtstr('now', 'Y-m-d H:i:s'),
			];

		// insert
		$gameID = self::pdo('i_custom_game', [$mand['gameDate'], $mand['duration'], $opt['sourceID'], $opt['canceled'] ? 1 : 0, $opt['createTime']]);

		// on error
		if($gameID === false) return self::response(560);

		// on success
		return self::response(201, (object)['gameID' => $gameID]);
		}

	public static function update_custom_game($req = []){

		// mandatory
		$mand = h::eX($req, [
			'gameID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'canceled'		=> '~/b',
			'gameDate'		=> '~Y-m-d/d',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'duration'		=> '~/s',
			'sourceID'		=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// search game by ID
		$res = self::get_custom_game([
			'gameID'	=> $mand['gameID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take champion
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
        $query = self::pdo_extract('u_custom_game', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$mand['gameID']]));

        // on error
        if($upd === false) return self::response(560);

		// cleanup file cache
		self::cleanup_cache();

        // return success
        return self::response(204);
		}
	}
