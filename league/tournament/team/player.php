<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class team_player {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			// Queries: tournament_team_player
			'l_tournament_team_player'										=> 'SELECT * FROM `tournament_team_player`',
			's_tournament_team_player_by_teamID_playerID'					=> 'SELECT * FROM `tournament_team_player` WHERE `teamID` = ? AND `playerID` = ? LIMIT 1',

			'i_tournament_team_player'										=> 'INSERT INTO `tournament_team_player` (`teamID`, `playerID`, `leader`, `role`, `tag`) VALUES (?,?,?,?,?)',
			'u_tournament_team_player'										=> 'UPDATE `tournament_team_player` SET `leader` = ?, `role` = ?, `tag` = ? WHERE `teamID` = ? AND `playerID` = ?',
			'd_tournament_team_player'										=> 'DELETE FROM `tournament_team_player` WHERE `teamID` = ? AND `playerID` = ?',


			// Queries: tournament_team_player | tournament_player
			'l_tournament_team_player_by_teamID'							=> 'SELECT * FROM `tournament_team_player` ttp
																				INNER JOIN `tournament_player` tp ON tp.playerID = ttp.playerID
																				WHERE `teamID` = ?
																				',


			// Queries: tournament_team_player | tournament_team
			'l_tournament_team_player_by_playerID'							=> 'SELECT tt.*, ttp.playerID, ttp.leader, ttp.role, ttp.tag
																				FROM `tournament_team_player` ttp
																				INNER JOIN `tournament_team` tt ON tt.teamID = ttp.teamID
																				WHERE ttp.playerID = ?
																				',
			'l_tournament_team_player_by_playerID_and_tournamentID'			=> ['l_tournament_team_player_by_playerID', ['ttp.playerID = ?' => 'ttp.playerID = ? AND tt.tournamentID = ?']],

			]];
		}


	public static function get_team_player($req = []){

		// alternative
		$alt = h::eX($req, [
			'teamID'			=> '~1,4294967295/i',
			'playerID'			=> '~1,4294967295/i',
			'tournamentID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define closure function
		$alter_list = function($list){

			// for each entry
			foreach($list as $entry){

				// convert to bool
				$entry->leader = (bool) $entry->leader;

				// decode tag
				$entry->tag = json_decode($entry->tag);

				// if name given (join on tournament_team), convert lowecase without special chars
				if(isset($entry->name)) $entry->team_name_converted = strtolower(str_replace(' ', '', $entry->name));

				// if enabled given (join on tournament_team), convert to bool
				if(isset($entry->enabled)) $entry->enabled = (bool) $entry->enabled;
				}
			};


		// param order 1: teamID & playerID
		if(isset($alt['teamID']) and isset($alt['playerID'])){

			// load entry
			$entry = self::pdo('s_tournament_team_player_by_teamID_playerID', [$alt['teamID'], $alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$alter_list([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: playerID & tournamentID
		if(isset($alt['playerID']) and isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_team_player_by_playerID_and_tournamentID', [$alt['playerID'], $alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 3: teamID
		if(isset($alt['teamID'])){

			// load list
			$list = self::pdo('l_tournament_team_player_by_teamID', [$alt['teamID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 4: playerID
		if(isset($alt['playerID'])){

			// load list
			$list = self::pdo('l_tournament_team_player_by_playerID', [$alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_team_player');

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting teamID+playerID|playerID+tournamentID|teamID|playerID or no parameter');
		}

	public static function create_team_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'teamID'	=> '~1,4294967295/i',
			'playerID'	=> '~1,4294967295/i',
			'leader'	=> '~/b',
			'role'		=> '~^(?:top|jungle|mid|adc|support)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'tag'		=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_team_player([
			'teamID'	=> $mand['teamID'],
			'playerID'	=> $mand['playerID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// if entry already exists, return conflict
		if($res->status == 200) return self::response(409);

		// default opt
		$opt += [
			'tag'		=> [],
			];

		// encode tag
		$opt['tag'] = json_encode($opt['tag']);

		// create entry
		$insert = self::pdo('i_tournament_team_player', [$mand['teamID'], $mand['playerID'], $mand['leader'] ? 1 : 0, $mand['role'], $opt['tag']]);

		// on error
		if($insert === false) return self::response(560);

		// return success
		return self::response(201, (object)['teamID' => $mand['teamID'], 'playerID' => $mand['playerID']]);
		}

	public static function update_team_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'teamID'	=> '~1,4294967295/i',
			'playerID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'leader'	=> '~/b',
			'role'		=> '~^(?:top|jungle|mid|adc|support)$',
			'tag'		=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_team_player([
			'teamID'	=> $mand['teamID'],
			'playerID'	=> $mand['playerID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// for each optional param
		foreach($opt as $key => $val){

			// if nothing changed, skip
			if($entry->{$key} === $val) continue;

			// take new value
			$entry->{$key} = $val;
			}

		// encode tag
		$entry->tag = json_encode($entry->tag);

		// update entry
		$upd = self::pdo('u_tournament_team_player', [$entry->leader, $entry->role, $entry->tag, $entry->teamID, $entry->playerID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function delete_team_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'teamID'		=> '~1,4294967295/i',
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_team_player([
			'teamID'		=> $mand['teamID'],
			'playerID'		=> $mand['playerID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// delete entry
		$del = self::pdo('d_tournament_team_player', [$entry->teamID, $entry->playerID]);

		// on error
		if($del === false) return self::response(560);

		// return success
		return self::response(204);
		}

	}
