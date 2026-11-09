<div class="wrap">
    <h1><?php esc_html_e( 'لاگ‌های فعالیت افزونه فالنیک CRM', 'falnic-crm' ); ?></h1>

    <table class="wp-list-table widefixed fixed striped">
        <thead>
        <tr>
            <th scope="col" style="width:40px;">#</th>
            <th scope="col"><?php esc_html_e( 'عمل', 'falnic-crm' ); ?></th>
            <th scope="col"><?php esc_html_e( 'جزئیات', 'falnic-crm' ); ?></th>
            <th scope="col"><?php esc_html_e( 'کاربر', 'falnic-crm' ); ?></th>
            <th scope="col"><?php esc_html_e( 'زمان', 'falnic-crm' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( ! empty( $logs ) ) : ?>
            <?php foreach ( $logs as $log ) :
                $user = get_userdata( $log->user_id );
            ?>
            <tr>
                <td><?php echo esc_html( $log->id ); ?></td>
                <td><?php echo esc_html( $log->action ); ?></td>
                <td><?php echo esc_html( wp_kses_post( nl2br( substr( $log->details, 0, 400 ) ) ) ); ?></td>
                <td><?php echo esc_html( $user ? $user->display_name : '-' ); ?></td>
                <td><?php echo esc_html( $log->created_at ); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="5"><?php esc_html_e( 'لاگی وجود ندارد.', 'falnic-crm' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="tablenav bottom">
        <div class="tablenav-pagination">
            <?php
            echo paginate_links( array(
                'base' => add_query_arg( 'paged', '%#%' ),
                'format' => '',
                'prev_text' => __( 'قبلی', 'falnic-crm' ),
                'next_text' => __( 'بعدی', 'falnic-crm' ),
                'total' => $pages,
                'current' => $paged,
            ) );
            ?>
        </div>
    </div>
</div>
