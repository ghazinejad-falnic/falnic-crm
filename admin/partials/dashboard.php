<div class="wrap">
    <h1><?php esc_html_e( 'داشبورد فالنیک CRM', 'falnic-crm' ); ?></h1>

    <div class="falnic-crm-stats" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
        <div class="stat-card" style="flex:1; min-width:140px; border:1px solid #ccc; padding:16px; background:#fff;">
            <div class="stat-label"><?php esc_html_e( 'کل تریگرها', 'falnic-crm' ); ?></div>
            <div class="stat-number" id="stat-triggers-total"><?php echo esc_html( $stats['triggers']['total'] ); ?></div>
        </div>
        <div class="stat-card" style="flex:1; min-width:140px; border:1px solid #ccc; padding:16px; background:#fff;">
            <div class="stat-label"><?php esc_html_e( 'فعال', 'falnic-crm' ); ?></div>
            <div class="stat-number" id="stat-triggers-active" style="color:#2271b1"><?php echo esc_html( $stats['triggers']['active'] ); ?></div>
        </div>
        <div class="stat-card" style="flex:1; min-width:140px; border:1px solid #ccc; padding:16px; background:#fff;">
            <div class="stat-label"><?php esc_html_e( 'غیرفعال', 'falnic-crm' ); ?></div>
            <div class="stat-number" id="stat-triggers-inactive" style="color:#888"><?php echo esc_html( $stats['triggers']['inactive'] ); ?></div>
        </div>
        <div class="stat-card" style="flex:1; min-width:140px; border:1px solid #ccc; padding:16px; background:#fff;">
            <div class="stat-label"><?php esc_html_e( 'در انتظار ارسال', 'falnic-crm' ); ?></div>
            <div class="stat-number" id="stat-events-pending" style="color:#b35c00"><?php echo esc_html( $stats['events']['pending'] ); ?></div>
        </div>
        <div class="stat-card" style="flex:1; min-width:140px; border:1px solid #ccc; padding:16px; background:#fff;">
            <div class="stat-label"><?php esc_html_e( 'ارسال موفق', 'falnic-crm' ); ?></div>
            <div class="stat-number" id="stat-events-success" style="color:#2e7d32"><?php echo esc_html( $stats['success'] ); ?></div>
        </div>
        <div class="stat-card" style="flex:1; min-width:140px; border:1px solid #ccc; padding:16px; background:#fff;">
            <div class="stat-label"><?php esc_html_e( 'ارسال ناموفق', 'falnic-crm' ); ?></div>
            <div class="stat-number" id="stat-events-failed" style="color:#c62828"><?php echo esc_html( $stats['failed'] ); ?></div>
        </div>
    </div>

    <div style="display:flex; gap:24px; flex-wrap:wrap;">
        <div style="flex:1; min-width:420px; border:1px solid #ccc; padding:16px; background:#fff;">
            <h3><?php esc_html_e( 'روند رویدادها (۳۰ روز اخیر)', 'falnic-crm' ); ?></h3>
            <canvas id="falnic-crm-trend-chart" height="120"></canvas>
        </div>
        <div style="flex:1; min-width:420px; border:1px solid #ccc; padding:16px; background:#fff;">
            <h3><?php esc_html_e( 'آمار بر اساس تریگر', 'falnic-crm' ); ?></h3>
            <table class="wp-list-table widefixed fixed striped" style="margin-top:8px;">
                <thead>
                <tr><th><?php esc_html_e( 'تریگر', 'falnic-crm' ); ?></th><th style="width:60px;"><?php esc_html_e( 'موفق', 'falnic-crm' ); ?></th><th style="width:60px;"><?php esc_html_e( 'ناموفق', 'falnic-crm' ); ?></th><th style="width:70px;"><?php esc_html_e( 'در انتظار', 'falnic-crm' ); ?></th><th style="width:70px;"><?php esc_html_e( 'وضعیت', 'falnic-crm' ); ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ( $stats['per_trigger'] as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['title'] ); ?></td>
                        <td style="color:#2e7d32"><?php echo esc_html( $row['success'] ); ?></td>
                        <td style="color:#c62828"><?php echo esc_html( $row['failed'] ); ?></td>
                        <td style="color:#b35c00"><?php echo esc_html( $row['pending'] ); ?></td>
                        <td><?php echo esc_html( $row['status'] === 'active' ? 'فعال' : 'غیرفعال' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $stats['per_trigger'] ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'داده‌ای وجود ندارد.', 'falnic-crm' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h3 style="margin-top:32px;"><?php esc_html_e( 'آخرین خطاهای ارسال', 'falnic-crm' ); ?></h3>
    <table class="wp-list-table widefixed fixed striped">
        <thead>
        <tr><th><?php esc_html_e( 'زمان', 'falnic-crm' ); ?></th><th><?php esc_html_e( 'تریگر', 'falnic-crm' ); ?></th><th><?php esc_html_e( 'خطا', 'falnic-crm' ); ?></th></tr>
        </thead>
        <tbody>
        <?php foreach ( $stats['recent_errors'] as $row ) : ?>
            <tr>
                <td><?php echo esc_html( $row['created_at'] ); ?></td>
                <td><?php echo esc_html( $row['trigger_title'] ?: '-' ); ?></td>
                <td><?php echo esc_html( substr( $row['error_message'], 0, 200 ) ); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ( empty( $stats['recent_errors'] ) ) : ?>
            <tr><td colspan="3"><?php esc_html_e( 'خطایی ثبت نشده.', 'falnic-crm' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(function($){
    if (typeof Chart === 'undefined') return;
    var ctx = document.getElementById('falnic-crm-trend-chart');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: Array.from({length: 30}, function(_, i){ return (30 - i) + ' روز قبل'; }).reverse(),
            datasets: [{
                label: '<?php esc_js(__('رویدادها','falnic-crm')); ?>',
                data: <?php echo json_encode( $stats['trend'] ); ?>,
                borderColor: '#2271b1',
                backgroundColor: 'rgba(34,113,177,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
