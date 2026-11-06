package org.tianyutang.staff;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;

import androidx.activity.result.ActivityResult;

import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

/**
 * window.Capacitor.Plugins.TytScan.scan({ mode: 'ic'|'qr'|'any', title })
 *   → { cancelled: false, type: 'ic'|'qr'|'manual', value: '…' }
 *   → { cancelled: true }
 *
 * Only pages of the site this app was built for get the Capacitor bridge,
 * so only they can open the scanner. It returns text only.
 */
@CapacitorPlugin(
        name = "TytScan",
        permissions = { @Permission(strings = { Manifest.permission.CAMERA }, alias = "camera") }
)
public class TytScanPlugin extends Plugin {

    @PluginMethod
    public void scan(PluginCall call) {
        if (getPermissionState("camera") != PermissionState.GRANTED) {
            requestPermissionForAlias("camera", call, "afterCameraPermission");
            return;
        }
        open(call);
    }

    /** Opens even when refused: the scan screen still has the manual box. */
    @PermissionCallback
    private void afterCameraPermission(PluginCall call) {
        open(call);
    }

    private void open(PluginCall call) {
        Intent intent = new Intent(getContext(), ScanActivity.class);
        intent.putExtra(ScanActivity.EXTRA_MODE, call.getString("mode", "any"));
        intent.putExtra(ScanActivity.EXTRA_TITLE, call.getString("title", ""));
        startActivityForResult(call, intent, "afterScan");
    }

    @ActivityCallback
    private void afterScan(PluginCall call, ActivityResult result) {
        if (call == null) {
            return;
        }
        JSObject out = new JSObject();
        Intent data = result.getData();
        if (result.getResultCode() != Activity.RESULT_OK || data == null) {
            out.put("cancelled", true);
        } else {
            out.put("cancelled", false);
            out.put("type", data.getStringExtra(ScanActivity.RESULT_TYPE));
            out.put("value", data.getStringExtra(ScanActivity.RESULT_VALUE));
        }
        call.resolve(out);
    }
}
