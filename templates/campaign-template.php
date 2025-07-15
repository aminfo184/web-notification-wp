<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<div class="wrap wnw-campaign-wrap">
    <h1>ارسال کمپین جدید</h1>
    <p>در این صفحه می‌توانید یک نوتیفیکیشن جدید برای مشترکین خود ارسال یا زمان‌بندی کنید.</p>

    <div id="wnw-ajax-message" style="display:none;"></div>

    <form id="wnw-campaign-form">
        <?php wp_nonce_field('wnw_campaign_nonce', 'nonce'); ?>

        <!-- Step 1: Select Template -->
        <div class="postbox">
            <div class="postbox-header"><h2><span class="step-number">۱</span>انتخاب قالب نوتیفیکیشن</h2></div>
            <div class="inside">
                <p>قالبی که می‌خواهید ارسال شود را انتخاب کنید. می‌توانید از قالب‌هایی که در صفحه <a href="<?php echo admin_url('admin.php?page=wnw-notifications'); ?>" target="_blank">قالب‌های نوتیفیکیشن</a> ساخته‌اید، استفاده کنید.</p>
                <select id="wnw-template-selector" name="template_id" style="width: 100%;" required>
                    <option></option>
                </select>
            </div>
        </div>

        <!-- Step 2: Select Audience -->
        <div class="postbox">
            <div class="postbox-header"><h2><span class="step-number">۲</span>انتخاب مخاطبان</h2></div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">ارسال به</th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="target_type" value="all" checked> همه مشترکین</label><br>
                                <label><input type="radio" name="target_type" value="registered"> فقط کاربران عضو (با اشتراک فعال)</label><br>
                                <label><input type="radio" name="target_type" value="guests"> فقط کاربران مهمان</label><br>
                                <label><input type="radio" name="target_type" value="specific_users"> کاربران خاص (با اشتراک فعال)</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr id="wnw-specific-users-row" class="hidden">
                        <th scope="row"><label for="wnw-users-selector">انتخاب کاربران</label></th>
                        <td>
                            <select id="wnw-users-selector" name="target_users[]" multiple="multiple" style="width: 100%;"></select>
                            <p class="description">نام، نام کاربری یا ایمیل کاربر مورد نظر را جستجو کنید.</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Step 3: Schedule & Delivery -->
        <div class="postbox">
            <div class="postbox-header"><h2><span class="step-number">۳</span>زمان‌بندی و تحویل</h2></div>
            <div class="inside">
                 <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wnw-schedule-time">زمان ارسال</label></th>
                        <td>
                            <input type="datetime-local" id="wnw-schedule-time" name="scheduled_for">
                            <p class="description">اگر خالی بماند، نوتیفیکیشن‌ها بلافاصله پس از تایید، در صف ارسال قرار می‌گیرند.</p>
                        </td>
                    </tr>
                 </table>
            </div>
        </div>
        
        <!-- Step 4: Review and Send -->
        <div class="postbox">
             <div class="postbox-header"><h2><span class="step-number">۴</span>تایید و ارسال</h2></div>
             <div class="inside">
                <p>لطفاً تنظیمات کمپین را بازبینی کرده و در صورت تایید، دکمه "افزودن به صف ارسال" را بزنید.</p>
                <button type="submit" class="button button-primary button-large">افزودن به صف ارسال</button>
                <span class="spinner"></span>
             </div>
        </div>
    </form>
</div>

<style>
.wnw-campaign-wrap .postbox { margin-top: 20px; }
.wnw-campaign-wrap .postbox-header h2 { font-size: 16px; padding: 10px 12px; margin: 0; display: flex; align-items: center; gap: 8px; }
.wnw-campaign-wrap .step-number { background: #007cba; color: #fff; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }
.wnw-campaign-wrap .form-table th { width: 150px; }
.hidden { display: none; }
.select2-container--default .select2-selection--multiple { border: 1px solid #8c8f94; }
</style>

<script>
jQuery(document).ready(function($) {
    // --- Initialize Select2 ---
    $('#wnw-template-selector').select2({
        placeholder: 'جستجو و انتخاب قالب...',
        allowClear: true,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'wnw_search_templates',
                    nonce: $('#nonce').val(),
                    q: params.term
                };
            },
            processResults: function(data) { return { results: data.results }; },
            cache: true
        }
    });

    $('#wnw-users-selector').select2({
        placeholder: 'جستجوی کاربر...',
        minimumInputLength: 2,
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'wnw_search_users',
                    nonce: $('#nonce').val(),
                    q: params.term
                };
            },
            processResults: function(data) { return data; }
        }
    });

    // --- UI Logic ---
    $('input[name="target_type"]').on('change', function() {
        if ($(this).val() === 'specific_users') {
            $('#wnw-specific-users-row').removeClass('hidden');
        } else {
            $('#wnw-specific-users-row').addClass('hidden');
        }
    });
    
    // --- Form Submission ---
    $('#wnw-campaign-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!$('#wnw-template-selector').val()) {
            alert('لطفاً یک قالب نوتیفیکیشن انتخاب کنید.');
            return;
        }
        
        if (!confirm('آیا از افزودن این کمپین به صف ارسال اطمینان دارید؟')) return;

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $spinner = $form.find('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        
        const data = $form.serialize() + '&action=wnw_queue_campaign';

        $.post(ajaxurl, data)
            .done(function(response) {
                const messageType = response.success ? 'success' : 'error';
                $('#wnw-ajax-message').html(`<div class="notice notice-${messageType} is-dismissible"><p>${response.data.message}</p></div>`).show();
                
                if(response.success) {
                    $form[0].reset();
                    $('#wnw-template-selector, #wnw-users-selector').val(null).trigger('change');
                }
                $('html, body').animate({ scrollTop: 0 }, 'slow');
            })
            .fail(function() {
                alert('خطا در ارتباط با سرور.');
            })
            .always(function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
    });
});
</script>
