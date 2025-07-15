<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Adds the "Subscribers" page to the main plugin menu.
 */
function wnw_add_subscribers_page() {
    add_submenu_page(
        'web-notification-wp-dashboard',
        'مشترکین',
        'مشترکین',
        'manage_options',
        'wnw-subscribers', // Page slug
        'wnw_subscribers_page_render'
    );
}

/**
 * Renders the content of the subscribers management page.
 */
function wnw_subscribers_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
    }

    global $wpdb;
    $subs_table = $wpdb->prefix . 'wn_subscriptions';
    $users_table = $wpdb->users;

    // Pagination parameters
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Filter and Search parameters
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $browser_filter = isset($_GET['browser']) ? sanitize_text_field($_GET['browser']) : '';
    $os_filter = isset($_GET['os']) ? sanitize_text_field($_GET['os']) : '';

    $where_clause = '1=1';
    $params = [];

    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_clause .= ' AND (sub.ip_address LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($status_filter)) {
        $where_clause .= ' AND sub.status = %s';
        $params[] = $status_filter;
    }
    
    // Add browser and OS filters to the query
    if (!empty($browser_filter)) {
        // We use LIKE because the version is stored with the name (e.g., "Chrome 126.0.0.0")
        $where_clause .= ' AND sub.browser LIKE %s';
        $params[] = $wpdb->esc_like($browser_filter) . '%';
    }

    if (!empty($os_filter)) {
        $where_clause .= ' AND sub.os = %s';
        $params[] = $os_filter;
    }

    // Get total items for pagination
    $total_query = "SELECT COUNT(sub.id) FROM {$subs_table} sub LEFT JOIN {$users_table} u ON sub.user_id = u.ID WHERE {$where_clause}";
    if (!empty($params)) {
        $total_query = $wpdb->prepare($total_query, ...$params);
    }
    $total_items = $wpdb->get_var($total_query);

    // Get subscribers for the current page
    $query = "
        SELECT sub.*, u.display_name, u.user_email
        FROM {$subs_table} as sub
        LEFT JOIN {$users_table} as u ON sub.user_id = u.ID
        WHERE {$where_clause}
        ORDER BY sub.id DESC
        LIMIT %d OFFSET %d
    ";
    $query_params = array_merge($params, [$per_page, $offset]);
    $subscribers = $wpdb->get_results($wpdb->prepare($query, ...$query_params));

    // Get available browsers and OSes for filter dropdowns
    $available_browsers = $wpdb->get_col("SELECT DISTINCT SUBSTRING_INDEX(browser, ' ', 1) FROM {$subs_table} WHERE browser != '' ORDER BY browser ASC");
    $available_os = $wpdb->get_col("SELECT DISTINCT os FROM {$subs_table} WHERE os != '' ORDER BY os ASC");

    $data_to_pass = [
        'subscribers'        => $subscribers,
        'total_items'        => $total_items,
        'per_page'           => $per_page,
        'current_page'       => $page,
        'search_term'        => $search,
        'status_filter'      => $status_filter,
        'browser_filter'     => $browser_filter,
        'os_filter'          => $os_filter,
        'available_browsers' => $available_browsers,
        'available_os'       => $available_os,
    ];

    require_once WNW_PATH . 'templates/subscribers-template.php';
}

/**
 * AJAX handler for deleting a subscriber.
 */
add_action('wp_ajax_wnw_delete_subscriber', 'wnw_ajax_delete_subscriber');
function wnw_ajax_delete_subscriber() {
    check_ajax_referer('wnw_subscriber_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'شناسه مشترک نامعتبر است.']);
    }

    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'wn_subscriptions', ['id' => $id]);

    if ($result) {
        wp_send_json_success(['message' => 'مشترک با موفقیت حذف شد.']);
    } else {
        wp_send_json_error(['message' => 'خطا در حذف مشترک.']);
    }
}
