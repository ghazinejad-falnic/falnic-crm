<div class="wrap">
    <h1>
        <?php esc_html_e( 'مدیریت تریگرها', 'falnic-crm' ); ?>
        <a href="admin.php?page=falnic-crm-triggers&action=add" class="page-title-action"><?php esc_html_e( 'افزودن تریگر جدید', 'falnic-crm' ); ?></a>
    </h1>

    <?php if ( $message ) : ?>
        <div class="notice <?php echo esc_attr( $message_type ); ?> is-dismissible"><p><?php echo wp_kses_post( $message ); ?></p></div>
    <?php endif; ?>

    <form method="post" id="falnic-crm-triggers-list">
        <?php wp_nonce_field( 'falnic_crm_triggers' ); ?>

        <div class="tablenav top">
            <label class="screen-reader-text"><?php esc_html_e( 'جستجو', 'falnic-crm' ); ?></label>
            <input type="search" name="s" class="search-input" placeholder="<?php esc_attr_e( 'جستجو در عناوین تریگر...', 'falnic-crm' ); ?>" value="<?php echo esc_attr( $search ); ?>"/>
            <?php submit_button( __( 'جستجو', 'falnic-crm' ), '', false, false, array( 'id' => 'search-submit' ) ); ?>
        </div>

        <table class="wp-list-table widefixed fixed striped">
            <thead>
            <tr>
                <th scope="col" style="width:30px;"><input type="checkbox" id="select-all"/></th>
                <th scope="col"><?php esc_html_e( 'عنوان', 'falnic-crm' ); ?></th>
                <th scope="col"><?php esc_html_e( 'جدول منبع', 'falnic-crm' ); ?></th>
                <th scope="col"><?php esc_html_e( 'رویدادها', 'falnic-crm' ); ?></th>
                <th scope="col"><?php esc_html_e( 'وضعیت', 'falnic-crm' ); ?></th>
                <th scope="col"><?php esc_html_e( 'تاریخ ایجاد', 'falnic-crm' ); ?></th>
                <th scope="col" style="width:180px;"><?php esc_html_e( 'عملیات', 'falnic-crm' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $triggers ) ) : ?>
                <?php foreach ( $triggers as $t ) : ?>
                    <tr>
                        <th scope="row" class="check-column"><input type="checkbox" name="trigger_ids[]" value="<?php echo intval( $t->id ); ?>"/></th>
                        <td><?php echo esc_html( $t->title ); ?></td>
                        <td><?php echo esc_html( $t->source_table ); ?></td>
                        <td><?php echo esc_html( nl2br( $t->event_types ) ); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr( $t->status ); ?>">
                                <?php echo esc_html( $t->status === 'active' ? 'فعال' : 'غیرفعال' ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $t->created_at ); ?></td>
                        <td class="actions">
                            <a href="admin.php?page=falnic-crm-triggers&action=edit&id=<?php echo intval( $t->id ); ?>" class="button-link"><?php esc_html_e( 'ویرایش', 'falnic-crm' ); ?></a>
                            <button type="button" class="button-link falnic-crm-toggle" data-id="<?php echo intval( $t->id ); ?>" data-status="<?php echo esc_attr( $t->status ); ?>">
                                <?php echo esc_html( $t->status === 'active' ? 'غیرفعال کردن' : 'فعال کردن' ); ?>
                            </button>
                            <button type="button" class="button-link falnic-crm-delete" data-id="<?php echo intval( $t->id ); ?>" data-title="<?php echo esc_attr( $t->title ); ?>"><?php esc_html_e( 'حذف', 'falnic-crm' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="7"><?php esc_html_e( 'تریگری یافت نشد.', 'falnic-crm' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pagination">
                <?php
                echo paginate_links( array(
                    'base' => add_query_arg( array( 'paged' => '%#%', 's' => $search ) ),
                    'format' => '',
                    'prev_text' => __( 'قبلی', 'falnic-crm' ),
                    'next_text' => __( 'بعدی', 'falnic-crm' ),
                    'total' => $pages,
                    'current' => $paged,
                ) );
                ?>
            </div>
        </div>

        <p class="submit" style="margin-top:1em;">
            <label for="falnic_crm_trigger_action"><?php esc_html_e( 'عملیات گروهی:', 'falnic-crm' ); ?></label>
            <select name="falnic_crm_trigger_action" id="falnic_crm_trigger_action">
                <option value=""><?php esc_html_e( 'انتخاب کنید...', 'falnic-crm' ); ?></option>
                <option value="activate"><?php esc_html_e( 'فعال کردن انتخاب شده‌ها', 'falnic-crm' ); ?></option>
                <option value="deactivate"><?php esc_html_e( 'غیرفعال کردن انتخاب شده‌ها', 'falnic-crm' ); ?></option>
                <option value="delete"><?php esc_html_e( 'حذف انتخاب شده‌ها', 'falnic-crm' ); ?></option>
            </select>
            <input type="submit" class="button" value="<?php esc_attr_e( 'اعمال', 'falnic-crm' ); ?>"/>
        </p>
    </form>
</div>

<script>
jQuery(function($){
    function csrf(){ return falnicCrm.nonce; }

    $('.falnic-crm-toggle').on('click', function(){
        var $btn = $(this);
        var id = $btn.data('id');
        var status = $btn.data('status');
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_toggle_trigger',
            _wpnonce: csrf(),
            id: id
        }, function(r){
            if (r.success) {
                var newStatus = r.data.status;
                $btn.data('status', newStatus).text(newStatus === 'active' ? '<?php esc_js(__('غیرفعال کردن','falnic-crm')) ?>' : '<?php esc_js(__('فعال کردن','falnic-crm')) ?>');
                $btn.closest('tr').find('.status-badge').removeClass('status-active status-inactive').addClass('status-'+newStatus).text(newStatus === 'active' ? '<?php esc_js(__('فعال','falnic-crm')) ?>' : '<?php esc_js(__('غیرفعال','falnic-crm')) ?>');
                location.reload();
            } else {
                alert('<?php esc_js(__('خطا در تغییر وضعیت.', 'falnic-crm')) ?>');
            }
        });
    });

    $('.falnic-crm-delete').on('click', function(){
        if ( ! confirm('<?php esc_js(__('آیا از حذف این تریگر اطمینان دارید؟', 'falnic-crm')) ?>') ) return;
        var id = $(this).data('id');
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_delete_trigger',
            _wpnonce: csrf(),
            id: id
        }, function(r){
            if (r.success) location.reload();
            else alert('<?php esc_js(__('خطا در حذف.', 'falnic-crm')) ?>');
        });
    });

    $('#select-all').on('click', function(){
        $('input[name="trigger_ids[]"]').prop('checked', this.checked);
    });
});
</script>
