<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\base\user;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\base\user as base_user;

class right {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: user_right */
			's_user_right'								=> 'SELECT * FROM `user_right` WHERE `userID` = ? AND `right` = ? LIMIT 1',
			'l_user_right'								=> 'SELECT * FROM `user_right` WHERE `userID` = ? ORDER BY `right` ASC',

			'i_user_right'								=> 'INSERT INTO `user_right` (`userID`,`right`) VALUES (?,?)',

			'd_user_right'								=> 'DELETE FROM `user_right` WHERE `userID` = ? AND `right` = ?',

			]];
		}

	public static function get_user_right($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'	=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'right'		=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// userID & right
		if(isset($alt['right'])){

			// load entry from DB
			$entry = self::pdo('s_user_right', [$mand['userID'], $alt['right']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// load list by userID
		$list = self::pdo('l_user_right', $mand['userID']);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);

		// on param error
		return self::response(400, 'Expecting userID|userID+right');
		}

	public static function create_user_right($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'		=> '~1,4294967295/i',
			'right'			=> '~1,255/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load user
		$res = base_user::get_user([
			'userID'		=> $mand['userID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take user
		$entry = $res->data;

		// load user right
		$res = self::get_user_right([
			'userID'		=> $entry->userID,
			'right'			=> $mand['right'],
			]);

		// if found, return conflict
		if($res->status == 200) return self::response(409);

		// create right
		$ins = self::pdo('i_user_right', [$entry->userID, $mand['right']]);

		// on error
		if($ins === false) return self::response(560);

		// return success
		return self::response(201);
		}

	public static function delete_user_right($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'		=> '~1,4294967295/i',
			'right'			=> '~1,255/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load user
		$res = base_user::get_user([
			'userID'		=> $mand['userID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take user
		$entry = $res->data;

		// load user right
		$res = self::get_user_right([
			'userID'		=> $entry->userID,
			'right'			=> $mand['right'],
			]);

		// if not found, return success (because deletion)
		if($res->status != 200) return self::response(204);

		// delete right
		$del = self::pdo('d_user_right', [$entry->userID, $mand['right']]);

		// on error
		if($del === false) return self::response(560);

		// return success
		return self::response(204);
		}
	}
