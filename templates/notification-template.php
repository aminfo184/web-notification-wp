<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

extract($data_to_pass);
$total_pages = ceil($total_items / $per_page);
?>
<div class="wrap">
    <h1 class="wp-heading-inline">قالب‌های نوتیفیکیشن</h1>
    <a href="#" id="wnw-add-new-template" class="page-title-action">افزودن قالب جدید</a>

    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="wnw-notifications">
        <p class="search-box">
            <label class="screen-reader-text" for="post-search-input">جستجوی قالب‌ها:</label>
            <input type="search" id="post-search-input" name="search" value="<?php echo esc_attr($search_term); ?>">
            <input type="submit" id="search-submit" class="button" value="جستجوی قالب‌ها">
        </p>
    </form>

    <div id="wnw-ajax-message" style="display:none;"></div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <!-- NEW: Image column -->
                <th scope="col" class="manage-column column-image" style="width: 60px;">تصویر</th>
                <th scope="col" class="manage-column column-title column-primary">نام داخلی</th>
                <th scope="col" class="manage-column">عنوان نوتیفیکیشن</th>
                <th scope="col" class="manage-column">تاریخ ایجاد</th>
                <th scope="col" class="manage-column">وضعیت</th>
                <th scope="col" class="manage-column">آمار (ارسالی / ناموفق)</th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if (empty($templates)): ?>
                <tr class="no-items"><td class="colspanchange" colspan="6">هیچ قالبی یافت نشد. برای شروع، یک قالب جدید اضافه کنید.</td></tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <?php echo wnw_render_template_row($template); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $total_items; ?> مورد</span>
                <span class="pagination-links"><?php echo paginate_links(['base' => add_query_arg(['paged' => '%#%', 'search' => $search_term]), 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $total_pages, 'current' => $current_page]); ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="wnw-template-modal" class="wnw-modal hidden">
    <div class="wnw-modal-backdrop"></div>
    <div class="wnw-modal-content">
        <form id="wnw-template-form">
            <div class="wnw-modal-header">
                <h2 id="wnw-modal-title">افزودن قالب جدید</h2>
                <!-- NEW: Close button with SVG -->
                <button type="button" class="wnw-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="wnw-modal-body">
                <input type="hidden" id="wnw-template-id" name="template_id" value="0">
                <?php wp_nonce_field('wnw_template_nonce', 'nonce'); ?>
                
                <div class="form-field">
                    <label for="wnw-internal-name">نام داخلی *</label>
                    <input type="text" id="wnw-internal-name" name="template[internal_name]" required>
                </div>
                <div class="form-field">
                    <label for="wnw-title">عنوان نوتیفیکیشن *</label>
                    <input type="text" id="wnw-title" name="template[title]" required maxlength="50">
                </div>
                <div class="form-field">
                    <label for="wnw-message">متن پیام *</label>
                    <textarea id="wnw-message" name="template[message]" rows="4" required maxlength="150"></textarea>
                </div>
                <div class="form-field">
                    <label for="wnw-url">آدرس URL (اختیاری)</label>
                    <input type="url" id="wnw-url" name="template[url]" placeholder="<?php echo esc_url(get_home_url()); ?>">
                </div>
                <div class="form-field">
                    <label for="wnw-image">تصویر بزرگ (اختیاری)</label>
                    <div class="wnw-image-uploader">
                        <input type="text" id="wnw-image" name="template[image]" placeholder="آدرس URL تصویر">
                        <button type="button" id="wnw-upload-image-btn" class="button">انتخاب تصویر</button>
                    </div>
                    <!-- NEW: Image preview container -->
                    <div id="wnw-image-preview-container"></div>
                </div>
            </div>
            <div class="wnw-modal-footer">
                <span class="spinner"></span>
                <button type="button" class="button button-secondary wnw-modal-close">انصراف</button>
                <button type="submit" class="button button-primary">ذخیره قالب</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Table thumbnail style */
.column-image .wnw-template-thumbnail { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
/* Modal styles */
.wnw-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 10001; display: flex; align-items: center; justify-content: center; }
.wnw-modal.hidden { display: none; }
.wnw-modal-backdrop { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); }
.wnw-modal-content { position: relative; background: #fff; width: 90%; max-width: 600px; border-radius: 4px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
.wnw-modal-header { padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
.wnw-modal-header h2 { margin: 0; }
.wnw-modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
.wnw-modal-body .form-field { margin-bottom: 15px; }
.wnw-modal-body label { font-weight: 600; display: block; margin-bottom: 5px; }
.wnw-modal-body input[type="text"], .wnw-modal-body input[type="url"], .wnw-modal-body textarea { width: 100%; }
.wnw-modal-footer { padding: 15px 20px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
.wnw-modal-footer .spinner { order: -1; margin: 0; }
.wnw-modal-close { border: none; background: none; cursor: pointer; padding: 5px; line-height: 1; color: #666; }
.wnw-modal-close:hover { color: #000; }
.wnw-modal-close > svg { width: 1.25rem; height: 1.25rem; transform: translateY(3px); }
.wnw-image-uploader { display: flex; gap: 10px; }
#wnw-image-preview-container img { max-width: 100px; height: auto; border-radius: 4px; margin-top: 10px; border: 1px solid #ddd; }
tr.new-item { background-color: #f0fff0 !important; }
tr.new-item td { border-color: #c7e9c7 !important; transition: background-color 2s; }
</style>

<script>
jQuery(document).ready(function($) {
    const $modal = $('#wnw-template-modal');
    const $form = $('#wnw-template-form');
    const $tableBody = $('#the-list');
    let wnw_media_uploader;

    function showMessage(message, type = 'success') {
        $('#wnw-ajax-message').html(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`).show();
    }
    
    function resetForm() {
        $form[0].reset();
        $('#wnw-template-id').val('0');
        $('#wnw-modal-title').text('افزودن قالب جدید');
        $('#wnw-image-preview-container').empty(); // Clear image preview
    }

    $('#wnw-add-new-template').on('click', function(e) {
        e.preventDefault();
        resetForm();
        $modal.removeClass('hidden');
    });

    $tableBody.on('click', '.edit-template', function(e) {
        e.preventDefault();
        resetForm();
        const templateId = $(this).data('id');
        
        $.post(ajaxurl, {
            action: 'wnw_handle_template_action',
            sub_action: 'get_template',
            template_id: templateId,
            nonce: $('#nonce').val()
        })
        .done(function(response) {
            if (response.success) {
                const data = response.data;
                $('#wnw-modal-title').text('ویرایش قالب');
                $('#wnw-template-id').val(data.id);
                $('#wnw-internal-name').val(data.internal_name);
                $('#wnw-title').val(data.title);
                $('#wnw-message').val(data.message);
                $('#wnw-url').val(data.url);
                $('#wnw-image').val(data.image);
                if (data.image) {
                    $('#wnw-image-preview-container').html('<img src="' + data.image + '">');
                }
                $modal.removeClass('hidden');
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });

    $('.wnw-modal-close, .wnw-modal-backdrop').on('click', function() { $modal.addClass('hidden'); });

    $form.on('submit', function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const $spinner = $(this).find('.spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        const data = $(this).serialize() + '&action=wnw_handle_template_action&sub_action=save';

        $.post(ajaxurl, data)
        .done(function(response) {
            if (response.success) {
                const template = response.data.template;
                const newRowHtml = response.data.rendered_row;
                const $newRow = $(newRowHtml);
                
                if ($('#template-' + template.id).length) {
                    $('#template-' + template.id).replaceWith($newRow);
                } else {
                    $tableBody.prepend($newRow);
                    $('.no-items').remove();
                }

                $newRow.addClass('new-item');
                setTimeout(() => $newRow.removeClass('new-item'), 2000);

                $modal.addClass('hidden');
                showMessage(response.data.message, 'success');
            } else {
                alert(response.data.message);
            }
        })
        .fail(function() { alert('خطا در ارتباط با سرور.'); })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $tableBody.on('click', '.delete-template', function(e) {
        e.preventDefault();
        if (!confirm('آیا از حذف این قالب اطمینان دارید؟')) return;
        
        const templateId = $(this).data('id');
        const $row = $('#template-' + templateId);

        $.post(ajaxurl, {
            action: 'wnw_handle_template_action',
            sub_action: 'delete',
            template_id: templateId,
            nonce: $('#nonce').val()
        })
        .done(function(response) {
            if (response.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
                showMessage(response.data.message, 'success');
            } else {
                showMessage(response.data.message, 'error');
            }
        });
    });

    // FIX: Correct and robust media uploader script based on official WordPress documentation.
    $('#wnw-upload-image-btn').on('click', function(e) {
        e.preventDefault();
        
        if (wnw_media_uploader) {
            wnw_media_uploader.open();
            return;
        }

        wnw_media_uploader = wp.media({
            title: 'انتخاب تصویر بزرگ',
            button: { text: 'انتخاب این تصویر' },
            multiple: false
        });

        wnw_media_uploader.on('select', function() {
            const attachment = wnw_media_uploader.state().get('selection').first().toJSON();
            $('#wnw-image').val(attachment.url);
            $('#wnw-image-preview-container').html('<img src="' + attachment.url + '">');
        });

        wnw_media_uploader.open();
    });
});
</script>
