<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\tournament\player;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\league\tournament\player as tournament_player;
use \customstats\league\tournament\game_player as tournament_game_player;
use \customstats\league\tournament\team_player as tournament_team_player;

class profile {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			// Queries: tournament, tournament_game, tournament_game_player, tournament_game_ban
			's_tournament_profile_metric'					=> 'SELECT p.playerID, p.ingame_name, p.real_name,
																COUNT(cgp.playerID) AS `times_played`, SUM(cgp.won) AS `times_won`,
																COUNT(cgp.playerID) - SUM(cgp.won) AS `times_lost`,
																(SELECT COUNT(`gameID`) FROM `tournament_game`) AS `total_games_general`,
																ROUND(COUNT(cgp.playerID) / (SELECT COUNT(`gameID`) FROM `tournament_game`) * 100, 2) AS `playrate`,
																ROUND(SUM(cgp.won) / COUNT(cgp.playerID) * 100, 2) AS `winrate`,
																SUM(cgp.kills) AS `total_kills`, SUM(cgp.deaths) AS `total_deaths`, SUM(cgp.assists) AS `total_assists`,
																ROUND((AVG(cgp.kills) + AVG(cgp.assists)) / AVG(cgp.deaths), 2) AS `kda_number`,
																ROUND(AVG(cgp.kills), 2) AS `avg_kills`, ROUND(AVG(cgp.deaths), 2) AS `avg_deaths`,
																ROUND(AVG(cgp.assists), 2) AS `avg_assists`, SUM(cgp.gold) AS `total_gold`,
																SUM(cgp.creep_score) AS `total_cs`, ROUND(SUM(cgp.creep_score) / SUM(cg.duration), 2) AS `cs_per_minute`,
																ROUND(SUM(cgp.creep_score) / COUNT(cgp.playerID), 2) AS `cs_per_game`,
																SUM(cg.duration) AS `minutes_played`, ROUND(SUM(cg.duration / 60), 2) AS `hours_played`,
																COUNT(DISTINCT cgp.championID) AS `different_champs_played`, MAX(cg.gameDate) AS `last_game`,
																(SELECT max(`gameDate`) FROM `tournament_game`) AS `last_game_date_general`
																FROM `tournament_player` p
																INNER JOIN `tournament_game_player` cgp ON cgp.playerID = p.playerID
																INNER JOIN `tournament_game` cg ON cg.gameID = cgp.gameID
																WHERE p.playerID = ? AND cg.canceled = 0 AND p.enabled = 1
																LIMIT 1
																',
			's_tournament_profile_metric_by_tournament'		=> ['s_tournament_profile_metric', ['AND p.enabled = 1' => 'AND p.enabled = 1 AND cgp.tournamentID = ?']],

			'l_tournament_profile_metric'					=> 'SELECT p.playerID
																FROM `tournament_player` p
																INNER JOIN `tournament_game_player` cgp ON cgp.playerID = p.playerID
																WHERE p.enabled = 1
																GROUP BY p.playerID',

			'l_tournament_profile_tournament'				=> 'SELECT t.*
																FROM `tournament` t
																INNER JOIN `tournament_game_player` tgp on tgp.tournamentID = t.tournamentID
																WHERE tgp.playerID = ?
                                                            	GROUP BY tgp.tournamentID, tgp.playerID
																',
			'l_tournament_profile_metric_by_tournament'		=> ['l_tournament_profile_metric', ['WHERE p.enabled = 1' => 'WHERE p.enabled = 1 AND cgp.tournamentID = ?']],


			'l_tournament_profile_champion'					=> 'SELECT cgp.championID, c.gender, cgp.champion, COUNT(cgp.championID) AS `times_played`, SUM(cgp.won) AS `times_won`,
																COUNT(cgp.championID) - SUM(cgp.won) AS `times_lost`,
																ROUND(SUM(cgp.won) / COUNT(cgp.championID) * 100, 2) AS `winrate`,
																ROUND(AVG(cgp.kills), 2) AS `avg_kills`, ROUND(AVG(cgp.deaths), 2) AS `avg_deaths`, ROUND(AVG(cgp.assists), 2) AS `avg_assists`, ROUND(AVG(cgp.creep_score), 2) AS `avg_creep_score`,
																ROUND(SUM(cgp.creep_score) / SUM(cg.duration), 2) AS `cs_per_minute`
																FROM `tournament_game_player` cgp
																INNER JOIN `tournament_game` cg ON cg.gameID = cgp.gameID
																INNER JOIN `champions` c ON cgp.championID = c.championID
																WHERE cgp.playerID = ? AND cg.canceled = 0
																GROUP BY cgp.championID
																',

			'l_tournament_profile_champion_by_tournament'	=> ['l_tournament_profile_champion', ['AND cg.canceled = 0' => 'AND cg.canceled = 0 AND cgp.tournamentID = ?']],

			'l_tournament_profile_vs_champ'			 	 	=> 'SELECT cgp2.championID, cgp2.champion,
																COUNT(cgp2.gameID) AS `times_played_against`,
																SUM(cgp2.won) AS `champ_won_against_player`, COUNT(cgp2.gameID) - SUM(cgp2.won) AS `champ_lost_against_player`,
																ROUND(SUM(cgp2.won) / COUNT(cgp2.gameID) * 100, 2) AS `winrate_vs_player`
																FROM `tournament_game_player` cgp1
																INNER JOIN `tournament_game_player` cgp2 ON cgp2.gameID = cgp1.gameID AND cgp2.team != cgp1.team
																WHERE cgp1.playerID = ?
																GROUP BY cgp2.championID
																',

			'l_tournament_profile_vs_champ_by_tournament' 	=> ['l_tournament_profile_vs_champ', ['WHERE cgp1.playerID = ?' => 'WHERE cgp1.playerID = ? AND cgp1.tournamentID = ?']],

			'l_tournament_profile_champion_ban'			  	=> 'SELECT cgb.championID, COUNT(cgb.gameID) AS `times_banned_vs_player`,
																(SELECT COUNT(gameID) FROM tournament_game_player cgp4 WHERE cgp4.playerID = cgp.playerID) AS `total_games_player`,
																COUNT(cgb.gameID) / (SELECT COUNT(c1.gameID) FROM `tournament_game_player` c1 WHERE c1.playerID = cgp.playerID) * 100 AS `banrate_vs_player`,
	                                                            (SELECT COUNT(gameID) FROM tournament_game_ban cgb1 WHERE cgb1.championID = cgb.championID GROUP BY `championID`) AS `times_banned_general`,
	                                                            (SELECT COUNT(`gameID`) FROM `tournament_game`) AS `total_games`
																FROM `tournament_game_player` cgp
																INNER JOIN `tournament_game_ban` cgb ON cgb.gameID = cgp.gameID AND cgb.team != cgp.team
																WHERE cgp.playerID = ?
																GROUP BY cgb.championID
																',

			'l_tournament_profile_position'					=> 'SELECT cgp.position AS `role`, COUNT(cgp.position) AS `times_played`, SUM(cgp.won) AS `times_won`,
																COUNT(cgp.position) - SUM(cgp.won) AS `times_lost`,
																ROUND(SUM(cgp.won) / COUNT(cgp.position) * 100, 2) AS `winrate`
																FROM `tournament_game_player` cgp
																WHERE cgp.playerID = ?
																GROUP BY cgp.position
																',
			'l_tournament_profile_position_by_tournament'	=> ['l_tournament_profile_position', ['WHERE cgp.playerID = ?' => 'WHERE cgp.playerID = ? AND cgp.tournamentID = ?']],


			's_tournament_profile_summoners'				=> 'SELECT COUNT(`game_playerID`) AS `total_sum`, (SELECT COUNT(`game_playerID`) FROM `tournament_game_player` WHERE `playerID` = ?) AS `total_games`,
																(SELECT COUNT(`game_playerID`) FROM `tournament_game_player` WHERE `summoner_one` = ? AND `playerID` = ?) AS `on_d`,
																(SELECT COUNT(`game_playerID`) FROM `tournament_game_player` WHERE `summoner_two` = ? AND `playerID` = ?) AS `on_f`
																FROM `tournament_game_player`
																WHERE `playerID` = ? AND (`summoner_one` = ? OR `summoner_two` = ?)
																LIMIT 1
																',

			's_tournament_profile_summoners_by_tournament'	=> 'SELECT COUNT(`game_playerID`) AS `total_sum`, (SELECT COUNT(`game_playerID`) FROM `tournament_game_player` WHERE `playerID` = ? AND `tournamentID` = ?) AS `total_games`,
																(SELECT COUNT(`game_playerID`) FROM `tournament_game_player` WHERE `summoner_one` = ? AND `playerID` = ? AND `tournamentID` = ?) AS `on_d`,
																(SELECT COUNT(`game_playerID`) FROM `tournament_game_player` WHERE `summoner_two` = ? AND `playerID` = ? AND `tournamentID` = ?) AS `on_f`
																FROM `tournament_game_player`
																WHERE `playerID` = ? AND `tournamentID` = ? AND (`summoner_one` = ? OR `summoner_two` = ?)
																LIMIT 1
																',

			's_tournament_profile_streak'					=> 'SELECT `game_playerID`, `won`
																FROM `tournament_game_player`
																WHERE `playerID` = ?
																ORDER BY `game_playerID` DESC
																',

			's_tournament_profile_streak_by_tournament'		=> ['s_tournament_profile_streak', ['`playerID` = ?' => '`playerID` = ? AND `tournamentID` = ?']],

			'l_tournament_profile_max_avg_total_stats'		=> 'SELECT cgp.playerID, COUNT(cgp.playerID) AS `times_played`,
																AVG(cgp.kills) AS `avg_kills`, AVG(cgp.deaths) AS `avg_deaths`, AVG(cgp.assists) AS `avg_assists`,
																SUM(cgp.creep_score) / SUM(cg.duration) AS `cs_per_minute`,
																(AVG(cgp.kills) + AVG(cgp.assists)) / AVG(cgp.deaths) AS `kda_number`
																FROM `tournament_game_player` cgp
																INNER JOIN `tournament_game` cg ON cg.gameID = cgp.gameID
																GROUP BY cgp.playerID
																',

			]];
		}

	protected static $lvl1_cache = [];

	public static function get_profile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert closure
		$convert = function($entry){

			// for each float value
			foreach(['winrate', 'playrate', 'avg_kills', 'avg_deaths', 'avg_assists', 'cs_per_game', 'cs_per_minute', 'hours_played', 'kda_number', 'minutes_played'] as $key){

				// convert
				$entry->{$key} = (float) $entry->{$key};
				}
			};


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// define player profile
			$profile = [
				'position_list'					=> [],
				'summoner_list'					=> [],
				'player_streak'					=> [],
				'champion_with_player'			=> [],
				'champion_vs_player'			=> [],
				'stat_playerlist'				=> [],
				'tournament_list'				=> [],
				'team_list'						=> [],
				'match_history'					=> [],
				];


			// param order 1: tournamentID
			if(isset($alt['tournamentID'])){

				// load player basic metric by tournament
				$entry = self::pdo('s_tournament_profile_metric_by_tournament', [$alt['playerID'], $alt['tournamentID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);
				}

			// else ...
			else{

				// load player basic metric
				$entry = self::pdo('s_tournament_profile_metric', [$alt['playerID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);
				}

			// convert entry
			$convert($entry);


			// load tournaments for player
			$tournament_list = self::pdo('l_tournament_profile_tournament', [$alt['playerID']]);

			// on error
			if($tournament_list === false) return self::response(560);

			// convert tournament list
			foreach($tournament_list as $tournament){

				// add converted name
				$tournament->name_converted = strtolower(str_replace(' ', '', $tournament->name));
				}

			// add result to profile
			$profile['tournament_list'] = $tournament_list;


			// load team list for profile
			$res = tournament_team_player::get_team_player([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['team_list'] = $res->data;


			// load profile stats
			$res = self::get_profile_stat();

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['stat_playerlist'] = $res->data;


			// load champion analysis
			$res = self::get_profile_champion([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['champion_with_player'] = $res->data;


			// load profile position
			$res = self::get_profile_position([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['position_list'] = $res->data;


			// load profile summoners
			$res = self::get_profile_summoner([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['summoner_list'] = $res->data;


			// load streak analysis
			$res = self::get_profile_streak([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['player_streak'] = $res->data;


			// load champion vs player analysis
			$res = self::get_profile_vs_champion([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['champion_vs_player'] = $res->data;


			// load profile match history
			$res = self::get_profile_match_history([
				'playerID'		=> $alt['playerID'],
				'tournamentID'	=> $alt['tournamentID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['match_history'] = $res->data;


			// finally combine entry (including basic metric for player) and profile
			$profile = $profile + (array) $entry;

			// return profile
			return self::response(200, (object) $profile);
			}

		// param order 2: tournamentID
		if(isset($alt['tournamentID'])){

			// load player list (only IDs)
			$list = self::pdo('l_tournament_profile_metric_by_tournament', [$alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// default profile list
			$profile_list = [];

			// for each player
			foreach($list as $entry){

				// use recursive call to load all profiles with tournamentID
				$res = self::get_profile([
					'project'		=> $mand['project'],
					'playerID'		=> $entry->playerID,
					'tournamentID'	=> $alt['tournamentID'],
					]);

				// on unexpected error
				if($res->status != 200) return $res;

				// add to profile list
				$profile_list[] = $res->data;
				}

			// return profile list
			return self::response(200, $profile_list);
			}

		// param order 3: empty
		if(empty($alt)){

			// load player list (only IDs)
			$list = self::pdo('l_tournament_profile_metric');

			// on error
			if($list === false) return self::response(560);

			// default profile list
			$profile_list = [];

			// for each player
			foreach($list as $entry){

				// use recursive call to load all profiles
				$res = self::get_profile([
					'project'		=> $mand['project'],
					'playerID'		=> $entry->playerID,
					]);

				// if not found or disabled, skip
				if($res->status == 404) continue;

				// on unexpected error
				if($res->status != 200) return $res;

				// add to profile list
				$profile_list[] = $res->data;
				}

			// return profile list
			return self::response(200, $profile_list);
			}

		// return param error
		return self::response(400, 'Expecting playerID or no param');
		}

	protected static function get_profile_champion($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert champion (without spaces)
		$convert = function($list){

			// foreach entry
			foreach($list as $entry){

				// convert champion name (lowercase without spaces)
				$entry->champion_converted = strtolower(str_replace(' ', '', $entry->champion));

				// for each float value
				foreach(['winrate', 'avg_kills', 'avg_deaths', 'avg_assists', 'avg_creep_score', 'cs_per_minute'] as $key){

					// convert
					$entry->{$key} = (float) $entry->{$key};
					}
				}
			};

		// closure sort function
		$sort_analyse = function($current, $next){

			// sort by times_played descending
			return ($current->times_played < $next->times_played) ? 1 : -1;
			};


		// param order 1: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_profile_champion_by_tournament', [$mand['playerID'], $alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// sort list
			usort($list, $sort_analyse);

			// return list
			return self::response(200, $list);
			}

		// load list
		$list = self::pdo('l_tournament_profile_champion', [$mand['playerID']]);

		// on error
		if($list === false) return self::response(560);

		// convert
		$convert($list);

		// sort list
		usort($list, $sort_analyse);

		// return list
		return self::response(200, $list);
		}

	protected static function get_profile_position($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure sort function
		$sort_analyse = function($current, $next){

			// sort by times_played descending
			return ($current->times_played < $next->times_played) ? 1 : -1;
			};

		// closure conversion function
		$convert = function($list) use ($sort_analyse){

			// for each float value
			foreach($list as $entry){

				// convert winrate (float)
				$entry->winrate = (float) $entry->winrate;
				}
			};

		// param order 1: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_profile_position_by_tournament', [$mand['playerID'], $alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// sort list
			usort($list, $sort_analyse);
			}

		// load list
		$list = self::pdo('l_tournament_profile_position', [$mand['playerID']]);

		// on error
		if($list === false) return self::response(560);

		// convert
		$convert($list);

		// sort list
		usort($list, $sort_analyse);

		// return list
		return self::response(200, $list);
		}

	protected static function get_profile_summoner($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure sort function
		$sort_analyse = function($current, $next){

			// descending
			return ($current->total < $next->total) ? 1 : -1;
			};

		// define summoner list
		$summoners = ['Flash', 'Ignite', 'Exhaust', 'Heal', 'Teleport', 'Smite', 'Cleanse', 'Ghost', 'Barrier'];

		// define result
		$result = [];

		// for each summoner
		foreach($summoners as $summoner){

			// if tournamentID given
			if(isset($alt['tournamentID'])){

				// load entry for player and tournament
				$entry = self::pdo('s_tournament_profile_summoners_by_tournament', [$mand['playerID'], $alt['tournamentID'], $summoner, $mand['playerID'], $alt['tournamentID'], $summoner, $mand['playerID'], $alt['tournamentID'], $mand['playerID'], $alt['tournamentID'], $summoner, $summoner]);
				}

			// else ...
			else {

				// load entry for player only
				$entry = self::pdo('s_tournament_profile_summoners', [$mand['playerID'], $summoner, $mand['playerID'], $summoner, $mand['playerID'], $mand['playerID'], $summoner, $summoner]);
				}

			// on error
			if($entry === false) return self::response(560);

			// if not found, skip
			if(!$entry) continue;

			// build object
			$stat = (object)[
				'summoner'	=> $summoner,
				'total'		=> $entry->total_sum,
				'per_game'	=> round($entry->total_sum / $entry->total_games * 100, 2),
				'on_d'		=> $entry->on_d ?: 0,
				'on_f'		=> $entry->on_f ?: 0,
				];

			// add to result
			$result[] = $stat;
			}

		// sort result by total picked
		usort($result, $sort_analyse);

		// return list
		return self::response(200, $result);
		}

	protected static function get_profile_streak($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if tournamentID given
		if(isset($alt['tournamentID'])){

			// load list for player and tournament (newest games first)
			$list = self::pdo('s_tournament_profile_streak_by_tournament', [$mand['playerID'], $alt['tournamentID']]);
			}

		// else ...
		else{

			// load list only for player (newest games first)
			$list = self::pdo('s_tournament_profile_streak', [$mand['playerID']]);
			}

		// on error
		if($list === false) return self::response(560);

		// define stat object
		$stat = (object)[
			'max_win_streak'	=> 0,
			'max_lose_streak'	=> 0,
			'actual_win_streak'	=> 0,
			'actual_lose_streak'=> 0,
			];


		// check actual win streak
		foreach($list as $entry){

			// if already lost, break
			if(!$entry->won) break;

			// increment actual win streak
			$stat->actual_win_streak++;
			}

		// check actual lose streak
		foreach($list as $entry){

			// if already won, break
			if($entry->won) break;

			// increment actual win streak
			$stat->actual_lose_streak++;
			}

		// define win count
		$win_count = 0;
		$lose_count = 0;

		// get max streak
		foreach($list as $index => $entry){

			if($entry->won){

				// increment win
				$win_count++;

				// take new maximum lose streak
				$stat->max_lose_streak = max($stat->max_lose_streak, $lose_count);

				// reset lose count
				$lose_count = 0;
				}

			// if lost
			if(!$entry->won){

				// increment lose
				$lose_count++;

				// take new maximum win streak
				$stat->max_win_streak = max($stat->max_win_streak, $win_count);

				// reset win count
				$win_count = 0;
				}
			}

		// return stat directly as object
		return self::response(200, $stat);
		}

	protected static function get_profile_vs_champion($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure conversion function
		$convert = function($list){

			// for each float value
			foreach($list as $entry){

				// convert winrate (float)
				$entry->winrate_vs_player = (float) $entry->winrate_vs_player;
				}
			};

		// param order 1: tournamentID
		if(isset($alt['tournamentID'])){

			// load list
			$list = self::pdo('l_tournament_profile_vs_champ_by_tournament', [$mand['playerID'], $alt['tournamentID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// load list
		$list = self::pdo('l_tournament_profile_vs_champ', [$mand['playerID']]);

		// on error
		if($list === false) return self::response(560);

		// convert
		$convert($list);

		// return list
		return self::response(200, $list);
		}

	protected static function get_profile_stat($req = []){

		// define cache key
		$cache_key = 'customstats:league:player_profile:analyse_stat_list';

		// if list is cached
		if(isset(self::$lvl1_cache[$cache_key])){

			// directly return list
			return self::response(200, self::$lvl1_cache[$cache_key]);
			}

		// load list
		$list = self::pdo('l_tournament_profile_max_avg_total_stats');

		// on error
		if($list === false) return self::response(560);

		// convert entries
		foreach($list as $entry){

			// for each float value
			foreach(['avg_kills', 'avg_deaths', 'avg_assists', 'kda_number', 'cs_per_minute'] as $key){

				// convert
				$entry->{$key} = (float) round($entry->{$key}, 2);
				}
			}

		// cache list
		self::$lvl1_cache[$cache_key] = $list;

		// return list
		return self::response(200, $list);
		}

	protected static function get_profile_match_history($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert
		$convert = function($list){

			// for each entry in list
			foreach($list as $entry){

				// calculate relative days since when game was played
				$entry->relative_day = intval((h::dtstr('now', 'U') - h::dtstr($entry->gameDate, 'U')) / 3600 / 24);

				// if it was yesterday
				$entry->date = (object)[
					'today'			=> ($entry->relative_day == 0),
					'yesterday'		=> ($entry->relative_day == 1),
					];

				// remove all whitespace and convert to lowercase
				$entry->champion_converted = strtolower(str_replace(' ', '', $entry->champion));

				// kda number
				$entry->kda_number = ($entry->deaths) ? round(($entry->kills + $entry->assists) / $entry->deaths, 2) : $entry->kills + $entry->assists;
				}
			};


		// load all players (for their names)
		$res = tournament_player::get_player();

		// on error
		if($res->status != 200) return $res;

		// take playerlist
		$playerlist = $res->data;

		// default index list
		$playerlist_index = [];

		// for each player
		foreach($playerlist as $player){

			// create playerID index list
			$playerlist_index[$player->playerID] = $player;
			}


		// load all games player played
		$res = tournament_game_player::get_game_player([
			'tournamentID'	=> $alt['tournamentID'] ?? null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take data
		$match_history = $res->data;


		// default list
		$list_converted = [];

		// get all gameIDs
		$gameID_list = array_column($match_history, 'gameID');

		// for each gameID
		foreach($gameID_list as $gameID){

			// if already set, skip
			if(isset($list_converted[$gameID])) continue;

			// create index list for each gameID
			$list_converted[$gameID] = [
				'history'			=> [
					'playerID_list'		=> [],
					'team_one_list'		=> [],
					'team_two_list'		=> [],
					],
				];
			}

		// remove unused
		unset($gameID_list);


		// for each match history
		foreach($match_history as $history){

			// if playerID matches, combine default history with lists with match history stats
			if($mand['playerID'] == $history->playerID) $list_converted[$history->gameID]['history'] += (array) $history;

			// add playerID to list
			$list_converted[$history->gameID]['history']['playerID_list'][] = $history->playerID;

			// if team one
			if($history->team == 1){

				// add player to team one list
				$list_converted[$history->gameID]['history']['team_one_list'][] = (object)[
					'playerID'			=> $history->playerID,
					'ingame_name'		=> $playerlist_index[$history->playerID]->ingame_name,
					'championID'		=> $history->championID,
					'champion'			=> $history->champion,
					'champion_converted'=> strtolower(str_replace(' ', '', $history->champion)),
					];
				}

			// if team two
			elseif($history->team == 2){

				// add player to team two list
				$list_converted[$history->gameID]['history']['team_two_list'][] = (object)[
					'playerID'			=> $history->playerID,
					'ingame_name'		=> $playerlist_index[$history->playerID]->ingame_name,
					'championID'		=> $history->championID,
					'champion'			=> $history->champion,
					'champion_converted'=> strtolower(str_replace(' ', '', $history->champion)),
 					];
				}
			}

		// default result
		$result = [];

		// for each list converted
		foreach($list_converted as $gameID => $entry){

			// if player was in game
			if(in_array($mand['playerID'], $entry['history']['playerID_list'])){

				// remove playerID list
				unset($entry['history']['playerID_list']);

				// add history as object to result
				$result[] = (object) $entry['history'];
				}
			}

		// convert match history
		$convert($result);

		// return list
		return self::response(200, $result);
		}

	}
