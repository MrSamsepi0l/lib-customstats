<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class schedule {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_tournament_schedule'						=> 'SELECT * FROM `tournament_schedule` WHERE `scheduleID` = ? LIMIT 1',

			'l_tournament_schedule'						=> 'SELECT * FROM `tournament_schedule`',

			'l_tournament_schedule_by_tournamentID'		=> 'SELECT ts.scheduleID, ts.team_oneID, ts.team_twoID, ts.tournamentID, ts.estimatedDate_from, ts.estimatedDate_to, ts.type, ts.stream_link, tt.name AS `team_one_name`, tt2.name as `team_two_name`
															FROM `tournament_schedule` ts
															LEFT JOIN `tournament_team` tt ON tt.teamID = ts.team_oneID
															LEFT JOIN `tournament_team` tt2 ON tt2.teamID = ts.team_twoID
															WHERE ts.tournamentID = ?
															',

			'l_tournament_schedule_by_tournamentID_type'=> 'SELECT ts.scheduleID, ts.team_oneID, ts.team_twoID, ts.tournamentID, ts.estimatedDate_from, ts.estimatedDate_to, ts.type, ts.stream_link, tg.winner_teamID,
																tg.gameDate, tg.canceled, tg.vod_link, tt.name AS `team_one_name`, tt2.name as `team_two_name`,
																CASE WHEN ts.estimatedDate_from > NOW() THEN 1 ELSE 0 END AS `future`,
																IF(tg.gameDate, tg.gameDate, "9999-99-99") AS `gameDate`
															FROM `tournament_schedule` ts
															LEFT JOIN `tournament_team` tt ON tt.teamID = ts.team_oneID
															LEFT JOIN `tournament_team` tt2 ON tt2.teamID = ts.team_twoID
															LEFT JOIN `tournament_game` tg ON tg.scheduleID = ts.scheduleID
															WHERE ts.tournamentID = ? AND ts.type = ?
															GROUP BY ts.scheduleID, tg.winner_teamID
															ORDER BY `gameDate` ASC
															',

			'l_tournament_schedule_by_teamID'			=> 'SELECT * FROM `tournament_schedule` WHERE `team_oneID` = ? OR `team_twoID` = ?',
			'l_tournament_schedule_by_teamID_type'		=> 'SELECT * FROM `tournament_schedule` WHERE (`team_oneID` = ? OR `team_twoID` = ?) AND `type` = ?',

			'i_tournament_schedule'						=> 'INSERT INTO `tournament_schedule` (`tournamentID`, `team_oneID`, `team_twoID`, `type`, `stream_link`, `estimatedDate_from`, `estimatedDate_to`) VALUES (?,?,?,?,?,?,?)',

			'u_tournament_schedule'						=> 'UPDATE `tournament_schedule` SET WHERE `scheduleID` = ?',
			]];
		}


	public static function get_schedule($req = []){

		// alternative
		$alt = h::eX($req, [
			'scheduleID'			=> '~1,4294967295/i',
			'tournamentID'			=> '~1,4294967295/i',
			'teamID'				=> '~1,4294967295/i',
			'type'					=> '~^(?:regular_season|knockout)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure function
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// convert to boolean
				$entry->future = (bool) $entry->future;

				// convert canceled if given
				if(isset($entry->canceled)) $entry->canceled = (bool) $entry->canceled;
				}
			};


		// param order 1: scheduleID
		if(isset($alt['scheduleID'])){

			// load entry
			$entry = self::pdo('s_tournament_schedule', [$alt['scheduleID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: tournamentID + type
		if(isset($alt['tournamentID']) and isset($alt['type'])){

			// load list
			$list = self::pdo('l_tournament_schedule_by_tournamentID_type', [$alt['tournamentID'], $alt['type']]);

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 2: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_schedule_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 3: teamID + type
		if(isset($alt['teamID']) and isset($alt['type'])){

			// load list
			$list = self::pdo('l_tournament_schedule_by_teamID_type', [$alt['teamID'], $alt['teamID'], $alt['type']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 4: teamID
		if(isset($alt['teamID'])){

			// load list
			$list = self::pdo('l_tournament_schedule_by_teamID', [$alt['teamID'], $alt['teamID'], $alt['type']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_schedule');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting scheduleID|tournamentID+type|tournamentID|teamID+type|teamID or no parameter');
		}

	public static function create_schedule($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'		=> '~1,4294967295/i',
			'team_oneID'		=> '~1,4294967295/i',
			'team_twoID'		=> '~1,4294967295/i',
			'type'				=> '~^(?:regular_season|knockout)$',
			'estimatedDate_from'=> '~Y-m-d H:i:s/d',
			'estimatedDate_to'	=> '~Y-m-d H:i:s/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'stream_link'		=> '~1,128/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'stream_link'		=> '',
			];

		// insert
		$scheduleID = self::pdo('i_tournament_schedule', [$mand['tournamentID'], $mand['team_oneID'], $mand['team_twoID'], $mand['type'], $opt['stream_link'], $mand['estimatedDate_from'], $mand['estimatedDate_to']]);

		// on error
		if($scheduleID === false) return self::response(560);

		// return primary key
		return self::response(201, (object)['scheduleID' => $scheduleID]);
		}

	public static function update_schedule($req = []){

		// mandatory
		$mand = h::eX($req, [
			'scheduleID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'tournamentID'		=> '~1,4294967295/i',
			'team_oneID'		=> '~1,4294967295/i',
			'team_twoID'		=> '~1,4294967295/i',
			'type'				=> '~^(?:regular_season|knockout)$',
			'stream_link'		=> '~1,128/s',
			'estimatedDate_from'=> '~Y-m-d H:i:s/d',
			'estimatedDate_to'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// get schedule by ID
		$res = self::get_schedule([
			'scheduleID'		=> $mand['scheduleID'],
			]);

		// on error
		if($res->status != 200) return $res;

        // take entry
        $entry = $res->data;

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
        $query = self::pdo_extract('u_tournament_schedule', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$mand['scheduleID']]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}