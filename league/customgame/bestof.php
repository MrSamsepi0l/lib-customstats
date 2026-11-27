<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\customgame;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\base\config as base_config;
use \customstats\league\customgame\player as game_player;
use \customstats\league\player as league_player;
use \customstats\league\customgame\analyse\player as analyse_player;

class bestof {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* basic stats of all customgames */
			's_bestof_basic_metric'						=> 'SELECT COUNT(DISTINCT(`gameID`)) AS `total_games`, (SELECT MAX(`duration`) FROM `custom_game`) AS `highest_duration`,
															(SELECT AVG(`duration`) FROM `custom_game`) AS `average_duration`,
															(SELECT MAX(`gameDate`) FROM `custom_game`) AS `last_gameDate`
															FROM `custom_game_player`
															LIMIT 1
															',

			/* most banned champs */
			'bestof_analyze_most_banned_champ'			=> 'SELECT `champion` AS `champion_name`, `championID`, COUNT(`championID`) AS `times_banned`,
															COUNT(`championID`) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `banrate`
															FROM `custom_game_ban`
															GROUP BY `championID`
															ORDER BY `banrate` DESC
															LIMIT ?
															',
			'bestof_analyze_most_baned_champ_playerlist'=>'SELECT `ingame_name`, COUNT(cgp.playerID) AS `times_played`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															WHERE cgp.championID = ?
															GROUP BY cgp.playerID
															ORDER BY `times_played` DESC
															',

			/* most played champs */
			'bestof_analyze_most_played_champ'			=> 'SELECT `champion` AS `champion_name`, `championID`, COUNT(`championID`) AS `times_played`,
															COUNT(`championID`) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `playrate`
															FROM `custom_game_player`
															GROUP BY `championID`
															ORDER BY `times_played` DESC
															LIMIT ?
															',
			'bestof_analyze_most_played_champ_playerlist'=>'SELECT `ingame_name`, COUNT(cgp.playerID) AS `times_played`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															WHERE cgp.championID = ?
															GROUP BY cgp.playerID
															ORDER BY `times_played` DESC
															',

			/* highest winrate champs */
			'bestof_analyze_highest_winrate_champ'		=> 'SELECT `champion` AS `champion_name`, `championID`, COUNT(`championID`) AS `times_played`,
															SUM(`won`) / COUNT(`championID`) * 100 AS `winrate`
															FROM `custom_game_player`
															GROUP BY `championID`
															HAVING COUNT(`championID`) >= ?
															ORDER BY `winrate` DESC
															LIMIT ?
															',

			'bestof_analyze_winrate_champ_playerlist'	=>'SELECT `ingame_name`, COUNT(cgp.playerID) AS `times_played`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															WHERE cgp.championID = ?
															GROUP BY cgp.playerID
															ORDER BY `times_played` DESC
															',

			/* most played player */
			'bestof_analyze_most_played_player'			=> 'SELECT p.ingame_name, COUNT(cgp.playerID) AS `times_played`, SUM(cgp.won) AS `times_won`,
															COUNT(cgp.playerID) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `playrate`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															GROUP BY cgp.playerID
															HAVING COUNT(cgp.playerID) >= ?
															ORDER BY `playrate` DESC
															LIMIT ?
															',

			/* highest winrate player */
			'bestof_analyze_highest_winrate_player'		=> 'SELECT p.ingame_name, COUNT(cgp.playerID) AS `times_played`, SUM(cgp.won) AS `times_won`,
															SUM(cgp.won) / COUNT(cgp.playerID) * 100 AS `winrate`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															GROUP BY cgp.playerID
															HAVING COUNT(cgp.playerID) >= ?
															ORDER BY `winrate` DESC
															LIMIT ?
															',

			/* highest KDA player */
			'bestof_analyze_highest_kda_player'			=> 'SELECT p.ingame_name, COUNT(cgp.playerID) AS `times_played`,
															ROUND((AVG(cgp.kills) + AVG(cgp.assists)) / AVG(cgp.deaths), 2) AS `kda`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															GROUP BY cgp.playerID
															HAVING COUNT(cgp.playerID) >= ?
															ORDER BY `kda` DESC
															LIMIT ?
															',

			/* most summs (on d) */
			'bestof_analyze_most_summoner_one'			=> 'SELECT `summoner_one`, COUNT(`game_playerID`) AS `times_picked`,
															COUNT(`game_playerID`) / (SELECT COUNT(`game_playerID`) / 10 FROM `custom_game_player`) * 10 AS `pickrate`
															FROM `custom_game_player`
															GROUP BY `summoner_one`
															ORDER BY `pickrate` DESC
															LIMIT ?
															',

			/* most summs (on f) */
			'bestof_analyze_most_summoner_two'			=> 'SELECT `summoner_two`, COUNT(`game_playerID`) AS `times_picked`,
															COUNT(`game_playerID`) / (SELECT COUNT(`game_playerID`) / 10 FROM `custom_game_player`) * 10 AS `pickrate`
															FROM `custom_game_player`
															GROUP BY `summoner_two`
															ORDER BY `pickrate` DESC
															LIMIT ?
															',

			/* most kills single game */
			'bestof_analyze_most_kills_single_game'		=> 'SELECT p.ingame_name, cgp.kills, cgp.champion AS `name`, cgp.deaths, cgp.assists
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															ORDER BY cgp.kills DESC, cgp.deaths ASC, cgp.assists DESC
															LIMIT ?
															',

			/* most deaths single game -> bad statistic, so order by deaths, then most less kills, then most less assists */
			'bestof_analyze_most_deaths_single_game'	=> 'SELECT p.ingame_name, cgp.deaths, cgp.champion AS `name`, cgp.kills, cgp.assists
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															ORDER BY cgp.deaths DESC, cgp.kills ASC, cgp.assists ASC
															LIMIT ?
															',

			/* most assists single game */
			'bestof_analyze_most_assists_single_game'	=> 'SELECT p.ingame_name, cgp.assists, cgp.champion AS `name`, cgp.kills, cgp.deaths
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															ORDER BY cgp.assists DESC, cgp.deaths ASC, cgp.kills DESC
															LIMIT ?
															',

			/* highest creep_score single game */
			'bestof_analyze_most_cs_single_game'		=> 'SELECT p.ingame_name, cgp.creep_score, cgp.champion AS `name`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															ORDER BY cgp.creep_score DESC
															LIMIT ?
															',

			/* most gold single game */
			'bestof_analyze_most_gold_single_game'		=> 'SELECT p.ingame_name, cgp.gold, cgp.champion AS `name`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															ORDER BY cgp.gold DESC
															LIMIT ?
															',

			/* highest KDA single game */
			'bestof_analyze_highest_kda_single_game'	=> 'SELECT p.ingame_name, cgp.champion as `name`, cgp.kills, cgp.deaths, cgp.assists,
															ROUND((cgp.kills + cgp.assists) / cgp.deaths, 2) AS `kda`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID
															ORDER BY `kda` DESC
															LIMIT ?
															',

			/* best duo / worst duo */
			'bestof_analyze_player_with_player'			=> 'SELECT COUNT(cgp1.game_playerID) AS `times_played`,
															SUM(cgp1.won) AS `times_won`,
															COUNT(cgp1.game_playerID) - SUM(cgp1.won) AS `times_lost`,
															SUM(cgp1.won) / COUNT(cgp1.game_playerID) * 100 AS `winrate`
															FROM `custom_game_player` cgp1
															INNER JOIN `custom_game_player` cgp2 ON cgp1.gameID = cgp2.gameID AND cgp1.teamID = cgp2.teamID
															WHERE cgp1.playerID = ? AND cgp2.playerID = ?
															HAVING COUNT(cgp1.game_playerID) >= ?
															LIMIT 1
															',

			/* not used here at the moment */
			'bestof_analyze_player_against_player'		=> 'SELECT COUNT(cgp1.game_playerID) AS `times_played_against`,
															SUM(cgp1.won) AS `times_won_player_one`,
															COUNT(cgp1.game_playerID) - SUM(cgp1.won) AS `times_lost_player_one`,
															SUM(cgp1.won) / COUNT(cgp1.game_playerID) * 100 AS `winrate_player_one`
															FROM `custom_game_player` cgp1
															INNER JOIN `custom_game_player` cgp2 ON cgp1.gameID = cgp2.gameID AND cgp1.teamID != cgp2.teamID
															WHERE cgp1.playerID = ? AND cgp2.playerID = ?
															HAVING COUNT(cgp1.game_playerID) > ?
															LIMIT 1
															',

			/* highest winstreak player */
			's_customgame_analyse_streak_for_player'	=> 'SELECT `won`
															FROM `custom_game_player`
															WHERE `playerID` = ?
															ORDER BY `game_playerID`
															',
			]];

		}

	static $lvl1_cache = [];

	public static function _get_default_config($req = []){

		// define default config pattern
		$default_config = [

			/* champion specific */
			'most_banned_champ'			=> (object)['limit' => 7],
			'most_played_champ'			=> (object)['limit' => 7],
			'highest_winrate_champ' 	=> (object)['min_games' => 19, 'limit' => 7],

			/* player specific */
			'most_played_player'		=> (object)['limit' => 7],
			'highest_winrate_player'	=> (object)['min_games' => 34, 'limit' => 7],
			'best_player_duo'			=> (object)['min_games' => 34, 'limit' => 7],
			'worst_player_duo'			=> (object)['min_games' => 15, 'limit' => 7],
			'most_kills_single_game'	=> (object)['limit' => 7],
			'most_deaths_single_game'	=> (object)['limit' => 7],
			'most_assists_single_game'	=> (object)['limit' => 7],
			'most_cs_single_game'		=> (object)['limit' => 7],
			'most_gold_single_game'		=> (object)['limit' => 7],
			'highest_win_streak_player' => (object)['limit' => 7],
			'highest_lose_streak_player'=> (object)['limit' => 7],

			/* game specific */
			'most_summoner_one'			=> (object)['limit' => 7],
			'most_summoner_two'			=> (object)['limit' => 7],
			];

		// return default config
		return self::response(200, $default_config);
		}

	public static function get_bestof_analysis($req = []){

		// get default config pattern
		$res = self::_get_default_config();

		// on error
		if($res->status != 200) return $res;

		// take default
		$default_config = $res->data;


		// load (project) config
		$res = base_config::get_config([
			'project'	=> 'customstats',
			]);

		// on error
		if($res->status != 200) return $res;

		// take config
		$config = $res->data;


		// if specific best of config set
		if(h::cX($config, 'param:base_config:best_of', '~/a')){

			// take it
			$bestof_config = (array) h::gX($config, 'param:base_config:best_of');

			// for each defined config
			foreach($bestof_config as $entry){

				// if not set in default, skip
				if(!isset($default_config[$entry->key])) continue;

				// replace default value with config value
				$default_config[$entry->key] = $entry;
				}
			}

		// take default config as 'new' config
		$config = $default_config;
		unset($default_config);

		// define result list
		$result = [
			'base_metric'				=> (object)[
				'total_games'			=> 0,
				'average_duration'		=> 0.00,
				'highest_duration'		=> 0.00,
				],
			'most_banned_champ'			=> [],
			'most_played_champ'			=> [],
			'highest_winrate_champ'		=> [],
			'most_played_player'		=> [],
			'highest_winrate_player'	=> [],
			'highest_kda_player'		=> [],
			'most_summoner_one'			=> [],
			'most_summoner_two'			=> [],
			'most_kills_single_game'	=> [],
			'most_deaths_single_game'	=> [],
			'most_assists_single_game'	=> [],
			'most_cs_single_game'		=> [],
			'most_gold_single_game'		=> [],
			'highest_kda_single_game'	=> [],
			'best_player_duo'			=> [],
			'worst_player_duo'			=> [],
			'highest_win_streak_player'	=> [],
			'highest_lose_streak_player'=> [],
			];


		/* Analysis begin */

		// load basic metric
		$entry = self::pdo('s_bestof_basic_metric');

		// on error
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// convert average_duration
		if($entry->average_duration) $entry->average_duration = round($entry->average_duration, 2);

		// take data
		$result['base_metric'] = $entry;


		// most banned champ
		$res = self::get_most_banned_champ([
			'limit'		=> h::gX($config, 'most_banned_champ:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_banned_champ'] = $res->data;


		// most played champ
		$res = self::get_most_played_champ([
			'limit'		=> h::gX($config, 'most_played_champ:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_played_champ'] = $res->data;


		// highest winrate champ
		$res = self::get_highest_winrate_champ([
			'min_games'	=> h::gX($config, 'highest_winrate_champ:min_games'),
			'limit'		=> h::gX($config, 'highest_winrate_champ:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['highest_winrate_champ'] = $res->data;


		// most played player
		$res = self::get_most_played_player([
			'min_games'	=> h::gX($config, 'most_played_player:min_games'),
			'limit'		=> h::gX($config, 'most_played_player:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_played_player'] = $res->data;


		// highest winrate player
		$res = self::get_highest_winrate_player([
			'min_games'	=> h::gX($config, 'highest_winrate_player:min_games'),
			'limit'		=> h::gX($config, 'highest_winrate_player:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['highest_winrate_player'] = $res->data;


		// highest KDA player
		$res = self::get_highest_kda_player([
			'min_games'	=> h::gX($config, 'highest_kda_player:min_games'),
			'limit'		=> h::gX($config, 'highest_kda_player:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['highest_kda_player'] = $res->data;


		// most summoner one
		$res = self::get_most_summoner_one([
			'limit'		=> h::gX($config, 'most_summoner_one:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_summoner_one'] = $res->data;


		// most summoner two
		$res = self::get_most_summoner_two([
			'limit'		=> h::gX($config, 'most_summoner_two:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_summoner_two'] = $res->data;


		// most kills
		$res = self::get_most_kills_single_game([
			'limit'		=> h::gX($config, 'most_kills_single_game:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_kills_single_game'] = $res->data;


		// most deaths
		$res = self::get_most_deaths_single_game([
			'limit'		=> h::gX($config, 'most_deaths_single_game:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_deaths_single_game'] = $res->data;


		// most assists
		$res = self::get_most_assists_single_game([
			'limit'		=> h::gX($config, 'most_assists_single_game:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_assists_single_game'] = $res->data;


		// most cs
		$res = self::get_most_cs_single_game([
			'limit'		=> h::gX($config, 'most_cs_single_game:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_cs_single_game'] = $res->data;


		// most gold
		$res = self::get_most_gold_single_game([
			'limit'		=> h::gX($config, 'most_gold_single_game:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['most_gold_single_game'] = $res->data;


		// highest kda single game
		$res = self::get_highest_kda_single_game([
			'limit'		=> h::gX($config, 'highest_kda_single_game:limit'),
			]);

		// on error
		if($res->status != 200) return $res;

		// add to result
		$result['highest_kda_single_game'] = $res->data;


		// load best duo analysis
		$res = analyse_player::prepare_and_process_duo_stat([
			'project'	=> 'customstats',
			'mode'		=> 'best_of',
			'type'		=> 'best_player_duo',
			'limit'		=> h::gX($config, 'best_player_duo:limit'),
			'min_games'	=> h::gX($config, 'best_player_duo:min_games'),
			]);

		// on error
		if($res->status != 200) return $res;

		// take it
		$result['best_player_duo'] = $res->data;


		// load worst duo analysis
		$res = analyse_player::prepare_and_process_duo_stat([
			'project'	=> 'customstats',
			'mode'		=> 'best_of',
			'type'		=> 'worst_player_duo',
			'limit'		=> h::gX($config, 'worst_player_duo:limit'),
			'min_games'	=> h::gX($config, 'worst_player_duo:min_games'),
			]);

		// on error
		if($res->status != 200) return $res;

		// take it
		$result['worst_player_duo'] = $res->data;


		// highest winstreak player
		$res = self::get_highest_winstreak_player([
			'type'		=> 'max_win_streak',
			]);

		// on error
		if($res->status != 200) return $res;

		// take it
		$result['highest_win_streak_player'] = $res->data;


		// highest losestreak player
		$res = self::get_highest_winstreak_player([
			'type'		=> 'max_lose_streak',
			]);

		// on error
		if($res->status != 200) return $res;

		// take it
		$result['highest_lose_streak_player'] = $res->data;


		// return result
		return self::response(200, $result);
		}

	public static function get_most_banned_champ($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_banned_champ', $opt['limit']);

		// on error
		if($list === false) return self::response(560);

		// for each champion
		foreach($list as $entry){

			// load players who played this champs most time
			$player_list = self::pdo('bestof_analyze_most_baned_champ_playerlist', [$entry->championID]);

			// on error
			if($player_list === false) return self::response(560);

			// add to entry
			$entry->player_list = $player_list;
			}

		// return list
		return self::response(200, $list);
		}

	public static function get_most_played_champ($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// most played champs
		$list = self::pdo('bestof_analyze_most_played_champ', $opt['limit']);

		// on error
		if($list === false) return self::response(560);

		// for each champion
		foreach($list as $entry){

			// load players who played this champs most time
			$player_list = self::pdo('bestof_analyze_most_played_champ_playerlist', [$entry->championID]);

			// on error
			if($player_list === false) return self::response(560);

			// add to entry
			$entry->player_list = $player_list;
			}

		// return list
		return self::response(200, $list);
		}

	public static function get_highest_winrate_champ($req = []){

		// optional
		$opt = h::eX($req, [
			'min_games'		=> '~0,10000/i',
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'min_games'		=> 10,
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_highest_winrate_champ', [$opt['min_games'], $opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// for each champion
		foreach($list as $entry){

			// load players who played this champs most time
			$player_list = self::pdo('bestof_analyze_winrate_champ_playerlist', [$entry->championID]);

			// on error
			if($player_list === false) return self::response(560);

			// add to entry
			$entry->player_list = $player_list;
			}

		// return list
		return self::response(200, $list);
		}

	public static function get_most_played_player($req = []){

		// optional
		$opt = h::eX($req, [
			'min_games'		=> '~0,10000/i',
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'min_games'		=> 10,
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_played_player', [$opt['min_games'], $opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_highest_winrate_player($req = []){

		// optional
		$opt = h::eX($req, [
			'min_games'		=> '~0,10000/i',
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'min_games'		=> 10,
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_highest_winrate_player', [$opt['min_games'], $opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_highest_kda_player($req = []){

		// optional
		$opt = h::eX($req, [
			'min_games'		=> '~0,10000/i',
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'min_games'		=> 10,
			'limit'			=> 7,
			];

		// highest KDA player
		$list = self::pdo('bestof_analyze_highest_kda_player', [$opt['min_games'], $opt['limit']]);

		e::logtrigger('DEBUG: '.h::encode_php($list));

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_summoner_one($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_summoner_one', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_summoner_two($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_summoner_two', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_kills_single_game($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_kills_single_game', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_deaths_single_game($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_deaths_single_game', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_assists_single_game($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_assists_single_game', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_cs_single_game($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_cs_single_game', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_most_gold_single_game($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest winrate champ
		$list = self::pdo('bestof_analyze_most_gold_single_game', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_highest_kda_single_game($req = []){

		// optional
		$opt = h::eX($req, [
			'limit'			=> '~1,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'			=> 7,
			];

		// highest KDA single game
		$list = self::pdo('bestof_analyze_highest_kda_single_game', [$opt['limit']]);

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_duo_statistic($req = []){

		// optional
		$opt = h::eX($req, [

			// how many games a pair needed to play together
			'min_games'			=> '~0,10000/i',
			'limit'				=> '~1,10000/i',

			// defines order for complete result list
			'order_option_one'	=> '~^(?:times_played|winrate)$',
			'order_option_two'	=> '~^(?:asc|desc)',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'min_games'			=> 30,
			'limit'				=> 7,
			'order_option_one'	=> 'winrate',
			'order_option_two'	=> 'desc',
			];


		// load player list
		$res = league_player::get_player();

		// on error
		if($res->status != 200) return $res;

		// take games
		$playerlist = $res->data;

		// define result
		$result = [];

		// for each player
		foreach($playerlist as $index => $player_one){

			// for each player
			foreach($playerlist as $player_two){

				// skip same player
				if($player_one->playerID == $player_two->playerID) continue;

				// execute query
				$entry = self::pdo('bestof_analyze_player_with_player', [$player_one->playerID, $player_two->playerID, $opt['min_games']]);

				// on error
				if($entry === false) return self::response(560);

				// if not found, skip
				if(!$entry) continue;

				// define result object
				$obj = (object)[
					'player_oneID'			=> $player_one->playerID,
					'player_one_name'		=> $player_one->ingame_name,
					'player_twoID'			=> $player_two->playerID,
					'player_two_name'		=> $player_two->ingame_name,
					'times_played'			=> $entry->times_played,
					'times_won'				=> (int) $entry->times_won,
					'times_lost'			=> (int) $entry->times_lost,
					'winrate'				=> round($entry->winrate, 2),
					];

				// add object to result
				$result[] = $obj;

				// player is fully checked
				unset($playerlist[$index]);
				}
			}

		// if asc option
		if($opt['order_option_two'] == 'asc'){

			// sort result by given options
			usort($result, function($obj1, $obj2) use ($opt){

				// sort by option ascending
				return ($obj1->{$opt['order_option_one']} < $obj2->{$opt['order_option_one']}) ? -1 : 1;
				});
			}

		// if desc option
		if($opt['order_option_two'] == 'desc'){

			// sort result by given options
			usort($result, function($obj1, $obj2) use ($opt){

				// sort by option descending
				return ($obj1->{$opt['order_option_one']} < $obj2->{$opt['order_option_one']}) ? 1 : -1;
				});
			}

		// reduce result by given or default limit
		$result = array_slice($result, 0, $opt['limit']);

		// return success
		return self::response(200, $result);
		}

	public static function get_highest_winstreak_player($req = []){

		// optional
		$opt = h::eX($req, [
			'type'		=> '~^(?:max_win_streak|max_lose_streak)',
			'limit'		=> '~0,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'type'		=> 'max_win_streak',
			'limit'		=> 7,
			];

		// define result
		$result = [];

		// define cache key
		$cache_key = 'customstats:bestof:highest_streak_player';

		// if not cached
		if(!isset($lvl1_cache[$cache_key])){

			// load player list
			$res = league_player::get_player();

			// on error
			if($res->status != 200) return $res;

			// take games
			$playerlist = $res->data;


			// for each player in list
			foreach($playerlist as $player){

				// run query for player
				$list = self::pdo('s_customgame_analyse_streak_for_player', [$player->playerID]);

				// on error
				if($list === false) return self::response(560);

				// define stat object
				$stat = (object)[
					'playerID'			=> $player->playerID,
					'ingame_name'		=> $player->ingame_name,
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

					// if won
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

				// add to result
				$result[] = $stat;
				}

			// cache result
			$lvl1_cache[$cache_key] = $result;
			}

		// take result (always in cache)
		$result = $lvl1_cache[$cache_key];

		// sort result by given options
		usort($result, function($obj1, $obj2) use ($opt){

			// sort by option descending
			return ($obj1->{$opt['type']} < $obj2->{$opt['type']}) ? 1 : -1;
			});

		// reduce list by limit
		$result = array_slice($result, 0, $opt['limit']);

		// return stat directly as object
		return self::response(200, $result);
		}

	}
