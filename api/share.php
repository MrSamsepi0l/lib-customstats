<?php
/* Last update: 2023-08-06
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\api;

use \heart\core\helper as h;
use \heart\core\error as e;

use \customstats\base\user as base_user;

class share {
	use \heart\core\libcom_trait;


	public static function get_active_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'api_key'		=> '~1,32/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load user
		$res = base_user::get_user([
			'api_key'		=> $mand['api_key'],
			]);

		// if not found, return not found
		if($res->status != 200) return self::response(404);

		// if found but disabled, return gone
		if($res->status == 200 and !$res->data->enabled) return self::response(410);

		// return user profile
		return self::response(200, $res->data);
		}

	}
