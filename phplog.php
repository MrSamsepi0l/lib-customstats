<?php
/* Last update: 2026-06-16
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats;

use \heart\core\helper as h;
use \heart\core\error as e;

class phplog {
	use \heart\core\libcom_trait;


	public static function get_info($req = []){

		// define server data
		$local_server = (object)[
			'key'			=> 'local',
			'name'			=> 'Lokaler Server',
			'root_logpath'	=> $_SERVER['LOG_PATH'],
			'is_production'	=> $_SERVER['PROJECT_MODE'] == 'prod',
			'domain'		=> $_SERVER['DOMAIN'],
			];

		// return server info
		return self::response(200, $local_server);
		}

	public static function get_logpath($req = []){

		// optional
		$opt = h::eX($req, [
			'pos'		=> '~/i',
			'max'		=> '~/i',
			'logfile'	=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'logfile'		=> '',
			];

		// load info
		$res = self::get_info();

		// on error
		if($res->status != 200) return $res;

		// take info
		$info = $res->data;

		// define result
		$result = (object)[
			];

		// define full path
		$logfile = ($opt['logfile']) ? ($info->root_logpath . '/' . $opt['logfile']) : $info->root_logpath;

		// if logfile does not exist
		if(!file_exists($logfile)){

			// return not found
			return self::response(404);
			}

		// define result
		$result = (object)[
			'logfile'		=> $logfile,
			'lines'			=> 0,
			'unfiltered'	=> 0,
			'pos'			=> 0,
			'filter_request'=> [],
			'filter_option' => [],
			'list'			=> [],
			];

		// for each requested filter
		foreach($opt['filter'] ?? [] as $request_filter){

			// mandatory
			$request_filter = h::eX($request_filter, [
				'type'	=> '~1,100/s',
				'key'	=> '~1,1000/s',
				], $error);

			// on error
			if($error) return self::response(400, $error);

			// add request filter
			$result->filter_request[] = (object) $request_filter;
			}

		// define filter function
		$log_filter = function($log) use ($result){

			// define if log should be filtered
			$filter_log = false;

			// define matched filter options
			$filter_option_matched = [];

			// for each filterable type
			foreach(['logtype','code','part','file'] as $type){ // Removed: ,'line'

				// define filter key
				$key = null;

				// for special line filter, if file and line exists
				if($type == 'line' and !empty($log->file) and !empty($log->line)){

					// define filter key
					$key = $log->file.':'.$log->line;
					}

				// for normal one-key filter, if key exists
				elseif(isset($log->{$type})){

					// define filter key
					$key = $log->{$type};
					}

				// if key is null, filter option does not work for this log
				if($key === null) continue;

				// if filter option is new
				if(!isset($result->filter_option[$type.'|'.$key])){

					// add filter option
					$result->filter_option[$type.'|'.$key] = (object)[
						'type'		=> $type,
						'key'		=> $key,
						'matched'	=> 0,
						'unfiltered'=> 0,
						];
					}

				// define shortcut to filter option
				$filter_option = $result->filter_option[$type.'|'.$key];

				// increment matched entries for filter option
				$filter_option->matched++;

				// save matched filter option
				$filter_option_matched[] = $filter_option;

				// for each requested filter
				foreach($result->filter_request as $request_filter){

					// if request filter does not match filter option, skip it
					if($request_filter->type !== $filter_option->type or $request_filter->key !== $filter_option->key) continue;

					// define log should be filtered
					$filter_log = true;
					}
				}

			// if log is not filtered (from any request filter)
			if(!$filter_log){

				// for each matched filter option
				foreach($filter_option_matched as $filter_option){

					// increment unfiltered count for filter option
					$filter_option->unfiltered++;
					}

				// increment unfiltered count
				$result->unfiltered++;
				}

			// return false if log is filtered, or the log
			return $filter_log ? false : $log;
			};


		// load logfile
		$result->list = explode("\n", file_get_contents($logfile));

		// the last line could always be an empty string (from linebreak)
		if(count($result->list) and trim(end($result->list)) == ''){

			// so remove this line
			array_pop($result->list);
			}

		// define multiline log
		$multiline_log = null;

		// for each entry
		foreach($result->list as $linepos => $line){

			// count line
			$result->lines++;

			// trim line
			$line = trim($line);

			// if line is an empty string
			if($line === ''){

				// convert line to object, extract filter and define if log should be filtered
				$result->list[$linepos] = $log_filter((object)[
					'time'		=> null,
					'logtype'	=> 'emptyline',
					'linepos'	=> $linepos+1,
					]);

				// if log is filtered, unset linepos
				if($result->list[$linepos] === false) unset($result->list[$linepos]);

				// set not as multiline log and skip
				$multiline_log = null;
				continue;
				}

			// if line is JSON
			if($line[0] == '{'){

				// convert to object
				$jsonlog = json_decode($line);

				// on success
				if(is_object($jsonlog)){

					// log type
					$jsonlog->logtype = 'jsonlog';
					}

				// else
				else{

					// rewrite and continue
					$jsonlog = (object)[
						'time'		=> null,
						'desc'		=> $line,
						'logtype'	=> 'invalidjson',
						];
					}

				// extract filter and define if log should be filtered
				$result->list[$linepos] = $log_filter($jsonlog);

				// if log is filtered, unset linepos
				if($result->list[$linepos] === false) unset($result->list[$linepos]);

				// set not as multiline log and skip
				$multiline_log = null;
				continue;
				}

			// if line is something like: [2019-10-22 17:00:43] doctrine.DEBUG: Error message
			if(preg_match('/^\[([0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})\] ([a-zA-Z0-9\_\-]+)\.([a-zA-Z0-9\_\-]+)\: (.*)$/', $line, $match)){

				// convert line to object, extract filter and define if log should be filtered
				$result->list[$linepos] = $log_filter($result->list[$linepos] = (object)[
					'time'		=> h::dtstr($match[1], 'U'),
					'part'		=> $match[2],
					'code'		=> $match[3],
					'desc'		=> trim($match[4]),
					'logtype'	=> 'symfony',
					]);

				// if log is filtered, unset linepos
				if($result->list[$linepos] === false) unset($result->list[$linepos]);

				// set not as multiline log and skip
				$multiline_log = null;
				continue;
				}

			// if line is something like: [14-Oct-2013 13:23:21 Europe/Berlin] PHP Fatal error: ...
			if(preg_match('/^\[([0-9a-zA-Z\-]{10,11} [0-9]{2}:[0-9]{2}:[0-9]{2}) Europe\/Berlin\] PHP ([a-zA-Z ]+)\: (.+) in ([a-zA-Z0-9\/_\-\.]+)(?: on line ([0-9]+)|\:([0-9]+)|.*)$/', $line, $match)){

				// convert line to object, extract filter and define if log should be filtered
				$result->list[$linepos] = $log_filter((object)[
					'time'		=> h::dtstr($match[1], 'U'),
					'code'		=> $match[2],
					'desc'		=> trim($match[3]),
					'file'		=> $match[4],
					'line'		=> $match[5] ?? '',
					'logtype'	=> 'fpmpool',
					]);

				// if log is filtered, unset linepos
				if($result->list[$linepos] === false) unset($result->list[$linepos]);

				// set not as multiline log and skip
				$multiline_log = $result->list[$linepos];
				continue;
				}

			// if line is something like: [2019-10-22 11:47:25] Error message
			if(preg_match('/^\[([0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})\] (.*)$/', $line, $match)){

				// convert line to object, extract filter and define if log should be filtered
				$result->list[$linepos] = $log_filter((object)[
					'time'		=> h::dtstr($match[1], 'U'),
					'desc'		=> trim($match[2]),
					'backtrace'	=> [],
					'logtype'	=> 'plain',
					]);

				// if log is filtered, unset linepos
				if($result->list[$linepos] === false) unset($result->list[$linepos]);

				// set not as multiline log and skip
				$multiline_log = $result->list[$linepos];
				continue;
				}

			// if line seems to be a following line
			if($line[0] !== '[' and $multiline_log !== null){

				// check for backtrace entries
				if(preg_match('/^#[0-9]+ ([a-zA-Z0-9\/_\-\.]+)\(([0-9]+)\)\: (.*)$/', $line, $match)){

					// add backtrace entry
					$multiline_log->backtrace[] = (object)[
						'file'		=> str_replace($_SERVER['ENV_PATH'], '', $match[1]),
						'line'		=> $match[2],
						'function'	=> $match[3],
						];
					}

				// check for backtrace entries
				elseif(preg_match('/^#[0-9]+ ([a-zA-Z0-9\/_\-\.]+) in (.*) on line ([0-9]+)$/', $line, $match)){

					// add backtrace entry
					$multiline_log->backtrace[] = (object)[
						'file'		=> str_replace($_SERVER['ENV_PATH'], '', $match[2]),
						'line'		=> $match[3],
						'function'	=> str_replace($_SERVER['ENV_PATH'], '', $match[1]),
						];
					}

				// check for backtrace entries
				elseif(preg_match('/^#[0-9]+ (\[internal function\])\: (.*)(\:\:|->)(.*)$/', $line, $match)){

					// add backtrace entry
					$multiline_log->backtrace[] = (object)[
						'file'		=> $match[1],
						'function'	=> $match[3],
						'type'		=> $match[2],
						];
					}

				// check for backtrace entries
				elseif(preg_match('/^#[0-9]+ (\{main\})$/', $line, $match)){

					// add backtrace entry
					$multiline_log->backtrace[] = (object)[
						'file'		=> $match[1],
						];
					}

				// check for...
				elseif(preg_match('/^(Stack trace|thrown in).*$/', $line, $match)){

					// TODO: is this skipped or what?!
					}

				// else
				else{

					// add to description
					$multiline_log->desc .= "\n".$line;
					}

				// unset line and skip
				unset($result->list[$linepos]);
				$result->lines--;
				continue;
				}

			// else for something unknown, convert line to object, extract filter and define if log should be filtered
			$result->list[$linepos] = $log_filter((object)[
				'time'		=> null,
				'desc'		=> $line,
				'logtype'	=> 'unknown',
				]);

			// set prev log
			$multiline_log = null;

			// if log is filtered, unset linepos
			if($result->list[$linepos] === false) unset($result->list[$linepos]);
			}

		// reset list and filter_option array
		$result->list = array_values($result->list);
		$result->filter_option = array_values($result->filter_option);

		// define values
		$result->pos = $opt['pos'] ?? 0;
		$result->max = $opt['max'] ?? $result->lines;

		// if max is defined
		if($result->max < $result->lines){

			// extract actual sector
			$result->list = array_slice($result->list, $result->pos, $result->max);
			}

		// return success with result
		return self::response(200, $result);
		}

	public static function get_dir($req = []){

		// optional
		$opt = h::eX($req, [
			'logfile'		=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'logfile'		=> '',
			];

		// load info
		$res = self::get_info();

		// on error
		if($res->status != 200) return $res;

		// take info
		$info = $res->data;

		// define result
		$result = (object)[
			'dir_list'		=> [],
			];

		// define full path
		$dir_path = ($opt['logfile']) ? ($info->root_logpath . '/' . $opt['logfile']) : $info->root_logpath;

		// 1. if directory
		if(is_dir($dir_path)){

			// open it
			if($dir = opendir($dir_path)){

				// read all files/dirs in it
				while(($file = readdir($dir)) !== false){

					// skip
					if($file == '..') continue;

					// full file
					$full_file = $dir_path . '/'. $file;

					// capture file/dir name and complete path to file
					$meta = (object)[
						'name'		=> $file,
						'rel'		=> $opt['logfile'],
						'path'		=> $full_file,
						'is_dir'	=> is_dir($full_file),
						'is_file'	=> is_file($full_file),
						'createTime'=> h::dtstr(filectime($full_file)),
						];

					// add to result list
					$result->dir_list[] = $meta;
					}
				}

			// close directory
			closedir($dir);
			}

		// 2. if file
		elseif(file_exists($dir_path)){

			}

		// return success with result
		return self::response(200, $result);
		}

	}
