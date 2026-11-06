package org.tianyutang.staff;

import android.content.pm.ApplicationInfo;
import android.os.Bundle;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.CapConfig;

/**
 * The staff pages of the website, plus the native scanner (TytScanPlugin).
 *
 * No address saved yet → the built-in setup screen (www/index.html) asks for
 * it. Saved → the site opens at /admin/login, and only that site gets the
 * native bridge; links elsewhere open in the phone's browser.
 */
public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        String site = SiteAddress.get(this);
        if (site != null) {
            config = new CapConfig.Builder(this)
                    .setServerUrl(SiteAddress.origin(site))
                    .setStartPath(SiteAddress.path(site) + "/admin/login")
                    .setAllowMixedContent(false)
                    // Site unreachable → built-in page with Retry / Change site
                    .setErrorPath("error.html")
                    .setWebContentsDebuggingEnabled((getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0)
                    .create();
        }
        registerPlugin(TytScanPlugin.class);
        registerPlugin(TytSetupPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
