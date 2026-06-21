<?php
/* Last update: 2025-10-24
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\customgame\player;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\base\user as base_user;
use \customstats\league\player as league_player;
use \customstats\league\customgame\player as game_player;
use \customstats\league\customgame\analyse\player as analyse_player;

class profile {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\customstats\extension\filecache_trait;

	protected static function pdo_config(){
		return [null, [

			's_customgame_profile_metric'				=> 'SELECT p.playerID, p.ingame_name, p.real_name, p.tag,
															COUNT(cgp.playerID) AS `times_played`, SUM(cgp.won) AS `times_won`,
															COUNT(cgp.playerID) - SUM(cgp.won) AS `times_lost`,
															(SELECT COUNT(`gameID`) FROM `custom_game`) AS `total_games_general`,
															ROUND(COUNT(cgp.playerID) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100, 2) AS `playrate`,
															ROUND(SUM(cgp.won) / COUNT(cgp.playerID) * 100, 2) AS `winrate`,
															SUM(cgp.kills) AS `total_kills`, SUM(cgp.deaths) AS `total_deaths`, SUM(cgp.assists) AS `total_assists`,
															ROUND((AVG(cgp.kills) + AVG(cgp.assists)) / AVG(cgp.deaths), 2) AS `kda_number`,
															ROUND(AVG(cgp.kills), 2) AS `avg_kills`, ROUND(AVG(cgp.deaths), 2) AS `avg_deaths`,
															ROUND(AVG(cgp.assists), 2) AS `avg_assists`, SUM(cgp.gold) AS `total_gold`,
															SUM(cgp.creep_score) AS `total_cs`, ROUND(SUM(cgp.creep_score) / SUM(cg.duration), 2) AS `cs_per_minute`,
															ROUND(SUM(cgp.creep_score) / COUNT(cgp.playerID), 2) AS `cs_per_game`,
															SUM(cg.duration) AS `minutes_played`, ROUND(SUM(cg.duration / 60), 2) AS `hours_played`,
															COUNT(DISTINCT cgp.championID) AS `different_champs_played`, MAX(cg.gameDate) AS `last_game`,
															(SELECT max(`gameDate`) FROM `custom_game`) AS `last_game_date_general`
															FROM `player` p
															INNER JOIN `custom_game_player` cgp ON cgp.playerID = p.playerID
															INNER JOIN `custom_game` cg ON cg.gameID = cgp.gameID
															WHERE p.playerID = ? AND cg.canceled = 0 AND p.enabled = 1
															LIMIT 1
															',

			// only playerIDs
			'l_customgame_profile_metric'				=> 'SELECT p.playerID
															FROM `player` p
															INNER JOIN `custom_game_player` cgp ON cgp.playerID = p.playerID
															WHERE p.enabled = 1
															GROUP BY p.playerID',


			'l_customgame_analyse_champs_for_player'	=> 'SELECT cgp.championID, c.gender, cgp.champion, COUNT(cgp.championID) AS `times_played`, SUM(cgp.won) AS `times_won`,
															COUNT(cgp.championID) - SUM(cgp.won) AS `times_lost`,
															SUM(cgp.won) / COUNT(cgp.championID) * 100 AS `winrate`,
															AVG(cgp.kills) AS `avg_kills`, AVG(cgp.deaths) AS `avg_deaths`, AVG(cgp.assists) AS `avg_assists`, AVG(cgp.creep_score) AS `avg_creep_score`,
															SUM(cgp.creep_score) / SUM(cg.duration) AS `cs_per_minute`
															FROM `custom_game_player` cgp
															INNER JOIN `custom_game` cg ON cg.gameID = cgp.gameID
															INNER JOIN `champions` c ON cgp.championID = c.championID
															WHERE cgp.playerID = ? AND cg.canceled = 0
															GROUP BY cgp.championID
															',

			'l_customgame_analyse_champs_against_player'=> 'SELECT cgp2.championID, cgp2.champion,
															COUNT(cgp2.gameID) AS `times_played_against`,
															SUM(cgp2.won) AS `champ_won_against_player`, COUNT(cgp2.gameID) - SUM(cgp2.won) AS `champ_lost_against_player`,
															SUM(cgp2.won) / COUNT(cgp2.gameID) * 100 AS `winrate_vs_player`
															FROM `custom_game_player` cgp1
															INNER JOIN `custom_game_player` cgp2 ON cgp2.gameID = cgp1.gameID AND cgp2.teamID != cgp1.teamID
															WHERE cgp1.playerID = ?
															GROUP BY cgp2.championID
															',

			'l_customgame_analyse_champ_bans_for_player'=> 'SELECT cgb.championID, COUNT(cgb.gameID) AS `times_banned_vs_player`,
															(SELECT COUNT(gameID) FROM custom_game_player cgp4 WHERE cgp4.playerID = cgp.playerID) AS `total_games_player`,
															COUNT(cgb.gameID) / (SELECT COUNT(c1.gameID) FROM `custom_game_player` c1 WHERE c1.playerID = cgp.playerID) * 100 AS `banrate_vs_player`,
                                                            (SELECT COUNT(gameID) FROM custom_game_ban cgb1 WHERE cgb1.championID = cgb.championID GROUP BY `championID`) AS `times_banned_general`,
                                                            (SELECT COUNT(`gameID`) FROM `custom_game`) AS `total_games`
															FROM `custom_game_player` cgp
															INNER JOIN `custom_game_ban` cgb ON cgb.gameID = cgp.gameID AND cgb.teamID != cgp.teamID
															WHERE cgp.playerID = ?
															GROUP BY cgb.championID
															',

			'l_customgame_analyse_position_for_player'	=> 'SELECT cgp.position AS `role`, COUNT(cgp.position) AS `times_played`, SUM(cgp.won) AS `times_won`,
															COUNT(cgp.position) - SUM(cgp.won) AS `times_lost`,
															SUM(cgp.won) / COUNT(cgp.position) * 100 AS `winrate`
															FROM `custom_game_player` cgp
															WHERE cgp.playerID = ?
															GROUP BY cgp.position
															',

			's_customgame_analyse_summoners_for_player'	=> 'SELECT COUNT(`game_playerID`) AS `total_sum`, (SELECT COUNT(`game_playerID`) FROM `custom_game_player` WHERE `playerID` = ?) AS `total_games`,
															(SELECT COUNT(`game_playerID`) FROM `custom_game_player` WHERE `summoner_one` = ? AND `playerID` = ?) AS `on_d`,
															(SELECT COUNT(`game_playerID`) FROM `custom_game_player` WHERE `summoner_two` = ? AND `playerID` = ?) AS `on_f`
															FROM `custom_game_player`
															WHERE `playerID` = ? AND (`summoner_one` = ? OR `summoner_two` = ?)
															LIMIT 1
															',

			's_customgame_analyse_streak_for_player'	=> 'SELECT `game_playerID`, `won`
															FROM `custom_game_player`
															WHERE `playerID` = ?
															ORDER BY `game_playerID`
															',

			'l_customgame_max_avg_total_stats'			=> 'SELECT cgp.playerID, p.ingame_name, COUNT(cgp.playerID) AS `times_played`,
															AVG(cgp.kills) AS `avg_kills`, AVG(cgp.deaths) AS `avg_deaths`, AVG(cgp.assists) AS `avg_assists`,
															SUM(cgp.creep_score) / SUM(cg.duration) AS `cs_per_minute`,
															(AVG(cgp.kills) + AVG(cgp.assists)) / AVG(cgp.deaths) AS `kda_number`,
															(SELECT MAX(`gameDate`) FROM `custom_game`) AS `last_game_date_general`,
															MAX(cg.gameDate) AS `last_game`
															FROM `custom_game_player` cgp
															INNER JOIN `custom_game` cg ON cg.gameID = cgp.gameID
															LEFT JOIN `player` p ON p.playerID = cgp.playerID
															GROUP BY cgp.playerID
															',

			]];
		}

	protected static $lvl1_cache = [];

	public static function get_profile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// define player profile
			$profile = [
				'position_list'					=> [],
				'summoner_list'					=> [],
				'player_streak'					=> [],
				'champion_with_player'			=> [],
				'champion_vs_player'			=> [],
				'donation'						=> null,
				'account_data'					=> null,
				'stat_playerlist'				=> [],

				'best_player_duo'				=> [],
				'good_against_player'			=> [],
				'weak_against_player'			=> [],

				'match_history'					=> [],
				];

			// load base user profile by playerID
			$res = base_user::get_user([
				'playerID'		=> $alt['playerID'],
				'with_right'	=> false,
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if found
			if($res->status == 200){

				// take base user
				$base_user = $res->data;

				// if not enabled, return not found
				if(!$base_user->enabled) return self::response(404);

				// add donation to profile
				$profile['donation'] = $base_user->donation;

				// add user data to profile
				$profile['account_data'] = $base_user;
				}


			// load player basic metric
			$entry = self::pdo('s_customgame_profile_metric', [$alt['playerID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// decode tag
			$entry->tag = json_decode($entry->tag);


			// load stat analysis
			$res = self::get_analyse_stat([]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['stat_playerlist'] = $res->data;


			// load champion analysis
			$res = self::get_champion_analysis([
				'playerID'	=> $alt['playerID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['champion_with_player'] = $res->data;


			// load position analysis
			$res = self::get_position_analysis([
				'playerID'	=> $alt['playerID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['position_list'] = $res->data;


			// load summoner analysis
			$res = self::get_summoner_analysis([
				'playerID'	=> $alt['playerID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['summoner_list'] = $res->data;


			// load streak analysis
			$res = self::get_streak_analysis([
				'playerID'	=> $alt['playerID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['player_streak'] = $res->data;


			// load champion vs player analysis
			$res = self::get_champion_vs_player_analysis([
				'playerID'	=> $alt['playerID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['champion_vs_player'] = $res->data;


			// load player with player analysis
			$res = analyse_player::prepare_and_process_duo_stat([
				'project'	=> $mand['project'],
				'mode'		=> 'player_profile',
				'playerID'	=> $alt['playerID'],
				'type'		=> 'best_player_duo',
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['best_player_duo'] = $res->data;


			// load player vs player analysis
			$res = analyse_player::prepare_and_process_duo_stat([
				'project'	=> $mand['project'],
				'mode'		=> 'player_profile',
				'playerID'	=> $alt['playerID'],
				'type'		=> 'good_against_player',
				]);

			// on error
			if($res->status != 200) return $res;

			// add result to profile
			$profile['good_against_player'] = $res->data;


			// load match history
			$res = self::get_match_history([
				'playerID'	=> $alt['playerID'],
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

		// param order 2: empty
		if(empty($alt)){

			// try to load file from cache
			$res = self::get_cached_file_refactored([
				'path'		=> $_SERVER['APP_DIRECTORY'].'/cache',
				'filename'	=> 'league_complete_profile_list.json',
				'type'		=> 'file',
				'decode'	=> true,
				]);

			// if found, directly return from cache
			if($res->status == 200) return self::response(200, $res->data);

			// load player list (only IDs)
			$list = self::pdo('l_customgame_profile_metric');

			// on error
			if($list === false) return self::response(560);

			// default profile list
			$profile_list = [];

			// for each player
			foreach($list as $entry){

				// use recursive call to load all profiles
				$res = self::get_profile([
					'project'	=> $mand['project'],
					'playerID'	=> $entry->playerID,
					]);

				// if not found or disabled, skip
				if($res->status == 404) continue;

				// on unexpected error
				if($res->status != 200) return $res;

				// add to profile list
				$profile_list[] = $res->data;
				}

			// cache complete list in json file
			$res = self::cache_in_file_refactored([
				'path'		=> $_SERVER['APP_DIRECTORY'].'/cache',
				'filename'	=> 'league_complete_profile_list',
				'extension'	=> 'json',
				'enc_data'	=> json_encode($profile_list),
				]);

			// return profile list
			return self::response(200, $profile_list);
			}

		// return param error
		return self::response(400, 'Expecting playerID or no param');
		}

	public static function get_champion_analysis($req = []){

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
			$list = self::pdo('l_customgame_analyse_champs_for_player', [$alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// sort list
			usort($list, $sort_analyse);

			// load list
			$list_ban = self::pdo('l_customgame_analyse_champ_bans_for_player', [$alt['playerID']]);

			// on error
			if($list_ban === false) return self::response(560);

			// for each found ban stat
			foreach($list_ban as $ban){

				// foreach
				foreach($list as $entry){

					// skip not matching champs
					if($ban->championID != $entry->championID) continue;

					// tims baned general
					$entry->times_banned_general = $ban->times_banned_general;

					// times baned vs player
					$entry->times_banned_vs_player = $ban->times_banned_vs_player;

					// banrate general
					// $entry->banrate_general = (float) round($ban->banrate_general, 2);
					$entry->banrate_general = (float) round($ban->times_banned_general / $ban->total_games * 100, 2);

					// banrate vs player
					$entry->banrate_vs_player = (float) round($ban->banrate_vs_player, 2);

					// deviation between general banrate and banrate vs player
					$entry->deviation_general_specific = round($entry->banrate_general - $entry->banrate_vs_player, 2);

					// champ was pickable
					$entry->times_champ_was_pickable = $ban->total_games_player - $entry->times_banned_vs_player;

					// pickable rate
					$entry->pickable_rate = round($entry->times_champ_was_pickable / $ban->total_games_player * 100, 2);

					// champ pickrate
					$entry->pickrate_player = round($entry->times_played / $ban->total_games_player * 100, 2);

					// deviation pickable rate, pickrate player
					$entry->deviation_pickable_rate_pickrate = round($entry->pickable_rate - $entry->pickrate_player, 2);
					}
				}

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playerID');
		}

	public static function get_position_analysis($req = []){

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
			$list = self::pdo('l_customgame_analyse_position_for_player', [$alt['playerID']]);

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

	public static function get_summoner_analysis($req = []){

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure sort function
		$sort_analyse = function($current, $next){

			// sort by total_sum descending
			return ($current->total_sum < $next->total_sum) ? 1 : -1;
			};

		// define summoner list
		$summoners = ['Flash', 'Ignite', 'Exhaust', 'Heal', 'Teleport', 'Smite', 'Cleanse', 'Ghost', 'Barrier'];

		// define result
		$analyze_result = [];


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// for each summoner
			foreach($summoners as $summoner){

				// run query for player
				$entry = self::pdo('s_customgame_analyse_summoners_for_player', [$alt['playerID'], $summoner, $alt['playerID'], $summoner, $alt['playerID'], $alt['playerID'], $summoner, $summoner]);

				// on error
				if($entry === false) return self::response(560);

				// if not found, skip
				if(!$entry) continue;

				// lowercase
				$lc_summoner = strtolower($summoner);

				// build object
				$stat = (object)[
					'summoner'	=> $summoner,
					'total'		=> $entry->total_sum,
					'per_game'	=> round($entry->total_sum / $entry->total_games * 100, 2),
					'on_d'		=> $entry->on_d ?: 0,
					'on_f'		=> $entry->on_f ?: 0,
					];

				// add to result
				$analyze_result[] = $stat;
				}

			// sort result by total picked
			usort($analyze_result, function($obj1, $obj2){

				// descending
				return ($obj1->total < $obj2->total) ? 1 : -1;
				});

			// return list
			return self::response(200, $analyze_result);
			}

		// on error
		return self::response(400, 'Expecting playerID');
		}

	public static function get_streak_analysis($req = []){

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// run query for player
			$list = self::pdo('s_customgame_analyse_streak_for_player', [$alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// define stat object
			$stat = (object)[
				'max_win_streak'	=> 0,
				'max_lose_streak'	=> 0,
				'actual_win_streak'	=> 0,
				'actual_lose_streak'=> 0,
				];

			// reverse order (new games first)
			$list_reverse = array_reverse($list);


			// check actual win streak
			foreach($list_reverse as $entry){

				// if already lost, break
				if(!$entry->won) break;

				// increment actual win streak
				$stat->actual_win_streak++;
				}

			// check actual lose streak
			foreach($list_reverse as $entry){

				// if already won, break
				if($entry->won) break;

				// increment actual win streak
				$stat->actual_lose_streak++;
				}

			// define win count
			$win_count = 0;
			$lose_count = 0;

			// get max streak
			foreach($list_reverse as $index => $entry){

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

		// on error
		return self::response(400, 'Expecting playerID');
		}

	public static function get_champion_vs_player_analysis($req = []){

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure conversion function
		$convert = function($list){

			// for each float value
			foreach($list as $entry){

				// convert winrate (float)
				$entry->winrate_vs_player = (float) round($entry->winrate_vs_player, 2);

				// convert times_won and times_lost (integer)
				$entry->champ_won_against_player = (int) $entry->champ_won_against_player;
				$entry->champ_lost_against_player = (int) $entry->champ_lost_against_player;
				}
			};


		// param order 1: playerID
		if(isset($alt['playerID'])){

			// load list
			$list = self::pdo('l_customgame_analyse_champs_against_player', [$alt['playerID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting playerID');
		}

	public static function get_analyse_stat($req = []){

		// define cache key
		$cache_key = 'customstats:league:player_profile:analyse_stat_list';

		// if list is cached
		if(isset(self::$lvl1_cache[$cache_key])){

			// directly return list
			return self::response(200, self::$lvl1_cache[$cache_key]);
			}

		// load list
		$list = self::pdo('l_customgame_max_avg_total_stats');

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


	/* Object: match history */
	public static function get_match_history($req = []){

		// mandatory
		$mand = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// convert
		$convert = function($list){

			// for each entry in list
			foreach($list as $entry){

				// remove all whitespace and convert to lowercase
				$entry->champion_converted = strtolower(str_replace(' ', '', $entry->champion));

				// kda number
				$entry->kda_number = ($entry->deaths) ? round(($entry->kills + $entry->assists) / $entry->deaths, 2) : $entry->kills + $entry->assists;
				}
			};


		// load all players (for their names)
		$res = league_player::get_player();

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
		$res = game_player::get_game_player();

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
			if($history->teamID == 1){

				// add player to team one list
				$list_converted[$history->gameID]['history']['team_one_list'][] = (object)[
					'playerID'			=> $history->playerID,
					'ingame_name'		=> $playerlist_index[$history->playerID]->ingame_name,
					'championID'		=> $history->championID,
					'champion'			=> $history->champion,
					'champion_converted'=> strtolower(str_replace(' ', '', $history->champion)),
					'position'			=> $history->position,
					];
				}

			// if team two
			elseif($history->teamID == 2){

				// add player to team two list
				$list_converted[$history->gameID]['history']['team_two_list'][] = (object)[
					'playerID'			=> $history->playerID,
					'ingame_name'		=> $playerlist_index[$history->playerID]->ingame_name,
					'championID'		=> $history->championID,
					'champion'			=> $history->champion,
					'champion_converted'=> strtolower(str_replace(' ', '', $history->champion)),
					'position'			=> $history->position,
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