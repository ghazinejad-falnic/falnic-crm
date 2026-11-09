<div class="wrap">
    <?php if ( $id ) : ?>
        <h1><?php esc_html_e( 'ویرایش تریگر', 'falnic-crm' ); ?></h1>
    <?php else : ?>
        <h1><?php esc_html_e( 'افزودن تریگر جدید', 'falnic-crm' ); ?></h1>
    <?php endif; ?>

    <form id="falnic-crm-trigger-form">
        <?php wp_nonce_field( 'falnic_crm_triggers' ); ?>
        <input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"/>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'عنوان تریگر', 'falnic-crm' ); ?></th>
                <td><input type="text" name="title" class="regular-text" required value="<?php echo esc_attr( $trigger ? $trigger->title : '' ); ?>"/></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'شرح', 'falnic-crm' ); ?></th>
                <td><textarea name="description" class="regular-text"><?php echo esc_textarea( $trigger ? $trigger->description : '' ); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'جدول منبع (Source Table)', 'falnic-crm' ); ?></th>
                <td>
                    <select name="source_table" id="falnic-crm-source-table" class="regular-text" required>
                        <option value=""><?php esc_html_e( 'انتخاب جدول...', 'falnic-crm' ); ?></option>
                    </select>
                    <button type="button" class="button button-secondary" id="falnic-crm-refresh-tables"><?php esc_html_e( 'بروزرسانی لیست جداول', 'falnic-crm' ); ?></button>
                    <p class="description"><?php esc_html_e( 'هر جدولی از دیتابیس وردپرس (جدول پیش‌فرض، سفارشی یا افزونه‌ای).', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'ستون کلید اصلی', 'falnic-crm' ); ?></th>
                <td>
                    <input type="text" name="primary_key_column" id="falnic-crm-pk-column" class="small-text" value="<?php echo esc_attr( $trigger ? $trigger->primary_key_column : 'id' ); ?>"/>
                    <p class="description"><?php esc_html_e( 'ستون‌شناسهٔ رکورد (معمولا id).', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'رویدادهای تریگر', 'falnic-crm' ); ?></th>
                <td>
                    <label><input type="checkbox" name="event_types[]" value="insert" <?php checked( $trigger && strpos( $trigger->event_types, 'insert' ) !== false ); ?>/> <?php esc_html_e( 'افزودن رکورد (INSERT)', 'falnic-crm' ); ?></label><br/>
                    <label><input type="checkbox" name="event_types[]" value="update" <?php checked( $trigger && strpos( $trigger->event_types, 'update' ) !== false ); ?>/> <?php esc_html_e( 'بروزرسانی (UPDATE)', 'falnic-crm' ); ?></label><br/>
                    <label><input type="checkbox" name="event_types[]" value="delete" <?php checked( $trigger && strpos( $trigger->event_types, 'delete' ) !== false ); ?>/> <?php esc_html_e( 'حذف (DELETE)', 'falnic-crm' ); ?></label>
                    <p class="description"><?php esc_html_e( 'هنگام رویکرد هر رویداد، یک رکورد در صف CRM ثبت می‌شود.', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'شرط فیلتر (اختیاری)', 'falnic-crm' ); ?></th>
                <td>
                    <textarea name="condition_sql" class="regular-text" rows="2"><?php echo esc_textarea( $trigger ? $trigger->condition_sql : '' ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'شرط SQL برای فیلتر رکوردها (استفاده با احتیاط).', 'falnic-crm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'وضعیت', 'falnic-crm' ); ?></th>
                <td>
                    <label><input type="radio" name="status" value="active" <?php checked( ! $trigger || $trigger->status === 'active' ); ?>/> <?php esc_html_e( 'فعال', 'falnic-crm' ); ?></label>
                    <label><input type="radio" name="status" value="inactive" <?php checked( $trigger && $trigger->status === 'inactive' ); ?>/> <?php esc_html_e( 'غیرفعال', 'falnic-crm' ); ?></label>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e( ' نگاشت فیلدها به پارامترهای لید CRM', 'falnic-crm' ); ?></h3>
        <p class="description"><?php esc_html_e( 'ستون‌های منبع را بکشید و بر روی پارامترها رها کنید. هر پارامتر می‌تواند مقدار ثابت (static) یا پویا بر اساس ستون جدول باشد (dynamic).', 'falnic-crm' ); ?></p>

        <div class="falnic-crm-mapping-container" style="display:flex; gap:20px; align-items:flex-start;">
            <div class="falnic-crm-source-columns" style="flex:1; border:1px solid #ccc; padding:10px; max-height:400px; overflow:auto; background:#f9f9f9;">
                <strong style="display:block; margin-bottom:8px;"><?php esc_html_e( 'ستون‌های جدول منبع', 'falnic-crm' ); ?></strong>
                <div id="falnic-crm-columns-list" style="display:flex; flex-direction:column; gap:6px;"></div>
            </div>
            <div id="falnic-crm-params" style="flex:1.4; border:1px solid #ccc; padding:10px; max-height:400px; overflow:auto; background:#fff;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong><?php esc_html_e( 'پارامترهای endpoint لید', 'falnic-crm' ); ?></strong>
                    <button type="button" id="falnic-crm-add-param" class="button button-small"><?php esc_html_e( '+ پارامتر', 'falnic-crm' ); ?></button>
                </div>
                <div id="falnic-crm-params-list" style="display:flex; flex-direction:column; gap:8px;"></div>
                <input type="hidden" name="mapping" id="falnic-crm-mapping-json"/>
            </div>
        </div>

        <div style="margin-top:16px;">
            <button type="button" class="button button-secondary" id="falnic-crm-preview-payload"><?php esc_html_e( 'پیش نمایش داده ارسالی', 'falnic-crm' ); ?></button>
            <div id="falnic-crm-preview" style="margin-top:8px; max-height:250px; overflow:auto; background:#f1f1f1; border:1px solid #ddd; padding:8px; font-family:monospace; font-size:12px; white-space:pre-wrap;"></div>
        </div>

        <p class="submit" style="margin-top:24px;">
            <input type="submit" class="button button-primary" value="<?php echo $id ? esc_attr__( 'به روز رسانی تریگر', 'falnic-crm' ) : esc_attr__( 'ذخیره تریگر', 'falnic-crm' ); ?>"/>
            <a href="admin.php?page=falnic-crm-triggers" class="button button-secondary"><?php esc_html_e( 'لغو', 'falnic-crm' ); ?></a>
        </p>
    </form>

    <div id="falnic-crm-template" style="display:none;">
        <div class="falnic-crm-param-row">
            <input type="text" class="param-name small-text" placeholder="نام پارامتر (مثل lead_name)"/>
            <select class="param-type small-text">
                <option value="static"><?php esc_html_e( 'ثابت', 'falnic-crm' ); ?></option>
                <option value="dynamic"><?php esc_html_e( 'پویا (ستون جدول)', 'falnic-crm' ); ?></option>
            </select>
            <input type="text" class="param-value static-value small-text" placeholder="مقدار ثابت"/>
            <select class="param-value dynamic-value small-text" style="display:none;"></select>
            <input type="text" class="param-default small-text" placeholder="<?php esc_attr_e( 'مقدار پیشفرض', 'falnic-crm' ); ?>"/>
            <button type="button" class="param-remove button button-link"><?php esc_html_e( 'حذف', 'falnic-crm' ); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    var $tableSelect = $('#falnic-crm-source-table');
    var $columnsList = $('#falnic-crm-columns-list');
    var $paramsList  = $('#falnic-crm-params-list');
    var $pkInput     = $('#falnic-crm-pk-column');

    function loadTables(){
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_get_tables',
            _wpnonce: falnicCrm.nonce,
            search: ''
        }, function(r){
            if (r.success) {
                var html = '';
                $.each(r.data, function(i, t){
                    html += '<option value="'+t.name+'">'+t.name+'</option>';
                });
                $tableSelect.html('<option value="">'+'<?php esc_js(__("انتخاب جدول...","falnic-crm")) ?>'+'</option>'+html);
                <?php if ( $trigger ) : ?>
                $tableSelect.val('<?php echo esc_js( $trigger->source_table ); ?>').trigger('change');
                <?php endif; ?>
            }
        });
    }
    loadTables();

    $('#falnic-crm-refresh-tables').on('click', loadTables);

    $tableSelect.on('change', function(){
        var table = $(this).val();
        $columnsList.empty();
        $pkInput.val('id');
        if ( ! table ) return;
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_get_columns',
            _wpnonce: falnicCrm.nonce,
            table: table
        }, function(r){
            if (r.success) {
                $.each(r.data, function(i, col){
                    var $item = $('<div class="falnic-crm-column" draggable="true"></div>')
                        .text(col.name + ' (' + col.type + ')')
                        .data('column', col.name)
                        .attr('title', col.name);
                    $columnsList.append($item);
                });
                var firstId = '';
                $.each(r.data, function(i, col){
                    if (col.name.toLowerCase() === 'id'){ firstId = col.name; return false; }
                });
                $pkInput.val(firstId || (r.data.length ? r.data[0].name : 'id'));
            }
        });
    });

    function addParamRow(name, type, value, column, defaultVal){
        var $row = $('#falnic-crm-template .falnic-crm-param-row').clone().show();
        $row.find('.param-name').val(name);
        $row.find('.param-type').val(type);
        var $static = $row.find('.static-value');
        var $dynamic = $row.find('.dynamic-value');
        if (type === 'dynamic') {
            $static.hide(); $dynamic.show();
            $dynamic.html('<option>'+'<?php esc_js(__("انتخاب ستون...")) ?>'+'</option>');
            $columnsList.find('.falnic-crm-column').each(function(){
                $dynamic.append('<option value="'+$(this).data('column')+'">'+$(this).data('column')+'</option>');
            });
            $dynamic.val(column);
        } else {
            $static.show(); $dynamic.hide();
            $static.val(value);
        }
        $row.find('.param-default').val(defaultVal);
        $row.find('.param-remove').on('click', function(){ $row.remove(); });
        $row.find('.param-type').on('change', function(){
            if ($(this).val() === 'dynamic'){ $static.hide(); $dynamic.show(); }
            else { $static.show(); $dynamic.hide(); }
        });
        $row.on('dragover', function(e){ e.preventDefault(); $(this).css('background','#e8f4ff'); });
        $row.on('dragleave', function(){ $(this).css('background',''); });
        $row.on('drop', function(e){
            e.preventDefault();
            $(this).css('background','');
            var col = e.originalEvent.dataTransfer.getData('text/plain');
            if ( ! col ) return;
            $row.find('.param-type').val('dynamic').trigger('change');
            $dynamic.val(col);
        });
        $paramsList.append($row);
        return $row;
    }

    $columnsList.on('dragstart', '.falnic-crm-column', function(e){
        e.originalEvent.dataTransfer.setData('text/plain', $(this).data('column'));
    });

    <?php if ( $trigger && ! empty( $mapping ) ) : ?>
        var mapping = <?php echo json_encode( $mapping ); ?>;
        $.each(mapping, function(param, spec){
            addParamRow(param, spec.type, spec.value, spec.column, spec.default);
        });
    <?php else : ?>
        addParamRow('', 'static', '', '', '');
    <?php endif; ?>

    $('#falnic-crm-add-param').on('click', function(){
        addParamRow('', 'static', '', '', '');
    });

    $('#falnic-crm-preview-payload').on('click', function(){
        var table = $tableSelect.val();
        var pk    = $pkInput.val();
        var mapping = {};
        $paramsList.find('.falnic-crm-param-row').each(function(){
            var $row = $(this);
            var pname = $.trim($row.find('.param-name').val());
            if (pname === '') return;
            mapping[pname] = {
                type: $row.find('.param-type').val(),
                value: $row.find('.static-value').val(),
                column: $row.find('.dynamic-value').val(),
                default: $row.find('.param-default').val()
            };
        });
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_preview_payload',
            _wpnonce: falnicCrm.nonce,
            source_table: table,
            primary_key_column: pk,
            mapping: mapping
        }, function(r){
            $('#falnic-crm-preview').html(r.success ? JSON.stringify(r.data.payload, null, 2) : ('ERROR: ' + r.data.message));
        });
    });

    $('#falnic-crm-trigger-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $(this).find('input[type=submit]');
        $btn.prop('disabled', true).text('<?php esc_js(__("ذخیره کردن...")) ?>');
        var mapping = {};
        $paramsList.find('.falnic-crm-param-row').each(function(){
            var $row = $(this);
            var pname = $.trim($row.find('.param-name').val());
            if (pname === '') return;
            mapping[pname] = {
                type: $row.find('.param-type').val(),
                value: $row.find('.static-value').val(),
                column: $row.find('.dynamic-value').val(),
                default: $row.find('.param-default').val()
            };
        });
        $('#falnic-crm-mapping-json').val(JSON.stringify(mapping));
        $.post(falnicCrm.ajax_url, {
            action: 'falnic_crm_save_trigger',
            _wpnonce: falnicCrm.nonce,
            title: $('input[name=title]').val(),
            description: $('textarea[name=description]').val(),
            source_table: $tableSelect.val(),
            primary_key_column: $pkInput.val(),
            event_types: $('input[name="event_types[]"]:checked').map(function(){return $(this).val();}).get().join(','),
            condition_sql: $('textarea[name=condition_sql]').val(),
            status: $('input[name=status]:checked').val(),
            mapping: mapping,
            id: $('input[name=id]').val()
        }, function(r){
            $btn.prop('disabled', false).text('<?php esc_js(__("ذخیره تریجر")) ?>');
            if (r.success){
                alert('<?php esc_js(__("ذخیره شد.")) ?>');
                window.location = 'admin.php?page=falnic-crm-triggers';
            } else {
                alert(r.data.message || '<?php esc_js(__("خطا در ذخیره.")) ?>');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('<?php esc_js(__("ذخیره تریگر")) ?>');
            alert('<?php esc_js(__("خطا در ارتباط با سرور.")) ?>');
        });
    });
});
</script>
