<?php

if (!defined('ABSPATH')) exit;

/**
 * Report-only NEIGHBOR discovery: enumerate OTHER sites that live on the same
 * server/account as this one — WordPress installs (site_type 'wp') and, since
 * 0.5.3, plain static HTML sites (site_type 'static', index.html/index.htm
 * with no wp-load.php anywhere) — so the panel can surface sibling sites
 * the operator has not added yet. This only READS the local filesystem — dirs the
 * current OS user can already see — and reports host / siteurl / WP version. It
 * NEVER writes, NEVER installs, and NEVER returns DB credentials over the wire.
 *
 * Naturally self-limiting: PHP can only read a sibling's wp-config.php when it is
 * owned by the same OS user (i.e. the operator's own account). On a properly
 * isolated shared host a neighbour's files are unreadable, so nothing leaks and
 * the op returns an empty list. Mirrors WLH_Clean::scan()'s shape/caps.
 */
class WLH_Neighbors
{
    const MAX_FOUND  = 200;   // distinct siblings returned (wp + static together)
    const MAX_STATIC = 100;   // of those, at most this many static (no-WP) sites
    const MAX_DIRS   = 4000;  // hard cap on directory iterations
    const MAX_PROBES = 200;   // hard cap on wp-config reads / DB connects
    const DB_TIMEOUT = 3;     // seconds per sibling DB connect

    /** A WP install can sit in the account dir directly or under a webroot subdir. */
    private static function webroots()
    {
        return array('', 'public_html', 'htdocs', 'www', 'httpdocs', 'web', 'wordpress', 'wp');
    }

    /**
     * @return array{ok:bool,host:string,abspath:string,roots:array,count:int,found:array}
     */
    public static function scan()
    {
        $selfHost = self::site_host();
        $selfReal = self::realpath_of(defined('ABSPATH') ? ABSPATH : '');
        $roots    = self::candidate_roots();
        $found    = array();
        $dirs     = 0;
        $probes   = 0;
        // shuffle=1 (panel rechecks): randomized dir order makes every scan of a
        // CAPPED mega-server (>MAX_FOUND sites) return a different sample, so
        // repeated rechecks accumulate the full sibling set instead of the same
        // alphabetical first-200 forever. Deterministic order by default keeps
        // single scans reproducible.
        $shuffle = !empty($_GET['shuffle']);
        // Dirs without wp-load.php are remembered here and probed for a static
        // site only AFTER the WP pass — static findings must never crowd WP
        // installs out of MAX_FOUND (a WP sibling is actionable, a static one
        // is mostly informational).
        $maybeStatic = array();

        foreach ($roots as $root) {
            $entries = @scandir($root);
            if (!is_array($entries)) {
                continue;
            }
            if ($shuffle) {
                shuffle($entries);
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (count($found) >= self::MAX_FOUND || $dirs >= self::MAX_DIRS || $probes >= self::MAX_PROBES) {
                    break 2;
                }
                $dirs++;
                $base = $root . '/' . $entry;
                if (!@is_dir($base)) {
                    continue;
                }
                $wp = self::wp_dir_under($base);
                if ($wp === '') {
                    $maybeStatic[] = $base;
                    continue;
                }
                if ($selfReal !== '' && self::realpath_of($wp) === $selfReal) {
                    continue; // ourselves
                }
                $probes++;
                $info = self::read_install($wp);
                if ($info === null) {
                    continue;
                }
                $host = $info['host'];
                if ($host !== '' && $host === $selfHost) {
                    continue;
                }
                $key = $host !== '' ? $host : $wp;
                if (isset($found[$key])) {
                    continue;
                }
                $found[$key] = $info;
            }
        }

        // Second pass: static sites (index.html/index.htm, no WP). Same MAX_FOUND
        // pool, but capped separately so a farm of static dirs can't fill it.
        $static = 0;
        foreach ($maybeStatic as $base) {
            if (count($found) >= self::MAX_FOUND || $static >= self::MAX_STATIC) {
                break;
            }
            $dir = self::static_dir_under($base);
            if ($dir === '') {
                continue;
            }
            if ($selfReal !== '' && self::realpath_of($dir) === $selfReal) {
                continue; // ourselves (paranoia: our own dir without wp-load.php)
            }
            $info = self::read_static($dir);
            if ($info === null) {
                continue;
            }
            $host = $info['host'];
            if ($host !== '' && $host === $selfHost) {
                continue;
            }
            $key = $host !== '' ? 'static:' . $host : $dir;
            if (isset($found[$key])) {
                continue;
            }
            $found[$key] = $info;
            $static++;
        }

        return array(
            'ok'      => true,
            'host'    => $selfHost,
            'abspath' => defined('ABSPATH') ? ABSPATH : '',
            'roots'   => array_values($roots),
            'count'   => count($found),
            'found'   => array_values($found),
        );
    }

    /** Plausible account/webroot parents to scan for sibling installs. */
    private static function candidate_roots()
    {
        $seeds = array();
        $abs = defined('ABSPATH') ? rtrim(str_replace('\\', '/', ABSPATH), '/') : '';
        $doc = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/') : '';
        foreach (array($abs, $doc) as $p) {
            if ($p === '') {
                continue;
            }
            // Walk up 1..3 levels: /home/u/domains/site/public_html -> .../domains, /home/u
            $cur = $p;
            for ($i = 0; $i < 3; $i++) {
                $cur = dirname($cur);
                if ($cur === '' || $cur === '.' || $cur === '/' || strlen($cur) < 4) {
                    break;
                }
                $seeds[$cur] = true;
            }
        }
        foreach (array('/var/www', '/srv/www', '/home') as $fixed) {
            if (@is_dir($fixed)) {
                $seeds[$fixed] = true;
            }
        }
        $out = array();
        foreach (array_keys($seeds) as $d) {
            if (@is_dir($d) && @is_readable($d)) {
                $out[] = $d;
            }
        }
        return $out;
    }

    /** Dir that holds wp-load.php for $base (base itself or a webroot subdir); '' if none. */
    private static function wp_dir_under($base)
    {
        foreach (self::webroots() as $sub) {
            $dir = $sub === '' ? $base : $base . '/' . $sub;
            if (@is_file($dir . '/wp-load.php')) {
                return $dir;
            }
        }
        return '';
    }

    /** Dir that holds a static index (index.html/index.htm) for $base; '' if none. */
    private static function static_dir_under($base)
    {
        foreach (self::webroots() as $sub) {
            $dir = $sub === '' ? $base : $base . '/' . $sub;
            if (@is_file($dir . '/index.html') || @is_file($dir . '/index.htm')) {
                return $dir;
            }
        }
        return '';
    }

    /**
     * Describe a STATIC sibling site (plain HTML, no WP). host is guessed from
     * the dir name; siteurl only if the index file volunteers a canonical/og:url.
     * Read-only, reads at most one file of at most 64KB.
     * @return array{site_type:string,host:string,siteurl:string,path:string,wp_version:string,source:string,writable:bool,same_uid:bool}|null
     */
    private static function read_static($dir)
    {
        $host    = self::domain_from_path($dir);
        $siteurl = self::siteurl_from_html($dir);
        if ($siteurl !== '') {
            $h = self::host_of($siteurl);
            if ($h !== '') {
                $host = $h; // canonical/og:url is a better source than the dir name
            }
        }
        if ($host === '') {
            return null; // nothing useful to report
        }
        return array(
            'site_type'  => 'static',
            'host'       => $host,
            'siteurl'    => $siteurl,
            'path'       => $dir,
            'wp_version' => '',
            'source'     => 'path',
            // For a static site there is no plugins/ dir to adopt into, so
            // writable = we could drop files next to its index.html.
            'writable'   => (bool) @is_writable($dir),
            'same_uid'   => self::same_owner_static($dir),
        );
    }

    /**
     * Cheap siteurl extraction from a static index file: <link rel="canonical">
     * or <meta property="og:url">. Reads the first matching index file only,
     * capped at 64KB; '' when absent or unreadable.
     */
    private static function siteurl_from_html($dir)
    {
        $f = @is_file($dir . '/index.html') ? $dir . '/index.html' : $dir . '/index.htm';
        if (!@is_file($f)) {
            return '';
        }
        $body = @file_get_contents($f, false, null, 0, 65536);
        if (!is_string($body) || $body === '') {
            return '';
        }
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $body, $m)
            || preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']/i', $body, $m)
            || preg_match('/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:url["\']/i', $body, $m)) {
            $url = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            if (preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }
        return '';
    }

    /** index.html owned by the same uid as our own wp-config.php; false on any doubt. */
    private static function same_owner_static($dir)
    {
        if (!function_exists('fileowner')) {
            return false;
        }
        $theirs = @is_file($dir . '/index.html') ? $dir . '/index.html' : $dir . '/index.htm';
        $abs = defined('ABSPATH') ? rtrim(str_replace('\\', '/', ABSPATH), '/') : '';
        if ($abs === '') {
            return false;
        }
        $ours = @is_file($abs . '/wp-config.php') ? $abs . '/wp-config.php' : dirname($abs) . '/wp-config.php';
        if (!@is_file($theirs) || !@is_file($ours)) {
            return false;
        }
        $a = @fileowner($theirs);
        $b = @fileowner($ours);
        return $a !== false && $b !== false && $a === $b;
    }

    /**
     * Read domain + WP version from a sibling install WITHOUT returning any secret.
     * @return array{host:string,siteurl:string,path:string,wp_version:string,source:string}|null
     */
    private static function read_install($wpDir)
    {
        $ver     = self::wp_version($wpDir);
        $cfg     = self::read_config($wpDir);
        $siteurl = '';
        $source  = 'none';
        if ($cfg !== null) {
            $siteurl = self::siteurl_from_db($cfg);
            if ($siteurl !== '') {
                $source = 'db';
            }
        }
        if ($siteurl === '') {
            $guess = self::domain_from_path($wpDir);
            if ($guess !== '') {
                $siteurl = 'http://' . $guess;
                $source  = 'path';
            }
        }
        $host = self::host_of($siteurl);
        if ($host === '' && $ver === '') {
            return null; // nothing useful to report
        }
        return array(
            'site_type'  => 'wp',
            'host'       => $host,
            'siteurl'    => $siteurl,
            'path'       => $wpDir,
            'wp_version' => $ver,
            'source'     => $source,
            // 0.5.1: adoption hints for the panel — can WE write the sibling's
            // plugins dir, and do both installs share one OS user. Read-only
            // facts; the actual adopt still requires a signed ?wlh_adopt.
            'writable'   => self::install_writable($wpDir),
            'same_uid'   => self::same_owner($wpDir),
        );
    }

    /** plugins/ writable, or wp-content writable so plugins/ could be created. */
    private static function install_writable($wpDir)
    {
        $plugins = $wpDir . '/wp-content/plugins';
        if (@is_dir($plugins)) {
            return (bool) @is_writable($plugins);
        }
        return (bool) @is_writable($wpDir . '/wp-content');
    }

    /** Both wp-config.php files owned by the same uid; false on any doubt. */
    private static function same_owner($wpDir)
    {
        if (!function_exists('fileowner')) {
            return false;
        }
        $theirs = @is_file($wpDir . '/wp-config.php') ? $wpDir . '/wp-config.php' : dirname($wpDir) . '/wp-config.php';
        $abs = defined('ABSPATH') ? rtrim(str_replace('\\', '/', ABSPATH), '/') : '';
        if ($abs === '') {
            return false;
        }
        $ours = @is_file($abs . '/wp-config.php') ? $abs . '/wp-config.php' : dirname($abs) . '/wp-config.php';
        if (!@is_file($theirs) || !@is_file($ours)) {
            return false;
        }
        $a = @fileowner($theirs);
        $b = @fileowner($ours);
        return $a !== false && $b !== false && $a === $b;
    }

    private static function wp_version($wpDir)
    {
        $f = $wpDir . '/wp-includes/version.php';
        if (!@is_file($f)) {
            return '';
        }
        $body = @file_get_contents($f, false, null, 0, 4096);
        if (!is_string($body)) {
            return '';
        }
        if (preg_match('/\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $body, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Parse DB constants + table_prefix from a sibling wp-config.php (dir or one
     * level up). PUBLIC since 0.5.1: WLH_Adopt reuses it for the signed adopt
     * flow. The result must still NEVER be returned over the wire.
     */
    public static function read_config($wpDir)
    {
        $f = @is_file($wpDir . '/wp-config.php') ? $wpDir . '/wp-config.php' : dirname($wpDir) . '/wp-config.php';
        if (!@is_file($f)) {
            return null;
        }
        $src = @file_get_contents($f);
        if (!is_string($src) || $src === '') {
            return null;
        }
        $c   = array('name' => '', 'user' => '', 'pass' => '', 'host' => 'localhost', 'prefix' => 'wp_');
        $map = array('DB_NAME' => 'name', 'DB_USER' => 'user', 'DB_PASSWORD' => 'pass', 'DB_HOST' => 'host');
        foreach ($map as $const => $k) {
            if (preg_match('/define\(\s*[\'"]' . $const . '[\'"]\s*,\s*[\'"]((?:[^\'"\\\\]|\\\\.)*)[\'"]\s*\)/', $src, $m)) {
                $c[$k] = stripcslashes($m[1]);
            }
        }
        if (preg_match('/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $src, $m)) {
            $c['prefix'] = $m[1];
        }
        if ($c['name'] === '' || $c['user'] === '') {
            return null;
        }
        return $c;
    }

    /** Connect to the sibling DB, read the `siteurl` option, close. Never returns creds. Public since 0.5.1 (WLH_Adopt). */
    public static function siteurl_from_db($cfg)
    {
        if (!function_exists('mysqli_init')) {
            return '';
        }
        $host = $cfg['host'];
        $port = 3306;
        $socket = null;
        if (strpos($host, ':') !== false) {
            list($h, $p) = explode(':', $host, 2);
            $host = $h;
            if (ctype_digit($p)) {
                $port = (int) $p;
            } else {
                $socket = $p;
            }
        }
        $mysqli = @mysqli_init();
        if (!$mysqli) {
            return '';
        }
        @mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, self::DB_TIMEOUT);
        $ok = @mysqli_real_connect($mysqli, $host, $cfg['user'], $cfg['pass'], $cfg['name'], $port, $socket);
        if (!$ok) {
            @mysqli_close($mysqli);
            return '';
        }
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $cfg['prefix']) . 'options';
        $url = '';
        $res = @mysqli_query($mysqli, "SELECT option_value FROM `{$table}` WHERE option_name='siteurl' LIMIT 1");
        if ($res && ($row = @mysqli_fetch_row($res))) {
            $url = (string) $row[0];
        }
        if ($res instanceof mysqli_result) {
            @mysqli_free_result($res);
        }
        @mysqli_close($mysqli);
        return $url;
    }

    /** Last path segment that looks like a domain (skips webroot dir names). */
    private static function domain_from_path($wpDir)
    {
        $skip  = array('public_html', 'htdocs', 'www', 'httpdocs', 'web', 'wordpress', 'wp');
        $parts = explode('/', trim(str_replace('\\', '/', $wpDir), '/'));
        foreach (array_reverse($parts) as $seg) {
            $seg = strtolower($seg);
            if (in_array($seg, $skip, true)) {
                continue;
            }
            if (strpos($seg, '.') !== false
                && preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?(?:\.[a-z0-9\-]+)+$/', $seg)) {
                return strpos($seg, 'www.') === 0 ? substr($seg, 4) : $seg;
            }
        }
        return '';
    }

    private static function realpath_of($p)
    {
        if ($p === '') {
            return '';
        }
        $r = @realpath($p);
        return is_string($r) ? rtrim(str_replace('\\', '/', $r), '/') : '';
    }

    private static function host_of($url)
    {
        $h = parse_url((string) $url, PHP_URL_HOST);
        if (!is_string($h) || $h === '') {
            return '';
        }
        $h = strtolower($h);
        return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
    }

    private static function site_host()
    {
        $h = function_exists('home_url') ? parse_url(home_url('/'), PHP_URL_HOST) : '';
        if (!is_string($h) || $h === '') {
            return '';
        }
        $h = strtolower($h);
        return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
    }
}
