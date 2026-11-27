<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league;

use \heart\core\error as e;
use \heart\core\helper as h;

class source {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			// Queries: source_image
			's_source_image'				=> 'SELECT * FROM `source_image` WHERE `sourceID` = ? LIMIT 1',

			'l_source_image'				=> 'SELECT * FROM `source_image` WHERE 1 ORDER BY `gameDate` DESC, `sourceID` DESC',
			'l_source_image_by_gameDate'	=> 'SELECT * FROM `source_image` WHERE `gameDate` = ?',
			'l_source_image_by_gameYear'	=> 'SELECT * FROM `source_image` WHERE YEAR(`gameDate`) = ? ORDER BY `gameDate` DESC, `sourceID` DESC',

			'i_source_image'				=> 'INSERT INTO `source_image` (`url`, `filename`, `gameDate`, `createTime`) VALUES (?,?,?,?)',
			]];
		}

	public static function get_source_url($req = []){

		// alternative
		$alt = h::eX($req, [
			'sourceID'			=> '~1,4294967295/i',
			'gameDate'			=> '~Y-m-d/d',
			'gameYear'			=> '~^[0-9]{4}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: sourceID
		if(isset($alt['sourceID'])){

			// load entry
			$entry = self::pdo('s_source_image', [$alt['sourceID']]);

			// on error
			if($entry === false) return self::response($entry === false ? 560 : 404);

			// on success
			return self::response(200, $entry);
			}

		// param order 2: gameDate
		if(isset($alt['gameDate'])){

			// load list
			$list = self::pdo('l_source_image_by_gameDate', [$alt['gameDate']]);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 3: gameYear
		if(isset($alt['gameYear'])){

			// load list
			$list = self::pdo('l_source_image_by_gameYear', [$alt['gameYear']]);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 4: empty
		if(empty($alt)){

			// call to pdo function
			$list = self::pdo('l_source_image');

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting sourceID|gameDate or no param');
		}

	public static function create_source_url($req = []){

		// mandatory
		$mand = h::eX($req, [
			'image_url'		=> '~1,255/s',
			'gameDate'		=> '~Y-m-d/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'filename'		=> '~1,50/s',
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// default opt
		$opt += [
			'filename'		=> '',
			'createTime'	=> h::dtstr('now', 'Y-m-d H:i:s'),
			];

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$sourceID = self::pdo('i_source_image', [$mand['image_url'], $opt['filename'], $mand['gameDate'], $opt['createTime']]);

		// on error
		if($sourceID === false) return self::response(560);

		// success
		return self::response(201, (object)['sourceID' => $sourceID]);
		}

	}
