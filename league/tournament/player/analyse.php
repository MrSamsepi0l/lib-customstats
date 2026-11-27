<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament\player;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\league\tournament\game_player as tournament_game_player;

class analyse {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: tournament_player */
			's_tournament_player_analyse'				=> 'SELECT tp.playerID, tp.ingame_name, tp.real_name,
															COUNT(tgp.playerID) AS `times_played`, SUM(tgp.won) AS `times_won`,
															COUNT(tgp.playerID) - SUM(tgp.won) AS `times_lost`,
															COUNT(tgp.playerID) / COUNT(tg.gameID) * 100 AS `playrate`,
															SUM(tgp.won) / COUNT(tgp.playerID) * 100 AS `winrate`,
															SUM(tgp.kills) AS `total_kills`, SUM(tgp.deaths) AS `total_deaths`, SUM(tgp.assists) AS `total_assists`,
															AVG(tgp.kills) AS `avg_kills`, AVG(tgp.deaths) AS `avg_deaths`,
															AVG(tgp.assists) AS `avg_assists`, SUM(tgp.gold) AS `total_gold`,
															SUM(tgp.creep_score) AS `total_cs`, SUM(tgp.creep_score) / SUM(tg.duration) AS `cs_per_minute`,
															SUM(tgp.creep_score) / COUNT(tgp.playerID) AS `cs_per_game`,
															SUM(tg.duration) AS `minutes_played`, SUM(tg.duration / 60) AS `hours_played`,
															COUNT(DISTINCT tgp.championID) AS `different_champs_played`, MAX(tg.gameDate) AS `last_game`
															FROM `tournament_player` tp
															INNER JOIN `tournament_game_player` tgp ON tgp.playerID = tp.playerID
															INNER JOIN `tournament_game` tg ON tg.gameID = tgp.gameID
															WHERE tgp.tournamentID = ? AND tp.playerID = ? AND tg.canceled = 0
															LIMIT 1
															',

			'l_tournament_player_analyse'				=> 'SELECT tp.playerID, tp.ingame_name, tp.real_name,
															COUNT(tgp.playerID) AS `times_played`, SUM(tgp.won) AS `times_won`,
															COUNT(tgp.playerID) - SUM(tgp.won) AS `times_lost`,
															COUNT(tgp.playerID) / COUNT(tg.gameID) * 100 AS `playrate`,
															SUM(tgp.won) / COUNT(tgp.playerID) * 100 AS `winrate`,
															SUM(tgp.kills) AS `total_kills`, SUM(tgp.deaths) AS `total_deaths`, SUM(tgp.assists) AS `total_assists`,
															AVG(tgp.kills) AS `avg_kills`, AVG(tgp.deaths) AS `avg_deaths`,
															AVG(tgp.assists) AS `avg_assists`, SUM(tgp.gold) AS `total_gold`,
															SUM(tgp.creep_score) AS `total_cs`, SUM(tgp.creep_score) / SUM(tg.duration) AS `cs_per_minute`,
															SUM(tgp.creep_score) / COUNT(tgp.playerID) AS `cs_per_game`,
															SUM(tg.duration) AS `minutes_played`, SUM(tg.duration / 60) AS `hours_played`,
															COUNT(DISTINCT tgp.championID) AS `different_champs_played`, MAX(tg.gameDate) AS `last_game`
															FROM `tournament_player` tp
															INNER JOIN `tournament_game_player` tgp ON tgp.playerID = tp.playerID
															INNER JOIN `tournament_game` tg ON tg.gameID = tgp.gameID
															WHERE tgp.tournamentID = ? AND tg.canceled = 0
															GROUP BY tp.playerID
															',

			'l_analyse_champs_for_player'				=> 'SELECT tgp.championID, tgp.champion, COUNT(tgp.championID) AS `times_played`, SUM(tgp.won) AS `times_won`,
															COUNT(tgp.championID) - SUM(tgp.won) AS `times_lost`,
															SUM(tgp.won) / COUNT(tgp.championID) * 100 AS `winrate`,
															AVG(tgp.kills) AS `avg_kills`, AVG(tgp.deaths) AS `avg_deaths`, AVG(tgp.assists) AS `avg_assists`, AVG(tgp.creep_score) AS `avg_creep_score`,
															SUM(tgp.creep_score) / SUM(tg.duration) AS `cs_per_minute`
															FROM `tournament_player` tp
															INNER JOIN `tournament_game_player` tgp ON tgp.playerID = tp.playerID
															INNER JOIN `tournament_game` tg ON tg.gameID = tgp.gameID
															WHERE tgp.tournamentID = ? AND tp.playerID = ? AND tg.canceled = 0
															GROUP BY tgp.championID
															',

			'l_analyse_position_for_player'				=> 'SELECT tgp.position AS `role`, COUNT(tgp.position) AS `times_played`, SUM(tgp.won) AS `times_won`,
															COUNT(tgp.position) - SUM(tgp.won) AS `times_lost`,
															SUM(tgp.won) / COUNT(tgp.position) * 100 AS `winrate`
															FROM `tournament_player` tp
															INNER JOIN `tournament_game_player` tgp ON tgp.playerID = tp.playerID
															INNER JOIN `tournament_game` tg ON tgp.gameID = tg.gameID
															WHERE tgp.tournamentID = ? AND tp.playerID = ? AND tg.canceled = 0
															GROUP BY tgp.position
															',
			]];

		}


	public static function get_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'with_champs'	=> '~/b',
			'with_position'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'with_champs'	=> true,
			'with_position'	=> true,
			];

		// alter list
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// decode tag
				// $entry->tag = json_decode($entry->tag);

				// for each float value (since live sql version doesn't support cast function in query)
				foreach(['playrate', 'winrate', 'avg_kills', 'avg_deaths', 'avg_assists', 'cs_per_minute', 'cs_per_game', 'minutes_played', 'hours_played'] as $key){

					// convert
					$entry->{$key} = (float) round($entry->{$key}, 2);
					}

				// for each int value (since live sql version doesn't support cast function in query)
				foreach(['times_won', 'times_lost', 'total_kills', 'total_deaths', 'total_assists', 'total_gold', 'total_cs'] as $key){

					// convert
					$entry->{$key} = (int) $entry->{$key};
					}
				}
			};


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// load entry
			$entry = self::pdo('s_tournament_player_analyse', [$mand['tournamentID'], $alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert
			$convert([$entry]);

			// if champ analysis
			if($opt['with_champs']){

				// load champ analysis
				$res = self::get_analyse_champ([
					'tournamentID'	=> $mand['tournamentID'],
					'playerID'		=> $alt['playerID'],
					]);

				// on error
				if($res->status != 200) return $res;

				// and add it to entry
				$entry->champion_list = $res->data;
				}

			// if position analysis
			if($opt['with_position']){

				// load position analysis
				$res = self::get_analyse_position([
					'tournamentID'	=> $mand['tournamentID'],
					'playerID'		=> $alt['playerID'],
					]);

				// on error
				if($res->status != 200) return $res;

				// and add it to entry
				$entry->position_list = $res->data;
				}

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament_player_analyse', [$mand['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// if champ analysis
			if($opt['with_champs']){

				// for each player
				foreach($list as $entry){

					// load champ analysis
					$res = self::get_analyse_champ([
						'tournamentID'	=> $mand['tournamentID'],
						'playerID'		=> $entry->playerID,
						]);

					// on error
					if($res->status != 200) return $res;

					// and add it to entry
					$entry->champion_list = $res->data;
					}
				}

			// if position analysis
			if($opt['with_position']){

				// for each player
				foreach($list as $entry){

					// load position analysis
					$res = self::get_analyse_position([
						'tournamentID'	=> $mand['tournamentID'],
						'playerID'		=> $entry->playerID,
						]);

					// on error
					if($res->status != 200) return $res;

					// and add it to entry
					$entry->position_list = $res->data;
					}
				}

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playerID or no param');
		}

	public static function get_analyse_champ($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert champion (without spaces)
		$convert = function($list){

			// foreach entry
			foreach($list as $entry){

				// convert champion name (lowecase without spaces)
				$entry->champion_converted = strtolower(str_replace(' ', '', $entry->champion));

				// for each float value
				foreach(['winrate', 'avg_kills', 'avg_deaths', 'avg_assists', 'avg_creep_score', 'cs_per_minute'] as $key){

					// convert
					$entry->{$key} = (float) round($entry->{$key}, 2);
					}

				// for each int value
				foreach(['times_won', 'times_lost'] as $key){

					// convert
					$entry->{$key} = (int) $entry->{$key};
					}
				}
			};

		// closure sort function
		$sort_analyse = function($current, $next){

			// sort by times_played descending
			return ($current->times_played < $next->times_played) ? 1 : -1;
			};


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// load list
			$list = self::pdo('l_analyse_champs_for_player', [$mand['tournamentID'], $alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// sort list
			usort($list, $sort_analyse);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playerID');
		}

	public static function get_analyse_position($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure sort function
		$sort_analyse = function($current, $next){

			// sort by times_played descending
			return ($current->times_played < $next->times_played) ? 1 : -1;
			};

		// closure conversion function
		$convert = function($list){

			// for each float value
			foreach($list as $entry){

				// convert winrate (float)
				$entry->winrate = (float) round($entry->winrate, 2);

				// convert times_won and times_lost (integer)
				$entry->times_won = (int) $entry->times_won;
				$entry->times_lost = (int) $entry->times_lost;
				}
			};


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// load list
			$list = self::pdo('l_analyse_position_for_player', [$mand['tournamentID'], $alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// sort list
			usort($list, $sort_analyse);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playerID');
		}

	}
