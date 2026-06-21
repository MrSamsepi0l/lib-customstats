<?php
/* Last update: 2022-05-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\user;

use \heart\core\error as e;
use \heart\core\helper as h;

class user {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\heart\core\usersession_trait;

	protected static function pdo_config(){

		return [null, [
						's_user'									=> 'SELECT * FROM user WHERE userID = ? LIMIT 1',
						's_user_by_username'						=> 'SELECT * FROM user WHERE username = ? LIMIT 1',
						's_user_by_email'							=> 'SELECT * FROM user WHERE email = ? LIMIT 1',
						's_user_by_hash_key'						=> 'SELECT * FROM user WHERE hash_key = ? LIMIT 1',
						's_user_by_username_canonical'				=> 'SELECT * FROM `user` WHERE `username_canonical` = ? LIMIT 1',

						'l_user'									=> 'SELECT * FROM user WHERE 1 ORDER BY `userID` DESC',

						'i_user'									=> 'INSERT INTO user
																		(email, username, username_canonical, password, real_name, role, enabled, chat_color, online_status, last_activity, last_password_reset, hash_key, createTime)
																		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',

						'u_user'									=> 'UPDATE user SET role = ?, chat_color = ?, enabled = ? WHERE userID = ?',

						'u_user_password'							=> 'UPDATE user SET password = ?, last_password_reset = ? WHERE userID = ?',

						'u_user_login'								=> 'UPDATE user SET online_status = 1 WHERE userID = ?',

						'u_user_login_timestamp'					=> 'UPDATE user SET last_activity = ? WHERE userID = ?',

						'u_user_logout'								=> 'UPDATE user SET online_status = 0 WHERE userID = ?',
			]];
		}

	/* DEPRECATED -> REMOVE WHEN SESSION SECURE API CHECK MIGRATED */
	public static function get_user($req = []){

		// alternative
		$alt = h::eX($req, [
			'userID'			=> '~1,4294967295/i',
			'username'			=> '~1,60/s',
			'email'				=> '~1,30/s',
			'hash_key'			=> '~32,32/s',
			'username_canonical'=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: userID
		if(isset($alt['userID'])){

			// call to pdo function
			$entry = self::pdo('s_user', [$alt['userID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// change to bool
			$entry->enabled = (bool) $entry->enabled;

			// return success
			return self::response(200, $entry);
			}

		// param order 2: username
		if(isset($alt['username'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_username', [$alt['username']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// change to bool
			$entry->enabled = (bool) $entry->enabled;

			// return success
			return self::response(200, $entry);
			}

		// param order 2: username
		if(isset($alt['username_canonical'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_username_canonical', [$alt['username_canonical']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// change to bool
			$entry->enabled = (bool) $entry->enabled;

			// return success
			return self::response(200, $entry);
			}

		// param order 3: email
		if(isset($alt['email'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_email', [$alt['email']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// change to bool
			$entry->enabled = (bool) $entry->enabled;

			// return success
			return self::response(200, $entry);
			}

		// param order 4: hash_key
		if(isset($alt['hash_key'])){

			// call to pdo function
			$entry = self::pdo('s_user_by_hash_key', [$alt['hash_key']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// change to bool
			$entry->enabled = (bool) $entry->enabled;

			// return success
			return self::response(200, $entry);
			}

		// param order 5: empty
		if(empty($alt)){

			// call to pdo function
			$list = self::pdo('l_user');

			// on error
			if($list === false) return self::response(560);

			// convert date
			foreach($list as $key => $entry){

				// get new date format
				$entry->createDate = date('Y-m-d', strtotime($entry->createTime));

				// get bool for enabled value
				$entry->enabled = (bool) $entry->enabled;

				// unset password - never send to client
				unset($entry->password);

				// unset hash_key
				unset($entry->hash_key);
				}

			// return success
			return self::response(200, $list);
			}

		// if nothing matched return error
		return self::response(400, 'Expecting userID|username|email|hash_key or no param');
		}

	}
