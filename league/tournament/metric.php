<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class metric {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: tournament_game_player */
			's_team_metric'							=> 'SELECT tt.*, tt.name AS `team_name`,
															tgp.teamID,
															COUNT(DISTINCT tgp.gameID) AS games_played,
															COUNT(DISTINCT CASE WHEN tgp.won = 1 THEN tgp.gameID END) AS games_won,
															COUNT(DISTINCT CASE WHEN tgp.won = 0 THEN tgp.gameID END) AS games_lost,
															ROUND(
																COUNT(DISTINCT CASE WHEN tgp.won = 1 THEN tgp.gameID END)
																/ COUNT(DISTINCT tgp.gameID) * 100,
																2
															) AS winrate
														FROM tournament_game_player tgp
														INNER JOIN `tournament_team` tt ON tt.teamID  = tgp.teamID
														WHERE tgp.teamID = ?
														GROUP BY tgp.teamID
														LIMIT 1
														',

			'l_team_metric_by_tournamentID'			=> 'SELECT tt.*, tt.name AS `team_name`,
															COUNT(DISTINCT tgp.gameID) AS games_played,
															COUNT(DISTINCT CASE WHEN tgp.won = 1 THEN tgp.gameID END) AS games_won,
															COUNT(DISTINCT CASE WHEN tgp.won = 0 THEN tgp.gameID END) AS games_lost,
															ROUND(
																COUNT(DISTINCT CASE WHEN tgp.won = 1 THEN tgp.gameID END)
																/ COUNT(DISTINCT tgp.gameID) * 100,
																2
															) AS winrate
														FROM `tournament_team` tt
														LEFT JOIN `tournament_game_player` tgp ON tgp.teamID  = tt.teamID
														WHERE tt.tournamentID = ?
														GROUP BY tt.teamID
														',

			'l_team_metric'							=> 'SELECT tt.*, tt.name AS `team_name`,
															COUNT(DISTINCT tgp.gameID) AS games_played,
															COUNT(DISTINCT CASE WHEN tgp.won = 1 THEN tgp.gameID END) AS games_won,
															COUNT(DISTINCT CASE WHEN tgp.won = 0 THEN tgp.gameID END) AS games_lost,
															ROUND(
																COUNT(DISTINCT CASE WHEN tgp.won = 1 THEN tgp.gameID END)
																/ COUNT(DISTINCT tgp.gameID) * 100,
																2
															) AS winrate
														FROM `tournament_team` tt
														LEFT JOIN `tournament_game_player` tgp ON tgp.teamID  = tt.teamID
														GROUP BY tt.teamID
														',

			]];
		}

	public static function get_team_metric($req = []){

		// alternative
		$alt = h::eX($req, [
			'teamID'		=> '~1,4294967295/i',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure
		$convert = function($list){

			// for each entry in list
			foreach($list as $entry){

				// convert to float
				$entry->winrate = (float) $entry->winrate;

				// convert teamname
				$entry->team_name_converted = strtolower(str_replace(' ', '', $entry->team_name));
				}
			};


		// param order 1: teamID
		if(isset($alt['teamID'])){

			// load entry
			$entry = self::pdo('s_team_metric', [$alt['teamID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_team_metric_by_tournamentID', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// param order 3: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_team_metric');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting teamID|tournametID or no parameter');
		}

	}