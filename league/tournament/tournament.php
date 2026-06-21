<?php
/* Last update: 2022-09-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\league\tournament;

use \heart\core\error as e;
use \heart\core\helper as h;

class tournament {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait;

	protected static function pdo_config(){

		return [null, [

			's_tournament'								=> 'SELECT * FROM `tournament` WHERE `tournamentID` = ? LIMIT 1',
			's_tournament_by_name'						=> 'SELECT * FROM `tournament` WHERE `name` = ? LIMIT 1',

			'l_tournament'								=> 'SELECT * FROM `tournament`',

			'i_tournament'								=> 'INSERT INTO `tournament` (`name`, `startDate`, `endDate`, `createTime`) VALUES (?,?,?,?)',

			'u_tournament'               				=> 'UPDATE `tournament` SET WHERE `tournamentID` = ?',

			]];
		}

	public static function get_tournament($req = []){

		// alternative
		$alt = h::eX($req, [
			'tournamentID'		=> '~1,4294967295/i',
			'name'				=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: tournamentID
		if(isset($alt['tournamentID'])){

			// load entry
			$entry = self::pdo('s_tournament', [$alt['tournamentID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: name
		if(isset($alt['name'])){

			// load entry
			$entry = self::pdo('s_tournament_by_name', [$alt['name']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: empty
		if(empty($alt)){

			// load list
			$list = self::pdo('l_tournament');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// on error
		return self::response(400, 'Expecting tournamentID|name or no parameter');
		}

	public static function create_tournament($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,255/s',
			'startDate'		=> '~Y-m-d/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s',
			'endDate'		=> '~Y-m-d/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'createTime'	=> h::dtstr('now', 'Y-m-d H:i:s'),
			'endDate'		=> null,
			];

		// get tournament by name
		$res = self::get_tournament([
			'name'			=> $mand['name'],
			]);

		// if found, return conflict
		if($res->status == 200) return self::response(409);

		// create tournament
		$tournamentID = self::pdo('i_tournament', [$mand['name'], $mand['startDate'], $opt['endDate'], $opt['createTime']]);

		// on error
		if($tournamentID === false) return self::response(560);

		// return success
		return self::response(201, (object)['tournamentID' => $tournamentID]);
		}

	public static function update_tournament($req = []){

		// mandatory
		$mand = h::eX($req, [
			'tournamentID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,255/s',
			'startDate'		=> '~Y-m-d/d',
			'endDate'		=> '~Y-m-d/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// get tournament by ID
		$res = self::get_tournament([
			'tournamentID'	=> $mand['tournamentID'],
			]);

		// on error
		if($res->status != 200) return $res;

        // take entry
        $entry = $res->data;

		// if tournament name is about to be changed
		if(isset($opt['name'])){

			// load tournament by name
			$res = self::get_tournament([
				'name'			=> $opt['name'],
				]);

			// if found and not the identical entry, return conflict
			if($res->status == 200 and $res->data->tournamentID != $mand['tournamentID']) return self::response(409);
			}

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
        $query = self::pdo_extract('u_tournament', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

        // update
        $upd = self::pdo($query, array_merge(array_values($update), [$mand['tournamentID']]));

        // on error
        if($upd === false) return self::response(560);

        // return success
        return self::response(204);
		}

	}
