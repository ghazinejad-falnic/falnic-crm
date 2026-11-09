<div class="wrap">
    <h1><?php esc_html_e( 'تنظیمات اتصال به CRM فالنیک', 'falnic-crm' ); ?></h1>
    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات با موفقیت ذخیره شد.', 'falnic-crm' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="falnic-crm-settings-form">
        <?php wp_nonce_field( 'falnic_crm_settings' ); ?>
        <input type="hidden" name="falnic_crm_save_settings" value="1"/>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'آدرس Endpoint CRM', 'falnic-crm' ); ?></th>
                <td>
                    <input type="url" name="falnic_crm_endpoint_url" class="regular-text" value="<?php echo esc_attr( $endpoint ); ?>" placeholder="https://api.falnic.com/apiCrm/public/api/v1/crm"/>
                    <p class="description"><?php esc_html_e( 'آدرس end point ارسال لید به CRM فالنیک (مثال: https://api.falnic.com/apiCrm/public/api/v1/crm).', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'کلید API (X-API-KEY)', 'falnic-crm' ); ?></th>
                <td>
                    <input type="text" name="falnic_crm_api_key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" placeholder="X-API-KEY"/>
                    <p class="description"><?php esc_html_e( 'مقدار هدر X-API-KEY. این مقدار در پایگاه داده رمزگذاری می‌شود.', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'سایر هدرها', 'falnic-crm' ); ?></th>
                <td>
                    <textarea name="falnic_crm_headers" class="code" rows="4"><?php echo esc_textarea( $headers ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'هر هدر در یک خط به صورت نام: مقدار (مثال: X-ALLOW-CRM-CORE-WRITE: 1).', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'حداکثر رویداد در هر پردازش', 'falnic-crm' ); ?></th>
                <td>
                    <input type="number" name="falnic_crm_max_events_per_run" min="1" value="<?php echo esc_attr( $max ); ?>"/>
                    <p class="description"><?php esc_html_e( 'تعداد رویدادهایی که در هر اجرا از Cron بررسی و ارسال می‌شود.', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'حد آستانه هشدار صف', 'falnic-crm' ); ?></th>
                <td>
                    <input type="number" name="falnic_crm_queue_alert_threshold" min="1" value="<?php echo esc_attr( $threshold ); ?>"/>
                    <p class="description"><?php esc_html_e( 'اگر رویدادهای در انتظار بیشتر از این عدد باشند، به مدیر هشدار ارسال می‌شود.', 'falnic-crm' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" class="button button-secondary" id="falnic-crm-test-connection"><?php esc_html_e( 'تست اتصال به CRM', 'falnic-crm' ); ?></button>
            <span id="falnic-crm-test-result" style="margin-left:10px;"></span>
            <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'ذخیره تنظیمات', 'falnic-crm' ); ?>"/>
        </p>
    </form>
</div>

<script>
jQuery(function($){
    $('#falnic-crm-test-connection').on('click', function(){
        var $btn = $(this);
        var $result = $('#falnic-crm-test-result');
        $btn.prop('disabled', true).text('<?php esc_attr_e( 'در حال بررسی...', 'falnic-crm' ); ?>');
        $result.html('');
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_test_connection',
            _wpnonce: falnicCrm.nonce,
            endpoint_url: $('input[name="falnic_crm_endpoint_url"]').val(),
            api_key: $('input[name="falnic_crm_api_key"]').val(),
            headers: $('textarea[name="falnic_crm_headers"]').val()
        }, function(r){
            $btn.prop('disabled', false).text('<?php esc_attr_e( 'تست اتصال به CRM', 'falnic-crm' ); ?>');
            if (r.success) {
                $result.html('<span style="color:green"><strong>✓ ' + r.data.message + '</strong></span>');
            } else {
                $result.html('<span style="color:red"><strong>✗ ' + r.data.message + '</strong></span>');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('<?php esc_attr_e( 'تست اتصال به CRM', 'falnic-crm' ); ?>');
            $result.html('<span style="color:red"><strong>✗ ' + '<?php esc_js(__('خطا در ارتباط با سرور.', 'falnic-crm')) ?>' + '</strong></span>');
        });
    });
});
</script>
