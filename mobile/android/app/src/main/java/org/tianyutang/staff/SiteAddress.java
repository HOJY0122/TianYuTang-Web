package org.tianyutang.staff;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;

import java.util.Locale;

/**
 * The website this app opens, typed once on the setup screen and kept on
 * the phone. One APK therefore works for the live site and for testing on
 * the local Wi-Fi (no rebuild per address).
 *
 * Accepted: https://any-host[:port][/sub/folder]
 *           http:// only for a local network address (192.168.x.x, 10.x.x.x,
 *           172.16–31.x.x) — the live site must be HTTPS.
 */
final class SiteAddress {
    private static final String PREFS = "tyt";
    private static final String KEY = "site";

    private SiteAddress() {}

    /** Saved address (normalised), or null. */
    static String get(Context ctx) {
        return ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null);
    }

    static void save(Context ctx, String normalised) {
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY, normalised).apply();
    }

    static void clear(Context ctx) {
        SharedPreferences p = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        p.edit().remove(KEY).apply();
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
