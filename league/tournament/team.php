<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class team {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_tournament_team'						=> 'SELECT * FROM `tournament_team` WHERE `teamID` = ? LIMIT 1',
			's_tournament_team_by_name'				=> 'SELECT * FROM `tournament_team` WHERE `name` = ? LIMIT 1',

			'l_tournament_team'						=> 'SELECT * FROM `tournament_team`',

			'l_tournament_team_by_teamID'			=> 'SELECT tt.teamID, tt.tournamentID, tt.name, tt.createTime,
															tp.playerID, tp.ingame_name, tp.real_name, tp.enabled, tp.createTime, ttp.tag, ttp.leader, ttp.role
														FROM `tournament_team` tt
														LEFT JOIN `tournament_team_player` ttp ON ttp.teamID = tt.teamID
														LEFT JOIN `tournament_player` tp ON tp.playerID = ttp.playerID
														WHERE tt.teamID = ?
														',
			'l_tournament_team_by_tournamentID'		=> 'SELECT tt.teamID, tt.tournamentID, tt.name, tt.createTime, SUM(CASE WHEN tg.canceled = 0 THEN 1 ELSE 0 END) AS `times_played`, SUM(CASE WHEN tg.winner_teamID = tt.teamID AND tg.canceled = 0 THEN 1 ELSE 0 END) AS `times_won`, SUM(CASE WHEN tg.winner_teamID != tt.teamID AND tg.canceled = 0 THEN 1 ELSE 0 END) AS `times_lost`
														FROM `tournament_team` tt
														LEFT JOIN `tournament_game` tg ON (tg.team_oneID = tt.teamID OR tg.team_twoID = tt.teamID) AND tg.type = "regular_season"
														WHERE tt.tournamentID = ?
														GROUP BY tt.teamID
														',

			'i_tournament_team'						=> 'INSERT INTO `tournament_team` (`tournamentID`, `name`, `createTime`) VALUES (?,?,?)',

			'u_tournament_team'						=> 'UPDATE `tournament_team` SET WHERE `teamID` = ?',
			]];
		}


	public static function get_team($req = []){

		// alternative
		$alt = h::eX($req, [
			'teamID'			=> '~1,4294967295/i',
			'name'				=> '~1,255/s',
			'tournamentID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'with_player'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'with_player'		=> true,
			];

		// alter list
		$alter_list = function($list){

			// foreach entry
			foreach($list as $entry){

				// decode tag if given
				if(h::cX($entry, 'tag')) $entry->tag = json_decode($entry->tag);

				// decode leader if given
				if(h::cX($entry, 'leader')) $entry->leader = (bool) $entry->leader;

				// decode wins and loses to int
				if(h::cX($entry, 'times_won')) $entry->times_won = (int) $entry->times_won;
				if(h::cX($entry, 'times_lost')) $entry->times_lost = (int) $entry->times_lost;
				}

			};


		// param order 1: teamID
		if(isset($alt['teamID']) and !empty($opt['with_player'])){

			// load list
			$list = self::pdo('l_tournament_team_by_teamID', [$alt['teamID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 1: teamID
		if(isset($alt['teamID'])){

			// load list
			$entry = self::pdo('s_tournament_team', [$alt['teamID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return list
			return self::response(200, $entry);
			}

		// param order 2: name
		if(isset($alt['name'])){

			// load entry
			$entry = self::pdo('s_tournament_team_by_name', [$alt['name']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: tournamentID + with player
		if(isset($alt['tournamentID']) and !empty($opt['with_player'])){

			// load list
			$list = self::pdo('l_tournament_team_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// for each entry
			foreach($list as $entry){

				// load player by teamID
				$playerlist = self::pdo('l_tournament_team_by_teamID', [$entry->teamID]);

				// on error
				if(!$playerlist) return self::response($playerlist === false ? 560 : 404);

				// for each player
				foreach($playerlist as $player){

					// decode tag
					$player->tag = json_decode($player->tag);
					}

				// take it
				$entry->playerlist = $playerlist;
				}

			// return list
			return self::response(200, $list);
			}

		// param order 4: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_team_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// return list
			return self::response(200, $list);
			}

		// param order 5: empty + with player
		if(empty($alt) and !empty($opt['with_player'])){

			// load list
			$list = self::pdo('l_tournament_team');

			// on error
			if($list === false) return self::response(560);

			// alter list
			$alter_list($list);

			// for each entry
			foreach($list as $entry){

				// load player by teamID
				$playerlist = self::pdo('l_tournament_team_by_teamID', [$entry->teamID]);

				// on error
				if(!$playerlist) return self::response($playerlist === false ? 560 : 404);

				// for each player
				foreach($playerlist as $player){

					// decode tag
					$player->tag = json_decode($player->tag);
					}

				// take it
				$entry->playerlist = $playerlist;
				}

			// return list
			return self::response(200, $list);
			}

		// param order 6: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_team');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting teamID|name|tournamentID+with_player|tournamentID or no parameter');
		}

	public static function create_team($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			'name'			=> '~1,255/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'createTime'	=> h::dtstr('now'),
			];

		// create entry
		$teamID = self::pdo('i_tournament_team', [$mand['tournamentID'], $mand['name'], $opt['createTime']]);

		// on error
		if($teamID === false) return self::response(560);

		// return success
		return self::response(201, (object)['teamID' => $teamID]);
		}

	public static function update_team($req = []){

		// mandatory
		$mand = h::eX($req, [
			'teamID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,255/s',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// get tournament by ID
		$res = self::get_team([
			'teamID'		=> $mand['teamID'],
			'with_player'	=> false,
			]);

		// on error
		if($res->status != 200) return $res;

        // take entry
        $entry = $res->data;

		// if tournament name is about to be changed
		if(isset($opt['name'])){

			// load tournament by name
			$res = self::get_team([
				'name'		=> $opt['name'],
				]);

			// if found and not the identical entry, return conflict
			if($res->status == 200 and $res->data->teamID != $mand['teamID']) return self::response(409);
			}

        // default update
        $update = [];

        // check for each opt
        foreach($opt as $k => $v){

            // if value does not match push into update
            if($entry->{$k} !== $v) $update[$k] = $v;
            }

        // if there is nothing to update
        if(!$update) return self::response(204);

        // set query
        $query = self::pdo_extract('u_tournament_team', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$mand['teamID']]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}