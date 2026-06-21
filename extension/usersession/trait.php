<?php
/* Last update: 2023-02-04
	Jeremiah Meyer - mobile TREND GmbH
	*/
namespace customstats\extension;

use \heart\core\helper as h;
use \heart\core\error as e;

trait usersession_trait {

	protected function us_init($reinit = false){

		// define session
		static $session;
		if($session !== null) return $session;

		// get session
		$session = (!$reinit and $this->us_get()) ? $this->us_get() : null;

		// if no session available
		if(!$session){

			// create new one
			$this->us_create();
			}
		}

	protected function us_set($key, $val){

		// if no session, return false
		if(!$this->us_get()) return false;

		// set param in session
		$_SESSION[$this->project.':'.$key] = $val;
		}

	protected function us_get($key = null){

		// if no session available
		if(empty($_SESSION)) return null;

		// convert to object if array
		$session = is_array($_SESSION) ? (object) $_SESSION : $_SESSION;

		// define complete session key
		if($key) $key = $this->project.':'.$key;

		// get session key
		return ($key) ? $session->{$key} ?? null : $session;
		}

	protected function us_create(){

		// define createTime
		$_SESSION[$this->project.':createTime'] = h::dtstr('now');
		}

	protected function us_destroy(){

		// destory and unset
		session_destroy();
		session_unset();

		// complete unset
		$_SESSION = [];

		// return true
		return true;
		}

	protected function us_expired($lifetime = 28800, $session_key = null){

		// check if there even is a session and a createTime
		if(!$this->us_get($session_key) or !$this->us_get('createTime')) return true;

		// get session createTime
		$timestamp_now = h::dtstr('now', 'U');
		$session_created = h::dtstr($this->us_get('createTime'), 'U');

		// if time elapsed between now and create Time reached lifetime
		if($timestamp_now - $session_created >= $lifetime){

			// destroy session
			$this->us_destroy();

			// session is expired
			return true;
			}

		// session is not expired
		return false;
		}

	protected function us_lifetime($time = null){

		// get session createTime
		$createTime = $this->us_get('createTime');
		$createTimeUnix = h::dtstr($createTime, 'U');

		// if no time given, take actual time
		if(!$time) $time = h::dtstr('now', 'U');

		// define lifetime
		$lifetime = (object)[
			'seconds'	=> 0,
			'minutes'	=> 0,
			'hours'		=> 0,
			];

		// define seconds
		$lifetime->seconds = $time - $createTimeUnix;

		// define hours
		$lifetime->hours = round($lifetime->seconds / 3600, 2);

		// define minutes
		$lifetime->minutes = explode('.', $lifetime->hours)[1] ?? 0;

		// convert hours to int
		$lifetime->hours = (int) $lifetime->hours;

		// convert minutes if given or take 0
		$lifetime->minutes = ($lifetime->minutes) ? (int) ($lifetime->minutes * 60 / 100) : 0;

		// return lifetime
		return $lifetime;
		}

	}
