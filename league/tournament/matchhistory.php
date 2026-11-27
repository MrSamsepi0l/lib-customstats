<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class matchhistory {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: tournament_game */
			// 'l_match_history_by_tournament_teamID'	=> '',

			'l_match_history_by_tournamentID'		=> 'SELECT tg.gameID, tg.tournamentID, tg.team_oneID, tg.team_twoID, tg.winner_teamID, tg.type, tg.gameDate, tg.duration, tg.scheduleID, tg.canceled, tg.vod_link, tg.createTime,
															(SELECT SUM(`kills`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 1) AS `kills_team_one`,
															(SELECT SUM(`kills`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 2) AS `kills_team_two`,
															(SELECT SUM(`deaths`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 1) AS `deaths_team_one`,
															(SELECT SUM(`deaths`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 2) AS `deaths_team_two`,
															(SELECT SUM(`assists`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 1) AS `assists_team_one`,
															(SELECT SUM(`assists`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 2) AS `assists_team_two`,
															(SELECT SUM(`gold`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 1) AS `gold_team_one`,
															(SELECT SUM(`gold`) FROM `tournament_game_player` tgp WHERE tgp.gameID = tg.gameID AND tgp.team = 2) AS `gold_team_two`
															FROM `tournament_game` tg
															WHERE tg.tournamentID = ?
															ORDER BY tg.gameID DESC',

			]];
		}

	public static function get_match_history($req = []){

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			'teamID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert function
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// convert to bool
				$entry->canceled = (bool) $entry->canceled;
				}
			};

		// param order 1: tournamentID & teamID
		if(isset($alt['tournamentID']) and isset($alt['teamID'])){

			// return not implemented yet
			return self::response(501);

			// load list
			$list = self::pdo('l_match_history_by_tournament_teamID', [$alt['tournamentID'], $alt['teamID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 2: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_match_history_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting tournamentID+teamID or tournamentID');
		}

	}