<?php
/* Last update: 2024-06-28
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\extension;

use \heart\core\helper as h;
use \heart\core\error as e;

trait filecache_trait {
	use \heart\core\libcom_trait;

	protected static $file_cache_path = null;


	/* Refactored */
	protected static function cache_in_file_refactored($data){

		// mandatory
		$mand = h::eX($data, [

			// path to directory where the file will be saved
			'path'			=> '~/s',

			// name of file that will be saved (without extension)
			// 'filename'		=> '~^[a-z0-9_\/]{1,256}$',
			'filename'		=> '~/s',

			// define file extension
			'extension'		=> '~^(?:json)$',

			// value to be cached,
			'enc_data'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($data, [

			// defines if the given file should be replaced if already existing
			'reset_file'	=> '~/b',

			// completely removes and recreates directory and files if possible
			'reset_full'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default opt
		$opt += [
			'reset_file'	=> false,
			'reset_full'	=> false,
			];

		// if full reset wanted
		if($opt['reset_full']){

			// define default directory file list
			$file_list = is_dir($mand['path']) ? scandir($mand['path']) : [];

			// for each file in directory
			foreach($file_list as $file){

				// skip directories
				if($file == '.' or $file == '..') continue;

				// check if file exists
				if(!file_exists($mand['path'].'/'.$file)) continue;

				// remove file
				$deleted_file = unlink($mand['path'].'/'.$file);

				// on error
				if($deleted_file !== true){

					// log it
					e::logtrigger('Filecache trait - failed to delete existing file: '.$mand['path'].'/'.$file);

					// and skip
					continue;
					}
				}

			// if the directory exists, remove it
			if(is_dir($mand['path'])) $deleted_dir = rmdir($mand['path']);

			// on error
			if($deleted_dir !== true) return self::response(500, 'Filecache trait - failed to delete dir for reset_full: '.$mand['path']);
			}

		// check if given directory is existing
		if(!is_dir($mand['path'])){

			// create directory at given path
			$created_dir = mkdir($mand['path'], 0755);

			// on error
			if($created_dir !== true) return self::response(500, 'Filecache trait - failed to create dir: '.$mand['path']);
			}

		// define complete path + filename
		$complete_file = $mand['path'].'/'.$mand['filename'].'.'.$mand['extension'];

		// if this file already exists and no file reset wanted, interpret as success and return
		if(file_exists($complete_file) and !$opt['reset_file']) return self::response(204);

		// otherwise create file with given content
		$created_file = file_put_contents($complete_file, $mand['enc_data']);

		// on error
		if($created_file === false) return self::response(500, 'Filecache trait - failed to create file: '.$complete_file);

		// define result data
		$result = (object)[
			'dir'		=> $mand['path'],
			'name'		=> $mand['filename'],
			'extension'	=> $mand['extension'],
			'size'		=> $created_file,
			];

		// return success
		return self::response(200, $result);
		}

	protected static function get_cached_file_refactored($data){

		// mandatory
		$mand = h::eX($data, [

			// path to directory
			'path'		=> '~/s',

			// defines if a single file or a list of files from directory should be searched
			'type'		=> '~^(?:directory|file)$',
			], $error);

		// optional
		$opt = h::eX($data, [

			// conditional for type file
			// 'filename'	=> '~^[a-z0-9_\/]{1,256}$',
			'filename'		=> '~/s',

			// filters everything instead of given extension
			'extension'	=> '~^(?:json)$',

			// defines if content should be decoded on after loading
			'decode'	=> '~/b',
			], $error, true);

		// if no error until here, type is file and filename not given, define error
		if(empty($error) and $mand['type'] == 'file' and !isset($opt['filename'])) $error[] = 'type+filename';

		// on error
		if($error) return self::response(400, $error);

		// if directory path doesn't exist, return not found
		if(!is_dir($mand['path'])) return self::response(404);

		// return subfunction result
		return ($mand['type'] == 'directory')
			? self::get_directory_list($mand + $opt)
			: self::get_directory_file($mand + $opt);
		}

	protected static function get_directory_list($data){

		// mandatory
		$mand = h::eX($data, [

			// path to directory
			'path'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($data, [

			// filters everything instead of given extension
			'extension'	=> '~^(?:json)$',

			// defines if content should be decoded on after loading
			'decode'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if directory path doesn't exist, return not found
		if(!is_dir($mand['path'])) return self::response(404);

		// get list of files from directory
		$file_list = scandir($mand['path']);

		// define result list
		$result = [];

		// for each file in list
		foreach($file_list as $file){

			// if directory, skip
			if($file == '.' or $file == '..') continue;

			// if extension filter given and file extension doesn't match extension, skip
			if(isset($opt['extension']) and substr($file, -strlen($opt['extension'])) != $opt['extension']) continue;

			// define complete file
			$complete_file = $mand['path'].'/'.$file;

			// get data from file
			$file_data = file_get_contents($complete_file);

			// if decoding wanted and it's json file, decode it
			if(!empty($opt['decode']) and isset($opt['extension']) == 'json') $file_data = json_decode($file_data);

			// add file to result list on filename index
			$result[$file] = $file_data;
			}

		// return list
		return self::response(200, $result);
		}

	protected static function get_directory_file($data){

		// mandatory
		$mand = h::eX($data, [

			// path to directory
			'path'		=> '~/s',
			'filename'	=> '~/s',

			// filename of cached file
			// 'filename'	=> '~^[a-z0-9_\/]{1,256}$',
			], $error);

		// optional
		$opt = h::eX($data, [

			// defines if content should be decoded on after loading
			'decode'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if directory path doesn't exist, return not found
		if(!is_dir($mand['path'])) return self::response(404);

		// define complete file
		$complete_file = $mand['path'].'/'.$mand['filename'];

		// if not existing
		if(!file_exists($complete_file)) return self::response(404);

		// get data from file
		$file_data = file_get_contents($complete_file);

		// if decoding wanted and it's json file, decode it
		if(!empty($opt['decode'])) $file_data = json_decode($file_data);

		// return filedata
		return self::response(200, $file_data);
		}

	protected static function cleanup_cache(){

		// define cleanup path
		$cleanup_path = $_SERVER['APP_DIRECTORY'].'/cache';

		// if directory doesn't exist, return success
		if(!is_dir($cleanup_path)) return self::response(204);

		// get list of files in cleanup directory
		$filelist = scandir($cleanup_path);

		// for each file in list
		foreach($filelist as $file){

			// if file doesn't exist, skip
			if(!file_exists($cleanup_path.'/'.$file)) continue;

			// if not json file, skip
			if(substr($file, -5) !== '.json') continue;

			// remove file
			unlink($cleanup_path.'/'.$file);
			}

		// return success
		return self::response(204);
		}

	}
