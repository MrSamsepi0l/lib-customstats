<?php
/* Last update: 2022-05-10
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\media;

use \heart\core\error as e;
use \heart\core\helper as h;
use \customstats\user\user;
use \customstats\league\source;

class upload {
	use \heart\core\libcom_trait,
		\heart\core\pdo_trait,
		\heart\core\usersession_trait;

	protected static function pdo_config(){

		return [null, [


			]];
		}

	public static function upload_video($req = []){

		// mandatory
		$mand = h::eX($req, [
			'api_key'	=> '~32,32/s',
			'filepath'	=> '~/s',
			'filetype'	=> '~^(?:mp4|mkv|webm)$',
			'filesize'	=> '~1,94371840/i', // 1 - 90 MB
			'type'		=> '~^(?:replay)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'data'		=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'data'		=> [],
			];

		// load user
		$res = user::get_user([
			'hash_key'	=> $mand['api_key'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take user
		$user = $res->data;

		// generate hash with api key and timestamp
		$md5_hash = md5($user->hash_key . h::dtstr('now', 'U'));

		// and define filename
		$username_canonical = $user->username_canonical ?: strtolower(str_replace(' ', '', $user->username));

		// define new filename with unique hash of timestamp and session key
		$filename = $username_canonical.'_'.$md5_hash;


		// on type: replay
		if($mand['type'] == 'replay'){

			// deeper check data
			$param = h::eX($opt['data'], [
				'type'		=> '~/s',
				'gameDate'	=> '~Y-m-d/d',
				], $error, true);

			// on error
			if($error) return self::response(400, $error);

			// check if local or remote
			$server_add = $_SERVER['SERVER_NAME'] == 'localhost' ? '/live_version/live_version' : '';

			// define upload path
			$target_directory = $_SERVER['DOCUMENT_ROOT'].$server_add.'/module/replay/data'.'/'.$filename;

			// define serviceFn
			$serviceFn = 'customstats\media\replay::create_replay';

			// define service param
			$service_param = [
				'name'			=> $filename,
				'url'			=> 'module/replay/data/'.$filename.'.'.$mand['filetype'],
				'directory'		=> $target_directory.'.'.$mand['filetype'],
				'gameDate'		=> $param['gameDate'] ?? null,
				'type'			=> $param['type'] ?? null,
				];
			}

		// upload file
		$upload = move_uploaded_file($mand['filepath'], $service_param['directory']);

		// on error
		if(!$upload) return self::response(503);


		// if service function is not available
		if(!is_callable($serviceFn)) return self::response(501, 'Unavailable serviceFn: '.$serviceFn.' for User: '.$user->username);

		// call service
		$process = call_user_func($serviceFn, $service_param);

		// return response
		return $process;
		}

	public static function upload_image($req = []){

		// mandatory
		$mand = h::eX($req, [
			'api_key'		=> '~32,32/s',
			'type'			=> '~^(?:tournament_game|customgame_source)$',
			'base64_image'	=> '~/s',
			'extension'		=> '~^(?:png|jpeg)$',
			'param'			=> '~/l',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// type order 1: customgame_source
		if($mand['type'] == 'customgame_source'){

			// mandatory customgame param
			$param = h::eX($mand['param'], [
				'gameDate'			=> '~Y-m-d/d',
				], $error);

			// on error
			if($error) return self::response(400, $error);


			// define upload path
			$upload_path = $_SERVER['APP_DIRECTORY'].'/public/img/customgame/source';

			// check if dir exists
			if(!is_dir($upload_path)){

				// log this
				e::logtrigger('Unknown directory while trying to upload customgame image by api_key: '.h::encode_php($mand['api_key']).' and upload_path: '.h::encode_php($upload_path));

				// and return not found
				return self::response(404);
				}

			// create filename (e. g. ldkfjsdlfkjdsfl...._2023-10-21.png)
			$filename = h::random_str(20).'_'.h::dtstr($param['gameDate'], 'Y_m_d').'.'.$mand['extension'];

			// decode image to base64
			$image_decoded = base64_decode($mand['base64_image']);

			// create file in directory (path/filename) with actual image data
			$image_upload = file_put_contents($upload_path.'/'.$filename, $image_decoded);

			// on error
			if($image_upload === false) return self::response(500, 'Error while uploading customgame image');

			// create source image
			$res = source::create_source_url([
				'image_url'		=> $upload_path.'/'.$filename,
				'filename'		=> $filename,
				'gameDate'		=> $param['gameDate'],
				]);

			// on error
			if($res->status != 201) return self::response(570, $res);

			// define result
			$result = (object)[
				'filename'		=> $filename,
				'sourceID'		=> $res->data->sourceID,
				];

			// return success result
			return self::response(201, $result);
			}

		// type order 2: tournament_game TODO: REWORK THIS
		if($mand['type'] == 'tournament_game'){

			// deeper check for param
			$mand_tournament = h::eX($mand['param'], [
				'tournamentID'		=> '~1,4294967295/i',
				'scheduleID'		=> '~1,4294967295/i',
				'gameDate'			=> '~Y-m-d/d',
				], $error);

			// on error
			if($error) return self::response(400, $error);

			// define complete upload path
			$upload_path = $mand['directory'].'/tournament/source';

			// check for dir again
			if(!is_dir($upload_path)){

				// log this
				e::logtrigger('Unknown directory while trying to upload tournament image by api_key: '.h::encode_php($mand['api_key']).' and upload_path: '.h::encode_php($upload_path));

				// and return not found
				return self::response(404);
				}

			// create filename (game date separated by underscore and scheduleID)
			$filename = h::dtstr($mand_tournament['gameDate'], 'Y-m-d').'_'.$mand_tournament['scheduleID'].'.'.$mand['extension'];

			// check if this file already exists
			if(file_exists($upload_path.'/'.$filename)){

				// return conflict
				return self::response(409);
				}

			// decode image
			$image_decoded = base64_decode($mand['base64_image']);

			// try to create image file
			$image_upload = file_put_contents($upload_path.'/'.$filename, $image_decoded);

			// on error
			if($image_upload === false) return self::response(404);

			// return success status and filename
			return self::response(201, (object)['filename' => $filename]);
			}

		// if no type matches
		return self::response(400, 'Expecting type customgame_source|tournament_game');
		}

	}
