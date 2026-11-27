<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\base;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\base\user\right;


class user {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\customstats\extension\filecache_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: base_user */
			's_user'								=> 'SELECT * FROM `base_user` WHERE `userID` = ? LIMIT 1',
			's_user_by_username'					=> 'SELECT * FROM `base_user` WHERE `username` = ? LIMIT 1',
			's_user_by_email'						=> 'SELECT * FROM `base_user` WHERE `email` = ? LIMIT 1',
			's_user_by_api_key'						=> 'SELECT * FROM `base_user` WHERE `api_key` = ? LIMIT 1',
			's_user_by_playerID'					=> 'SELECT * FROM `base_user` WHERE `playerID` = ? LIMIT 1',

			'i_user'								=> 'INSERT INTO `base_user` (`email`,`username`,`username_canonical`,`password`,`real_name`,`enabled`,`online`,`last_activity`,`password_reset`,`donation`,`api_key`,`playerID`, `data`, `createTime`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',

			'u_user'								=> 'UPDATE `base_user` SET WHERE `userID` = ?',
			'u_user_password'						=> 'UPDATE `base_user` SET `password` = ?, `password_reset` = ? WHERE `userID` = ?',

			'l_user'								=> 'SELECT * FROM `base_user`',
			]];
		}

	public static function get_user($req = []){

		// alternative
		$alt = h::eX($req, [
			'userID'		=> '~1,4294967295/i',
			'playerID'		=> '~1,4294967295/i',
			'username'		=> '~1,60/s',
			'email'			=> '~1,30/s',
			'api_key'		=> '~32,32/s',
			'verify_token'	=> '~1,48/s',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'with_right'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure
		$convert = function($list) use ($opt){

			// for each user in list
			foreach($list as $entry){

				// change to bool
				$entry->enabled = (bool) $entry->enabled;
				$entry->online = (bool) $entry->online;

				// decode data
				$entry->data = json_decode($entry->data);

				// if right list shoud be loaded
				if(!empty($opt['with_right'])){

					// load user specific rights
					$res = right::get_user_right([
						'userID'	=> $entry->userID,
						]);

					// on error
					if($res->status != 200) return $res;

					// take rights just as list of strings
					$entry->right = array_column($res->data, 'right');
					}
				}
			};


		// param order 1: userID
		if(isset($alt['userID'])){

			// call to pdo function
			$entry = self::pdo('s_user', [$alt['userID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 2: username
		if(isset($alt['username'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_username', [$alt['username']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 3: email
		if(isset($alt['email'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_email', [$alt['email']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 4: api_key
		if(isset($alt['api_key'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_api_key', [$alt['api_key']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 5: playerID
		if(isset($alt['playerID'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_playerID', [$alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 6: empty
		if(empty($alt)){

			// load list from DB
			$list = self::pdo('l_user');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return success
			return self::response(200, $list);
			}

		// if nothing matched return error
		return self::response(400, 'Expecting userID|username|email|api_key or no param');
		}

	public static function create_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'email'			=> '~1,255/s',
			'username'		=> '~1,255/s',
			'real_name'		=> '~1,255/s',
			'password'		=> '~/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// basic check email
		if(!filter_var($mand['email'], FILTER_VALIDATE_EMAIL)) return self::response(400, ['email']);

		// optional
		$opt = h::eX($req, [
			'enabled'			=> '~/b',
			'createTime'		=> '~Y-m-d H:i:s/d',
			'online'			=> '~0,1/i',
			'api_key'			=> '~32,32/s',
			'playerID'			=> '~0,4294967295/i',
			'donation'			=> '~/f',
			'last_activity'		=> '~/i',
			'password_reset'	=> '~/i',
			'username_canonical'=> '~1,255/s',
			'data'				=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += [
			'enabled'			=> true,
			'createTime'		=> h::dtstr('now', 'Y-m-d H:i:s'),
			'online'			=> false,
			'api_key'			=> substr(md5(mt_rand()), 0, 32),
			'playerID'			=> 0,
			'donation'			=> 0,
			'last_activity'		=> 0,
			'password_reset'	=> 0,
			'username_canonical'=> strtolower(str_replace(' ', '', $mand['username'])),
			'data'				=> [],
			];

		// search for username
		$res = self::get_user([
			'username'		=> $mand['username'],
			]);

		// if found return
		if($res->status == 200) return self::response(409);

		// search for email
		$res = self::get_user([
			'email'			=> $mand['email'],
			]);

		// if found return
		if($res->status == 200) return self::response(409);

		// hash password
		$mand['password'] = password_hash($mand['password_1'], PASSWORD_DEFAULT);

		// encode data
		$opt['data'] = json_encode($opt['data']);

		// insert
		$ins = self::pdo('i_user', [$mand['email'], $mand['username'], $opt['username_canonical'], $mand['password'], $mand['real_name'], $opt['enabled'] ? 1 : 0, $opt['online'] ? 1 : 0, $opt['last_activity'], $opt['password_reset'], $opt['donation'], $opt['api_key'], $opt['playerID'], $opt['createTime']]);

		// on error
		if($ins === false) return self::response(560);

		// cleanup file cache
		self::cleanup_cache();

		// return success 
		return self::response(201);
		}

	public static function update_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'email'				=> '~1,255/s',
			'username'			=> '~1,255/s',
			'username_canonical'=> '~1,255/s',
			'real_name'			=> '~1,255/s',
			'enabled'			=> '~/b',
			'online'			=> '~/b',
			'last_activity'		=> '~U/d',
			'password_reset'	=> '~U/d',
			'donation'			=> '~/f',
			'playerID'			=> '~0,4294967295/i',
			'createTime'		=> '~Y-m-d H:i:s/d',
			'data'				=> '~/c',
			], $error, true);

		// optional 2, file cache
		$opt2 = h::eX($req, [
			'reset_filecache'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt2
		$opt2 += [
			'reset_filecache'	=> false,
			];

		// search user by userID
		$res = self::get_user([
			'userID'	=> $mand['userID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take user
		$entry = $res->data;

		// check for conflicts
		if(isset($opt['username'])){

			// search user by userID
			$res = self::get_user([
				'username'	=> $opt['username'],
				]);

			// on error
			if($res->status == 200 and $res->data->userID != $entry->userID) return self::response(409);

			// set username canonical (for updating)
			$opt['username_canonical'] = $opt['username'];
			}

		// special check
		if(isset($opt['username_canonical'])){

			// if also username not set, set error
			if(!isset($opt['username'])) return self::response(400, ['username']);

			// if username doesnt match username canonical
			$opt['username_canonical'] = strtolower(str_replace(' ', '', $opt['username']));
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

        // encode data
        if(isset($update['data'])) $update['data'] = json_encode($update['data']);

        // set query
        $query = self::pdo_extract('u_user', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$mand['userID']]));

        // on error
        if($upd === false) return self::response(560);

		// if cache cleanup wanted, do it
		if(!empty($opt2['reset_filecache'])) self::cleanup_cache();

        // return success
        return self::response(204);
		}

	public static function update_user_password($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'		=> '~1,4294967295/i',
			'new_password'	=> '~6,2028/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// search user by userID
		$res = self::get_user([
			'userID'	=> $mand['userID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take user
		$entry = $res->data;

		// timestamp right now
		$now = h::dtstr('now', 'U');

		// hash new password
		$mand['new_password'] = password_hash($mand['new_password'], PASSWORD_DEFAULT);

		// update password and reset timestamp
		$upd = self::pdo('u_user_password', [$mand['new_password'], $now, $entry->userID]);

		// on error
		if($upd === false) return self::response(560);

	    // spam protection
	    sleep(2);

	    // return success
	    return self::response(204);
		}

	}
