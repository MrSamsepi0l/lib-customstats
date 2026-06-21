<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\base;

use \heart\core\error as e;
use \heart\core\helper as h;

class config {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_base_config'									=> 'SELECT * FROM `base_config` WHERE `configID` = ? LIMIT 1',
			's_base_config_by_project'						=> 'SELECT * FROM `base_config` WHERE `project` = ? LIMIT 1',

			'l_base_config'									=> 'SELECT * FROM `base_config`',

			'i_base_config'									=> 'INSERT INTO `base_config` (`project`, `enabled`, `param`, `createTime`) VALUES (?,?,?,?)',

			'u_base_config'									=> 'UPDATE `base_config` SET WHERE `configID` = ?',

			]];
		}

	protected static $lvl1_cache = [];


	public static function get_config($req = []){

		// alternative
		$alt = h::eX($req, [
			'configID'		=> '~1,4294967295/i',
			'project'		=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure convert
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// enabled to boolean
				$entry->enabled = (bool) $entry->enabled;

				// encode param
				$entry->param = json_decode($entry->param);
				}
			};


		// param order 1: configID
		if(isset($alt['configID'])){

			// load entry
			$entry = self::pdo('s_base_config', [$alt['configID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: project
		if(isset($alt['project'])){

			// define cache key
			$cache_key = 'customstats:base_config:by_project:'.$alt['project'];

			// if defined in lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// directly return config
				return self::response(200, $lvl1_cache[$cache_key]);
				}

			// load entry
			$entry = self::pdo('s_base_config_by_project', [$alt['project']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// cache entry in lvl1 cache
			$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_base_config');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting configID|project or no parameter');
		}

	public static function create_config($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~1,255/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'enabled'	=> '~/b',
			'param'		=> '~/l',
			'createTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'enabled'	=> true,
			'param'		=> [],
			'createTime'=> h::dtstr('now'),
			];

		// encode param
		$opt['param'] = json_encode($opt['param']);

		// insert
		$configID = self::pdo('i_base_config', [$mand['project'], $opt['enabled'] ? 1 : 0, $opt['param'], $opt['createTime']]);

		// on error
		if($configID === false) return self::response(560);

		// return success (unique index)
		return self::response(201, (object)['configID' => $configID]);
		}

	public static function update_config($req = []){

        // mandatory
        $mand = h::eX($req, [
            'configID'      => '~1,4294967295/i',
            ], $error);

        // optional
        $opt = h::eX($req, [
        	'project'	=> '~1,255/s',
			'enabled'	=> '~/b',
			'param'		=> '~/l',
            ], $error, true);

        // on error
        if($error) return self::response(400, $error);

        // load entry
        $res = self::get_config([
        	'configID'	=> $mand['configID'],
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

        // encode param if set
        if(isset($update['param'])) $update['param'] = json_encode($update['param']);

        // set query
        $query = self::pdo_extract('u_base_config', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->configID]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
