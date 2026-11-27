<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\base;

use \heart\core\error as e;
use \heart\core\helper as h;

class leagueapi {
	use \heart\core\libcom_trait,
		\heart\core\usersession_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* queries: leagueapi */
			's_leagueapi'                    => 'SELECT * FROM `leagueapi` WHERE `name` = ? LIMIT 1',
			'l_leagueapi'                    => 'SELECT * FROM `leagueapi`',
			'i_leagueapi'                    => 'INSERT INTO `leagueapi` (`name`, `fn`, `enabled`, `right`, `createTime`) VALUES (?,?,?,?,?)',
			'u_leagueapi'                    => 'UPDATE `leagueapi` SET WHERE `name` = ?',
			'd_leagueapi'                    => 'DELETE FROM `leagueapi` WHERE `name` = ?',
			]];
		}

	public static function get_leagueapi($req = []){

		// alternative
		$alt = h::eX($req, [
			'name'      => '~^[a-z0-9_\.]{1,64}$',
			'fn'        => '~^\\\\[a-z0-9\\\\:_]{1,254}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// convert to bool
				$entry->enabled = (bool) $entry->enabled;
				}
			};


		// param order 1: name
		if(isset($alt['name'])){

			// load entry from DB
			$entry = self::pdo('s_leagueapi', $alt['name']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($alt)){

			// load list from DB
			$list = self::pdo('l_leagueapi');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting name or no param');
		}

	public static function create_leagueapi($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'      => '~^[a-z0-9_\.]{1,64}$',
			'fn'        => '~^\\\\[a-z0-9\\\\:_]{1,254}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'enabled'       => '~/b',
			'right'         => '~0,2/i', // 0 = registered & confirmed & admins; 1 = admin only; 2 = confirmed & admins;
			'createTime'    => '~Y-m-d H:i:s/d',
			], $error, true);

		// default opt
		$opt += [
			'enabled'       => false,
			'right'         => 1, // admin only
			'createTime'    => h::dtstr('now', 'Y-m-d H:i:s'),
			];

		// on error
		if($error) return self::response(400, $error);

		// insert entry
		$ins = self::pdo('i_leagueapi', [$mand['name'], $mand['fn'], $opt['enabled'] ? 1 : 0, $opt['right'], $opt['createTime']]);

		// on error
		if($ins === false) return self::response(560);

		// success
		return self::response(201, (object)['name' => $mand['name']]);
		}

	public static function update_leagueapi($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'      => '~^[a-z0-9_\.]{1,64}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'fn'        => '~^\\\\[a-z0-9\\\\:_]{1,254}$',
			'enabled'   => '~/b',
			'right'     => '~0,2/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// try to load entry by name
		$res = self::get_leagueapi([
			'name'            => $mand['name'],
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

		// set query
		$query = self::pdo_extract('u_leagueapi', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

		// update
		$upd = self::pdo($query, array_merge(array_values($update), [$mand['name']]));

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function delete_leagueapi($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'	=> '~^[a-z0-9_\.]{1,64}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry by name
		$res = self::get_leagueapi([
			'name'	=> $mand['name'],
			]);

		// if not found
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// delete entry
		$del = self::pdo('d_leagueapi', [$entry->name]);

		// on error
		if($del === false) return self::response(560);

		// success
		return self::response(204);
		}

	}
