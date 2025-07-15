<?php
if (!defined('ABSPATH')) {
    exit;
}

// دریافت آمار
global $wpdb;

$stats = $wpdb->get_row(
    "SELECT 
         COUNT(*) as total_notifications,
         SUM(total_sent) as total_sent,
         SUM(total_failed) as total_failed
     FROM {$wpdb->prefix}wn_notifications"
);

$queue_stats = $wpdb->get_row(
    "SELECT 
         COUNT(*) as total_queue,
         SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
         SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
         SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
         SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
     FROM {$wpdb->prefix}wn_queue"
);

$subscribers_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wn_subscriptions WHERE status = 'active'"
);

$process_status = get_option('wnw_process_status', 'stopped');
$last_process_time = get_option('wnw_process_status_time');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('wnw_messages'); ?>

    <div class="wnw-dashboard-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

        <!-- آمار کلی -->
        <div class="wnw-stats-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2>آمار کلی</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="stat-item">
                    <h3 style="margin: 0; font-size: 24px; color: #0073aa;"><?php echo number_format($subscribers_count); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">مشترک فعال</p>
                </div>
                <div class="stat-item">
                    <h3 style="margin: 0; font-size: 24px; color: #46b450;"><?php echo number_format($stats->total_sent ?? 0); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">ارسال موفق</p>
                </div>
                <div class="stat-item">
                    <h3 style="margin: 0; font-size: 24px; color: #dc3232;"><?php echo number_format($stats->total_failed ?? 0); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">ارسال ناموفق</p>
                </div>
                <div class="stat-item">
                    <h3 style="margin: 0; font-size: 24px; color: #ffb900;"><?php echo number_format($stats->total_notifications ?? 0); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">کل قالب‌ها</p>
                </div>
            </div>
        </div>

        <!-- کنترل پردازش -->
        <div class="wnw-process-control" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2>کنترل سیستم ارسال</h2>

            <div class="process-status" style="margin-bottom: 15px;">
                <h4>وضعیت فعلی:
                    <span id="process-status-text" style="color: <?php echo $process_status === 'running' ? '#46b450' : '#dc3232'; ?>">
                        <?php echo $process_status === 'running' ? 'در حال اجرا' : 'متوقف'; ?>
                    </span>
                </h4>
                <?php if ($last_process_time): ?>
                    <p style="margin: 5px 0; color: #666;">آخرین به‌روزرسانی: <?php echo $last_process_time; ?></p>
                <?php endif; ?>
            </div>

            <div class="process-controls" style="margin-bottom: 15px;">
                <button type="button" id="start-processing" class="button button-primary"
                    <?php echo $process_status === 'running' ? 'disabled' : ''; ?>>
                    شروع پردازش
                </button>
                <button type="button" id="stop-processing" class="button button-secondary"
                    <?php echo $process_status === 'stopped' ? 'disabled' : ''; ?>>
                    توقف پردازش
                </button>
                <button type="button" id="refresh-status" class="button">
                    به‌روزرسانی وضعیت
                </button>
            </div>

            <div class="process-stats" id="process-stats">
                <h4>آمار صف ارسال:</h4>
                <ul style="margin: 10px 0;">
                    <li>در انتظار: <span id="queued-count"><?php echo number_format($queue_stats->queued ?? 0); ?></span></li>
                    <li>در حال پردازش: <span id="processing-count"><?php echo number_format($queue_stats->processing ?? 0); ?></span></li>
                    <li>ارسال شده: <span id="sent-count"><?php echo number_format($queue_stats->sent ?? 0); ?></span></li>
                    <li>ناموفق: <span id="failed-count"><?php echo number_format($queue_stats->failed ?? 0); ?></span></li>
                </ul>
            </div>
        </div>

        <!-- تنظیمات سریع -->
        <div class="wnw-quick-settings" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2>تنظیمات سریع</h2>

            <?php
            $settings = get_option('wnw_settings', []);
            $batch_size = $settings['batch_size'] ?? 100;
            ?>

            <div class="setting-item" style="margin-bottom: 15px;">
                <label for="batch-size">تعداد ارسال در هر دقیقه:</label>
                <input type="number" id="batch-size" value="<?php echo $batch_size; ?>" min="1" max="500" style="width: 80px;">
                <button type="button" id="save-batch-size" class="button button-small">ذخیره</button>
            </div>

            <div class="quick-actions">
                <h4>عملیات سریع:</h4>
                <a href="<?php echo admin_url('admin.php?page=wnw-notification'); ?>" class="button">ایجاد نوتیفیکیشن</a>
                <a href="<?php echo admin_url('admin.php?page=wnw-campaign'); ?>" class="button">ارسال کمپین</a>
                <a href="<?php echo admin_url('admin.php?page=wnw-settings'); ?>" class="button">تنظیمات</a>
            </div>
        </div>

        <!-- راهنما سریع -->
        <div class="wnw-quick-help" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2>راهنمای سریع</h2>

            <div class="api-endpoints">
                <h4>API Endpoints:</h4>
                <code style="background: #f9f9f9; padding: 10px; display: block; margin: 10px 0; border-radius: 3px;">
                    POST <?php echo home_url('/wp-json/wnw/v1/send/user'); ?><br>
                    Body: {"user_id": 1, "title": "عنوان", "message": "پیام"}
                </code>

                <code style="background: #f9f9f9; padding: 10px; display: block; margin: 10px 0; border-radius: 3px;">
                    POST <?php echo home_url('/wp-json/wnw/v1/send/custom'); ?><br>
                    Body: {"title": "عنوان", "message": "پیام", "user_ids": "all"}
                </code>
            </div>

            <div class="troubleshooting" style="margin-top: 15px;">
                <h4>رفع مشکل:</h4>
                <ul style="font-size: 13px; color: #666;">
                    <li>اگر نوتیفیکیشن ارسال نمی‌شود، ابتدا کلیدهای VAPID را بررسی کنید</li>
                    <li>مطمئن شوید که مرورگر اجازه نمایش نوتیفیکیشن را داده باشد</li>
                    <li>در صورت مشکل، سیستم ارسال را متوقف و مجدداً شروع کنید</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // متغیرهای عمومی
        const nonce = '<?php echo wp_create_nonce('wnw_process_nonce'); ?>';

        // شروع پردازش
        $('#start-processing').click(function() {
            const button = $(this);
            button.prop('disabled', true).text('در حال شروع...');

            $.post(ajaxurl, {
                action: 'wnw_start_processing',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    updateProcessStatus();
                    alert('پردازش شروع شد');
                } else {
                    alert('خطا: ' + response.data);
                }
            }).always(function() {
                button.prop('disabled', false).text('شروع پردازش');
            });
        });

        // توقف پردازش
        $('#stop-processing').click(function() {
            const button = $(this);
            button.prop('disabled', true).text('در حال توقف...');

            $.post(ajaxurl, {
                action: 'wnw_stop_processing',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    updateProcessStatus();
                    alert('پردازش متوقف شد');
                } else {
                    alert('خطا: ' + response.data);
                }
            }).always(function() {
                button.prop('disabled', false).text('توقف پردازش');
            });
        });

        // به‌روزرسانی وضعیت
        $('#refresh-status').click(function() {
            updateProcessStatus();
        });

        // ذخیره batch size
        $('#save-batch-size').click(function() {
            const batchSize = $('#batch-size').val();
            const button = $(this);

            button.prop('disabled', true).text('در حال ذخیره...');

            $.post(ajaxurl, {
                action: 'wnw_handle_settings_actions',
                submit_action: 'save_settings',
                wnw_nonce_field: '<?php echo wp_create_nonce('wnw_settings_action'); ?>',
                'settings[batch_size]': batchSize
            }, function(response) {
                if (response.success) {
                    alert('تعداد ارسال ذخیره شد');
                } else {
                    alert('خطا در ذخیره: ' + response.data.message);
                }
            }).always(function() {
                button.prop('disabled', false).text('ذخیره');
            });
        });

        // تابع به‌روزرسانی وضعیت
        function updateProcessStatus() {
            $.post(ajaxurl, {
                action: 'wnw_get_process_status',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    const data = response.data;

                    // به‌روزرسانی وضعیت
                    const statusText = data.status === 'running' ? 'در حال اجرا' : 'متوقف';
                    const statusColor = data.status === 'running' ? '#46b450' : '#dc3232';
                    $('#process-status-text').text(statusText).css('color', statusColor);

                    // به‌روزرسانی دکمه‌ها
                    $('#start-processing').prop('disabled', data.status === 'running');
                    $('#stop-processing').prop('disabled', data.status === 'stopped');

                    // به‌روزرسانی آمار
                    if (data.stats) {
                        $('#queued-count').text(data.stats.queued || 0);
                        $('#processing-count').text(data.stats.processing || 0);
                        $('#sent-count').text(data.stats.sent || 0);
                        $('#failed-count').text(data.stats.failed || 0);
                    }
                }
            });
        }

        // به‌روزرسانی خودکار هر 30 ثانیه
        setInterval(updateProcessStatus, 30000);
    });
</script>

<style>
    .wnw-dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    @media (max-width: 768px) {
        .wnw-dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-item h3 {
        margin: 0;
        font-size: 24px;
    }

    .stat-item p {
        margin: 5px 0 0 0;
        color: #666;
        font-size: 14px;
    }

    .process-controls button {
        margin-left: 5px;
    }

    .quick-actions a {
        margin-left: 5px;
        margin-bottom: 5px;
        display: inline-block;
    }

    code {
        font-size: 12px;
        line-height: 1.4;
    }
</style>