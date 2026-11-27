<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\league\customgame;

use \heart\core\error as e;
use \heart\core\helper as h;

class dashboard {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return ['dbs1395906', [

			/* Queries custom_game_player */
			's_dashboard_game_information'		=> 'SELECT COUNT(DISTINCT(`gameID`)) AS `total_games`, (SELECT MAX(`duration`) FROM `custom_game`) AS `highest_duration`,
													(SELECT AVG(`duration`) FROM `custom_game`) AS `average_duration`,
													(SELECT MAX(`gameDate`) FROM `custom_game`) AS `last_gameDate`
													FROM `custom_game_player`
													LIMIT 1
													',
			]];
		}

	public static function get_dashboard_information($req = []){

		// load data
		$entry = self::pdo('s_dashboard_game_information');

		// on error
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// return data
		return self::response(200, $entry);
		}
	}
