<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league;

use \heart\core\error as e;
use \heart\core\helper as h;

class champion {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\customstats\extension\filecache_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: champions */
			's_champion'					=> 'SELECT * FROM `champions` WHERE `championID` = ? LIMIT 1',
			's_champion_by_name'			=> 'SELECT * FROM `champions` WHERE `name` = ? LIMIT 1',
			'l_champion'					=> 'SELECT * FROM `champions`',

			'i_champion'					=> 'INSERT INTO `champions` (`name`, `gender`) VALUES (?,?)',
			'u_champion'					=> 'UPDATE `champions` SET WHERE `championID` = ?',

			]];
		}

	public static function get_champion($req = []){

		// alternative
		$alt = h::eX($req, [
			'championID'	=> '~1,65535/i',
			'name'			=> '~1,255/s'
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure function
		$convert = function($list){

			// for each champion
			foreach($list as $entry){

				// remove all whitespace and convert to lowercase
				$entry->name_converted = strtolower(str_replace(' ', '', $entry->name));
				}
			};

		// param order 1: championID
		if(isset($alt['championID'])){

			// call to pdo function
			$entry = self::pdo('s_champion', [$alt['championID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return success
			return self::response(200, $entry);
			}

		// param order 2: champion name
		if(isset($alt['name'])){

			// call to pdo function
			$entry = self::pdo('s_champion_by_name', [$alt['name']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return success
			return self::response(200, $entry);
			}

		// param order 3: empty
		if(empty($alt)){

			// call to pdo function
			$list = self::pdo('l_champion');

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// return success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Wrong parameters or not found');
		}

	public static function create_champion($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'		=> '~1,255/s',
			'gender'	=> '~0,1/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load champion by name
		$res = self::get_champion([
			'name'	=> $mand['name'],
			]);

		// on name conflict
		if($res->status == 200) return self::response(409);

		// insert into db
		$championID = self::pdo('i_champion', [$mand['name'], $mand['gender']]);

		// on error
		if($championID === false) return self::response(560);

		// cleanup file cache
		self::cleanup_cache();

		// return success
		return self::response(201, (object) ['championID' => $championID]);
		}

	public static function update_champion($req = []){

		// mandatory
		$mand = h::eX($req, [
			'championID'=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'		=> '~1,255/s',
			'gender'	=> '~0,1/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// search champion by ID
		$res = self::get_champion([
			'championID'	=> $mand['championID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take champion
		$entry = $res->data;

		// check for conflicts
		if(isset($opt['name'])){

			// search champion by name
			$res = self::get_champion([
				'name'	=> $opt['name'],
				]);

			// on conflict (duplicate name) return error
			if($res->status == 200 and $res->data->championID != $entry->championID) return self::response(409);
			}

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
        $query = self::pdo_extract('u_champion', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$mand['championID']]));

        // on error
        if($upd === false) return self::response(560);

		// cleanup file cache
		self::cleanup_cache();

        // return success
        return self::response(204);
		}

	}
