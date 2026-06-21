<?php
/* Last update: 2022-05-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats;

class postdata {

	static $postdata;

	public static function get(){

		// get decoded postdata from php input
		$postdata = json_decode(file_get_contents('php://input'));

		// set static variable
		self::$postdata = $postdata;

		// return postdata
		return $postdata;
		}

	}
