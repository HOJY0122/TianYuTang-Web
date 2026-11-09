package org.tianyutang.staff;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/**
 * The website this app opens. One APK therefore works for the live site and
 * for testing on the local Wi-Fi (no rebuild per address).
 *
 * The setup screen shows every time the app starts, with the last address
 * filled in and the recent ones one tap away (testing: the PC's address
 * changes) — unless "記住 Remember" was ticked (the live domain): then the
 * app opens it straight away. An address chosen without "Remember" is used
 * until the app is closed (sessionSite, in memory only).
 *
 * Accepted: https://any-host[:port][/sub/folder]
 *           http:// only for a local network address (192.168.x.x, 10.x.x.x,
 *           172.16–31.x.x) — the live site must be HTTPS.
 */
final class SiteAddress {
    private static final String PREFS = "tyt";
    private static final String KEY = "site";            // last address used
    private static final String KEY_REMEMBER = "remember"; // open it without asking
    private static final String KEY_RECENT = "recent";     // up to 4, newest first, one per line

    /** Chosen on the setup screen for this run of the app (not remembered). */
    static volatile String sessionSite = null;

    private SiteAddress() {}

    /** Saved address (normalised), or null. */
    static String get(Context ctx) {
        return ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null);
    }

    static void save(Context ctx, String normalised) {
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY, normalised).apply();
    }

    /** Keep the address as the last used one, add it to the recent list, and remember (or not). */
    static void use(Context ctx, String normalised, boolean remember) {
        List<String> recent = recent(ctx);
        recent.remove(normalised);
        recent.add(0, normalised);
        while (recent.size() > 4) recent.remove(recent.size() - 1);
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
                .putString(KEY, normalised)
                .putBoolean(KEY_REMEMBER, remember)
                .putString(KEY_RECENT, lines(recent))
                .apply();
        sessionSite = normalised;
    }

    /** One per line (String.join needs Android 8; this app runs from Android 7). */
    private static String lines(List<String> list) {
        StringBuilder b = new StringBuilder();
        for (String s : list) {
            if (b.length() > 0) b.append('\n');
            b.append(s);
        }
        return b.toString();
    }

    static boolean remembered(Context ctx) {
        return ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean(KEY_REMEMBER, false);
    }

    /** The address to open now: the remembered one, or the one chosen this run; else null (setup screen). */
    static String toOpen(Context ctx) {
        String saved = get(ctx);
        if (saved != null && remembered(ctx)) return saved;
        return sessionSite;
    }

    static List<String> recent(Context ctx) {
        List<String> out = new ArrayList<>();
        String raw = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY_RECENT, "");
        for (String s : raw.split("\n")) {
            if (!s.trim().isEmpty() && normalise(s) != null) out.add(s.trim());
        }
        String last = get(ctx);
        if (last != null && !out.contains(last)) out.add(0, last);
        return out;
    }

    /** "Change site": stop opening it by itself (the address stays filled in on the setup screen). */
    static void clear(Context ctx) {
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean(KEY_REMEMBER, false).apply();
        sessionSite = null;
    }

    /** Forget one address from the recent list (long-press on the setup screen). */
    static void forget(Context ctx, String normalised) {
        List<String> recent = recent(ctx);
        recent.remove(normalised);
        SharedPreferences.Editor e = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
                .putString(KEY_RECENT, lines(recent));
        if (normalised.equals(get(ctx))) e.remove(KEY).putBoolean(KEY_REMEMBER, false);
        e.apply();
    }

    /** scheme://host[:port] of the address. */
    static String origin(String normalised) {
        Uri u = Uri.parse(normalised);
        String port = u.getPort() > 0 ? ":" + u.getPort() : "";
        return u.getScheme() + "://" + u.getHost() + port;
    }

    /** Sub-folder the site lives in ("" for the root), without a trailing slash. */
    static String path(String normalised) {
        String p = Uri.parse(normalised).getPath();
        return p == null ? "" : p.replaceAll("/+$", "");
    }

    /** Cleaned-up address, or null when it is not acceptable. */
    static String normalise(String typed) {
        if (typed == null) return null;
        String s = typed.trim();
        if (s.isEmpty() || s.length() > 300) return null;
        // Forgive "http:192.168.0.5:8000" / "https:/x" (missing slashes) and spaces.
        s = s.replace(" ", "").replaceFirst("(?i)^(https?):/*", "$1://");
        if (!s.contains("://")) {
            // "192.168.1.20:8000" typed without http:// is a PC on this Wi-Fi
            // (plain http); anything else is assumed to be the HTTPS site.
            String hostPart = s.split("[/:]", 2)[0];
            s = (isLocalNetwork(hostPart) ? "http://" : "https://") + s;
        }
        Uri u = Uri.parse(s);
        String scheme = u.getScheme() == null ? "" : u.getScheme().toLowerCase(Locale.ROOT);
        String host = u.getHost() == null ? "" : u.getHost().toLowerCase(Locale.ROOT);
        if (host.isEmpty() || u.getUserInfo() != null || u.getQuery() != null || u.getFragment() != null) return null;
        if (!host.matches("[a-z0-9.-]+") || !host.contains(".")) return null;
        if ("http".equals(scheme)) {
            if (!isLocalNetwork(host)) return null;
        } else if (!"https".equals(scheme)) {
            return null;
        }
        String port = u.getPort() > 0 ? ":" + u.getPort() : "";
        String path = u.getPath() == null ? "" : u.getPath().replaceAll("/+$", "");
        if (!path.isEmpty() && !path.matches("(/[A-Za-z0-9._~-]+)+")) return null;
        return scheme + "://" + host + port + path;
    }

    private static boolean isLocalNetwork(String host) {
        if (!host.matches("\\d{1,3}(\\.\\d{1,3}){3}")) return false;
        String[] p = host.split("\\.");
        int a = Integer.parseInt(p[0]);
        int b = Integer.parseInt(p[1]);
        return a == 10 || (a == 192 && b == 168) || (a == 172 && b >= 16 && b <= 31);
    }
}
