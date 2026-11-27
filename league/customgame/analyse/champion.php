<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\customgame\analyse;

use \heart\core\error as e;
use \heart\core\helper as h;

class champion {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries: champions / custom_game_player / custom_game_ban */
			's_customgame_champion_analyse'				=> 'SELECT c.championID, c.name, c.gender, COUNT(cgp.championID) AS `times_played`,
															(SELECT COUNT(`championID`) FROM `custom_game_ban` WHERE `championID` = c.championID) AS `times_banned`,
															SUM(cgp.won) / COUNT(cgp.championID) * 100 AS `winrate`,
															SUM(cgp.won) AS `times_won`, COUNT(cgp.championID) - SUM(cgp.won) AS `times_lost`,
															((SELECT COUNT(`championID`) FROM `custom_game_ban` WHERE `championID` = c.championID) + COUNT(cgp.championID)) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `present_rate`,
															(SELECT COUNT(`championID`) FROM `custom_game_ban` WHERE `championID` = c.championID) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `banrate`
															FROM `champions` c
															LEFT JOIN `custom_game_player` cgp ON cgp.championID = c.championID
															WHERE c.championID = ?
															LIMIT 1
															',

			'l_customgame_champion_analyse'				=> 'SELECT c.championID, c.name, c.gender, COUNT(cgp.championID) AS `times_played`,
															(SELECT COUNT(`championID`) FROM `custom_game_ban` WHERE `championID` = c.championID) AS `times_banned`,
															SUM(cgp.won) / COUNT(cgp.championID) * 100 AS `winrate`,
															SUM(cgp.won) AS `times_won`, COUNT(cgp.championID) - SUM(cgp.won) AS `times_lost`,
															((SELECT COUNT(`championID`) FROM `custom_game_ban` WHERE `championID` = c.championID) + COUNT(cgp.championID)) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `present_rate`,
															(SELECT COUNT(`championID`) FROM `custom_game_ban` WHERE `championID` = c.championID) / (SELECT COUNT(`gameID`) FROM `custom_game`) * 100 AS `banrate`
															FROM `champions` c
															LEFT JOIN `custom_game_player` cgp ON cgp.championID = c.championID
															GROUP BY c.championID
															',

			]];

		}


	public static function get_champion($req = []){

		// alternative
		$alt = h::eX($req, [
			'championID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// alter list
		$convert = function($list){

			// for each entry
			foreach($list as $entry){

				// for each float value (since live sql version doesn't support cast function in query)
				foreach(['banrate', 'winrate', 'present_rate'] as $key){

					// convert
					$entry->{$key} = (float) round($entry->{$key}, 2);
					}

				// for each int value (since live sql version doesn't support cast function in query)
				foreach(['times_won', 'times_lost'] as $key){

					// convert
					$entry->{$key} = (int) $entry->{$key};
					}
				}
			};


		// param order 1: championID
		if(isset($alt['championID'])){

			// load champion from DB
			$entry = self::pdo('s_customgame_champion_analyse', [$alt['championID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// convert list
			$convert([$entry]);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: empty
		if(empty($alt)){

			// load list from DB
			$list = self::pdo('l_customgame_champion_analyse');

			// on error
			if($list === false) return self::response(560);

			// convert list
			$convert($list);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting championID or no param');
		}
	}
