<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * مرحله ۱: خنثی‌سازی بخش باگ‌دار افزونه رقیب (PWA)
 * این تابع با اولویت بالا در هوک 'wp_loaded' اجرا می‌شود تا مطمئن شویم
 * افزونه رقیب کاملاً بارگذاری شده و سپس بخش معیوب آن را حذف می‌کنیم.
 */
add_action('wp_loaded', 'wnw_neutralize_pwa_plugin_bug', 1);
function wnw_neutralize_pwa_plugin_bug() {
    // فقط در فرانت‌اند اجرا شود، نه در پیشخوان
    if (is_admin()) {
        return;
    }

    // بررسی می‌کنیم که آیا تابع مربوط به افزونه PWA وجود دارد یا خیر
    if (function_exists('pwa_get_instance') && isset(pwa_get_instance()->AddToHomeScreen)) {
        // با استفاده از تابع remove_action، قلاب متصل شده توسط افزونه رقیب که باعث خطای 500 می‌شود را حذف می‌کنیم.
        remove_action('parse_request', [pwa_get_instance()->AddToHomeScreen, 'generate_manifest']);
    }
}


/**
 * مرحله ۲: تزریق اسکریپت "کودتا" (Hijacker)
 * این تابع با اولویت بسیار بالا در هدر سایت اجرا می‌شود تا کنترل ثبت Service Worker را به دست بگیرد.
 */
add_action('wp_head', 'wnw_inject_sw_hijacker_script', -9999);
function wnw_inject_sw_hijacker_script() {
    $sw_url = home_url('/service-worker.js?v=' . WNW_VERSION);
    $correct_scope = trailingslashit(wp_parse_url(home_url('/'), PHP_URL_PATH));
    $conflicting_keyword = '?pwa_serviceworker=1';
    ?>
    <script id="wnw-sw-hijacker">
    (function() {
        if (!navigator.serviceWorker) return;
        
        const swContainer = navigator.serviceWorker;
        const originalRegister = swContainer.register;
        
        // تابع اصلی مرورگر را با نسخه سفارشی خودمان جایگزین می‌کنیم
        swContainer.register = function(scriptURL, options) {
            // اگر درخواست از طرف افزونه رقیب بود
            if (scriptURL.includes('<?php echo $conflicting_keyword; ?>')) {
                console.warn('WNW Hijacker: Competitor SW detected! Taking over.');
                // درخواست رقیب را با درخواست خودمان جایگزین می‌کنیم
                return originalRegister.call(swContainer, '<?php echo $sw_url; ?>', { scope: '<?php echo $correct_scope; ?>' });
            }
            // اگر درخواست از طرف خودمان یا افزونه دیگری بود، اجازه می‌دهیم انجام شود
            return originalRegister.apply(swContainer, arguments);
        };
    })();
    </script>
    <?php
}

/**
 * مرحله ۳: بارگذاری اسکریپت اصلی برای مدیریت دکمه اشتراک.
 */
add_action('wp_enqueue_scripts', 'wnw_enqueue_frontend_scripts');
function wnw_enqueue_frontend_scripts() {
    wp_enqueue_script(
        'wnw-subscribe-js',
        WNW_URL . 'assets/js/wnw-subscribe.js',
        [],
        WNW_VERSION,
        true
    );

    // ساخت کد امنیتی صحیح برای جلوگیری از خطای 403
    $wnw_data = [
        'ajax_url'            => admin_url('admin-ajax.php'),
        'nonce'               => wp_create_nonce('wnw_subscribe_nonce'),
        'public_key'          => get_option('wnw_settings', [])['public_key'] ?? ''
    ];

    wp_add_inline_script(
        'wnw-subscribe-js',
        'const wnw_data = ' . json_encode($wnw_data) . ';',
        'before'
    );
}