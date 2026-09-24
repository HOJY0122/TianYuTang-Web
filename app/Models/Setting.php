<?php
namespace App\Models;

use App\Core\Model;

/**
 * Setting — site-level values that outlive any single event.
 *
 * The header name is the clear case: the temple's name does not change
 * from year to year, so putting it on an event row would mean retyping
 * it every time a new event is created, with the chance of a typo each
 * time. Per-year branding (banner, favicon) stays on the event; things
 * true of the site itself live here.
 */
class Setting extends Model
{
    /** Everything, as key => value. Cached per request. */
    public function all(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM settings');
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $cache = $out;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();
        $value = $all[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    /** Write one setting, creating it if it does not exist. */
    public function set(string $key, ?string $value): void
    {
        $this->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
    }
}
