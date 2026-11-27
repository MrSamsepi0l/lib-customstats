<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\base;

use \heart\core\error as e;
use \heart\core\helper as h;

class session {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: base_session */
			's_session'								=> 'SELECT * FROM `base_session` WHERE `sessionID` = ? LIMIT 1',
			's_session_by_hash'						=> 'SELECT * FROM `base_session` WHERE `hash` = ? LIMIT 1',

			'l_session_by_identifier'				=> 'SELECT * FROM `base_session` WHERE `identifier` = ?',

			'i_session'								=> 'INSERT INTO `base_session` (`identifier`,`hash`, `data`, `createTime`) VALUES (?,?,?,?)',

			'u_session'								=> 'DELETE FROM `base_session` WHERE `sessionID` = ?',

			]];
		}

	public static function get_session($req = []){

		// alternative
		$alt = h::eX($req, [
			'sessionID'		=> '~1,4294967295/i',
			'identifier'	=> '~1,255/s',
			'hash'			=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// decode data
				$entry->data = json_decode($entry->data);
				}
			};


		// param order 1: sessionID
		if(isset($alt['sessionID'])){

			// load entry from DB
			$entry = self::pdo('s_session', $alt['sessionID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: identifier
		if(isset($alt['identifier'])){

			// load list from DB
			$list = self::pdo('l_session_by_identifier', $alt['identifier']);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 3: hash
		if(isset($alt['hash'])){

			// load entry from DB
			$entry = self::pdo('s_session_by_hash', $alt['hash']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param error
		return self::response(400, 'Expecting sessionID|identifier or hash');
		}

	public static function create_session($req = []){

		// mandatory
		$mand = h::eX($req, [
			'identifier'	=> '~1,255/s',
			'hash'			=> '~1,255/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'data'			=> '~/V',
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += [
			'data'			=> [],
			'createTime'	=> h::dtstr('now'),
			];

		// encode data for db
		$opt['data'] = json_encode($opt['data']);

		// create entry
		$sessionID = self::pdo('i_session', [$mand['identifier'], $mand['hash'], $opt['data'], $opt['createTime']]);

		// on error
		if($sessionID === false) return self::response(560);

		// return created sessionID
		return self::response(201, (object)['sessionID' => $sessionID]);
		}

	public static function update_session($req = []){

		}
	}
