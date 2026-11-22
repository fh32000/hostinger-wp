<?php

if (!defined('ABSPATH')) exit;

/**
 * STATIC placement channel (0.7.0): the panel hands this donor the link sets of
 * the STATIC sites sharing its server account (stitch_link_neighbor
 * site_type=static, adopted as VIRTUAL donors managed_by us) as `static_tasks`
 * on the heartbeat answer. There is no WordPress on the target — we reach its
 * index.html directly over the shared filesystem.
 *
 * Per site the file carries one hidden block per link, marker-wrapped:
 *   <!--lh:s{id}--><a href="URL" rel="..." style="HIDDEN">ANCHOR</a><!--lh:e{id}-->
 * Sync is IDEMPOTENT against the panel's desired list: blocks whose ids are no
 * longer listed are removed, listed blocks are (re)written in place, and new
 * ids land as ONE container right after <body> (prepended when the page has no
 * <body>). Writes are atomic (temp file in the same dir + flock + rename) and
 * ANY doubt about the file's structure (unbalanced/stray markers, unreadable,
 * unwritable, oversized) leaves the file UNTOUCHED — a half-rewritten
 * index.html is a defaced site, which is worse than a missing link.
 */
class WLH_Static
{
    /** Pilot ceiling — a static page accumulating links is a footprint. */
    const MAX_LINKS_PER_SITE = 30;
    /** Sanity ceiling on index.html size; a bigger "index" is not what we think. */
    const MAX_FILE_BYTES = 1048576;

    /**
     * Apply the panel's desired link sets. NEVER throws (a static task must not
     * fatal the donor's heartbeat); per-host outcome is returned for the next
     * heartbeat's `static_results`:
     *   array<int, array{host:string, placed_ids:int[], error:string}>
     * placed_ids = the ids whose blocks are in the file after the sync (empty
     * when the sync failed — the panel then leaves that host's links alone).
     *
     * @param array<int,array> $tasks [{host, path, links:[{id,anchor,url,rel}]}]
     */
    public static function apply_tasks($tasks)
    {
        $results = array();
        if (!is_array($tasks)) {
            return $results;
        }
        foreach ($tasks as $t) {
            if (!is_array($t)) {
                continue;
            }
            $host = trim((string)(isset($t['host']) ? $t['host'] : ''));
            $path = rtrim(trim((string)(isset($t['path']) ? $t['path'] : '')), '/');
            $links = isset($t['links']) && is_array($t['links']) ? $t['links'] : array();
            if ($host === '') {
                continue;
            }
            $res = self::apply_one($host, $path, $links);
            // ONE retry on failure (a transient fs hiccup — NFS stall, quota
            // flush — must not cost the link a whole heartbeat round); then the
            // error stands and is reported.
            if ($res['error'] !== '') {
                $res = self::apply_one($host, $path, $links);
            }
            $results[] = $res;
        }
        return $results;
    }

    /**
     * Sync one static site's index.html to its desired link set.
     * @return array{host:string, placed_ids:int[], error:string}
     */
    private static function apply_one($host, $path, $links)
    {
        $out = array('host' => $host, 'placed_ids' => array(), 'error' => '');
        try {
            if ($path === '' || strpos($path, '/') !== 0 || strpos($path, "\0") !== false) {
                $out['error'] = 'bad_path';
                return $out;
            }
            // Every fs probe is @-guarded: on an open_basedir-restricted host a
            // bare is_dir()/is_file() outside the allowed tree raises a warning —
            // suppressed, the site simply reports unreachable instead of
            // polluting the donor's error log on every heartbeat.
            if (!@is_dir($path)) {
                $out['error'] = 'path_missing';
                return $out;
            }
            $file = $path . '/index.html'; // pilot: root index.html only
            if (!@is_file($file) || !@is_readable($file)) {
                $out['error'] = 'index_unreadable';
                return $out;
            }
            // The atomic replace needs a writable DIRECTORY (the rename target),
            // not a writable file — check the dir itself.
            if (!@is_writable($path)) {
                $out['error'] = 'dir_unwritable';
                return $out;
            }
            $html = @file_get_contents($file);
            if (!is_string($html) || $html === '') {
                $out['error'] = 'read_failed';
                return $out;
            }
            if (strlen($html) > self::MAX_FILE_BYTES) {
                $out['error'] = 'too_big';
                return $out;
            }
            $desired = self::desired_blocks($host, $links);
            $sync = self::sync_html($html, $desired, $host);
            if ($sync === null) {
                // Marker structure untrustworthy — hands completely off.
                $out['error'] = 'structure';
                return $out;
            }
            $out['placed_ids'] = array_map('intval', array_keys($desired));
            if ($sync === $html) {
                return $out; // already in the desired state — no write, no mtime bump
            }
            $err = self::atomic_write($file, $sync);
            if ($err !== '') {
                // Unknown on-disk state (the old content should have survived the
                // failed rename, but claim nothing): empty placed_ids + error.
                $out['error'] = $err;
                $out['placed_ids'] = array();
            }
        } catch (\Throwable $e) {
            $out['error'] = 'ex: ' . substr($e->getMessage(), 0, 100);
            $out['placed_ids'] = array();
        } catch (\Exception $e) { // PHP 5.x has no Throwable — belt and braces
            $out['error'] = 'ex: ' . substr($e->getMessage(), 0, 100);
            $out['placed_ids'] = array();
        }
        return $out;
    }

    /**
     * id => fully marker-wrapped block for every valid link (capped). The hide
     * technique comes from the renderer's own per-link deterministic pool with
     * the static's host as the site seed — stable per link, mixed per page, and
     * indistinguishable from what a WP donor renders.
     * @return array<int,string>
     */
    private static function desired_blocks($host, $links)
    {
        $out = array();
        foreach (array_slice($links, 0, self::MAX_LINKS_PER_SITE) as $l) {
            if (!is_array($l)) {
                continue;
            }
            $id = (int)(isset($l['id']) ? $l['id'] : 0);
            $url = esc_url((string)(isset($l['url']) ? $l['url'] : ''));
            $anchor = esc_html((string)(isset($l['anchor']) ? $l['anchor'] : ''));
            if ($id <= 0 || $url === '' || $anchor === '' || isset($out[$id])) {
                continue;
            }
            $rel = '';
            if (isset($l['rel']) && (string)$l['rel'] !== '') {
                $rel = ' rel="' . esc_attr((string)$l['rel']) . '"';
            }
            $out[$id] = '<!--lh:s' . $id . '-->'
                . WLH_Render::hidden_anchor($url, $anchor, $rel, $host)
                . '<!--lh:e' . $id . '-->';
        }
        return $out;
    }

    /**
     * Bring $html's <!--lh--> blocks to the desired set. Returns the new html,
     * or NULL when the marker structure is untrustworthy (an opening without
     * its closing or vice versa — hand-edited or third-party-injected file);
     * the caller then leaves the file alone entirely.
     * @param array<int,string> $desired
     * @return string|null
     */
    private static function sync_html($html, $desired, $host)
    {
        // Pair s{id}...e{id} blocks; the backreference forbids cross-pairs.
        $matched = preg_match_all('/<!--lh:s(\d+)-->.*?<!--lh:e\1-->/s', $html, $m, PREG_SET_ORDER);
        if ($matched === false) {
            return null;
        }
        if (substr_count($html, '<!--lh:s') !== $matched
            || substr_count($html, '<!--lh:e') !== $matched) {
            return null; // stray/orphan marker
        }
        // Replace-or-drop every existing block IN PLACE: a surviving block keeps
        // its position (stable page, minimal diff), a duplicated id collapses to
        // its first occurrence, an unlisted id is removed (that is the static
        // removal signal — the panel simply stops listing the link).
        $seen = array();
        $new = preg_replace_callback(
            '/<!--lh:s(\d+)-->.*?<!--lh:e\1-->/s',
            function ($mm) use ($desired, &$seen) {
                $id = (int)$mm[1];
                if (isset($seen[$id])) {
                    return '';
                }
                $seen[$id] = true;
                return isset($desired[$id]) ? $desired[$id] : '';
            },
            $html
        );
        if ($new === null) {
            return null;
        }
        // Blocks the file does not have yet arrive as ONE container — a single
        // insertion point keeps re-syncs trivially idempotent.
        $fresh = array();
        foreach ($desired as $id => $block) {
            if (!isset($seen[$id])) {
                $fresh[] = $block;
            }
        }
        if ($fresh) {
            $container = self::container($host, $fresh);
            if (preg_match('/<body[^>]*>/i', $new, $bm, PREG_OFFSET_CAPTURE)) {
                $at = $bm[0][1] + strlen($bm[0][0]);
                $new = substr($new, 0, $at) . "\n" . $container . substr($new, $at);
            } else {
                // Frameset/fragment without <body>: prepend. The markers own the
                // blocks, the position is cosmetic.
                $new = $container . "\n" . $new;
            }
        }
        return $new;
    }

    /**
     * The wrapper for freshly-added blocks — the same plausible-builder class
     * style as the WP renderer's block, seeded by the static's host.
     * @param array<int,string> $blocks
     */
    private static function container($host, $blocks)
    {
        $pool = array(
            'elementor-widget-container', 'wp-block-group__inner-container', 'wpb_wrapper',
            'et_pb_text_inner', 'vc_column-inner', 'fusion-text', 'entry-content-inner',
            'widget-inner', 'tdb-block-inner', 'kt-inside-inner-col',
        );
        $seed = crc32($host);
        if ($seed % 6 === 0) {
            return '<div>' . implode('', $blocks) . '</div>';
        }
        return '<div class="' . $pool[$seed % count($pool)] . '">' . implode('', $blocks) . '</div>';
    }

    /**
     * temp-in-same-dir + flock + rename: a concurrent reader (the web server
     * serving this very index.html) sees the whole old file or the whole new
     * one, never a half-written mix. Same-dir temp is what makes the rename
     * atomic. The original file's permissions are carried over (tempnam forces
     * 0600, which some web servers refuse to serve).
     * @return string '' on success, error code otherwise
     */
    private static function atomic_write($file, $content)
    {
        $tmp = @tempnam(dirname($file), '.lh');
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
        $perms = @fileperms($file);
        if ($perms !== false) {
            @chmod($tmp, $perms & 0777);
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return 'rename';
        }
        return '';
    }
}
