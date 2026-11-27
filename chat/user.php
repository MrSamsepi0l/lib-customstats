<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\chat;

use \heart\core\error as e;
use \heart\core\helper as h;

class user {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			// Queries: chat_user
			's_chat_user'							=> 'SELECT * FROM `chat_user` WHERE `chatID` = ? AND `userID` = ? LIMIT 1',

			'l_chat_user'							=> 'SELECT * FROM `chat_user`',
			'l_chat_user_by_chatID'					=> 'SELECT * FROM `chat_user` WHERE `chatID` = ?',
			'l_chat_user_by_userID'					=> 'SELECT * FROM `chat_user` WHERE `userID` = ?',
			'l_chat_user_with_profile'				=> 'SELECT cu.userID, cu.chatID, cu.data, cu.enabled, cu.createTime AS `chat_joinDate`,
																u.username, u.real_name AS `realname`, u.last_activity, u.online, u.playerID, u.createTime AS `registerDate`,
																p.ingame_name
														FROM `chat_user` cu
														INNER JOIN `base_user` u ON u.userID = cu.userID
														LEFT JOIN `player` p ON p.playerID = u.playerID
														WHERE cu.chatID = ?
															AND cu.enabled = 1
															AND u.enabled = 1
														GROUP BY cu.userID
														',

			'i_chat_user'							=> 'INSERT INTO `chat_user` (`chatID`, `userID`, `data`, `enabled`, `createTime`) VALUES (?,?,?,?,?)',
			'u_chat_user'							=> 'UPDATE `chat_user` SET WHERE `chatID` = ? AND `userID` = ?',
			]];
		}

	public static function get_user($req = []){

		// alternative
		$alt = h::eX($req, [
			'chatID'	=> '~1,4294967295/i',
			'userID'	=> '~1,4294967295/i',
			'with_profile'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert closure
		$convert = function($list){

			// for each chat user
			foreach($list as $entry){

				// decode data
				$entry->data = json_decode($entry->data);

				// convert to boolean
				$entry->enabled = (bool) $entry->enabled;
				}
			};


		// param order 1: chatID and userID
		if(isset($alt['chatID']) and isset($alt['userID'])){

			// load entry
			$entry = self::pdo('s_chat_user', [$alt['chatID'], $alt['userID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 2: chat ID and with_profile
		if(isset($alt['chatID']) and !empty($alt['with_profile'])){

			// load list
			$list = self::pdo('l_chat_user_with_profile', [$alt['chatID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return success
			return self::response(200, $list);
			}

		// param order 3: chatID
		if(isset($alt['chatID'])){

			// load list
			$list = self::pdo('l_chat_user_by_chatID', [$alt['chatID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// on success
			return self::response(200, $list);
			}

		// param order 4: userID
		if(isset($alt['userID'])){

			// load list
			$list = self::pdo('l_chat_user_by_userID', [$alt['userID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// on success
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_chat_user');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting chatID+userID|chatID|userID or no param');
		}

	public static function create_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'chatID'	=> '~1,4294967295/i',
			'userID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'data'		=> '~/c',
			'enabled'	=> '~/b',
			'createTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'data'		=> [],
			'enabled'	=> true,
			'createTime'=> h::dtstr('now'),
			];

		// encode data
		$opt['data'] = json_encode($opt['data']);

		// insert chat message
		$insert = self::pdo('i_chat_user', [$mand['chatID'], $mand['userID'], $opt['data'], $opt['enabled'] ? 1 : 0, $opt['createTime']]);

		// on error
		if($insert === false) return self::response(560);

		// return success
		return self::response(201, (object)['chatID' => $mand['chatID'], 'userID' => $mand['userID']]);
		}

	public static function update_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'chatID'	=> '~1,4294967295/i',
			'userID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'data'		=> '~/c',
			'enabled'	=> '~/b',
			'createTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load chat message
		$res = self::get_user([
			'chatID'	=> $mand['chatID'],
			'userID'	=> $mand['userID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take chat
		$entry = $res->data;

        // default update
        $update = [];

        // check for each opt
        foreach($opt as $k => $v){

            // if value does not match add to update
            if($entry->{$k} !== $v) $update[$k] = $v;
            }

        // if there is nothing to update
        if(!$update) return self::response(204);

        // if data is updated, encode it
        if(isset($update['data'])) $update['data'] = json_encode($update['data']);
        if(isset($update['enabled'])) $update['enabled'] = $update['enabled'] ? 1 : 0;

        // set query
        $query = self::pdo_extract('u_chat_user', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->chatID, $entry->userID]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
