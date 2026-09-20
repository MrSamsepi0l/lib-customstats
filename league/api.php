<?php
/* Last update: 2023-01-13
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league;

use \heart\core\helper as h;
use \heart\core\error as e;

use \customstats\league\player;
use \customstats\league\source;
use \customstats\league\replay;
use \customstats\league\champion;
use \customstats\league\customgame;

use \customstats\api\share as api_share;
use \customstats\media\upload as media_upload;

use \customstats\league\customgame\ban as game_ban;
use \customstats\league\customgame\player as game_player;
use \customstats\league\customgame\analyse\player as analyse_player;
use \customstats\league\customgame\player\profile as player_profile;

use \customstats\league\timeserie as league_timeserie;
use \customstats\league\customgame\bestof as league_best_of;
use \customstats\league\customgame\dashboard as league_dashboard;
use \customstats\league\customgame\analyse\champion as league_analyse_champion;

class api {
	use \heart\core\libcom_trait,
	    \heart\core\pdo_trait,
	    \customstats\extension\filecache_trait;


	/* Object: Champion */
	public static function load_champion($req = []){

		// process request
		return champion::get_champion($req);
		}

	public static function create_champion($req = []){

		// process request
		return champion::create_champion($req);
		}

	public static function update_champion($req = []){

		// process request
		return champion::update_champion($req);
		}


	/* Object: Player */
	public static function get_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'api_key'		=> '~1,32/s',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'playerID' 		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [

			// includes game statistic for player
			'with_analyze'		=> '~/b',
			'enabled_only'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'with_analyze'	=> true,
			];

		// if anaylsis wanted
		if($opt['with_analyze']){

			// load profile
			$res = player_profile::get_profile([
				'project'	=> $mand['project'],
				'playerID'	=> $alt['playerID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;
			}

		// if list only
		elseif(!$opt['with_analyze']){

			// load player
			$res = player::get_player([
				'playerID'		=> $alt['playerID'] ?? null,
				'enabled_only'	=> $opt['enabled_only'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;
			}

		// return result
		return self::response(200, $res->data);
		}

	public static function create_player($req = []){

		// process request
		return player::create_player($req);
		}

	public static function update_player($req = []){

		// process request
		return player::update_player($req);
		}


	/* Object: Game */
	public static function get_custom_game($req = []){

		// process request
		return customgame::get_custom_game($req);
		}

	public static function create_custom_game($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'api_key'		=> '~1,32/s',
			'gameDate'		=> '~Y-m-d/d',
			'duration'		=> '~/s',

			// collection of gamedata for each player and banlist
			'data'			=> '~/l',
			], $error);

		// optional
		$opt = h::eX($req, [
			'sourceID'		=> '~0,4294967295/i',
			'canceled'		=> '~/b',
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

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
				'teamID'		=> '~1,2/i',
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
				'teamID'		=> '~1,2/i',
				'championID'	=> '~1,4294967295/i',
				'champion'		=> '~1,255/s',
				], $error);

			// on error
			if($error) return self::response(400, $error);
			}


		// create game
		$res = customgame::create_custom_game([
			'gameDate'		=> $mand['gameDate'],
			'duration'		=> $mand['duration'],
			'sourceID'		=> $opt['sourceID'] ?? null,
			'canceled'		=> $opt['canceled'] ?? null,
			'createTime'	=> $opt['createTime'] ?? null,
			]);

		// on error
		if($res->status != 201) return self::response(570, $res);

		// take data (gameID only)
		$custom_game = $res->data;

		// for each game player
		foreach($mand['data']['game_player'] as $game_player){

			// add previous created game ID
			$game_player->gameID = $custom_game->gameID;

			// create game player
			$res = game_player::create_game_player($game_player);

			// on error
			if($res->status != 201) return self::response(570, $res);
			}

		// for each game ban
		foreach($mand['data']['game_ban'] as $game_ban){

			// add previous created game ID
			$game_ban->gameID = $custom_game->gameID;

			// create game ban
			$res = game_ban::create_game_ban($game_ban);

			// on error
			if($res->status != 201) return self::response(570, $res);
			}

		// cleanup file cache
		self::cleanup_cache();

		// return success
		return self::response(201);
		}

	public static function update_custom_game($req = []){

		// mandatory
		$mand = h::eX($req, [
			'gameID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'canceled'		=> '~/b',
			'gameDate'		=> '~Y-m-d/d',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'duration'		=> '~/s',
			'sourceID'		=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// forward request and update game
		$res = customgame::update_custom_game($mand + $opt);

		// on error
		if($res->status != 204) return self::response(570, $res);

		// return success
		return self::response(204);
		}


	/* Object: Game_player */
	public static function get_game_player($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'api_key'		=> '~1,32/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// forward request
		return game_player::get_game_player($req);
		}

	public static function create_game_player($req = []){

		// forward request
		return game_player::create_game_player($req);
		}

	public static function update_game_player($req = []){

		// forward request
		return game_player::update_game_player($req);
		}

	/* Object: Game_ban */
	public static function get_game_ban($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'api_key'		=> '~1,32/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// forward request
		return game_ban::get_game_ban($req);
		}

	public static function create_game_ban($req = []){

		// forward request
		return game_ban::create_game_ban($req);
		}

	public static function update_game_ban($req = []){

		// forward request
		return game_ban::update_game_ban($req);
		}


	/* Object: Source */
	public static function load_source_url($req = []){

		// alternative
		$alt = h::eX($req, [
			'sourceID'			=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if single source wanted
		if(isset($alt['sourceID'])){

			// load urls
			$res = source::get_source_url($req);

			// on error
			if($res->status !== 200) return self::response(570, $res);

			// take source
			$source = $res->data;

			// add url
			// TODO: change this
			$source->image_url = '/public/img/customgame/source/' . $source->filename;

			// directly return response
			return self::response(200, $source);
			}

		// load urls
		$res = source::get_source_url($req);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take data
		$url = $res->data;

		// default
		$result = [];

		// for each img
		foreach($url as $entry){

			// create output object
			$result[] = (object) [
				'sourceID'		=> $entry->sourceID,
				'url'			=> $entry->url,
				'filename'		=> $entry->filename,
				'gameDate'		=> $entry->gameDate,
				'month_year'	=> h::dtstr($entry->gameDate, 'M-Y'),
				'image_url'		=> ($entry->filename) ? '/public/img/customgame/source/' . $entry->filename : '',
				];
			}

		// return success
		return self::response(200, $result);
		}

	/* DEPRECATED */
	public static function create_source_url($req = []){

		// process request
		return source::create_source_url($req);
		}


	/* Object: Timeserie */
	public static function get_timeserie($req = []){

		// process request
		return league_timeserie::get_timeserie($req);
		}


	/* Object: Replay */
	public static function get_replay($req = []){

		// get replay
		$res = replay::get_replay($req);

		// on error
		if($res->status != 200) return $res;

		// take replay data
		$data = $res->data;

		// if array
		if(!is_array($data)) $data = [$data];

		// default
		$result = [];

		// for each replay
		foreach($data as $entry){

			// default array
			$involved_player = [];

			// load replay_player by replayID
			$res = replay::get_replay_player([
				'replayID'		=> $entry->replayID,
				]);

			// on error
			if($res->status != 200) return $res;

			// take data
			$replay_player = $res->data;

			// for each replay player
			foreach($replay_player as $rp){

				// create object
				$involved_player[] = (object)[
					'playerID'		=> $rp->playerID,
					'ingame_name'	=> $rp->ingame_name,
					'real_name'		=> $rp->real_name,
					];
				}

			// append to entry
			$entry->involved_player = $involved_player;
			}

		// convert to object if only one entry
		if(count($data) == 1) $data = $data[0];

		// return result
		return self::response(200, $data);
		}

	public static function create_replay($req = []){

		// process request
		return replay::create_replay($req);
		}

	public static function update_replay($req = []){

		// process request
		return replay::update_replay($req);
		}

	public static function delete_replay($req = []){

		// process request
		return replay::delete_replay($req);
		}

	public static function get_replay_player($req = []){

		// process request
		return replay::get_replay_player($req);
		}

	public static function create_replay_player($req = []){

		// process request
		return replay::create_replay_player($req);
		}

	public static function update_replay_player($req = []){

		// process request
		return replay::update_replay_player($req);
		}

	public static function delete_replay_player($req = []){

		// process request
		return replay::delete_replay_player($req);
		}


	/* Object: Image */
	public static function upload_image($req = []){

		// mandatory
		$mand = h::eX($req, [
			'api_key'		=> '~32,32/s',
			'type'			=> '~^(?:customgame_source)$',
			'base64_image'	=> '~/s',
			'mime_type'		=> '~^(?:image\/png|image\/jpeg|image\/gif)$',
			'size'			=> '~1,10485760/i', // 10 MB
			], $error);

		// optional
		$opt = h::ex($req, [
			'param'			=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// basic string check, if it is base64 encoded
		if(!preg_match('/^data:image\/(\w+);base64,/', $mand['base64_image'], $extension)){

			// return error
			return self::response(400, ['base64_image']);
			}

		// take data and remove base64 standard encoding parts from source string
		$base64_image = substr($mand['base64_image'], strpos($mand['base64_image'], ',') + 1);
		$base64_image = str_replace(' ', '+', $base64_image);

		// process image media upload
		return media_upload::upload_image([
			'api_key'		=> $mand['api_key'],
			'type'			=> $mand['type'],
			'base64_image'	=> $base64_image,
			'extension'		=> strtolower($extension[1]),
			'param'			=> $opt['param'] ?? null,
			]);
		}


	/* Object: Best of */
	public static function get_bestof_analysis($req = []){

		// process request
		return league_best_of::get_bestof_analysis($req);
		}

	public static function get_champion_analysis($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'api_key'		=> '~1,32/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load active user
		$res = api_share::get_active_user([
			'api_key'		=> $mand['api_key'],
			]);

		// if user disabled or not found, return gone
		if(in_array($res->status, [404, 410])) return self::response(410);

		// on unexpected error
		if($res->status != 200) return self::response(570, $res);

		// process request
		return league_analyse_champion::get_champion($req);
		}

	/* Dashboard */
	public static function get_dashboard_information($req = []){

		// process fn
		return league_dashboard::get_dashboard_information($req);
		}

	}
