package org.tianyutang.staff;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Capacitor.Plugins.TytSetup
 *   setSite({ url })  → saves the address and opens the site (rejects a bad address)
 *   resetSite()       → forgets it and shows the setup screen again
 *   getSite()         → { url } or { url: null }
 */
@CapacitorPlugin(name = "TytSetup")
public class TytSetupPlugin extends Plugin {

    @PluginMethod
    public void setSite(PluginCall call) {
        String site = SiteAddress.normalise(call.getString("url", ""));
        if (site == null) {
            call.reject("網址不正確：要用 https://…（本地測試可用 http://192.168.x.x）。Address not accepted: use https://… (http://192.168.x.x only for local testing).");
            return;
        }
        SiteAddress.save(getContext(), site);
        JSObject out = new JSObject();
        out.put("url", site);
        call.resolve(out);
        getActivity().runOnUiThread(() -> getActivity().recreate());
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
        call.resolve(out);
    }
}
