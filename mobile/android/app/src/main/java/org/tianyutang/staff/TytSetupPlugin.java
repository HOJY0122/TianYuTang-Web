package org.tianyutang.staff;

import android.view.WindowManager;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.net.HttpURLConnection;
import java.net.URL;

/**
 * Capacitor.Plugins.TytSetup
 *   setSite({ url, remember, force })
 *                     → checks the site answers (5 s), then opens it. remember:
 *                       open it straight away next time too. force: skip the
 *                       check ("open anyway"). Rejects a bad address (code
 *                       BAD_ADDRESS) or an unreachable one (code UNREACHABLE).
 *   resetSite()       → back to the setup screen (the address stays filled in)
 *   getSite()         → { url, remember, recent: [...] }
 *   forgetSite({ url }) → remove one address from the recent list
 *   keepAwake({ on }) → keep the screen on (easy mode at the counter) or not
 */
@CapacitorPlugin(name = "TytSetup")
public class TytSetupPlugin extends Plugin {

    @PluginMethod
    public void setSite(PluginCall call) {
        String site = SiteAddress.normalise(call.getString("url", ""));
        if (site == null) {
            call.reject("網址不正確：要用 https://…（本地測試可用 http://192.168.x.x）。Address not accepted: use https://… (http://192.168.x.x only for local testing).", "BAD_ADDRESS");
            return;
        }
        boolean remember = Boolean.TRUE.equals(call.getBoolean("remember", false));
        boolean force = Boolean.TRUE.equals(call.getBoolean("force", false));
        new Thread(() -> {
            String problem = force ? null : reach(site);
            if (problem != null) {
                call.reject("連不上 " + site + "（" + problem + "）。\nCannot reach " + site + " (" + problem + ").", "UNREACHABLE");
                return;
            }
            SiteAddress.use(getContext(), site, remember);
            JSObject out = new JSObject();
            out.put("url", site);
            call.resolve(out);
            getActivity().runOnUiThread(() -> getActivity().recreate());
        }).start();
    }

    /** null when the site answers at all within 5 s, else a short reason. */
    private static String reach(String site) {
        HttpURLConnection c = null;
        try {
            c = (HttpURLConnection) new URL(site + "/admin/login").openConnection();
            c.setConnectTimeout(5000);
            c.setReadTimeout(5000);
            c.setInstanceFollowRedirects(false);
            c.setRequestMethod("GET");
            int code = c.getResponseCode();
            return code > 0 ? null : "沒有回應 no reply";
        } catch (java.net.SocketTimeoutException e) {
            return "逾時 timed out";
        } catch (java.net.UnknownHostException e) {
            return "找不到網域 unknown domain";
        } catch (javax.net.ssl.SSLException e) {
            return "HTTPS 憑證問題 certificate problem";
        } catch (java.io.IOException e) {
            return "無法連線 connection refused";
        } finally {
            if (c != null) c.disconnect();
        }
    }

    @PluginMethod
    public void resetSite(PluginCall call) {
        SiteAddress.clear(getContext());
        call.resolve();
        getActivity().runOnUiThread(() -> getActivity().recreate());
    }

    @PluginMethod
    public void getSite(PluginCall call) {
        JSObject out = new JSObject();
        out.put("url", SiteAddress.get(getContext()));
        out.put("remember", SiteAddress.remembered(getContext()));
        JSArray recent = new JSArray();
        for (String s : SiteAddress.recent(getContext())) recent.put(s);
        out.put("recent", recent);
        call.resolve(out);
    }

    @PluginMethod
    public void forgetSite(PluginCall call) {
        String site = SiteAddress.normalise(call.getString("url", ""));
        if (site != null) SiteAddress.forget(getContext(), site);
        call.resolve();
    }

    @PluginMethod
    public void keepAwake(PluginCall call) {
        boolean on = Boolean.TRUE.equals(call.getBoolean("on", false));
        getActivity().runOnUiThread(() -> {
            if (on) {
                getActivity().getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            } else {
                getActivity().getWindow().clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            }
        });
        call.resolve();
    }
}
