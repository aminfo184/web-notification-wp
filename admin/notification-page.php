<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Adds the "Notification Templates" page to the main plugin menu.
 */
function wnw_add_notification_page() {
    $hook_suffix = add_submenu_page(
        'web-notification-wp-dashboard',
        'قالب‌های نوتیفیکیشن',
        'قالب‌ها',
        'manage_options',
        'wnw-notifications',
        'wnw_notification_templates_page_render'
    );

    // Use the $hook_suffix to conditionally load scripts only on this page.
    add_action('admin_enqueue_scripts', function($hook) use ($hook_suffix) {
        if ($hook !== $hook_suffix) {
            return;
        }
        // FIX: Enqueue media scripts required for the uploader. This is the correct way.
        wp_enqueue_media();
    });
}

/**
 * Renders the content of the notification templates management page.
 */
function wnw_notification_templates_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wn_notifications';

    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    
    $where_clause = '1=1';
    $params = [];
    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_clause .= ' AND (internal_name LIKE %s OR title LIKE %s OR message LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $total_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
    if (!empty($params)) {
        $total_query = $wpdb->prepare($total_query, ...$params);
    }
    $total_items = $wpdb->get_var($total_query);

    $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d";
    $query_params = array_merge($params, [$per_page, $offset]);
    $templates = $wpdb->get_results($wpdb->prepare($query, ...$query_params));

    $data_to_pass = [
        'templates'   => $templates,
        'total_items' => $total_items,
        'per_page'    => $per_page,
        'current_page'=> $page,
        'search_term' => $search,
    ];

    require_once WNW_PATH . 'templates/notification-template.php';
}

/**
 * Helper function to render a single row of the templates table.
 */
function wnw_render_template_row($template) {
    ob_start();
    ?>
    <tr id="template-<?php echo $template->id; ?>">
        <td class="column-image">
            <?php if (!empty($template->image)): ?>
                <img src="<?php echo esc_url($template->image); ?>" class="wnw-template-thumbnail" alt="پیش‌نمایش">
            <?php endif; ?>
        </td>
        <td class="title column-title has-row-actions column-primary">
            <strong><a href="#" class="edit-template" data-id="<?php echo $template->id; ?>"><?php echo esc_html($template->internal_name); ?></a></strong>
            <div class="row-actions">
                <span class="edit"><a href="#" class="edit-template" data-id="<?php echo $template->id; ?>">ویرایش</a> | </span>
                <span class="trash"><a href="#" class="delete-template" data-id="<?php echo $template->id; ?>">حذف</a></span>
            </div>
        </td>
        <td><?php echo esc_html($template->title); ?></td>
        <td><?php echo esc_html(date_i18n('Y/m/d', strtotime($template->created_at))); ?></td>
        <td><?php echo ($template->status === 'active') ? 'فعال' : 'بایگانی'; ?></td>
        <td><?php echo esc_html($template->total_sent); ?> / <?php echo esc_html($template->total_failed); ?></td>
    </tr>
    <?php
    return ob_get_clean();
}

/**
 * A single AJAX handler for all template actions.
 */
add_action('wp_ajax_wnw_handle_template_action', 'wnw_ajax_handle_template_action');
function wnw_ajax_handle_template_action() {
    check_ajax_referer('wnw_template_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $action = $_POST['sub_action'] ?? '';
    $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'wn_notifications';

    switch ($action) {
        case 'save':
            $data = $_POST['template'] ?? [];
            $internal_name = sanitize_text_field($data['internal_name'] ?? '');
            $title = sanitize_text_field($data['title'] ?? '');
            $message = sanitize_textarea_field($data['message'] ?? '');
            $url = !empty($data['url']) ? esc_url_raw($data['url']) : get_home_url();
            $image = esc_url_raw($data['image'] ?? '');

            if (empty($internal_name) || empty($title) || empty($message)) {
                wp_send_json_error(['message' => 'نام داخلی، عنوان و متن پیام الزامی هستند.']);
            }

            $template_data = compact('internal_name', 'title', 'message', 'url', 'image');

            if ($id > 0) {
                $wpdb->update($table_name, $template_data, ['id' => $id]);
            } else {
                $template_data['created_at'] = current_time('mysql');
                $wpdb->insert($table_name, $template_data);
                $id = $wpdb->insert_id;
            }

            $saved_template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
            if (!$saved_template) {
                wp_send_json_error(['message' => 'خطا در ذخیره یا بازیابی قالب.']);
            }
            
            $rendered_row = wnw_render_template_row($saved_template);
            wp_send_json_success(['message' => 'قالب با موفقیت ذخیره شد.', 'template' => $saved_template, 'rendered_row' => $rendered_row]);
            break;

        case 'delete':
            if ($id > 0) {
                $wpdb->delete($table_name, ['id' => $id]);
                wp_send_json_success(['message' => 'قالب با موفقیت حذف شد.']);
            }
            break;
        
        case 'get_template':
             if ($id > 0) {
                $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id), ARRAY_A);
                if ($template) {
                    wp_send_json_success($template);
                }
            }
            wp_send_json_error(['message' => 'قالب یافت نشد.']);
            break;

        default:
            wp_send_json_error(['message' => 'عملیات نامعتبر.']);
    }
}
