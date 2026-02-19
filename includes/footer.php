<?php
/**
 * Footer Template
 * Engineering Utility Monitoring System (EUMS)
 */
?>
            </div>
        </section>
    </div>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> <?php echo $appVersion ?? '1.0.0'; ?>
        </div>
        <strong>Copyright &copy; <?php echo date('Y'); ?> <?php echo $appName ?? 'EUMS'; ?>.</strong> All rights reserved.
    </footer>
    
    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <div class="p-3">
            <h5>ตั้งค่า</h5>
            <hr class="mb-2">
            
            <!-- Theme Settings -->
            <div class="form-group">
                <label>ธีม</label>
                <select class="form-control form-control-sm" id="themeSelector">
                    <option value="light">สว่าง</option>
                    <option value="dark">มืด</option>
                    <option value="blue">น้ำเงิน</option>
                </select>
            </div>
            
            <!-- Font Size -->
            <div class="form-group">
                <label>ขนาดตัวอักษร</label>
                <select class="form-control form-control-sm" id="fontSizeSelector">
                    <option value="14px">เล็ก</option>
                    <option value="16px" selected>ปกติ</option>
                    <option value="18px">ใหญ่</option>
                </select>
            </div>
            
            <!-- Refresh Interval -->
            <div class="form-group">
                <label>รีเฟรชอัตโนมัติ</label>
                <select class="form-control form-control-sm" id="refreshInterval">
                    <option value="0">ปิด</option>
                    <option value="300000">5 นาที</option>
                    <option value="600000">10 นาที</option>
                    <option value="900000">15 นาที</option>
                </select>
            </div>
            
            <hr>
            
            <!-- Module Settings -->
            <h6>การแสดงผลโมดูล</h6>
            <?php foreach (config('modules') as $key => $module): ?>
                <?php if ($module['enabled']): ?>
                    <div class="form-check">
                        <input class="form-check-input module-toggle" type="checkbox" 
                               id="module_<?php echo $key; ?>" 
                               data-module="<?php echo $key; ?>" 
                               checked>
                        <label class="form-check-label" for="module_<?php echo $key; ?>">
                            <?php echo $module['name']; ?>
                        </label>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </aside>
    
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<!-- Moment.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/th.js"></script>

<!-- Chart.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/charts.css/dist/charts.min.css">

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

<!-- DatePicker -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.th.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- SheetJS (for Excel export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- Custom JS -->
<script src="/eums/assets/js/main.js"></script>

<!-- Module specific JS -->
<?php if (isset($module_js)): ?>
    <?php foreach ($module_js as $js): ?>
        <script src="/eums/assets/js/modules/<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Page specific JS -->
<?php if (isset($page_js)): ?>
    <script src="/eums/assets/js/pages/<?php echo $page_js; ?>"></script>
<?php endif; ?>

<!-- Custom inline scripts -->
<script>
    // Set moment locale to Thai
    moment.locale('th');
    
    // Toastr configuration
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };
    
    // Auto refresh functionality
    let refreshInterval;
    $('#refreshInterval').on('change', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        const interval = parseInt($(this).val());
        if (interval > 0) {
            refreshInterval = setInterval(function() {
                $('#refreshData').click();
            }, interval);
        }
    });
    
    // Theme switcher
    $('#themeSelector').on('change', function() {
        const theme = $(this).val();
        $('body').attr('data-theme', theme);
        localStorage.setItem('eums_theme', theme);
    });
    
    // Font size switcher
    $('#fontSizeSelector').on('change', function() {
        const size = $(this).val();
        $('body').css('font-size', size);
        localStorage.setItem('eums_fontSize', size);
    });
    
    // Load saved preferences
    $(document).ready(function() {
        const savedTheme = localStorage.getItem('eums_theme');
        if (savedTheme) {
            $('#themeSelector').val(savedTheme).trigger('change');
        }
        
        const savedFontSize = localStorage.getItem('eums_fontSize');
        if (savedFontSize) {
            $('#fontSizeSelector').val(savedFontSize).trigger('change');
        }
    });
</script>

</body>
</html>