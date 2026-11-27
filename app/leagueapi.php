<?php
/* Last update: 2026-11-27
***** Jeremiah Meyer *****
*****/
namespace customstats\app;

use \heart\core\error as e;
use \heart\core\helper as h;

use \customstats\base\config as base_config;
use \customstats\base\session as base_session;
use \customstats\base\leagueapi as base_leagueapi;

class leagueapi {
    use \heart\core\libcom_trait;

    protected static $right_translation = [
        0   => 'registered',
        1   => 'admin',
        2   => 'confirmed',
        ];

    public static function process_leagueapi_method($req = []){

        // alternative
        $alt = h::eX($req, [
            'fn'        => '~^[a-z0-9_\.]{1,64}$',
            ], $error, true);

        // optional
        $opt = h::eX($req, [
            'param'        => '~/l',
            ], $error, true);

        // on error
        if($error) return self::response(400, $error);

        // return result of leagueapi method
        return self::process_leagueapi_fn($alt + $opt);
        }

    public static function process_leagueapi_fn($req = []){

        // mandatory
        $mand = h::eX($req, [
            'fn'        => '~^[a-z0-9_\.]{1,64}$',
            ], $error);

        // optional
        $opt = h::eX($req, [
            'param'     => '~/l',
            ], $error, true);

        // on error
        if($error) return self::response(400, $error);

        // load leagueapi fn
        $res = base_leagueapi::get_leagueapi([
            'name'      => $mand['fn'],
            ]);

        // on error
        if(!in_array($res->status, [200, 404])) return self::response(570, $res);

        // if not found or found and disabled, return not implemented
        if($res->status == 404 or ($res->status == 200 and !$res->data->enabled)) return self::response(501, 'Configuration of '.$mand['fn'].' not implemented');

        // take leagueapi
        $leagueapi = $res->data;


        // take param
        $param = $opt['param'] ?? [];

        // if project given in param
        if(h::cX($param, 'project', '~^[a-z0-9_]{1,32}$')){

            // load config
            $res = base_config::get_config([
                'project'   => h::gX($param, 'project'),
                ]);

            // if not found, return precondition failed
            if($res->status == 404) return self::response(412);

            // on unexpected error
            if($res->status != 200) return self::response(570, $res);

            // take config
            $config = $res->data;

            // check if leagueapi blacklist defined in config
            if(h::cX($config, 'param:base_config:leagueapi:blacklist', '~!empty/a')){

                // take blacklist from config
                $leagueapi_blacklist = h::gX($config, 'param:base_config:leagueapi:blacklist');
                }

            // if admin list is defined in project config, overwrite predifined default admin list
            if(h::cX($config, 'param:base_config:leagueapi:admin_list', '~/a')) $admin_list = h::gX($config, 'param:base_config:leagueapi:admin_list');
            }


        // run security check for blacklist and admin list
        $res = self::run_security_check([

            // user param (optional)
            'project'       => h::gX($param, 'project'),
            'api_key'       => h::gX($param, 'api_key'),

            // config param
            'blacklist'     => $leagueapi_blacklist ?? [],
            'admin_list'    => $admin_list ?? [],

            // leagueapi param
            'api_name'      => $leagueapi->name,
            'api_right'     => self::$right_translation[$leagueapi->right],
            ]);

        // if security check failed in any way
        if($res->status != 204) return self::response(403);


        // if function is not callable
        if(!is_callable($leagueapi->fn)) return self::response(500, 'Configured namespace of '.$mand['fn'].' is not callable');

        // run associated leagueapi function
        $res = call_user_func_array($leagueapi->fn, [$param]);

        // return result
        return $res;
        }

    protected static function run_security_check($req = []){

        // mandatory
        $mand = h::eX($req, [
            'api_name'      => '~^[a-z0-9_\.]{1,64}$',
            'api_right'     => '~1,255/s',
            'blacklist'     => '~/a',
            'admin_list'    => '~/a',
            ], $error);

        // optional
        $opt = h::eX($req, [
            'project'       => '~^[a-z0-9_]{1,32}$',
            'api_key'       => '~1,32/s',
            ], $error, true);

        // on error
        if($error) return self::response(400, $error);

        // take api key and project
        $api_key = $opt['api_key'] ?? null;
        $project = $opt['project'] ?? null;

        // if no api_key given, try to load from session
        if(!$api_key) $api_key = $_SESSION[$project ? $project.':api_key' : 'customstats:api_key'] ?? null;

        // if still no api key, return not found
        if(!$api_key) return self::response(404);


        // security check 1: blacklist (prio over admin -> this allows admin blacklisting!)
        if(in_array($api_key, $mand['blacklist'])){

            // return and log server error
            return self::response(500, 'Leagueapi blacklisted user tries to run leagueapi with api_key: '.$api_key.'; calling: '.$mand['api_name']);
            }

        // security check 2: admin if leagueapi needs one
        if($mand['api_right'] == 'admin' and !in_array($api_key, $mand['admin_list'])){

            // return and log server error
            return self::response(500, 'Leagueapi admin security check triggered for api_key: '.$api_key.'; calling: '.$mand['api_name']);
            }

        // return success, no response
        return self::response(204);
        }

    }
