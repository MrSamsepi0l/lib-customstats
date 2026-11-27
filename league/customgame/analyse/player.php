<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\customgame\analyse;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\league\player as league_player;
use \customstats\base\user as base_user;
use \customstats\base\config as base_config;

use \customstats\league\customgame\player as game_player;

class player {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: custom_game_player */
			'l_profile_customgame_player_with_data'		=> 'SELECT p.playerID, p.ingame_name,
															COUNT(cgp.playerID) AS `times_played`
															FROM `custom_game_player` cgp
															INNER JOIN `player` p ON p.playerID = cgp.playerID AND p.enabled = 1
															WHERE p.enabled = 1
															GROUP BY p.playerID
															HAVING `times_played` >= ?
															',
			]];

		}

	/* Used for player profile AND best of (customgames - NOT tournament) */
	public static function prepare_and_process_duo_stat($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'mode'		=> '~^(?:best_of|player_profile)$',
			'type'		=> '~^(?:best_player_duo|worst_player_duo|good_against_player|weak_against_player)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'playerID'	=> '~1,4294967295/i',
			'min_games'	=> '~/i',
			'limit'		=> '~/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load project config
		$res = base_config::get_config([
			'project'	=> $mand['project'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take config
		$config = $res->data;

		// for player profile TODO: refactor this
		if($mand['mode'] == 'player_profile'){

			// define gX config string
			$config_search = 'param:base_config:'.$mand['mode'].':'.$mand['type'];

			// for each config param that needs to be set
			foreach(['min_games', 'min_winrate'] as $mand_config){

				// if not defined in config, return precondition failed
				if(!h::cX($config, $config_search.':'.$mand_config)) return self::response(412);
				}

			// extract limit from config (if available)
			$config_limit = h::gX($config, $config_search.':limit');

			// extract minimium games from config
			$config_min_games = h::gX($config, $config_search.':min_games');

			// extract limit from config
			$config_min_winrate = h::gX($config, $config_search.':min_winrate');
			}

		// process stat function
		return self::get_duo_stat([
			'type'				=> $mand['type'],
			'playerID'			=> $opt['playerID'] ?? null,
			'limit'				=> $config_limit ?? $opt['limit'] ?? null,
			'min_games' 		=> $config_min_games ?? $opt['min_games'] ?? null,
			'min_winrate'		=> $config_min_winrate ?? $opt['min_winrate'] ?? null,
			'min_games_general'	=> $config_min_games ?? $opt['min_games'] ?? null,
			]);
		}

	public static function get_duo_stat($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^(?:best_player_duo|worst_player_duo|good_against_player|weak_against_player)$',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [

			// reduce final list by given limit
			'limit'				=> '~1,10000/i',

			// how many games a pair needed to play together/against
			'min_games'			=> '~0,10000/i',
			'min_winrate'		=> '~0,100/i',

			// while cache is active, min games cant be used in calculation, this is for general games played by player
			'min_games_general'	=> '~0,10000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'limit'				=> null,
			'min_games'			=> null,
			'min_winrate'		=> null,
			'min_games_general'	=> 30,
			];

		// internal cache
		static $result = [];

		// only if not cached already
		if($result === []){

			// load ALL games player
			$res = game_player::get_game_player();

			// on error
			if($res->status != 200) return $res;

			// take list
			$games_player_list = $res->data;

			// get all gameIDs
			$gameID_list = array_column($games_player_list, 'gameID');

			// default list
			$generated_list = [];

			// create index list for each gameID
			foreach($gameID_list as $gameID){

				// if already set, skip (ToDo: This should be unnecessary)
				if(isset($generated_list[$gameID])) continue;

				// default index list
				$generated_list[$gameID] = [
					'playerID_list'		=> [],
					'team_oneID_list'	=> [],
					'team_twoID_list'	=> [],
					'winner_teamID'		=> 0,
					];
				}

			// remove unused
			unset($gameID_list);


			// for each player's game
			foreach($games_player_list as $entry){

				// add playerID to list
				$generated_list[$entry->gameID]['playerID_list'][] = $entry->playerID;

				// if team one
				if($entry->teamID == 1) $generated_list[$entry->gameID]['team_oneID_list'][] = $entry->playerID;

					// add playerID to list
					// $generated_list[$entry->gameID]['team_oneID_list'][] = $entry->playerID;

					// if team one won, set it
					// if($entry->won) $generated_list[$entry->gameID]['winner_teamID'] = 1;
					// }

				// if team two
				elseif($entry->teamID == 2) $generated_list[$entry->gameID]['team_twoID_list'][] = $entry->playerID;

					// add playerID to list


				// define winner teamID
				if($entry->won) $generated_list[$entry->gameID]['winner_teamID'] = $entry->teamID;
					// }
				}


			// load players (played min games, enabled) -> playerID, ingame_name, times_played
			$list = self::pdo('l_profile_customgame_player_with_data', [$opt['min_games_general']]);

			// on error
			if($list === false) return self::response(560);

			// default result list
			$result = [];

			// default check list
			$check_list = [];

			// for each player
			foreach($list as $player_one){

				// if playerID given, skip not matching players (this should increase performance really hard for only one player)
				// if(isset($alt['playerID']) and $alt['playerID'] != $player_one->playerID) continue;

				// for each (another player)
				foreach($list as $player_two){

					// if same player, skip
					if($player_one->playerID == $player_two->playerID) continue;

					// if player two was already checked completly (so he was player one in a run before), skip
					if(in_array($player_two->playerID, $check_list)) continue;

					// define stat for these two players
					$stat = (object)[

						// both player identifiers
						'player_oneID'			=> $player_one->playerID,
						'player_one_name'		=> $player_one->ingame_name,
						'player_twoID'			=> $player_two->playerID,
						'player_two_name'		=> $player_two->ingame_name,

						// together one team
						'times_played_in_team'	=> 0,
						'times_won_in_team'		=> 0,
						'winrate_p1_with_p2'	=> 0,

						// against each other
						'times_played_against'	=> 0,
						'times_lost_in_team'	=> 0,
						'times_won_player_one'	=> 0,
						'times_lost_player_two'	=> 0,
						'winrate_p1_vs_p2'		=> 0,

						];

					// for each genereated list
					foreach($generated_list as $gameID => $data){

						// if player one was not in this game, skip
						if(!in_array($player_one->playerID, $data['playerID_list'])) continue;

						// if player two was not in this game, skip
						if(!in_array($player_two->playerID, $data['playerID_list'])) continue;

						// CASE 1: both players were in TEAM ONE
						if(in_array($player_one->playerID, $data['team_oneID_list']) and in_array($player_two->playerID, $data['team_oneID_list'])){

							// increment times played stat
							$stat->times_played_in_team++;

							// increment stat if they won or lost
							if($data['winner_teamID'] == 1) $stat->times_won_in_team++;
							if($data['winner_teamID'] == 2) $stat->times_lost_in_team++;

							// next game
							continue;
							}

						// CASE 2: both players were in TEAM TWO
						if(in_array($player_one->playerID, $data['team_twoID_list']) and in_array($player_two->playerID, $data['team_twoID_list'])){

							// increment times played stat
							$stat->times_played_in_team++;

							// increment stat if they won or lost
							if($data['winner_teamID'] == 2) $stat->times_won_in_team++;
							if($data['winner_teamID'] == 1) $stat->times_lost_in_team++;

							// next game
							continue;
							}

						// CASE 3: players played against each other, increment stat
						$stat->times_played_against++;

						// if player one won with team 1
						if(in_array($player_one->playerID, $data['team_oneID_list']) and $data['winner_teamID'] == 1){

							// increment win/lose stat
							$stat->times_won_player_one++;
							$stat->times_lost_player_two++;
							}

						// if player one won with team 2
						elseif(in_array($player_one->playerID, $data['team_twoID_list']) and $data['winner_teamID'] == 2){

							// increment win/lose stat
							$stat->times_won_player_one++;
							$stat->times_lost_player_two++;
							}

						// ... continue with next game
						}

					// if they played at least one game together, calculate winrate together
					if($stat->times_played_in_team) $stat->winrate_p1_with_p2 = round($stat->times_won_in_team / $stat->times_played_in_team * 100, 2);

					// if they played at least one game against each other, calculate winrate p1 vs p2
					if($stat->times_played_against) $stat->winrate_p1_vs_p2 = round($stat->times_won_player_one / $stat->times_played_against * 100, 2);

					// add stat to result
					$result[] = $stat;
					}

				// add player oneID to list (player is completly checked)
				$check_list[] = $player_one->playerID;
				}
			}

		// if nothing survived until here, return empty list
		if(!$result) return self::response(200, []);


		// default result updated
		$result_updated = [];

		// now check list for given input options like, minimum times played, minimum winrate, etc. (needed to be done here because of lvl1 cache)
		foreach($result as $entry){

			// type order 1: best player duo | worst player duo
			if(in_array($mand['type'], ['best_player_duo', 'worst_player_duo'])){

				// if minimum games together not reached, skip
				if($opt['min_games'] and $entry->times_played_in_team < $opt['min_games']) continue;

				// if minimum winrate together set and not reached, skip
				if($opt['min_winrate'] and $entry->winrate_p1_with_p2 < $opt['min_winrate']) continue;

				// add to updated result
				$result_updated[] = $entry;
				}

			// type order 2: good against player | weak against player
			if(in_array($mand['type'], ['good_against_player', 'weak_against_player'])){

				// if minimum games against not reached, skip
				if($opt['min_games'] and $entry->times_played_against < $opt['min_games']) continue;

				// if minimum winrate given for p1 vs p2 and not reached, skip
				if($opt['min_winrate'] and $entry->winrate_p1_vs_p2 < $opt['min_winrate']) continue;

				// add tp updated result
				$result_updated[] = $entry;
				}
			}

		// if nothing survived until here, return empty list
		if(!$result_updated) return self::response(200, []);


		// sort list by given option
		usort($result_updated, function($obj1, $obj2) use ($mand){

			// define type
			$type = $mand['type'];

			// type order 1: best player duo -> sort by winrate together DESC
			if($type == 'best_player_duo') return $obj1->winrate_p1_with_p2 < $obj2->winrate_p1_with_p2;

			// type order 2: worst player duo -> sort by winrate together ASC
			elseif($type == 'worst_player_duo') return $obj1->winrate_p1_with_p2 > $obj2->winrate_p1_with_p2;

			// type order 3: good against player -> sort by winrate p1 against p2 DESC
			elseif($type == 'good_against_player') return $obj1->winrate_p1_vs_p2 < $obj2->winrate_p1_vs_p2;

			// type order 4: weak against player -> sort by winrate p1 against p2 ASC
			elseif($type == 'weak_against_player') return $obj1->winrate_p1_vs_p2 > $obj2->winrate_p1_vs_p2;
			});

		// if limit given, reduce result by given or default limit
		if($opt['limit']) $result_updated = array_slice($result_updated, 0, $opt['limit']);

		// return generated result list with stats
		return self::response(200, $result_updated);
		}


	}
