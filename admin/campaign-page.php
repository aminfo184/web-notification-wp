<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// ... (کد ثبت منو و اسکریپت‌ها بدون تغییر) ...
function wnw_add_campaign_page() {
    $hook_suffix = add_submenu_page(
        'web-notification-wp-dashboard', 'ارسال جدید', 'ارسال جدید', 'manage_options', 'wnw-send-campaign', 'wnw_campaign_page_render'
    );
    add_action('admin_enqueue_scripts', function($hook) use ($hook_suffix) {
        if ($hook !== $hook_suffix) return;
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0-rc.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0-rc.0', true);
    });
}

function wnw_campaign_page_render() {
    if (!current_user_can('manage_options')) wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
    require_once WNW_PATH . 'templates/campaign-template.php';
}

add_action('wp_ajax_wnw_search_templates', 'wnw_ajax_search_templates');
function wnw_ajax_search_templates() {
    check_ajax_referer('wnw_campaign_nonce', 'nonce');
    global $wpdb;
    $table_name = $wpdb->prefix . 'wn_notifications';
    $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $like = '%' . $wpdb->esc_like($search) . '%';
    $results = $wpdb->get_results($wpdb->prepare("SELECT id, internal_name as text FROM {$table_name} WHERE status = 'active' AND internal_name LIKE %s ORDER BY id DESC LIMIT 20", $like));
    wp_send_json(['results' => $results]);
}

add_action('wp_ajax_wnw_queue_campaign', 'wnw_ajax_queue_campaign');
function wnw_ajax_queue_campaign() {
    check_ajax_referer('wnw_campaign_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);

    global $wpdb;
    $subs_table = $wpdb->prefix . 'wn_subscriptions';
    $queue_table = $wpdb->prefix . 'wn_queue';

    $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    $target_type = isset($_POST['target_type']) ? sanitize_key($_POST['target_type']) : 'all';
    $target_users = isset($_POST['target_users']) && is_array($_POST['target_users']) ? array_map('absint', $_POST['target_users']) : [];
    $scheduled_for = isset($_POST['scheduled_for']) && !empty($_POST['scheduled_for']) ? sanitize_text_field($_POST['scheduled_for']) : current_time('mysql');

    if (!$template_id) wp_send_json_error(['message' => 'لطفاً یک قالب نوتیفیکیشن انتخاب کنید.']);

    $where_clause = "status = 'active'";
    $params = [];
    switch ($target_type) {
        case 'guests': $where_clause .= " AND user_id = 0"; break;
        case 'registered': $where_clause .= " AND user_id > 0"; break;
        case 'specific_users':
            if (empty($target_users)) wp_send_json_error(['message' => 'لطفاً حداقل یک کاربر را برای ارسال انتخاب کنید.']);
            $placeholders = implode(',', array_fill(0, count($target_users), '%d'));
            $where_clause .= " AND user_id IN ({$placeholders})";
            $params = $target_users;
            break;
    }
    
    $subscriber_ids_query = "SELECT id FROM {$subs_table} WHERE {$where_clause}";
    if (!empty($params)) $subscriber_ids_query = $wpdb->prepare($subscriber_ids_query, ...$params);
    $subscriber_ids = $wpdb->get_col($subscriber_ids_query);

    if (empty($subscriber_ids)) wp_send_json_error(['message' => 'هیچ مشترک فعالی با شرایط انتخابی شما یافت نشد.']);

    $values = [];
    $placeholders = [];
    foreach ($subscriber_ids as $sub_id) {
        $placeholders[] = '(%d, %d, %s, %s)';
        array_push($values, $template_id, $sub_id, 'queued', $scheduled_for);
    }

    $query = "INSERT INTO {$queue_table} (notification_id, subscription_id, status, scheduled_for) VALUES " . implode(', ', $placeholders);
    $result = $wpdb->query($wpdb->prepare($query, $values));

    if ($result === false) {
        wp_send_json_error(['message' => 'خطا در افزودن نوتیفیکیشن‌ها به صف ارسال.']);
    }

    // FINAL: Trigger the background process immediately after queueing.
    // این تابع موتور ارسال را بلافاصله بیدار می‌کند.
    wnw_spawn_runner();

    wp_send_json_success(['message' => count($subscriber_ids) . ' نوتیفیکیشن با موفقیت در صف ارسال قرار گرفت و پردازش آن بلافاصله آغاز شد.']);
}


