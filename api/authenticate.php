<?php
/* Last update: 2026-06-13
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\api;

use \heart\core\helper as h;
use \heart\core\error as e;

use \customstats\base\user as base_user;
use \customstats\base\user\right as base_user_right;

class authenticate {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			// Queries: base_user
			's_api_base_user_authenticate'			=> 'SELECT `userID`, `email`, `username`, `password`, `real_name`, `enabled`, `online`, `last_activity`, `password_reset`, `api_key`, `createTime`
														FROM `base_user`
														WHERE `email` = ? OR `username` = ? OR `username_canonical` = ?
														LIMIT 1',
			]];
		}


	public static function login_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'identifier'	=> '~1,255/s',
			'auth'			=> '~/s',
			], $error);

		// on error
		if($error){

			// log it
			e::logtrigger('Invalid param for authentication: '.h::encode_php($error));

			// return not allowed
			return self::response(403);
			}

		// trim identifier
		$mand['identifier'] = trim($mand['identifier']);

		// convert identifier
		$identifier_canonical = strtolower(str_replace(' ', '', $mand['identifier']));

		// load user by username_canonical
		$user = self::pdo('s_api_base_user_authenticate', [$mand['identifier'], $mand['identifier'], $identifier_canonical]);

		// on error or if not found
		if(!$user){

			// on error, log
			if($user === false) self::response(560);

			// return not allowed
			return self::response(403);
			}

		// if user is disabled
		if(!$user->enabled){

			// return not allowed
			return self::response(403);
			}

		// if password verification fails
		if(password_verify($mand['auth'], $user->password) !== true){

			// return not allowed
			return self::response(403);
			}


		// load user right
		$res = base_user_right::get_user_right([
			'userID'	=> $user->userID,
			]);

		// on error
		if($res->status != 200){

			// log this case (because it shouldn't happen)
			e::logtrigger('Loading user right failed with status: '.h::encode_php($res->status).' for successfully authenticated user (but couldn\'t login): '.h::encode_php($user->username));

			// return not allowed
			return self::response(403);
			}

		// take user right list only
		$user_right_list = array_column($res->data, 'right');


		// set reduced return data to allowed fields only
		$user_data = (object)[
			'api_key'			=> $user->api_key,
			'email'				=> $user->email,
			'username'			=> $user->username,
			'real_name'			=> $user->real_name,
			'enabled'			=> (bool) $user->enabled,
			'online'			=> (bool) $user->online,
			'last_activity'		=> $user->last_activity,
			'password_reset'	=> $user->password_reset,
			'right'				=> $user_right_list,
			'createTime'		=> $user->createTime,		// DEPRECATED, REMOVE IF POSSIBLE
			'registrationTime'	=> $user->createTime,
			];

		// return success with data
		return self::response(200, $user_data);
		}

	}
