<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats;

use \heart\core\helper as h;
use \heart\core\error as e;

use \customstats\api\share as api_share;
use \customstats\base\config as base_config;
use \customstats\base\user as base_user;
use \customstats\base\leagueapi as base_leagueapi;

use \customstats\chat;
use \customstats\chat\user as chat_user;
use \customstats\chat\message as chat_message;

class api {
	use \heart\core\libcom_trait;


	public static function load_chatlist($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'api_key'		=> '~1,32/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'with_userlist'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load active user profile
		$res = api_share::get_active_user([
			'api_key'		=> $mand['api_key'],
			]);

		// if not found or disabled, return gone
		if(in_array($res->status, [404, 410])) return self::response(410);

		// on unexpected error
		if($res->status != 200) return self::response(570, $res);

		// take user profile
		$user = $res->data;


		// load chat user by userID
		$res = chat_user::get_user([
			'userID'		=> $user->userID,
			]);

		// on error, return not found
		if($res->status != 200) return self::response(404);

		// take users chat list
		$user_chatlist = $res->data;

		// define result
		$result_chatlist = [];

		// for each users chat
		foreach($user_chatlist as $user_chat){

			// if disabled, skip
			if(!$user_chat->enabled) continue;

			// load chat by chatID
			$res = chat::get_chat([
				'chatID'	=> $user_chat->chatID,
				]);

			// on error
			if($res->status != 200) return self::response(404);

			// take chat
			$chat = $res->data;

			// if chat is disabled, skip
			if(!$chat->enabled) continue;


			// load user profile list for this chat
			$res = chat_user::get_user([
				'chatID'		=> $chat->chatID,
				'with_profile'	=> true,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take profile list
			$profile_list = $res->data;

			// collect profile data
			$user_profile = (object)[
				'list'			=> [],
				'total_profile'	=> 0,
				'total_online'	=> 0,
				'total_offline'	=> 0,
				];

			// for each profile
			foreach($profile_list as $profile){

				// check if profile is offline at the moment
				$is_offline = (!$profile->online or !$profile->last_activity or h::dtstr('now', 'U') - $profile->last_activity > 1200);

				// increment profile
				$user_profile->total_profile++;

				// increment stat by online state
				if($is_offline) $user_profile->total_offline++;
				if(!$is_offline) $user_profile->total_online++;

				// build profile data
				$profile_data = (object)[
					'username'		=> $profile->username,
					'realname'		=> $profile->realname,
					'ingame_name'	=> $profile->ingame_name,
					'chatuser_data'	=> $profile->data,
					'is_online'		=> !$is_offline,
					'online_status'	=> (!$is_offline) ? 'Online' : 'Offline',
					'onlineTime'	=> ($is_offline and $profile->last_activity) ? h::dtstr($profile->last_activity, 'Y-m-d') : 0,
					'registerDate'	=> h::dtstr($profile->registerDate, 'Y-m-d'),
					'chat_joinDate'	=> h::dtstr($profile->chat_joinDate, 'Y-m-d'),
					'chat_color'	=> h::gX($profile, 'data:chat_color'),
					];

				// add to list
				$user_profile->list[] = $profile_data;
				}


			// load messages for this chat by chatID
			$res = chat_message::get_message([
				'chatID'	=> $chat->chatID,
				]);

			// on error
			if($res->status != 200) return self::response(404);

			// take chat messages
			$chat_message_list = $res->data;

			// for each chat message
			foreach($chat_message_list as $chat_message){

				// add createDate
				$chat_message->createDate = h::dtstr($chat_message->sentTime, 'Y-m-d');

				// remove chatID and userID
				unset($chat_message->chatID);
				unset($chat_message->from_userID);
				}

			// define chat object
			$chat_result = (object)[

				// chat
				'chat_hash'			=> $chat->hash,
				'last_messageID'	=> $chat->last_messageID,
				'last_messageTime'	=> $chat->last_messageTime,

				// chat user
				'api_key'			=> $user->api_key,
				'user_chat_data'	=> $user_chat->data,
				'user_chat_joinTime'=> $user_chat->createTime,

				// chat message
				'chat_message_list'	=> $chat_message_list,

				// all chat profiles
				'user_profile_list'	=> $user_profile->list,
				'total_profile'		=> $user_profile->total_profile,
				'total_online'		=> $user_profile->total_online,
				'total_offline'		=> $user_profile->total_offline,
				];

			// add to result
			$result_chatlist[] = $chat_result;
			}

		// return user chat list
		return self::response(200, $result_chatlist);
		}

	public static function send_message($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'api_key'	=> '~1,32/s',
			'chat_hash'	=> '~1,40/s',
			'message'	=> '~1,500/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// load config
		$res = base_config::get_config([
			'project'	=> $mand['project'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take config
		$config = $res->data;


		// load active user profile
		$res = api_share::get_active_user([
			'api_key'	=> $mand['api_key'],
			]);

		// on error, directly return result
		if($res->status != 200) return $res;

		// take user profile
		$user = $res->data;


		// load chat by hash
		$res = chat::get_chat([
			'hash'		=> $mand['chat_hash'],
			]);

		// on error
		if($res->status != 200) return self::response(404);

		// take chat
		$chat = $res->data;


		// load chat user by chat ID and userID
		$res = chat_user::get_user([
			'chatID'	=> $chat->chatID,
			'userID'	=> $user->userID,
			]);

		// on unexpected error error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return not allowed
		if($res->status == 404) return self::response(404);

		// take chat user
		$chat_user = $res->data;


		// load last users message
		$res = chat_message::get_message([
			'from_userID'	=> $user->userID,
			'latest_only'	=> true,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// take min seconds elapsed between last message and now from config or define default 10 seconds
		$min_second_elapsed = h::gX($config, 'param:base_config:message:min_seconds_between_message') ?? 10;

		// if found
		if($res->status == 200 and h::dtstr('now', 'U') - h::dtstr($res->data->sentTime, 'U') < $min_second_elapsed) return self::response(403);


		// create chat message
		$res = chat_message::create_message([
			'from_userID'	=> $user->userID,
			'from_username'	=> $user->username,
			'chatID'		=> $chat->chatID,
			'chat_message'	=> trim($mand['message']),
			'typeID'		=> 0,
			'sentTime'		=> h::dtstr($_SERVER['REQUEST_TIME']),
			]);

		// on error
		if($res->status != 201) return self::response(570, $res);


		// update chat
		$res = chat::update_chat([
			'chatID'			=> $chat->chatID,
			'last_messageID'	=> $res->data->chat_messageID,
			'last_messageTime'	=> h::dtstr($_SERVER['REQUEST_TIME']),
			]);

		// on error
		if($res->status != 204){

			// just log it
			e::logtrigger('send_message() error on updating chat after successfully created message with msgID: '.$res->data->chat_messageID.' and chatID: '.$chat->chatID.' for userID: '.$user->userID);
			}

		// return success
		return self::response(201);
		}

	public static function update_user_password($req = []){

		// mandatory
		$mand = h::eX($req, [
			'api_key'			=> '~1,32/s',
			'old_password'		=> '~1,2048/s',
			'new_password'		=> '~6,2048/s',
			'password_check'	=> '~6,2028/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// compare new password with check
		if($mand['new_password'] !== $mand['password_check']) return self::response(400);

		// get api key from session
		$api_key = $_SESSION['customstats:api_key'] ?? null;

		// if not found or does not match sent api key, return
		if(!$api_key or $api_key !== $mand['api_key']) return self::response(403);

		// load user by api key
		$res = base_user::get_user([
			'api_key'	=> $api_key,
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take user
		$user = $res->data;

		// if old password does not match, return error
		if(password_verify($mand['old_password'], $user->password) !== true) return self::response(403);

		// check last password reset time
		if((h::dtstr('now', 'U') - $user->password_reset) < 172800) return self::response(425); // 48h

		// update user password
		$res = base_user::update_user_password([
			'userID'		=> $user->userID,
			'new_password'	=> $mand['new_password'],
			]);

		// on any error, return 500
		if($res->status !== 204) return self::response(500, 'Internal Server Error');

		// log successfull password update
		e::logtrigger('Password update of user ('.$user->userID.' - '.$user->username.' - '.$api_key.') was successfull!');

		// return success
		return self::response(204);
		}

	public static function get_leagueapi($req = []){

		// forward request
		$res = base_leagueapi::get_leagueapi($req);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take list
		$list = is_object($res->data) ? [$res->data] : $res->data;

		// for each entry in list
		foreach($list as $entry){

			// check if function is callable
			$entry->is_callable = is_callable($entry->fn);
			}

		// return list
		return self::response(200, $list);
		}

	public static function delete_leagueapi($req = []){

		// forward request
		return base_leagueapi::delete_leagueapi($req);
		}

	}
