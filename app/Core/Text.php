<?php
namespace App\Core;

use App\Models\Setting;

/**
 * Text — every piece of fixed wording on the public site, in one list.
 *
 * Each entry has a Chinese and an English default. The system admin can
 * replace either at 系統管理 → 網站文字 Wording; the replacement is
 * stored in the settings table as "txt.<key>.zh" / "txt.<key>.en".
 * Clearing a box brings the default back, so nothing can be lost for good.
 *
 * In views:
 *   t('home.news')        → "最新消息"                 (Chinese)
 *   t('home.news', 'en')  → "News & Announcements"    (English)
 *   tb('home.news')       → 最新消息<span class="en">News &amp; Announcements</span>
 *
 * {placeholders} such as {n} are filled in by the caller.
 */
class Text
{
    /** Groups, in the order the Wording page shows them. */
    public const GROUPS = [
        'nav'      => '選單 Menu',
        'home'     => '首頁 Home page',
        'register' => '報名頁 Registration page',
        'donate'   => '布施頁 Donation page',
        'success'  => '完成頁 Confirmation page',
        'gallery'  => '相簿 Gallery',
        'help'     => 'ⓘ 說明視窗 Help panel',
        'pdf'      => '列印 / PDF Printouts',
    ];

    /**
     * key => [group, 中文 default, English default, long?]
     * "long" gives the admin a textarea instead of a one-line box.
     */
    public const ITEMS = [
        // --- Menu ---
        'nav.home'     => ['nav', '首頁', 'Home'],
        'nav.register' => ['nav', '報名', 'Register'],
        'nav.donate'   => ['nav', '布施', 'Donate'],
        'nav.gallery'  => ['nav', '相簿', 'Gallery'],

        // --- Home page ---
        'home.welcome'        => ['home', '誠邀十方善信共襄盛舉，同結善緣，共種福田。', 'All are warmly welcome to join us in this celebration.', true],
        'home.days'           => ['home', '起 · {n} 天', '{n} days'],
        'home.cta_register'   => ['home', '報名參加', 'Register to Attend'],
        'home.cta_donate'     => ['home', '功德布施', 'Make a Donation'],
        'home.opening_soon'   => ['home', '尚未開放', 'Opening soon'],
        'home.closed'         => ['home', '已截止', 'Closed'],
        'home.info'           => ['home', '活動資料', 'Event Information'],
        'home.date'           => ['home', '📅 日期', 'Date'],
        'home.venue'          => ['home', '📍 地點', 'Venue'],
        'home.enquiry'        => ['home', '🙏 現場詢問', 'Enquiries'],
        'home.enquiry_text'   => ['home', '歡迎於活動當日親臨櫃台詢問。', 'Walk-in registration is available at the counter on the day.', true],
        'home.directions'     => ['home', '🚗 如何前往', 'Getting there'],
        'home.directions_text'=> ['home', '用手機掃描右邊的 QR Code，或按下面的按鈕開啟導航。', 'Scan the QR code with your phone, or tap a button below to open navigation.', true],
        'home.waze'           => ['home', '🚙 Waze 導航', 'Waze'],
        'home.maps'           => ['home', '🗺️ Google 地圖', 'Google Maps'],
        'home.scan'           => ['home', '掃描導航', 'Scan for Waze'],
        'home.news'           => ['home', '最新消息', 'News & Announcements'],
        'home.pinned'         => ['home', '📌 置頂', 'Pinned'],
        'home.photos'         => ['home', '活動留影', 'Photo Albums'],
        'home.photos_hint'    => ['home', '左右滑動看更多相片，點一下放大。', 'Swipe for more photos. Tap a photo to enlarge.'],
        'home.all_albums'     => ['home', '📸 瀏覽全部相簿', 'View all albums'],

        // --- Registration ---
        'register.title'      => ['register', '報名參加', 'Event Registration'],
        'register.step1'      => ['register', '幾位參加？', 'How many people?'],
        'register.limit'      => ['register', '每次最多 {max} 位，全部共用一個報名編號。', 'Up to {max} people, all under one reference number.'],
        'register.step2'      => ['register', '填寫每位參加者資料', 'Details for each person'],
        'register.submit'     => ['register', '📝 提交報名', 'Submit Registration'],
        'register.not_yet'    => ['register', '報名尚未開放', 'Registration is not open yet'],
        'register.closed'     => ['register', '線上報名已截止', 'Online registration has closed'],
        'register.walkin'     => ['register', '歡迎於活動當日親臨現場登記。', 'Walk-in registration is available at the counter on the day.'],

        // --- Donation ---
        'donate.title'        => ['donate', '功德布施', 'Merit & Donation'],
        'donate.intro'        => ['donate', '一份善念，一份布施，共種福田。', 'Every act of giving plants a seed of merit.'],
        'donate.step1'        => ['donate', '您的資料', 'Your details'],
        'donate.step2'        => ['donate', '布施方式（可選一項或兩項）', 'Choose one or both'],
        'donate.seats'        => ['donate', '🪷 功德席', 'Merit Seats'],
        'donate.free'         => ['donate', '🙏 隨喜布施', 'Freewill Donation'],
        'donate.free_hint'    => ['donate', '任何金額皆可。', 'Any amount you wish.'],
        'donate.submit'       => ['donate', '🙏 提交布施', 'Submit Donation'],
        'donate.after'        => ['donate', '提交後工作人員會與您聯繫確認付款。', 'Our staff will contact you to arrange payment.'],
        'donate.not_yet'      => ['donate', '布施尚未開放', 'Donations are not open yet'],
        'donate.closed'       => ['donate', '線上布施已截止', 'Online donations have closed'],
        'donate.counter'      => ['donate', '歡迎於活動當日親臨櫃台布施。', 'You are welcome to give at the counter on the day.'],

        // --- Confirmation ---
        'success.rsvp_title'  => ['success', '報名成功', 'Registration complete'],
        'success.rsvp_text'   => ['success', '請截圖或列印此頁，活動當日出示 QR Code 即可報到。', 'Please screenshot or print this page and show the QR code at the counter.', true],
        'success.don_title'   => ['success', '感恩您的布施', 'Thank you for your donation'],
        'success.don_text'    => ['success', '您的布施資料已收到，工作人員會盡快與您聯繫確認付款。活動當日可出示此 QR Code 到櫃台付款。', 'We have received your donation details. Our staff will contact you about payment — or show this QR code at the counter on the day.', true],
        'success.leave'       => ['success', '離開後本頁面將無法再次開啟，請先截圖保存。', 'This page cannot be opened again once you leave — please save a screenshot.', true],

        // --- Gallery ---
        'gallery.title'       => ['gallery', '相簿回顧', 'Photo Albums'],
        'gallery.hint'        => ['gallery', '歷年活動留影，左右滑動看更多，點一下放大。', 'Photos from every year. Swipe for more, tap to enlarge.'],
        'gallery.empty_title' => ['gallery', '相簿準備中', 'Photos coming soon'],
        'gallery.empty_text'  => ['gallery', '活動後將上傳精彩留影，敬請期待。', 'Photos will be added after the event.'],

        // --- ⓘ help panel, one per page ---
        'help.title'    => ['help', '使用說明', 'How to use this page'],
        'help.home'     => ['help', "・按「報名」為自己和家人登記參加，全部共用一個報名編號。\n・按「布施」認捐功德席或隨喜布施。\n・「如何前往」可用 Waze 或 Google 地圖導航到會場。\n・右上角的人形按鈕可放大字體。",
                                    "• Tap Register to sign up yourself and your family under one reference number.\n• Tap Donate to sponsor merit seats or give a freewill amount.\n• \"Getting there\" opens Waze or Google Maps to the venue.\n• The person button at the top makes the text bigger.", true],
        'help.register' => ['help', "・先選人數，再填寫每位的姓名、身份證（或護照）及電話。\n・身份證只用於活動當日核對，資料依個人資料保護法令（PDPA）保管。\n・提交後請截圖保存報名編號與 QR Code。",
                                    "• Choose how many people, then fill in each person's name, IC (or passport) and phone.\n• Your IC is only used to confirm who you are at check-in, and is protected under the PDPA.\n• After submitting, screenshot your reference number and QR code.", true],
        'help.donate'   => ['help', "・可選功德席、隨喜，或兩者一起。\n・提交後工作人員會聯絡您安排付款。\n・活動當日帶著布施編號的 QR Code 到櫃台，可直接付款及取收據。",
                                    "• Choose merit seats, a freewill amount, or both.\n• After you submit, our staff will contact you to arrange payment.\n• On the day, show your donation QR code at the counter to pay and collect your receipt.", true],
        'help.gallery'  => ['help', "・點相片可放大，左右滑動看下一張。", "• Tap a photo to enlarge it; swipe to see the next one.", true],
        'help.other'    => ['help', "如有疑問，歡迎於活動當日親臨櫃台詢問。", 'If you have any questions, please visit our counter on the event day.', true],

        // --- Printouts ---
        'pdf.attendees'    => ['pdf', '報名名單 · 現場報到表', 'Attendee list · Check-in sheet'],
        'pdf.donations'    => ['pdf', '布施名單 · 功德紀錄', 'Donation record'],
        'pdf.confidential' => ['pdf', '機密文件：內含個人資料，僅供本會內部使用，用後請妥善銷毀。', 'Confidential — contains personal data (PDPA). For internal use only; dispose of securely.', true],
    ];

    /** The wording for $key in 'zh' or 'en', with {placeholders} filled. */
    public static function get(string $key, string $lang = 'zh', array $vars = []): string
    {
        $item = self::ITEMS[$key] ?? null;
        if ($item === null) {
            return $key;   // a typo shows up on the page instead of vanishing
        }
        $saved = (new Setting())->all()["txt.{$key}.{$lang}"] ?? null;
        $text  = ($saved !== null && $saved !== '') ? $saved : ($lang === 'en' ? $item[2] : $item[1]);
        if ($vars) {
            $pairs = [];
            foreach ($vars as $k => $v) {
                $pairs['{' . $k . '}'] = (string) $v;
            }
            $text = strtr($text, $pairs);
        }
        return $text;
    }

    /** The default (before any change) — shown as a hint on the Wording page. */
    public static function fallback(string $key, string $lang): string
    {
        $item = self::ITEMS[$key] ?? null;
        return $item === null ? '' : ($lang === 'en' ? $item[2] : $item[1]);
    }
}
