<?php
/* Last update: 2022-06-15
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league;

use \heart\core\error as e;
use \heart\core\helper as h;

class replay {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			// queries: replay
			's_replay'									=> 'SELECT * FROM `replay` WHERE `replayID` = ? LIMIT 1',
			's_replay_by_name'							=> 'SELECT * FROM `replay` WHERE `name` = ? LIMIT 1',
			's_replay_by_url'							=> 'SELECT * FROM `replay` WHERE `url` = ? LIMIT 1',

			'l_replay_by_gameID'						=> 'SELECT * FROM `replay` WHERE `gameID` = ?',
			'l_replay_by_gameDate'						=> 'SELECT * FROM `replay` WHERE `gameDate` = ?',
			'l_replay'									=> 'SELECT * FROM `replay`',

			'i_replay'									=> 'INSERT INTO `replay` (`name`, `url`, `gameID`, `type`, `gameDate`) VALUES (?,?,?,?,?)',
			'u_replay'									=> 'UPDATE `replay` SET `name` = ?, `url` = ?, `gameID` = ?, `type` = ?, `gameDate` = ? WHERE `replayID` = ?',
			'd_replay'									=> 'DELETE FROM `replay` WHERE `replayID` = ?',


			// queries: replay_player
			's_replay_player'							=> 'SELECT * FROM `replay_player` WHERE `replay_playerID` = ? LIMIT 1',
			's_replay_player_by_replayID_and_playerID' 	=> 'SELECT * FROM `replay_player` WHERE `replayID` = ? AND `playerID` = ? LIMIT 1',

			'l_replay_player'							=> 'SELECT * FROM `replay_player`',

			'l_replay_player_by_replayID'				=> 'SELECT rp.replay_playerID, rp.replayID, rp.playerID, p.ingame_name, p.real_name
															FROM `replay_player` rp
															INNER JOIN `player` p ON p.playerID = rp.playerID
															WHERE rp.replayID = ?
															',

			'l_replay_player_by_playerID'				=> 'SELECT rp.replay_playerID, rp.replayID, rp.playerID, p.ingame_name, p.real_name
															FROM `replay_player` rp
															INNER JOIN `player` p ON p.playerID = rp.playerID
															WHERE rp.playerID = ?',

			'i_replay_player'							=> 'INSERT INTO `replay_player` (`replayID`, `playerID`) VALUES (?,?)',
			'u_replay_player'							=> 'UPDATE `replay_player` SET `replayID` = ?, `playerID` = ? WHERE `replay_playerID` = ?',
			'd_replay_player'							=> 'DELETE FROM `replay_player` WHERE `replay_playerID` = ?',
			'd_replay_player_by_replayID_and_playerID' 	=> 'DELETE FROM `replay_player` WHERE `replayID` = ? AND `playerID` = ?',

			]];
		}

	/* Object: Replay */
	public static function get_replay($req = []){

		// alternative
		$alt = h::eX($req, [
			'replayID'			=> '~1,4294967295/i',
			'name'				=> '~1,255/s',
			'url'				=> '~1,255/s',
			'gameID'			=> '~1,4294967295/i',
			'gameDate'			=> '~Y-m-d/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: replayID
		if(isset($alt['replayID'])){

			// load entry from DB
			$entry = self::pdo('s_replay', $alt['replayID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// on success
			return self::response(200, $entry);
			}

		// param order 2: name
		if(isset($alt['name'])){

			// load entry from DB
			$entry = self::pdo('s_replay_by_name', $alt['name']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// on success
			return self::response(200, $entry);
			}

		// param order 3: url
		if(isset($alt['url'])){

			// load entry from DB
			$entry = self::pdo('s_replay_by_url', $alt['url']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// on success
			return self::response(200, $entry);
			}

		// param order 4: gameID
		if(isset($alt['gameID'])){

			// load list from DB
			$list = self::pdo('l_replay_by_gameID', $alt['gameID']);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 5: gameDate
		if(isset($alt['gameDate'])){

			// load list from DB
			$list = self::pdo('l_replay_by_gameDate', $alt['gameDate']);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 6: empty
		if(empty($alt)){

			// load list from DB
			$list = self::pdo('l_replay');

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting replayID|name|url|gameID|gameDate or no param');
		}

	public static function create_replay($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'				=> '~1,255/s',
			'url'				=> '~1,255/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'gameID'			=> '~1,4294967295/i',
			'gameDate'			=> '~Y-m-d/d',
			'type'				=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'gameID'			=> null,
			'gameDate'			=> null,
			'type'				=> '',
			];

		// create DB entry
		$ins = self::pdo('i_replay', [$mand['name'], $mand['url'], $opt['gameID'], $opt['type'], $opt['gameDate']]);

		// on error
		if($ins === false) return self::response(560);

		// on success
		return self::response(201, (object)['replayID' => $ins, 'filename' => $mand['name']]);
		}

	public static function update_replay($req = []){

		// mandatory
		$mand = h::eX($req, [
			'replayID'			=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'				=> '~1,255/s',
			'url'				=> '~1,255/s',
			'gameID'			=> '~1,4294967295/i',
			'gameDate'			=> '~Y-m-d/d',
			'type'				=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry by replayID
		$res = self::get_replay([
			'replayID'			=> $mand['replayID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$entry = $res->data;

		// default update
		$update = false;

		// check updateable fields
		foreach($opt as $key => $val){

			// if nothing changed, skip
			if($entry->{$key} === $val) continue;

			// update entry
			$entry->{$key} = $val;

			// set update true
			if(!$update) $update = true;
			}

		// if nothing to update, return here
		if(!$update) return self::response(204);

		// update entry
		$upd = self::pdo('u_replay', [$entry->name, $entry->url, $entry->gameID, $entry->type, $entry->gameDate, $entry->replayID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function delete_replay($req = []){

		// mandatory
		$mand = h::eX($req, [
			'replayID'			=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry from DB
		$res = self::get_replay([
			'replayID'			=> $mand['replayID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$entry = $res->data;

		// delete entry from DB
		$del = self::pdo('d_replay', [$entry->replayID]);

		// on error
		if($del === false) return self::response(560);

		// return success
		return self::response(204);
		}


	/* Object: Replay_player */
	public static function get_replay_player($req = []){

		// alternative
		$alt = h::eX($req, [
			'replay_playerID'	=> '~1,4294967295/i',
			'replayID'			=> '~1,4294967295/i',
			'playerID'			=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: replay_playerID
		if(isset($alt['replay_playerID'])){

			// load entry from DB
			$entry = self::pdo('s_replay_player', [$alt['replay_playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: replayID and playerID
		if(isset($alt['replayID']) and isset($alt['playerID'])){

			// load entry from DB
			$entry = self::pdo('s_replay_player_by_replayID_and_playerID', [$alt['replayID'], $alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: replayID
		if(isset($alt['replayID'])){

			// load list from DB
			$list = self::pdo('l_replay_player_by_replayID', $alt['replayID']);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 4: playerID
		if(isset($alt['playerID'])){

			// load list from DB
			$list = self::pdo('l_replay_player_by_playerID', $alt['playerID']);

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// load list from DB
			$list = self::pdo('l_replay_player');

			// on error
			if($list === false) return self::response(560);

			// on success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting replayID or no param');
		}

	public static function create_replay_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'replayID'				=> '~1,4294967295/i',
			'playerID'				=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create DB entry
		$ins = self::pdo('i_replay_player', [$mand['replayID'], $mand['playerID']]);

		// on error
		if($ins === false) return self::response(560);

		// on success
		return self::response(201, (object)['replay_playerID' => $ins]);
		}

	public static function update_replay_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'replay_playerID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'replayID'				=> '~1,4294967295/i',
			'playerID'				=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry by replay_playerID
		$res = self::get_replay_player([
			'replay_playerID'		=> $mand['replay_playerID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$entry = $res->data;

		// default update
		$update = false;

		// check updateable fields
		foreach($opt as $key => $val){

			// if nothing changed, skip
			if($entry->{$key} === $val) continue;

			// update entry
			$entry->{$key} = $val;

			// set update true
			if(!$update) $update = true;
			}

		// if nothing to update, return here
		if(!$update) return self::response(204);

		// update entry
		$upd = self::pdo('u_replay_player', [$entry->replayID, $entry->playerID, $entry->replay_playerID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function delete_replay_player($req = []){

		// mandatory
		$alt = h::eX($req, [
			'replay_playerID'		=> '~1,4294967295/i',
			'replayID'				=> '~1,4294967295/i',
			'playerID'				=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: replay_playerID
		if(isset($alt['replay_playerID'])){

			// load entry from DB
			$res = self::get_replay_player([
				'replay_playerID'		=> $alt['replay_playerID'],
				]);

			// on error
			if($res->status !== 200) return $res;

			// take entry
			$entry = $res->data;

			// delete entry from DB
			$del = self::pdo('d_replay_player', [$entry->replay_playerID]);

			// on error
			if($del === false) return self::response(560);
			}

		// param order 2: replayID and playerID
		if(isset($alt['replayID']) and isset($alt['playerID'])){

			// load entry from DB
			$res = self::get_replay_player([
				'replayID'		=> $alt['replayID'],
				'playerID'		=> $alt['playerID'],
				]);

			// on error
			if($res->status !== 200) return $res;

			// take entry
			$entry = $res->data;

			// delete entry from DB
			$del = self::pdo('d_replay_player_by_replayID_and_playerID', [$entry->replayID, $entry->playerID]);

			// on error
			if($del === false) return self::response(560);

			// return success
			return self::response(204);
			}

		// on param error
		return self::response(400, 'Expeting replay_playerID or replayID and playerID');
		}

	}
