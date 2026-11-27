<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\api;

use \heart\core\helper as h;
use \heart\core\error as e;

use \customstats\base\user as base_user;

class registration {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: base_user */
			'i_api_base_user_registration'			=> 'INSERT INTO `base_user`
														(`email`, `username`, `username_canonical`, `password`, `real_name`, `enabled`, `online`, `last_activity`, `password_reset`, `api_key`, `data`, `createTime`)
														VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
														',
			]];
		}


	public static function register_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'username'		=> '~1,255/s',
			'email'			=> '~1,255/s',
			'real_name'		=> '~1,255/s',
			'auth'			=> '~/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// basic php email check
		if(!filter_var($mand['email'], FILTER_VALIDATE_EMAIL)) return self::response(400, ['email']);

		// combine mandatory with defaults
		$param = [
			'username'			=> trim($mand['username']),
			'email'				=> trim($mand['email']),
			'real_name'			=> trim($mand['real_name']),
			'auth'				=> $mand['auth'],
			'enabled'			=> 1,
			'createTime'		=> h::dtstr('now'),
			'online'			=> 0,
			'api_key'			=> substr(md5(mt_rand()), 0, 32),
			'username_canonical'=> strtolower(str_replace(' ', '', trim($mand['username']))),
			'last_activity'		=> 0,
			'password_reset'	=> 0,
			'data'				=> '',
			];

		// search username
		$res = base_user::get_user([
			'username'	=> $param['username'],
			]);

		// if found, return conflict
		if($res->status == 200) return self::response(409);

		// on error
		if($res->status != 404) return self::response(570, $res);

		// search email
		$res = base_user::get_user([
			'email'		=> $param['email'],
			]);

		// if found, return conflict
		if($res->status == 200) return self::response(409);

		// on error
		if($res->status != 404) return self::response(570, $res);

		// hash password
		$param['auth'] = password_hash($param['auth'], PASSWORD_DEFAULT);

	    // spam protection
	    sleep(2);

	    // create base user
	    $userID = self::pdo('i_api_base_user_registration', [
	    	$param['email'],
	    	$param['username'],
	    	$param['username_canonical'],
	    	$param['auth'],
	    	$param['real_name'],
	    	$param['enabled'],
	    	$param['online'],
	    	$param['last_activity'],
	    	$param['password_reset'],
	    	$param['api_key'],
	    	$param['data'],
	    	$param['createTime'],
	    	]);

	    // return success with created userID
	    return self::response(201, (object)['userID' => $userID]);
		}

	}
