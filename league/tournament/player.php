<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class player {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_tournament_player'						=> 'SELECT * FROM `tournament_player` WHERE `playerID` = ? LIMIT 1',
			'l_tournament_player'						=> 'SELECT * FROM `tournament_player`',

			'i_tournament_player'						=> 'INSERT INTO `tournament_player` (`ingame_name`, `real_name`, `enabled`, `createTime`) VALUES (?,?,?,?)',

			'u_tournament_player'						=> 'UPDATE `tournament_player` SET `ingame_name` = ?, `real_name` = ?, `enabled` = ? WHERE `playerID` = ?',
			]];
		}


	public static function get_player($req = []){

		// alternative
		$alt = h::eX($req, [
			'playerID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: playerID
		if(isset($alt['playerID'])){

			// load entry
			$entry = self::pdo('s_tournament_player', [$alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_player');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playerID or no parameter');
		}

	public static function create_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'ingame_name'	=> '~1,255/s',
			'real_name'		=> '~1,255/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'enabled'		=> '~/b',
			'createTime'	=> '~Y-m-d/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'enabled'		=> true,
			'createTime'	=> h::dtstr('now'),
			];

		// create entry
		$playerID = self::pdo('i_tournament_player', [$mand['ingame_name'], $mand['real_name'], $opt['enabled'] ? 1 : 0, $opt['createTime']]);

		// on error
		if($playerID === false) return self::response(560);

		// return success
		return self::response(201, (object)['playerID' => $playerID]);
		}

	public static function update_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'ingame_name'	=> '~1,255/s',
			'real_name'		=> '~1,255/s',
			'enabled'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load player
		$res = self::get_player([
			'playerID'	=> $mand['playerID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take player
		$entry = $res->data;

		// convert special values
		if(isset($opt['enabled'])) $opt['enabled'] = $opt['enabled'] ? 1 : 0;

		// for each optional param
		foreach($opt as $key => $val){

			// if nothing changed, skip
			if($entry->{$key} === $val) continue;

			// change value
			$entry->{$key} = $val;
			}

		// update entry
		$upd = self::pdo('u_tournament_player', [$entry->ingame_name, $entry->real_name, $entry->enabled, $entry->playerID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	}
