#!/usr/bin/env python3
"""
Automated WebKit / Safari Layout & Overflow Auditor
Runs native WebKitGTK to mathematically audit horizontal overflow,
element clipping, and layout anomalies on Desktop & Mobile (iOS Safari).
"""
import sys
import os
import json

os.environ["WEBKIT_DISABLE_SANDBOX_THIS_IS_DANGEROUS"] = "1"

try:
    import gi
    gi.require_version('Gtk', '4.0')
    gi.require_version('WebKit', '6.0')
    from gi.repository import Gtk, WebKit, GLib, Gio
except Exception as e:
    print(f"Error loading WebKitGTK: {e}", file=sys.stderr)
    sys.exit(2)

DEVICES = {
    'desktop': {
        'name': 'Desktop Safari (Mac)',
        'width': 1280,
        'height': 820,
        'user_agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
    },
    'iphone': {
        'name': 'iPhone 15 Pro (iOS Safari)',
        'width': 393,
        'height': 852,
        'user_agent': 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    },
    'android': {
        'name': 'Android Mobile (Chrome/Android)',
        'width': 393,
        'height': 852,
        'user_agent': 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
    }
}

class LayoutAuditorApp(Gtk.Application):
    def __init__(self, target_url, device_key='iphone', out_screenshot=None):
        super().__init__(application_id='com.safia.webkit.auditor', flags=Gio.ApplicationFlags.NON_UNIQUE)
        self.target_url = target_url
        self.device_key = device_key
        self.cfg = DEVICES[device_key]
        self.out_screenshot = out_screenshot
        self.exit_code = 0
        self.audit_result = None

    def do_activate(self):
        win = Gtk.ApplicationWindow(application=self)
        win.set_default_size(self.cfg['width'], self.cfg['height'])
        
        webview = WebKit.WebView()
        settings = webview.get_settings()
        settings.set_enable_developer_extras(True)
        settings.set_user_agent(self.cfg['user_agent'])
        win.set_child(webview)

        def on_load_finished(wv, event):
            if event == WebKit.LoadEvent.FINISHED:
                # Give dynamic hydration / API calls a grace period then poll for ready
                GLib.timeout_add(1200, wait_for_hydration, wv, 0)

        def wait_for_hydration(wv, poll_count):
            check_js = '''
            (function() {
                // Wait for document ready and absence of splash / skeleton
                const hasSplash = !!document.querySelector('.global-splash');
                const hasSkeleton = !!document.querySelector('.ipd-skeleton, .v-skeleton-loader, [class*="skeleton"]');
                const isReady = document.readyState === 'complete';
                return JSON.stringify({ ready: isReady && !hasSplash && !hasSkeleton });
            })()
            '''
            def on_check(src, res):
                try:
                    js_val = src.evaluate_javascript_finish(res)
                    data = json.loads(js_val.to_string())
                    if data.get('ready') or poll_count >= 5:
                        GLib.timeout_add(1000, run_audit, wv)
                    else:
                        GLib.timeout_add(500, wait_for_hydration, wv, poll_count + 1)
                except Exception as ex:
                    GLib.timeout_add(500, run_audit, wv)

            wv.evaluate_javascript(check_js, -1, None, None, None, on_check)
            return False

        def run_audit(wv):
            audit_js = '''
            (function() {
                const winWidth = window.innerWidth;
                const docScrollWidth = document.documentElement.scrollWidth;
                const bodyScrollWidth = document.body.scrollWidth;
                const hasHScroll = docScrollWidth > winWidth + 1 || bodyScrollWidth > winWidth + 1;
                
                const overflowingElements = [];
                document.querySelectorAll('*').forEach(el => {
                    const r = el.getBoundingClientRect();
                    if (r.width === 0 && r.height === 0) return;
                    const cs = window.getComputedStyle(el);
                    if (cs.display === 'none' || cs.visibility === 'hidden') return;
                    // Ignore off-screen drawers, mobile navigation menus, or hidden modals
                    if (el.closest('.mobile-menu, [aria-hidden="true"], .v-overlay:not(.v-overlay--active)')) return;
                    if (cs.position === 'fixed' && (r.left >= winWidth || r.right <= 0)) return;

                    // If an ancestor has overflow: hidden and is strictly within bounds, the child is clipped
                    let parent = el.parentElement;
                    let isClipped = false;
                    while (parent && parent !== document.body && parent !== document.documentElement) {
                        const pcs = window.getComputedStyle(parent);
                        const pr = parent.getBoundingClientRect();
                        if ((pcs.overflowX === 'hidden' || pcs.overflow === 'hidden') && pr.left >= -2 && pr.right <= winWidth + 2) {
                            isClipped = true;
                            break;
                        }
                        parent = parent.parentElement;
                    }
                    if (isClipped) return;
                    
                    if (r.left < -2 || r.right > winWidth + 2) {
                        overflowingElements.push({
                            tag: el.tagName,
                            className: el.className || '',
                            id: el.id || '',
                            left: Math.round(r.left),
                            right: Math.round(r.right),
                            width: Math.round(r.width),
                            scrollWidth: el.scrollWidth,
                            direction: cs.direction,
                            textSnippet: el.innerText ? el.innerText.slice(0, 35).replace(/\\s+/g, ' ') : ''
                        });
                    }
                });

                return JSON.stringify({
                    viewport: {
                        width: winWidth,
                        height: window.innerHeight,
                        docScrollWidth: docScrollWidth,
                        bodyScrollWidth: bodyScrollWidth,
                        hasHorizontalOverflow: hasHScroll
                    },
                    overflowCount: overflowingElements.length,
                    overflowingElements: overflowingElements.slice(0, 20)
                }, null, 2);
            })()
            '''
            def on_audit_done(src, res):
                try:
                    js_val = src.evaluate_javascript_finish(res)
                    self.audit_result = json.loads(js_val.to_string())
                    if self.audit_result['viewport']['hasHorizontalOverflow'] or self.audit_result['overflowCount'] > 0:
                        self.exit_code = 1
                    else:
                        self.exit_code = 0
                except Exception as ex:
                    print(f"Error parsing audit: {ex}", file=sys.stderr)
                    self.exit_code = 2

                if self.out_screenshot:
                    capture_screenshot(wv)
                else:
                    self.finish()

            wv.evaluate_javascript(audit_js, -1, None, None, None, on_audit_done)
            return False

        def capture_screenshot(wv):
            def on_snap_done(src, res):
                try:
                    paintable = src.get_snapshot_finish(res)
                    paintable.save_to_png(self.out_screenshot)
                except Exception as ex:
                    print(f"Failed to save screenshot: {ex}", file=sys.stderr)
                self.finish()
            wv.get_snapshot(WebKit.SnapshotRegion.FULL_DOCUMENT, WebKit.SnapshotOptions.NONE, None, on_snap_done)

        webview.connect('load-changed', on_load_finished)
        win.present()
        webview.load_uri(self.target_url)

    def finish(self):
        if self.audit_result:
            print(json.dumps(self.audit_result, indent=2, ensure_ascii=False))
        self.quit()

def main():
    target_url = 'http://localhost:3000'
    device = 'iphone'
    out_snap = None

    args = sys.argv[1:]
    for arg in args:
        if arg.startswith('http://') or arg.startswith('https://'):
            target_url = arg
        elif arg in ('--desktop', '-d'):
            device = 'desktop'
        elif arg in ('--mobile', '-m', '--iphone'):
            device = 'iphone'
        elif arg in ('--android', '-a'):
            device = 'android'
        elif arg.startswith('--screenshot='):
            out_snap = arg.split('=', 1)[1]
        elif arg == '--screenshot':
            out_snap = '/tmp/webkit_audit.png'

    app = LayoutAuditorApp(target_url=target_url, device_key=device, out_screenshot=out_snap)
    app.run([])
    sys.exit(app.exit_code)

if __name__ == '__main__':
    main()
