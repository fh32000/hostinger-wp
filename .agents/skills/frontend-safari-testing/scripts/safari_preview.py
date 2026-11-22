#!/usr/bin/env python3
"""
Safari / WebKit Native Inspector & Preview Tool for Linux
Uses native WebKitGTK 6.0 with Web Inspector and Device Emulation.
"""
import sys
import os

# Bypass Ubuntu 24.04 noble unprivileged userns bwrap restriction for local preview
os.environ["WEBKIT_DISABLE_SANDBOX_THIS_IS_DANGEROUS"] = "1"

try:
    import gi
    gi.require_version('Gtk', '4.0')
    gi.require_version('WebKit', '6.0')
    from gi.repository import Gtk, WebKit, GLib, Gio
except Exception as e:
    print(f"Error loading WebKitGTK: {e}")
    print("Please install: sudo apt-get install python3-gi gir1.2-webkit-6.0")
    sys.exit(1)

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
    'ipad': {
        'name': 'iPad Pro (iPadOS Safari)',
        'width': 834,
        'height': 1112,
        'user_agent': 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    }
}

class SafariPreviewApp(Gtk.Application):
    def __init__(self, initial_url='http://localhost:3000', initial_device='desktop'):
        super().__init__(application_id='com.safia.safari.preview', flags=Gio.ApplicationFlags.NON_UNIQUE)
        self.initial_url = initial_url
        self.current_device = initial_device
        self.window = None
        self.webview = None
        self.url_entry = None
        self.status_label = None

    def do_activate(self):
        if not self.window:
            self.build_ui()
        self.window.present()

    def build_ui(self):
        cfg = DEVICES[self.current_device]
        self.window = Gtk.ApplicationWindow(application=self)
        self.window.set_title(f"Safari Preview (WebKit) - {cfg['name']}")
        self.window.set_default_size(cfg['width'], cfg['height'])

        main_box = Gtk.Box(orientation=Gtk.Orientation.VERTICAL, spacing=0)
        self.window.set_child(main_box)

        # Header / Toolbar
        header_bar = Gtk.HeaderBar()
        self.window.set_titlebar(header_bar)

        # Nav Buttons
        back_btn = Gtk.Button(label="◀")
        back_btn.set_tooltip_text("Back")
        back_btn.connect('clicked', lambda _: self.webview.go_back() if self.webview.can_go_back() else None)
        header_bar.pack_start(back_btn)

        fwd_btn = Gtk.Button(label="▶")
        fwd_btn.set_tooltip_text("Forward")
        fwd_btn.connect('clicked', lambda _: self.webview.go_forward() if self.webview.can_go_forward() else None)
        header_bar.pack_start(fwd_btn)

        reload_btn = Gtk.Button(label="⟳")
        reload_btn.set_tooltip_text("Hard Reload (Bypass Cache)")
        reload_btn.connect('clicked', lambda _: self.webview.reload_bypass_cache())
        header_bar.pack_start(reload_btn)

        # URL Entry
        self.url_entry = Gtk.Entry()
        self.url_entry.set_hexpand(True)
        self.url_entry.set_text(self.initial_url)
        self.url_entry.connect('activate', self.on_url_entered)
        header_bar.set_title_widget(self.url_entry)

        # Device Presets
        desktop_btn = Gtk.Button(label="🖥️ Desktop")
        desktop_btn.set_tooltip_text("Desktop Safari Mode")
        desktop_btn.connect('clicked', lambda _: self.set_device('desktop'))
        header_bar.pack_end(desktop_btn)

        iphone_btn = Gtk.Button(label="📱 iPhone")
        iphone_btn.set_tooltip_text("iPhone 15 (iOS Safari Mode)")
        iphone_btn.connect('clicked', lambda _: self.set_device('iphone'))
        header_bar.pack_end(iphone_btn)

        ipad_btn = Gtk.Button(label="💻 iPad")
        ipad_btn.set_tooltip_text("iPad Pro Mode")
        ipad_btn.connect('clicked', lambda _: self.set_device('ipad'))
        header_bar.pack_end(ipad_btn)

        inspect_btn = Gtk.Button(label="🔍 فحص (Inspect)")
        inspect_btn.set_tooltip_text("Open WebKit Web Inspector")
        inspect_btn.connect('clicked', self.on_inspect)
        header_bar.pack_end(inspect_btn)

        # WebKit WebView
        self.webview = WebKit.WebView()
        self.webview.set_hexpand(True)
        self.webview.set_vexpand(True)

        # Configure WebKit Settings
        settings = self.webview.get_settings()
        settings.set_enable_developer_extras(True)
        settings.set_user_agent(cfg['user_agent'])
        settings.set_enable_smooth_scrolling(True)
        settings.set_enable_webgl(True)
        settings.set_enable_2d_canvas_acceleration(True)

        # Connect Events
        self.webview.connect('load-changed', self.on_load_changed)

        # Container
        main_box.append(self.webview)

        # Initial Load
        self.webview.load_uri(self.initial_url)

    def set_device(self, device_key):
        if device_key not in DEVICES:
            return
        self.current_device = device_key
        cfg = DEVICES[device_key]
        self.window.set_title(f"Safari Preview (WebKit) - {cfg['name']}")
        
        # Update User-Agent
        settings = self.webview.get_settings()
        settings.set_user_agent(cfg['user_agent'])
        
        # Resize window
        self.window.set_size_request(-1, -1)
        self.window.set_default_size(cfg['width'], cfg['height'])
        # In GTK4 window resize request
        self.window.set_size_request(cfg['width'], cfg['height'])
        
        # Reload to apply UA changes without cache
        self.webview.reload_bypass_cache()
        print(f"Switched to: {cfg['name']} ({cfg['width']}x{cfg['height']})")

    def on_url_entered(self, entry):
        url = entry.get_text().strip()
        if not url.startswith('http://') and not url.startswith('https://'):
            url = 'http://' + url
            entry.set_text(url)
        self.webview.load_uri(url)

    def on_load_changed(self, webview, load_event):
        if load_event == WebKit.LoadEvent.COMMITTED:
            uri = webview.get_uri()
            if uri and self.url_entry:
                self.url_entry.set_text(uri)
        elif load_event == WebKit.LoadEvent.FINISHED:
            title = webview.get_title() or "Safia"
            cfg = DEVICES[self.current_device]
            self.window.set_title(f"Safari Preview | {title} - {cfg['name']}")

    def on_inspect(self, button):
        inspector = self.webview.get_inspector()
        if inspector:
            inspector.show()

def parse_args():
    url = 'http://localhost:3000'
    device = 'desktop'
    
    for arg in sys.argv[1:]:
        if arg.startswith('http://') or arg.startswith('https://'):
            url = arg
        elif arg in ('--mobile', '-m', '--iphone'):
            device = 'iphone'
        elif arg in ('--ipad', '-t'):
            device = 'ipad'
        elif arg in ('--desktop', '-d'):
            device = 'desktop'
        elif arg.isdigit():
            url = f"http://localhost:{arg}"

    return url, device

if __name__ == '__main__':
    url, device = parse_args()
    print("=" * 60)
    print(" Safari (WebKit Engine) Native Preview for Linux")
    print(f" URL    : {url}")
    print(f" Device : {device}")
    print(" Shortcuts: Right-click -> Inspect Element (فحص العنصر)")
    print("=" * 60)
    app = SafariPreviewApp(initial_url=url, initial_device=device)
    app.run([])
