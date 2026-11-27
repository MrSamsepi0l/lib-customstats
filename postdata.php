<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
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
