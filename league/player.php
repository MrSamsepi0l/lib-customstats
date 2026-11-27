<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league;

use \heart\core\error as e;
use \heart\core\helper as h;

class player {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\customstats\extension\filecache_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: player */
			's_player'					=> 'SELECT * FROM `player` WHERE `playerID` = ? LIMIT 1',
			's_player_by_ingame_name'	=> 'SELECT * FROM `player` WHERE `ingame_name` = ? LIMIT 1',
			'l_player'					=> 'SELECT * FROM `player`',

			'i_player'					=> 'INSERT INTO `player` (`ingame_name`, `real_name`, `enabled`, `createTime`, `tag`) VALUES (?,?,?,?,?)',
			'u_player'               	=> 'UPDATE `player` SET WHERE `playerID` = ?',
			]];

		}

	static $lvl1_cache = [];

	public static function get_player($req = []){

		// alternative
		$alt = h::eX($req, [
			'playerID'			=> '~1,4294967295/i',
			'ingame_name'		=> '~1,30/s',
			'real_name'			=> '~1,30/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure convert function
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// if tag given, decode it
				if(isset($entry->tag)) $entry->tag = json_decode($entry->tag);
				}
			};


		// param order 1: ingame_name
		if(isset($alt['ingame_name'])){

			// call to pdo function
			$entry = self::pdo('s_player_by_ingame_name', [$alt['ingame_name']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 2: playerID
		if(isset($alt['playerID'])){

			// call to pdo function
			$entry = self::pdo('s_player', [$alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 3: empty
		if(empty($alt)){

			// if cached
			if(isset(self::$lvl1_cache['customstats:playerlist_complete'])){

				// return from cache
				return self::response(200, self::$lvl1_cache['customstats:playerlist_complete']);
				}

			// call to pdo function
			$list = self::pdo('l_player');

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// cache result
			self::$lvl1_cache['customstats:playerlist_complete'] = $list;

			// return success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Wrong parameters or not found');
		}

	public static function create_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'ingame_name'	=> '~1,30/s',
			'real_name'		=> '~1,30/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'enabled'		=> '~/b',
			'tag'			=> '~/o',
			], $error, true);

		// default
		$opt += [
			'createTime'	=> h::dtstr('now', 'Y-m-d H:i:s'),
			'enabled'		=> true,
			'tag'			=> (object)[],
			];

		// on error
		if($error) return self::response(400, $error);

		// load by ingame name
		$res = self::get_player([
			'ingame_name'	=> $mand['ingame_name'],
			]);

		// if found
		if($res->status == 200) return self::response(409);

		// encode tag
		$opt['tag'] = json_encode($opt['tag']);

		// create player
		$playerID = self::pdo('i_player', [$mand['ingame_name'], $mand['real_name'], $opt['enabled'] ? 1 : 0, $opt['createTime'], $opt['tag']]);

		// on error
		if($playerID === false) return self::response(560);

		// cleanup file cache
		self::cleanup_cache();

		// on success
		return self::response(201);
		}

	public static function update_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'ingame_name'	=> '~1,255/s',
			'real_name'		=> '~1,255/s',
			'enabled'		=> '~/b',
			'tag'			=> '~/o',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

        // try player by ID
        $res = self::get_player([
            'playerID'		=> $mand['playerID'],
            ]);

        // if not found
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

        // if enabled isset, convert bool to 0 or 1
        if(isset($update['enabled'])) $update['enabled'] = $update['enabled'] ? 1 : 0;

        // if tag isset, encode it
        if(isset($update['tag'])) $update['tag'] = json_encode($update['tag']);

        // set query
        $query = self::pdo_extract('u_player', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->playerID]));

        // on error
        if($upd === false) return self::response(560);

		// cleanup file cache
		self::cleanup_cache();

        // return success
        return self::response(204);
		}

	}
