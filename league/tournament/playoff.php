<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class playoff {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: tournament_playoff */
			's_playoff'								=> 'SELECT * FROM `tournament_playoff` WHERE `playoffID` = ? LIMIT 1',

			'l_playoff'								=> 'SELECT * FROM `tournament_playoff`',
			'l_playoff_by_tournamentID'				=> 'SELECT * FROM `tournament_playoff` WHERE `tournamentID` = ?',

			'l_playoff_by_tournament_with_schedule'	=> 'SELECT tpo.playoffID, tpo.tournamentID, tpo.scheduleID, tpo.roundID, tpo.type, tpo.winnerID, tpo.createTime, ts.team_oneID,
														ts.team_twoID, tg.winner_teamID, tt1.name AS `team_one_name`, tt2.name AS `team_two_name`,
														SUM(CASE WHEN (tg.winner_teamID = ts.team_oneID) THEN 1 ELSE 0 END) AS `times_won_team_one`,
														SUM(CASE WHEN (tg.winner_teamID = ts.team_twoID) THEN 1 ELSE 0 END) AS `times_won_team_two`
														FROM `tournament_playoff` tpo
														LEFT JOIN `tournament_game` tg ON tg.scheduleID = tpo.scheduleID
														LEFT JOIN `tournament_schedule` ts ON ts.scheduleID = tpo.scheduleID
														LEFT JOIN `tournament_team` tt1 ON tt1.teamID = ts.team_oneID
														LEFT JOIN `tournament_team` tt2 ON tt2.teamID = ts.team_twoID
														WHERE tpo.tournamentID = ?
														GROUP BY tpo.scheduleID, tpo.playoffID
														'

			]];
		}


	public static function get_playoff($req = []){

		// alternative
		$alt = h::eX($req, [
			'playoffID'		=> '~1,4294967295/i',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure
		$convert = function($list){

			// for each entry in list
			foreach($list as $entry){

				// default
				$entry->finished = false;

				// on type: best of three
				if($entry->type == 'best_of_three'){

					// check if finished and set it true
					if(max($entry->times_won_team_one, $entry->times_won_team_two) == 2) $entry->finished = true;

					// if finished, take winner
					if($entry->finished) $entry->winner_teamID = ($entry->times_won_team_one > $entry->times_won_team_two) ? $entry->team_oneID : $entry->team_twoID;
					}

				// on type: best of five
				if($entry->type == 'best_of_five'){

					// check if finished and set it true
					if(max($entry->times_won_team_one, $entry->times_won_team_two) == 3) $entry->finished = true;

					// if finished, take winner
					if($entry->finished) $entry->winner_teamID = ($entry->times_won_team_one > $entry->times_won_team_two) ? $entry->team_oneID : $entry->team_twoID;
					}
				}
			};


		// param order 1: playoffID
		if(isset($alt['playoffID'])){

			// load entry
			$entry = self::pdo('s_playoff', [$alt['playoffID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_playoff_by_tournament_with_schedule', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playoffID or tournametID');
		}

	}