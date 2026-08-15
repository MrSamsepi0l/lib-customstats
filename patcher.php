<?php
/* Last update: 2023-01-13
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\league\game;
use \customstats\league\player;
use \customstats\league\champion;

use \customstats\league\customgame\customgame;
use \customstats\league\customgame\game_player;
use \customstats\league\customgame\game_ban;

use \customstats\league\tournament\game as tournament_game;
use \customstats\league\tournament\team_player as tournament_team_player;
use \customstats\league\tournament\game_player as tournament_game_player;


class patcher {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			/* Queries: import_base_user */
			'import_base_user'						=> 'INSERT INTO `base_user` (`userID`,`email`,`username`,`username_canonical`,`password`,`real_name`,`enabled`,`online`,`last_activity`,`password_reset`,`donation`,`api_key`,`playerID`,`createTime`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			]];
		}


	public static function import_customgame($req = []){

		// load games
		$res = game::get_game([
			'converted' => true
			]);

		// on error
		if($res->status != 200) return $res;

		// take games
		$gamelist = $res->data;


		// define stats object
		$stat = (object)[
			'customgame_created'	=> 0,
			'game_player_created'	=> 0,
			'game_ban_created'		=> 0,
			];

		// for each game
		foreach($gamelist as $game){

			// take bans that team one made
			$banlist_one = array_unique(array_values((array) h::gX($game, 'team_1:bans')));
			$banlist_two = array_unique(array_values((array) h::gX($game, 'team_2:bans')));

			$banlist_team_one = [];
			$banlist_team_two = [];

			// remove duplicates
			foreach($banlist_one as $ban_team_one){

				//
				if(in_array($ban_team_one, $banlist_team_two)) continue;

				// take
				$banlist_team_one[] = $ban_team_one;
				}

			// remove duplicates
			foreach($banlist_two as $ban_team_two){

				//
				if(in_array($ban_team_two, $banlist_team_one)) continue;

				$banlist_team_two[] = $ban_team_two;
				}

			// create customgame
			$res = customgame::create_custom_game([
				'gameDate' 		=> $game->gameDate,
				'duration'		=> $game->game_length,
				'canceled'		=> false,
				]);

			// on error
			if($res->status != 201) return self::response(570, $res);

			// increment stat
			$stat->customgame_created++;

			// take customgame ID
			$customgameID = $res->data->gameID;

			// for each player
			foreach($game->playerdata as $player){

				// load champion
				$res = champion::get_champion([
					'name'			=> $player->champion,
					]);

				// on error
				if($res->status != 200) return $res;

				// take champion
				$champion = $res->data;

				// create entry
				$res = game_player::create_game_player([
					'gameID'		=> $customgameID,
					'playerID'		=> $player->playerID,
					'teamID'		=> $player->teamID,
					'championID'	=> $champion->championID,
					'champion'		=> $player->champion,
					'summoner_one'	=> $player->summoner_one,
					'summoner_two'	=> $player->summoner_two,
					'level'			=> $player->level,
					'kills'			=> $player->kills,
					'deaths'		=> $player->deaths,
					'assists'		=> $player->assists,
					'creep_score'	=> $player->cs,
					'gold'			=> $player->gold,
					'position'		=> $player->position,
					'won'			=> $player->won,
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// increment stat
				$stat->game_player_created++;
				}

			// for each team one ban
			foreach($banlist_team_one as $ban_team_one){

				// special check if null
				if(!$ban_team_one) continue;

				// load champion
				$res = champion::get_champion([
					'name'			=> $ban_team_one,
					]);

				// on error
				if($res->status != 200) return $res;

				// take champion
				$champion = $res->data;

				// create game ban
				$res = game_ban::create_game_ban([
					'gameID'		=> $customgameID,
					'teamID'		=> 1,
					'championID'	=> $champion->championID,
					'champion'		=> $ban_team_one,
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// increment stat
				$stat->game_ban_created++;
				}

			// for each team two ban
			foreach($banlist_team_two as $ban_team_two){

				// special check if null
				if(!$ban_team_two) continue;

				// load champion
				$res = champion::get_champion([
					'name'			=> $ban_team_two,
					]);

				// on error
				if($res->status != 200) return $res;

				// take champion
				$champion = $res->data;

				// create game ban
				$res = game_ban::create_game_ban([
					'gameID'		=> $customgameID,
					'teamID'		=> 2,
					'championID'	=> $champion->championID,
					'champion'		=> $ban_team_two,
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// increment stat
				$stat->game_ban_created++;
				}
			}

		// return stats
		return self::response(200, $stat);
		}

	public static function import_base_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'			=> '~1,4294967295/i',
			'email'				=> '~1,255/s',
			'username'			=> '~1,255/s',
			'username_canonical'=> '~1,255/s',
			'password'			=> '~/s',
			'real_name'			=> '~/s',
			'enabled'			=> '~0,1/i',
			'online'			=> '~0,1/i',
			'last_activity'		=> '~/i',
			'password_reset'	=> '~/i',
			'donation'			=> '~/f',
			'hash_key'			=> '~/s',
			'playerID'			=> '~/i',
			'createTime'		=> '~Y-m-d H:i:s/d',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$insert = self::pdo('import_base_user', array_values($mand));

		// on error
		if($insert === false) return self::response(560);

		// return success
		return self::response(201);
		}

	public static function patch_tournament_game_player($req = []){

		// define stats
		$stat = (object)[
			'found'		=> 0,
			'processed'	=> 0,
			'updated'	=> 0,
			'error'		=> 0,
			'error_list'=> [],
			];

		// load all tournament players
		$res = tournament_game_player::get_game_player();

		// on error
		if($res->status != 200) return $res;

		// take data
		$game_player = $res->data;

		// count list
		$stat->found = count($game_player);

		// load gamelist
		$res = tournament_game::get_game();

		// on error
		if($res->status != 200) return $res;

		// take data
		$game_list = $res->data;

		// updated list
		$game_list_indexed = [];

		// for each game
		foreach($game_list as $game){

			// add to list
			$game_list_indexed[$game->gameID] = (object)[
				'team_oneID'	=> $game->team_oneID,
				'team_twoID'	=> $game->team_twoID,
				];
			}

		// for each game player
		foreach($game_player as $entry){

			// increment
			$stat->processed++;

			// get both teams by gameID
			$both_teams = $game_list_indexed[$entry->gameID];

			// select team
			$teamID = ($entry->team == 1) ? $both_teams->team_oneID : $both_teams->team_twoID;

			// update entry
			$res = tournament_game_player::update_game_player([
				'game_playerID'	=> $entry->game_playerID,
				'teamID'		=> $teamID,
				]);

			// on error
			if($res->status != 204){

				// increment and add to list
				$stat->error++;
				$stat->error_list[$entry->game_playerID] = $res;

				// skip
				continue;
				}

			// increment success
			$stat->updated++;
			}

		// return stats
		return self::response(200, $stat);
		}

	}