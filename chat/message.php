<?php
/* Last update: 2022-05-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\chat;

use \heart\core\error as e;
use \heart\core\helper as h;

class message {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static $typeID_translation = [
		0 	=> 'standard',
		];

	protected static function pdo_config(){

		return [null, [
			's_chat_message'							=> 'SELECT * FROM `chat_message` WHERE `chat_messageID` = ? LIMIT 1',
			's_chat_message_latest_by_userID'			=> 'SELECT * FROM `chat_message` WHERE `from_userID` = ? ORDER BY `chat_messageID` DESC LIMIT 1',

			'l_chat_message'							=> 'SELECT * FROM `chat_message`',
			'l_chat_message_by_chatID'					=> 'SELECT cm.*, bu.username AS `current_from_username` FROM `chat_message` cm
															INNER JOIN `base_user` bu ON bu.userID = cm.from_userID
															WHERE `chatID` = ?
															',
			'l_chat_message_by_from_userID'				=> 'SELECT * FROM `chat_message` WHERE `from_userID` = ?',

			'i_chat_message'							=> 'INSERT INTO `chat_message` (`from_userID`, `from_username`, `chatID`, `chat_message`, `typeID`, `sentTime`) VALUES (?,?,?,?,?,?)',
			'u_chat_message'							=> 'UPDATE `chat_message` SET WHERE `chat_messageID` = ?',
			]];
		}

	public static function get_message($req = []){

		// alternative
		$alt = h::eX($req, [
			'chatID'			=> '~1,4294967295/i',
			'from_userID'		=> '~1,4294967295/i',
			'chat_messageID'	=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'latest_only'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert closure
		$convert = function($list){

			// for each chat message
			foreach($list as $entry){

				// extend typeID
				$entry->type = self::$typeID_translation[$entry->typeID];
				}
			};


		// param order 1: chat_messageID
		if(isset($alt['chat_messageID'])){

			// call to pdo function
			$entry = self::pdo('s_chat_message', [$alt['chat_messageID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 2: chatID
		if(isset($alt['chatID'])){

			// load list
			$list = self::pdo('l_chat_message_by_chatID', [$alt['chatID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// on success
			return self::response(200, $list);
			}

		// param order 3: from_userID & latest_only
		if(isset($alt['from_userID']) and !empty($opt['latest_only'])){

			// call to pdo function
			$entry = self::pdo('s_chat_message_latest_by_userID', [$alt['from_userID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 4: from_userID
		if(isset($alt['from_userID'])){

			// load list
			$list = self::pdo('l_chat_message_by_from_userID', [$alt['from_userID']]);

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// on success
			return self::response(200, $list);
			}

		// param order 5: empty
		if(empty($alt)){

			// call to pdo function
			$list = self::pdo('l_chat_message');

			// on error
			if($list === false) return self::response(560);

			// convert
			$convert($list);

			// return success
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting chat_messageID|from_userID or no param');
		}

	public static function create_message($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from_userID'	=> '~1,4294967295/i',
			'from_username'	=> '~1,255/s',
			'chatID'		=> '~1,4294967295/i',
			'chat_message'	=> '~1,500/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'typeID'		=> '~0,10/i',
			'sentTime'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'typeID'		=> 0,
			'sentTime'		=> h::dtstr('now'),
			];

		// insert chat message
		$chat_messageID = self::pdo('i_chat_message', [$mand['from_userID'], $mand['from_username'], $mand['chatID'], $mand['chat_message'], $opt['typeID'], $opt['sentTime']]);

		// on error
		if($chat_messageID === false) return self::response(560);

		// return success
		return self::response(201, (object)['chat_messageID' => $chat_messageID]);
		}

	public static function update_message($req = []){

		// mandatory
		$mand = h::eX($req, [
			'chat_messageID'=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'from_userID'	=> '~1,4294967295/i',
			'from_username'	=> '~1,255/s',
			'chatID'		=> '~1,4294967295/i',
			'chat_message'	=> '~1,500/s',
			'typeID'		=> '~0,10/i',
			'sentTime'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load chat message
		$res = self::get_message([
			'chat_messageID'=> $mand['chat_messageID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take chat
		$entry = $res->data;

        // default update
        $update = [];

        // check for each opt
        foreach($opt as $k => $v){

            // if value does not match push into update
            if($entry->{$k} !== $v) $update[$k] = $v;
            }

        // if there is nothing to update
        if(!$update) return self::response(204);

        // set query
        $query = self::pdo_extract('u_chat_message', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->chat_messageID]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
