<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
extract($data_to_pass);
?>
<div class="wrap wnw-settings-wrap">
    <h1>تنظیمات وب نوتیفیکیشن</h1>
    <p>در این بخش می‌توانید تنظیمات اصلی پلاگین و کلیدهای VAPID را مدیریت کنید.</p>

    <div id="wnw-ajax-message"></div>

    <form id="wnw-settings-form">
        <!-- A single, unified nonce field for the entire form. -->
        <?php wp_nonce_field('wnw_settings_action', 'wnw_nonce_field'); ?>

        <div class="wnw-settings-card">
            <h2 class="title">پیکربندی VAPID</h2>
            <p class="description">این کلیدها برای احراز هویت درخواست‌های شما به سرویس‌های Push استفاده می‌شوند. <strong>تغییر این کلیدها باعث می‌شود تمام مشترکین فعلی شما غیرفعال شوند.</strong></p>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="wnw_public_key">کلید عمومی (Public Key)</label></th>
                        <td>
                            <textarea id="wnw_public_key" readonly rows="3"><?php echo esc_textarea($public_key); ?></textarea>
                            <br>
                            <button type="button" class="button button-secondary copy-btn" data-target="#wnw_public_key">کپی</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wnw_private_key">کلید خصوصی (Private Key)</label></th>
                        <td><textarea id="wnw_private_key" readonly rows="3"><?php echo esc_textarea($private_key); ?></textarea></td>
                    </tr>
                     <tr>
                        <th scope="row"><label for="wnw_email">ایمیل (VAPID Subject)</label></th>
                        <td>
                            <input type="email" id="wnw_email" name="settings[email]" value="<?php echo esc_attr($email); ?>" placeholder="مثال: mailto:admin@yoursite.com">
                            <p class="description">این ایمیل به عنوان بخشی از احراز هویت VAPID استفاده می‌شود.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <!-- NEW: This is now a submit button with a specific name and value -->
            <p><button type="submit" name="submit_action" value="generate_keys" class="button button-secondary">ساخت کلیدهای جدید</button></p>
        </div>

        <div class="wnw-settings-card">
            <h2 class="title">تنظیمات عمومی و ظاهری</h2>
            <table class="form-table">
                 <tbody>
                    <tr>
                        <th scope="row"><label for="wnw_icon">آیکون پیش‌فرض نوتیفیکیشن</label></th>
                        <td>
                            <div class="wnw-image-uploader">
                                <input type="text" id="wnw_icon" name="settings[icon]" value="<?php echo esc_attr($icon); ?>" placeholder="برای استفاده از آیکون سایت، خالی بگذارید">
                                <button type="button" id="wnw_upload_icon_btn" class="button button-secondary">انتخاب تصویر</button>
                            </div>
                            <div id="wnw_icon_preview"><?php if ($icon): ?><img src="<?php echo esc_url($icon); ?>" alt="Icon Preview"><?php endif; ?></div>
                            <p class="description">توصیه شده: تصویر مربعی حداقل 192x192 پیکسل.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="wnw-settings-card">
            <h2 class="title">تنظیمات ارسال و زمان‌بندی</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="wnw_batch_size">اندازه بسته ارسالی</label></th>
                        <td>
                            <input type="number" id="wnw_batch_size" name="settings[batch_size]" class="small-text" value="<?php echo esc_attr($batch_size); ?>" min="10">
                            <p class="description">تعداد نوتیفیکیشن‌ها در هر دقیقه. (پیش‌فرض: 100)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wnw_ttl">عمر پیش‌فرض نوتیفیکیشن (TTL)</label></th>
                        <td>
                            <input type="number" id="wnw_ttl" name="settings[ttl]" class="small-text" value="<?php echo esc_attr($ttl); ?>" min="0">
                            <span>ثانیه</span>
                            <p class="description">مدت زمان اعتبار پیام برای کاربران آفلاین. (پیش‌فرض: 4 هفته)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wnw_urgency">فوریت پیش‌فرض</label></th>
                        <td>
                            <select id="wnw_urgency" name="settings[urgency]">
                                <option value="high" <?php selected($urgency, 'high'); ?>>بالا</option>
                                <option value="normal" <?php selected($urgency, 'normal'); ?>>معمولی</option>
                                <option value="low" <?php selected($urgency, 'low'); ?>>پایین</option>
                            </select>
                            <p class="description">اهمیت پیام برای بهینه‌سازی مصرف باتری.</p>
                        </td>
                    </tr>
                     <tr>
                        <th scope="row"><label for="wnw_gcm_key">کلید GCM/FCM (اختیاری)</label></th>
                        <td>
                            <input type="text" id="wnw_gcm_key" name="settings[gcm_key]" value="<?php echo esc_attr($gcm_key); ?>">
                            <p class="description">برای پشتیبانی از نسخه‌های قدیمی کروم. اگر ندارید، خالی بگذارید. (باید از Google Firebase دریافت کنید)</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="submit">
            <!-- NEW: This is now a submit button with a specific name and value -->
            <button type="submit" name="submit_action" value="save_settings" id="wnw-save-settings-btn" class="button button-primary button-large">ذخیره تمام تنظیمات</button>
            <span class="spinner"></span>
        </p>
    </form>
</div>

<style>
    .wnw-settings-wrap .form-table td input[type="text"],
    .wnw-settings-wrap .form-table td input[type="email"],
    .wnw-settings-wrap .form-table td textarea { width: 100%; max-width: 400px; }
    .wnw-settings-wrap .wnw-settings-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 1px 20px 20px; margin-top: 20px; }
    .wnw-settings-wrap .wnw-settings-card .title { padding-bottom: 10px; border-bottom: 1px solid #ddd; }
    .wnw-settings-wrap .form-table th { padding: 20px 10px 20px 0; width: 250px; }
    .wnw-settings-wrap .form-table td { padding: 15px 10px; }
    .wnw-settings-wrap .form-table textarea { direction: ltr; text-align: left; }
    .wnw-settings-wrap .copy-btn { vertical-align: middle; width: 100%; max-width: 400px; }
    .wnw-settings-wrap .wnw-image-uploader { display: flex; gap: 10px; align-items: center; }
    .wnw-settings-wrap #wnw_icon_preview img { max-width: 96px; height: auto; border: 1px solid #ddd; margin-top: 10px; border-radius: 5px; }
    #wnw-ajax-message .notice { margin: 5px 0 15px; }
    #wnw_ttl { width: 85px; }
</style>

<script>
jQuery(document).ready(function($) {
    function showMessage(message, type = 'success') {
        const className = type === 'success' ? 'notice-success' : 'notice-error';
        $('#wnw-ajax-message').html(`<div class="notice ${className} is-dismissible"><p>${message}</p></div>`);
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    }

    $('.copy-btn').on('click', function() {
        const target = $($(this).data('target'));
        target.select();
        document.execCommand('copy');
        showMessage('کلید عمومی کپی شد!', 'success');
    });

    let mediaUploader;
    $('#wnw_upload_icon_btn').on('click', function(e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }
        mediaUploader = wp.media({ title: 'انتخاب آیکون', button: { text: 'انتخاب' }, multiple: false });
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#wnw_icon').val(attachment.url);
            $('#wnw_icon_preview').html(`<img src="${attachment.url}" alt="Icon Preview">`);
        });
        mediaUploader.open();
    });

    // NEW: A single submit handler for the entire form.
    $('#wnw-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        // Determine which button was clicked
        const submitter = e.originalEvent.submitter;
        const $btn = $(submitter);
        const $spinner = $btn.siblings('.spinner');

        $btn.prop('disabled', true);
        if ($spinner.length) {
            $spinner.addClass('is-active');
        }

        const formData = $(this).serialize();
        // Add the submit button's value to the data
        const data = formData + '&action=wnw_handle_settings_actions&submit_action=' + $btn.val();

        $.post(ajaxurl, data)
        .done(function(response) {
            if (response.success) {
                showMessage(response.data.message, 'success');
                // Update fields if necessary (e.g., after generating keys)
                if (response.data.publicKey) {
                    $('#wnw_public_key').val(response.data.publicKey);
                    $('#wnw_private_key').val(response.data.privateKey);
                }
                // Update icon if it was changed to default
                if (response.data.new_icon) {
                    $('#wnw_icon_preview').html(`<img src="${response.data.new_icon}" alt="Icon Preview">`);
                    $('#wnw_icon').val(response.data.new_icon);
                }
            } else {
                const errorMessage = (response.data && response.data.message) ? response.data.message : 'یک خطای ناشناخته رخ داد.';
                showMessage(errorMessage, 'error');
            }
        })
        .fail(function() { showMessage('خطا در ارتباط با سرور.', 'error'); })
        .always(function() {
            $btn.prop('disabled', false);
            if ($spinner.length) {
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>
