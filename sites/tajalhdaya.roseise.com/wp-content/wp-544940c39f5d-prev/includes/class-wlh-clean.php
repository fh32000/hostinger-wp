<?php

if (!defined('ABSPATH')) exit;

/**
 * Phase 1 (read-only): find FOREIGN hidden backlinks on the donor — links some
 * other buyer / injection left in stored content. Our own links are rendered at
 * runtime (WLH_Render, from WLH_OPT_LINKS) and NEVER written to post content, so
 * anything hidden + external found in the DB is by definition not ours — that's
 * the safety anchor. This class only READS and reports; it never edits.
 */
class WLH_Clean
{
    /** Cap posts scanned + findings returned, to bound the response. */
    const MAX_POSTS = 400;
    const MAX_FOUND = 400;

    /**
     * @return array{ok:bool,site:string,scanned:int,count:int,found:array}
     */
    public static function scan()
    {
        global $wpdb;
        $site = self::site_host();
        $ours = self::our_hosts();
        $found = array();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post','page')
             ORDER BY (post_type = 'page') DESC, ID DESC LIMIT %d",
            self::MAX_POSTS
        ), ARRAY_A);

        $scanned = 0;
        foreach ((array)$rows as $p) {
            if (count($found) >= self::MAX_FOUND) {
                break;
            }
            $scanned++;
            $id = (int)$p['ID'];
            self::scan_blob($id, 'content', (string)$p['post_content'], $site, $ours, $found);
            $ed = get_post_meta($id, '_elementor_data', true);
            if (is_string($ed) && $ed !== '') {
                // Elementor stores HTML JSON-escaped — unescape for detection only.
                $ed = str_replace(array('\\/', '\\"', '\\n', '\\t'), array('/', '"', ' ', ' '), $ed);
                self::scan_blob($id, 'elementor', $ed, $site, $ours, $found);
            }
        }

        return array(
            'ok'      => true,
            'site'    => $site,
            'scanned' => $scanned,
            'count'   => count($found),
            'found'   => array_values($found),
        );
    }

    /** Extract external anchors from a blob and keep the hidden foreign ones. */
    private static function scan_blob($postId, $where, $html, $site, $ours, &$found)
    {
        if ($html === '' || stripos($html, '<a') === false) {
            return;
        }
        if (!preg_match_all('/<a\b[^>]*?href\s*=\s*["\']?(https?:\/\/[^"\'\s>]+)["\']?[^>]*>(.*?)<\/a>/is',
                $html, $ms, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }
        foreach ($ms as $m) {
            if (count($found) >= self::MAX_FOUND) {
                return;
            }
            $full = $m[0][0];
            $href = html_entity_decode($m[1][0], ENT_QUOTES);
            $host = self::host_of($href);
            if ($host === '' || self::is_internal($host, $site) || in_array($host, $ours, true)) {
                continue;
            }
            // Context = a window before the anchor (its wrapper) + the anchor tag.
            $at = (int)$m[0][1];
            $ctxStart = max(0, $at - 400);
            $context = substr($html, $ctxStart, ($at - $ctxStart) + strlen($full));
            $tech = self::hidden_technique($context);
            if ($tech === '') {
                continue;
            }
            $key = $host . '|' . $where;
            if (isset($found[$key])) {
                continue; // one entry per host+location keeps the report tight
            }
            $anchor = trim(preg_replace('/\s+/', ' ', strip_tags($m[2][0])));
            $found[$key] = array(
                'url'       => mb_substr($href, 0, 500),
                'host'      => $host,
                'anchor'    => mb_substr($anchor, 0, 120),
                'where'     => $where,
                'post_id'   => (int)$postId,
                'technique' => $tech,
                'snippet'   => mb_substr(trim(preg_replace('/\s+/', ' ', $context)), 0, 200),
            );
        }
    }

    /** Which hiding trick (if any) the context uses. '' = looks visible → skip. */
    private static function hidden_technique($ctx)
    {
        $c = strtolower($ctx);
        if (preg_match('/position\s*:\s*(?:absolute|fixed)/', $c)
            && preg_match('/(?:left|top|right|bottom)\s*:\s*-?\s*\d{3,}\s*px/', $c)) {
            return 'offscreen';
        }
        if (preg_match('/text-indent\s*:\s*-\s*\d{3,}/', $c)) return 'text-indent';
        if (preg_match('/display\s*:\s*none/', $c))          return 'display-none';
        if (preg_match('/visibility\s*:\s*hidden/', $c))     return 'visibility-hidden';
        if (preg_match('/opacity\s*:\s*0(?:\.0+)?\b/', $c))  return 'opacity-0';
        if (preg_match('/clip\s*:\s*rect\s*\(\s*0/', $c))    return 'clip';
        if (preg_match('/(?:height|width)\s*:\s*0(?:px)?\b/', $c) && preg_match('/overflow\s*:\s*hidden/', $c)) return 'zero-size';
        if (preg_match('/font-size\s*:\s*0\b/', $c))         return 'font-size-0';
        // Known third-party injection markers (class-hidden via a <style> block).
        if (preg_match('/class\s*=\s*["\'][^"\']*\bmp-mark[-\w]*/', $c)) return 'mp-mark';
        return '';
    }

    private static function host_of($url)
    {
        $h = parse_url((string)$url, PHP_URL_HOST);
        if (!is_string($h) || $h === '') return '';
        $h = strtolower($h);
        return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
    }

    private static function is_internal($host, $site)
    {
        if ($site === '') return false;
        return $host === $site || (strlen($host) > strlen($site) && substr($host, -strlen($site) - 1) === '.' . $site);
    }

    private static function site_host()
    {
        $h = function_exists('home_url') ? parse_url(home_url('/'), PHP_URL_HOST) : '';
        if (!is_string($h) || $h === '') return '';
        $h = strtolower($h);
        return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
    }

    /** Our own target hosts (from the synced link list) — never flag these. */
    private static function our_hosts()
    {
        $out = array();
        foreach ((array)get_option(WLH_OPT_LINKS, array()) as $l) {
            if (is_array($l) && isset($l['url'])) {
                $h = self::host_of((string)$l['url']);
                if ($h !== '') $out[] = $h;
            }
        }
        return array_values(array_unique($out));
    }
}
