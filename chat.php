<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats;

use \heart\core\error as e;
use \heart\core\helper as h;

class chat {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [
			's_chat'				=> 'SELECT * FROM `chat` WHERE `chatID` = ? LIMIT 1',
			's_chat_by_hash'		=> 'SELECT * FROM `chat` WHERE `hash` = ? LIMIT 1',

			'l_chat'				=> 'SELECT * FROM `chat`',

			'i_chat'				=> 'INSERT INTO `chat` (`hash`, `enabled`, `last_messageID`, `last_messageDate`, `createTime`) VALUES (?,?,?,?,?)',
			'u_chat'				=> 'UPDATE `chat` SET WHERE `chatID` = ?',
			]];
		}

	public static function get_chat($req = []){

		// alternative
		$alt = h::eX($req, [
			'chatID'	=> '~1,4294967295/i',
			'hash'		=> '~1,40/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// closure convert
		$convert = function($list){

			// for each chat
			foreach($list as $entry){

				// convert enabled to boolean
				$entry->enabled = (bool) $entry->enabled;
				}
			};

		// param order 1: chatID
		if(isset($alt['chatID'])){

			// call to pdo function
			$entry = self::pdo('s_chat', [$alt['chatID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 2: hash
		if(isset($alt['hash'])){

			// call to pdo function
			$entry = self::pdo('s_chat_by_hash', [$alt['hash']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert entry
			$convert([$entry]);

			// return success
			return self::response(200, $entry);
			}

		// param order 3: empty
		if(empty($alt)){

			// call to pdo function
			$list = self::pdo('l_chat');

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// return success
			return self::response(200, $list);
			}

		// on param error
		return self::response(400, 'expecting chatID|hash or no param');
		}

	public static function create_chat($req = []){

		// optional
		$opt = h::eX($req, [
			'hash'				=> '~1,40/s',
			'enabled'			=> '~/b',
			'last_messageID'	=> '~1,4294967295/i',
			'last_messageTime'	=> '~Y-m-d H:i:s/d',
			'createTime'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'hash'				=> substr(md5(mt_rand()), 0, 40),
			'enabled'			=> true,
			'last_messageID'	=> 0,
			'last_messageTime'	=> '0000-00-00 00:00:00',
			'createTime'		=> h::dtstr('now'),
			];

		// create chat in DB
		$chatID = self::pdo('i_chat', [$opt['hash'], $opt['enabled'] ? 1 : 0, $opt['last_messageID'], $opt['last_messageTime'], $opt['createTime']]);

		// on error
		if($chatID === false) return self::response(560);

		// return success
		return self::response(201, (object) ['chatID' => $chatID, 'hash' => $opt['hash']]);
		}

	public static function update_chat($req = []){

		// alternative
		$alt = h::eX($req, [
			'chatID'	=> '~1,4294967295/i',
			'hash'		=> '~1,40/s',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'enabled'			=> '~/b',
			'last_messageID'	=> '~1,4294967295/i',
			'last_messageTime'	=> '~Y-m-d H:i:s/d',
			'createTime'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// check for at least and not more that 1 alt param
		if(count($alt) != 1 and !$error) $error[] = 'chatID|hash';

		// on error
		if($error) return self::response(400, $error);

		// load chat by given param
		$res = self::get_chat([
			'chatID'	=> $alt['chatID'] ?? null,
			'hash'		=> $alt['hash'] ?? null,
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

        // special convert
        if(isset($update['enabled'])) $update['enabled'] = $update['enabled'] ? 1 : 0;

        // set query
        $query = self::pdo_extract('u_chat', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$entry->chatID]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
