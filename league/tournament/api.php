<?php
/* Last update: 2023-01-13
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\league\tournament\tournament;
use \customstats\league\tournament\game as tournament_game;
use \customstats\league\tournament\team as tournament_team;
use \customstats\league\tournament\player as tournament_player;
use \customstats\league\tournament\player\profile as tournament_profile;
use \customstats\league\tournament\playoff as tournament_playoff;
use \customstats\league\tournament\schedule as tournament_schedule;
use \customstats\league\tournament\game_ban as tournament_game_ban;
use \customstats\league\tournament\team_player as tournament_team_player;
use \customstats\league\tournament\game_player as tournament_game_player;
use \customstats\league\tournament\player\analyse as player_analyse;
use \customstats\league\tournament\matchhistory as tournament_match_history;


class api {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;


	/* Object: tournament */
	public static function get_tournament($req = []){

		// process function
		return tournament::get_tournament($req);
		}

	public static function create_tournament($req = []){

		// process function
		return tournament::create_tournament($req);
		}

	public static function update_tournament($req = []){

		// process function
		return tournament::update_tournament($req);
		}


	/* Object: profile */
	public static function get_profile($req = []){

		// alternative
		$alt = h::eX($req, [
			'playerID'		=> '~1,4294967295/i',
			'tournamentID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// forward request
		return tournament_profile::get_profile($req);
		}


	/* Object: player */
	public static function get_player($req = []){

		// process function
		return tournament_player::get_player($req);
		}

	public static function create_player($req = []){

		// process function
		return tournament_player::create_player($req);
		}

	public static function update_player($req = []){

		// process function
		return tournament_player::update_player($req);
		}


	/* Match-History (complete) */
	public static function get_match_history($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// define base path for source url
		$base_source_url = '/public/img/tournament/source';


		// load match history for tournament
		$res = tournament_match_history::get_match_history([
			'tournamentID'	=> $mand['tournamentID'],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take data
		$match_history = $res->data;


		// default reindexed list
		$tournament_game_list = [];

		// for each match in history
		foreach($match_history as $match){

			// load team (1)
			$res = tournament_team::get_team([
				'teamID'	=> $match->team_oneID,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// add team 1 to match
			$match->team_one = $res->data;

			// load team (2)
			$res = tournament_team::get_team([
				'teamID'	=> $match->team_twoID,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// add team 2 to match
			$match->team_two = $res->data;


			// translate game type
			$match->type = ($match->type == 'regular_season') ? 'Regular Season' : 'Playoffs';

			// build complete source url with filename
			$match->source_url_complete = ($match->scheduleID) ? $base_source_url.'/'.$match->gameDate.'_'.$match->scheduleID.'.png' : null;

			// for both teams
			foreach(['gold_team_one', 'gold_team_two'] as $team_key){

				// check if smaller than 10 k or larger/equal than 100 k
				$smaller_10k = ($match->{$team_key} < 10000);
				$larger_100k = ($match->{$team_key} >= 100000);

				// convert gold to string (make chars accessable)
				$gold_string = strval($match->{$team_key});

				// 1: smaller than 10 k
				if($smaller_10k) $match->{$team_key.'_string'} = $gold_string[0].'.'.$gold_string[1].' k';

				// 2: larger 10 k, smaller 100 k
				elseif(!$smaller_10k and !$larger_100k) $match->{$team_key.'_string'} = $gold_string[0].$gold_string[1].'.'.$gold_string[2].' k';

				// 3: larger 100 k
				elseif($larger_100k) $match->{$team_key.'_string'} = $gold_string[0].$gold_string[1].$gold_string[2].'.'.$gold_string[3].' k';
				}

			// set game at gameID index
			$tournament_game_list[$match->gameID] = $match;
			}


		// load all tournament game player data for this tournament
		$res = tournament_game_player::get_game_player([
			'tournamentID'	=> $mand['tournamentID'],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take list
		$game_player_list = $res->data;


		// load all game bans
		$res = tournament_game_ban::get_game_ban();

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take list of bans
		$gameban_list = $res->data;

		// restructured index list
		$banlist = [];

		// for each ban in list
		foreach($gameban_list as $ban){

			// convert champion name
			$ban->champion_converted = strtolower(str_replace(' ', '', $ban->champion));

			// reindex list
			$banlist[$ban->gameID][$ban->team][] = $ban;
			}

		// remove unused
		unset($gameban_list);

		// default result
		$result = [];

		// define prio translation list for roles
		$prio_role_list = [
			'top'		=> 0,
			'jungle'	=> 1,
			'mid'		=> 2,
			'adc'		=> 3,
			'support'	=> 4,
			];

		// load all players
		$res = tournament_player::get_player();

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take list of players
		$playerlist_default = $res->data;

		// restructured index list
		$playerlist = [];

		// for each player in list
		foreach($playerlist_default as $player){

			// set player at playerID index
			$playerlist[$player->playerID] = $player;
			}

		// remove unused
		unset($playerlist_default);

		// for each game player
		foreach($game_player_list as $gameplayer){

			// define string for team
			$team_string = ($gameplayer->team == 1) ? 'team_one' : 'team_two';

			// if not already set
			if(!isset($result[$gameplayer->gameID])){

				// create new
				$result[$gameplayer->gameID] = (object)[
					'gameID'			=> $gameplayer->gameID,
					'type'				=> $tournament_game_list[$gameplayer->gameID]->type,
					'gameDate'			=> $tournament_game_list[$gameplayer->gameID]->gameDate,
					'duration'			=> $tournament_game_list[$gameplayer->gameID]->duration,
					'source_url'		=> $tournament_game_list[$gameplayer->gameID]->source_url_complete,
					'vod_link'			=> $tournament_game_list[$gameplayer->gameID]->vod_link,
					'winner_teamID'		=> (($gameplayer->team == 1 and $gameplayer->won) or ($gameplayer->team == 2 and !$gameplayer->won)) ? 1 : 2,
					'team_one'			=> (object)[
						'name'			=> $tournament_game_list[$gameplayer->gameID]->team_one[0]->name,
						'teamID'		=> $tournament_game_list[$gameplayer->gameID]->team_one[0]->teamID,
						'stat_complete'		=> (object)[
							'kills'			=> (int) $tournament_game_list[$gameplayer->gameID]->kills_team_one,
							'deaths'		=> (int) $tournament_game_list[$gameplayer->gameID]->deaths_team_one,
							'assists'		=> (int) $tournament_game_list[$gameplayer->gameID]->assists_team_one,
							'gold'			=> (int) $tournament_game_list[$gameplayer->gameID]->gold_team_one,
							'gold_string'	=> $tournament_game_list[$gameplayer->gameID]->gold_team_one_string,
							'has_won'		=> ($gameplayer->team == 1 and $gameplayer->won) or ($gameplayer->team == 2 and !$gameplayer->won),
							],
						'playerID_list'		=> [],
						'championID_list'	=> [],
						'stat_player_list'	=> [],
						'ban_list'			=> $banlist[$gameplayer->gameID][1] ?? [],
						],
					'team_two'			=> (object)[
						'name'			=> $tournament_game_list[$gameplayer->gameID]->team_two[0]->name,
						'teamID'		=> $tournament_game_list[$gameplayer->gameID]->team_two[0]->teamID,
						'stat_complete'		=> (object)[
							'kills'			=> (int) $tournament_game_list[$gameplayer->gameID]->kills_team_two,
							'deaths'		=> (int) $tournament_game_list[$gameplayer->gameID]->deaths_team_two,
							'assists'		=> (int) $tournament_game_list[$gameplayer->gameID]->assists_team_two,
							'gold'			=> (int) $tournament_game_list[$gameplayer->gameID]->gold_team_two,
							'gold_string'	=> $tournament_game_list[$gameplayer->gameID]->gold_team_two_string,
							'has_won'		=> ($gameplayer->team == 2 and $gameplayer->won) or ($gameplayer->team == 1 and !$gameplayer->won),
							],
						'playerID_list'		=> [],
						'championID_list'	=> [],
						'stat_player_list'	=> [],
						'ban_list'			=> $banlist[$gameplayer->gameID][2] ?? [],
						],
					];
				}

			// add playerID to team list
			$result[$gameplayer->gameID]->{$team_string}->playerID_list[] = $gameplayer->playerID;

			// add championID to team list
			$result[$gameplayer->gameID]->{$team_string}->championID_list[] = $gameplayer->championID;

			// check if smaller than 10 k or larger/equal than 100 k
			$smaller_10k = ($gameplayer->gold < 10000);
			$larger_100k = ($gameplayer->gold >= 100000);

			// convert gold to string (make chars accessable)
			$gold_string = strval($gameplayer->gold);

			// 1: smaller than 10 k
			if($smaller_10k) $gameplayer->gold_string = $gold_string[0].'.'.$gold_string[1].' k';

			// 2: larger 10 k, smaller 100 k
			elseif(!$smaller_10k and !$larger_100k) $gameplayer->gold_string = $gold_string[0].$gold_string[1].'.'.$gold_string[2].' k';

			// 3: larger 100 k
			elseif($larger_100k) $gameplayer->gold_string = $gold_string[0].$gold_string[1].$gold_string[2].'.'.$gold_string[3].' k';

			// add player stats to list
			$result[$gameplayer->gameID]->{$team_string}->stat_player_list[$prio_role_list[$gameplayer->position]] = (object)[
				'playerID'			=> $gameplayer->playerID,
				'ingame_name'		=> $playerlist[$gameplayer->playerID]->ingame_name,
				'real_name'			=> $playerlist[$gameplayer->playerID]->real_name,
				'championID'		=> $gameplayer->championID,
				'champion_name'		=> $gameplayer->champion,
				'name_converted'	=> strtolower(str_replace(' ', '', $gameplayer->champion)),
				'summoner_one'		=> $gameplayer->summoner_one,
				'summoner_two'		=> $gameplayer->summoner_two,
				'position'			=> $gameplayer->position,
				'level'				=> $gameplayer->level,
				'total_kills'		=> $gameplayer->kills,
				'total_deaths'		=> $gameplayer->deaths,
				'total_assists'		=> $gameplayer->assists,
				'creep_score'		=> $gameplayer->creep_score,
				'total_gold'		=> $gameplayer->gold,
				'total_gold_string'	=> $gameplayer->gold_string,
				'has_won'			=> $gameplayer->won,
				'kda'				=> ($gameplayer->deaths) ? round(($gameplayer->kills + $gameplayer->assists) / $gameplayer->deaths, 2) : $gameplayer->kills + $gameplayer->assists,
				'cs_per_minute'		=> round($gameplayer->creep_score / $tournament_game_list[$gameplayer->gameID]->duration, 2),
				'kill_participation'=> round(($gameplayer->kills + $gameplayer->assists) / $result[$gameplayer->gameID]->{$team_string}->stat_complete->kills * 100, 2),
				];
			}


		// remove keys from match history (gameID)
		$result = array_values($result);

		// return reuslt
		return self::response(200, $result);
		}


	/* Object: team */
	public static function get_tournament_team($req = []){

		// process function
		return tournament_team::get_team($req);
		}

	public static function create_team($req = []){

		// process function
		return tournament_team::create_team($req);
		}

	public static function update_team($req = []){

		// process function
		return tournament_team::update_team($req);
		}


	/* Object: team_player */
	public static function get_team_player($req = []){

		// process function
		return tournament_team_player::get_team_player($req);
		}

	public static function create_team_player($req = []){

		// process function
		return tournament_team_player::create_team_player($req);
		}

	public static function update_team_player($req = []){

		// process function
		return tournament_team_player::update_team_player($req);
		}

	public static function delete_team_player($req = []){

		// process function
		return tournament_team_player::delete_team_player($req);
		}


	/* Object: schedule */
	public static function get_tournament_schedule($req = []){

		// process function
		return tournament_schedule::get_schedule($req);
		}

	public static function create_schedule($req = []){

		// process function
		return tournament_schedule::create_schedule($req);
		}

	public static function update_schedule($req = []){

		// process function
		return tournament_schedule::update_schedule($req);
		}

	/* Object: playoff */
	public static function get_playoff($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// process function
		return tournament_playoff::get_playoff($req);
		}


	public static function create_tournament_game($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			'team_oneID'	=> '~1,4294967295/i',
			'team_twoID'	=> '~1,4294967295/i',
			'winner_teamID'	=> '~1,4294967295/i',
			'type'			=> '~^(?:regular_season|knockout)$',
			'gameDate'		=> '~Y-m-d/d',
			'duration'		=> '~1,255/s',

			// collection of gamedata for each player and banlist
			'data'			=> '~/l',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'scheduleID'	=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'createTime'	=> h::dtstr('now', 'Y-m-d H:i:s'),
			'scheduleID'	=> 0,
			];

		// deeper check for data
		$mand['data'] = h::eX($mand['data'], [
			'game_player'	=> '~!empty/a',
			'game_ban'		=> '~/a',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// basic size check (10 players needed)
		if(count($mand['data']['game_player']) != 10) return self::response(400, ['game_player']);


		// for each game player
		foreach($mand['data']['game_player'] as $game_player){

			// deeper check game_player
			$game_player = h::eX($game_player, [
				'playerID'		=> '~1,4294967295/i',
				'team'			=> '~1,2/i',
				'championID'	=> '~1,4294967295/i',
				'champion'		=> '~1,255/s',
				'summoner_one'	=> '~^(?:flash|ignite|teleport|heal|barrier|exhaust|cleanse|clarity|smite|ghost|mark)$',
				'summoner_two'	=> '~^(?:flash|ignite|teleport|heal|barrier|exhaust|cleanse|clarity|smite|ghost|mark)$',
				'level'			=> '~1,20/i',
				'kills'			=> '~0,100/i',
				'deaths'		=> '~0,100/i',
				'assists'		=> '~0,100/i',
				'creep_score'	=> '~0,2000/i',
				'gold'			=> '~0,50000/i',
				'position'		=> '~^(?:top|jungle|mid|adc|support)$',
				'won'			=> '~/b',
				], $error);

			// on error
			if($error) return self::response(400, $error);
			}


		// for each game ban
		foreach($mand['data']['game_ban'] as $game_ban){

			// deeper check game ban
			$game_ban = h::eX($game_ban, [
				'team'			=> '~1,2/i',
				'championID'	=> '~1,4294967295/i',
				'champion'		=> '~1,255/s',
				], $error);

			// on error
			if($error) return self::response(400, $error);
			}

		// create tournament game
		$res = tournament_game::create_game([
			'tournamentID'	=> $mand['tournamentID'],
			'team_oneID'	=> $mand['team_oneID'],
			'team_twoID'	=> $mand['team_twoID'],
			'winner_teamID'	=> $mand['winner_teamID'],
			'type'			=> $mand['type'],
			'gameDate'		=> $mand['gameDate'],
			'duration'		=> $mand['duration'],
			'createTime'	=> $opt['createTime'],
			'scheduleID'	=> $opt['scheduleID'],
			]);

		// on error
		if($res->status != 201) return self::response(570, $res);

		// take created game
		$game = $res->data;


		// for each game player
		foreach($mand['data']['game_player'] as $game_player){

			// add tournamentID
			$game_player->tournamentID = $mand['tournamentID'];

			// add gameID (from previous created game)
			$game_player->gameID = $game->gameID;

			// add teamID
			$game_player->teamID = ($game_player->team == 1) ? $mand['team_oneID'] : $mand['team_twoID'];

			// create game player
			$res = tournament_game_player::create_game_player($game_player);

			// on error
			if($res->status != 201) return self::response(570, $res);
			}

		// for each game ban
		foreach($mand['data']['game_ban'] as $game_ban){

			// add gameID (from previous created game)
			$game_ban->gameID = $game->gameID;

			// create game ban
			$res = tournament_game_ban::create_game_ban($game_ban);

			// on error
			if($res->status != 201) return self::response(570, $res);
			}

		// return success
		return self::response(201);
		}


	/* Analysis API */
	public static function get_player_analysis($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			'playerID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// process analyse function
		return player_analyse::get_player([
			'tournamentID'	=> $mand['tournamentID'],
			'playerID'		=> $mand['playerID'],
			]);
		}


	/* Upload Tournament Image */
	public static function upload_tournament_image($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			'scheduleID'	=> '~1,4294967295/i',
			'gameDate'		=> '~Y-m-d/d',
			'base64_image'	=> '~/s',
			'extension'		=> '~^(?:image\/png)$',
			'size'			=> '~/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// basic src check
		if(!preg_match('/^data:image\/(\w+);base64,/', $mand['base64_image'], $type)) return self::response(403);

		// take file type
		$type = strtolower($type[1]);

		// check again for correct and allowed file type
		if(!in_array($type, ['png'])) return self::response(403);

		// take data
		$data = substr($mand['base64_image'], strpos($mand['base64_image'], ',') + 1);

		// define src
		$src = str_replace(' ', '+', $data);
		$src = base64_decode($data);

		// on error
		if($src === false) return self::response(403);


		// define filename (current date with underscore and scheduleID)
		$filename = h::dtstr($mand['gameDate'], 'Y-m-d').'_'.$mand['scheduleID'].'.'.$type;

		// check if local or remote and add extension
		$server_add = $_SERVER['SERVER_NAME'] == 'localhost' ? '/live_version/live_version' : '';

		// define upload path
		$complete_file = $_SERVER['DOCUMENT_ROOT'].$server_add.'/public/img/tournament/source/'.$filename;

		// upload image
		$upload = file_put_contents($complete_file, $src);

		// on error
		if($upload === false) return self::response(403);

		// return success
		return self::response(200, (object)['filename' => $filename]);
		}
	}
