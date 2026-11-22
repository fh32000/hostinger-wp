<?php

if (!defined('ABSPATH')) exit;

/**
 * 0.10.0: verified-Googlebot hit counter. template_redirect fires exactly once
 * per front-end request (never on admin-ajax/wp-admin), so the hook sees every
 * crawl hit that actually reaches PHP. Bot identity is NOT the UA string (it
 * is trivially spoofed) — the UA regex only gates the cheap path, and the
 * verdict comes from rDNS: the PTR must end in .googlebot.com/.google.com AND
 * forward-confirm back to the client IP (same scheme as WCH_Render's cloak
 * gate). Verdicts are transient-cached 12h per IP, so repeat crawls cost no
 * DNS. Only 'verified' hits count — spoofers and DNS failures never inflate
 * the stats the panel pulls via ?wlh_botstats.
 *
 * Counters live in the wlh_bot_hits option as UTC day => hits, capped to the
 * last KEEP_DAYS days. Server-side page caches (LiteSpeed etc.) can serve a
 * page without ever running PHP — on such donors the numbers are a LOWER
 * bound, not an absolute.
 */
class WLH_Botstat
{
    const OPT_HITS  = 'wlh_bot_hits';
    const KEEP_DAYS = 35;

    public static function register_hooks()
    {
        add_action('template_redirect', array('WLH_Botstat', 'maybe_count'), 0);
    }

    public static function maybe_count()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        // Дешёвый пре-фильтр: только UA google-краулеров — кандидаты, браузеры
        // не платят за DNS. Спуфер сюда пройдёт, но отсекается rDNS ниже.
        if ($ua === '' || !preg_match('/googlebot|google-inspectiontool|googleother|mediapartners|adsbot|feedfetcher/i', $ua)) {
            return;
        }
        if (self::rdns_verdict(self::client_ip()) !== 'verified') {
            return;
        }
        $day = gmdate('Y-m-d');
        $hits = (array) get_option(self::OPT_HITS, array());
        $hits[$day] = (int) (isset($hits[$day]) ? $hits[$day] : 0) + 1;
        ksort($hits);
        if (count($hits) > self::KEEP_DAYS) {
            $hits = array_slice($hits, -self::KEEP_DAYS, null, true);
        }
        update_option(self::OPT_HITS, $hits, false);
    }

    /** Payload of the ?wlh_botstats signed op (panel's daily pull). */
    public static function stats()
    {
        return array(
            'ok'      => true,
            'src'     => 'wlh_botstat',
            'version' => defined('WLH_VERSION') ? WLH_VERSION : '',
            'today'   => gmdate('Y-m-d'),
            'days'    => (array) get_option(self::OPT_HITS, array()),
        );
    }

    private static function client_ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])
            && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
            $ip = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return is_string($ip) ? $ip : '';
    }

    private static function rdns_verdict($ip)
    {
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }
        // Приватный/зарезервированный (обратный прокси на том же хосте, LAN) —
        // реальный клиентский IP скрыт → unknown.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'unknown';
        }
        $key = 'wlh_botv_' . md5($ip);
        $cached = get_transient($key);
        if ($cached === 'verified' || $cached === 'denied' || $cached === 'unknown') {
            return $cached;
        }
        $verdict = self::compute_rdns_verdict($ip);
        set_transient($key, $verdict, 12 * HOUR_IN_SECONDS);
        return $verdict;
    }

    private static function compute_rdns_verdict($ip)
    {
        $host = @gethostbyaddr($ip);
        if (!is_string($host) || $host === '' || $host === $ip) {
            return 'unknown'; // нет PTR / сбой DNS — не можем судить
        }
        $host = strtolower(rtrim($host, '.'));
        // Только google-семейство: счётчик про Googlebot, прочие краулеры мимо.
        $suffixes = array(
            '.googlebot.com',
            '.google.com',      // Google-InspectionTool, AdsBot, Mediapartners
        );
        $matched = false;
        foreach ($suffixes as $suf) {
            if (strlen($host) > strlen($suf) && substr($host, -strlen($suf)) === $suf) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return 'denied'; // PTR указывает на не-google → спуфер/человек
        }
        // Forward-confirm: подделанный PTR не пройдёт — атакующий не заставит
        // *.googlebot.com резолвиться в свой IP. null = сбой DNS → unknown.
        $fc = self::forward_confirm($host, $ip);
        if ($fc === true) {
            return 'verified';
        }
        if ($fc === false) {
            return 'denied';
        }
        return 'unknown';
    }

    private static function forward_confirm($host, $ip)
    {
        if (strpos($ip, ':') !== false) {
            // IPv6: gethostbynamel только IPv4, поэтому резолвим AAAA.
            if (!function_exists('dns_get_record')) {
                return null;
            }
            $recs = @dns_get_record($host, DNS_AAAA);
            if (!is_array($recs)) {
                return null;
            }
            $norm = @inet_pton($ip);
            foreach ($recs as $r) {
                if (isset($r['ipv6']) && @inet_pton($r['ipv6']) === $norm) {
                    return true;
                }
            }
            return false;
        }
        $forward = @gethostbynamel($host);
        if (!is_array($forward)) {
            return null;
        }
        return in_array($ip, $forward, true);
    }
}
