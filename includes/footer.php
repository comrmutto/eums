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

            <!-- Language Switcher -->
            <div class="form-group mb-3">
                <label class="d-block mb-1"><i class="fas fa-language mr-1"></i> ภาษา / Language</label>
                <div class="btn-group w-100" role="group" id="languageSwitcher">
                    <button type="button" class="btn btn-sm btn-outline-light lang-btn active" data-lang="th">🇹🇭 ไทย</button>
                    <button type="button" class="btn btn-sm btn-outline-light lang-btn" data-lang="en">🇬🇧 English</button>
                </div>
            </div>
            <hr class="mb-2">

            <!-- Theme -->
            <div class="form-group">
                <label><i class="fas fa-palette mr-1"></i> <span data-i18n="theme">ธีม</span></label>
                <select class="form-control form-control-sm" id="themeSelector">
                    <option value="light" data-i18n-opt="themeLight">สว่าง</option>
                    <option value="dark"  data-i18n-opt="themeDark">มืด</option>
                    <option value="blue"  data-i18n-opt="themeBlue">น้ำเงิน</option>
                </select>
            </div>

            <!-- Font Size -->
            <div class="form-group">
                <label><i class="fas fa-text-height mr-1"></i> <span data-i18n="fontSize">ขนาดตัวอักษร</span></label>
                <select class="form-control form-control-sm" id="fontSizeSelector">
                    <option value="13px" data-i18n-opt="fontSmall">เล็ก</option>
                    <option value="15px" selected data-i18n-opt="fontNormal">ปกติ</option>
                    <option value="17px" data-i18n-opt="fontLarge">ใหญ่</option>
                    <option value="19px" data-i18n-opt="fontXLarge">ใหญ่มาก</option>
                </select>
            </div>

            <!-- Auto Refresh -->
            <div class="form-group">
                <label><i class="fas fa-sync-alt mr-1"></i> <span data-i18n="autoRefresh">รีเฟรชอัตโนมัติ</span></label>
                <select class="form-control form-control-sm" id="refreshInterval">
                    <option value="0"      data-i18n-opt="off">ปิด</option>
                    <option value="300000">5 นาที</option>
                    <option value="600000">10 นาที</option>
                    <option value="900000">15 นาที</option>
                </select>
            </div>

            <hr>
            <h6 data-i18n="moduleDisplay">การแสดงผลโมดูล</h6>
            <?php if (function_exists('config') && config('modules')): ?>
                <?php foreach (config('modules') as $key => $module): ?>
                    <?php if (!empty($module['enabled'])): ?>
                        <div class="form-check">
                            <input class="form-check-input module-toggle" type="checkbox"
                                   id="module_<?php echo htmlspecialchars($key); ?>"
                                   data-module="<?php echo htmlspecialchars($key); ?>" checked>
                            <label class="form-check-label" for="module_<?php echo htmlspecialchars($key); ?>">
                                <?php echo htmlspecialchars($module['name']); ?>
                            </label>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

</div>
<!-- ./wrapper -->

<!-- jQuery (ต้องมาก่อนทุกอย่าง) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<!-- Moment.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/th.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>

 
<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>

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

<!-- Custom JS (โหลดหลัง libraries ทั้งหมด) -->
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

<!-- ═══════════════════════════════════════════════════════════════
     EUMS Inline Script — i18n, Theme, Font Size, Auto Refresh
     ═══════════════════════════════════════════════════════════════ -->
<script>
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
} else {

    /* ── I18N Dictionary ─────────────────────────────────────────── */
    window.EUMS_I18N = {
        th: {
            /* Navbar */
            navHome:'หน้าหลัก', navRefresh:'รีเฟรช',
            navNotifications:'การแจ้งเตือน', navNoNotif:'ไม่มีการแจ้งเตือน',
            navViewAll:'ดูทั้งหมด', navProfile:'โปรไฟล์',
            navSettings:'ตั้งค่า', navReport:'รายงาน',
            navMember:'สมาชิกตั้งแต่:', navLogout:'ออกจากระบบ',
            /* Sidebar */
            settings:'ตั้งค่า',
            theme:'ธีม', themeLight:'สว่าง', themeDark:'มืด', themeBlue:'น้ำเงิน',
            fontSize:'ขนาดตัวอักษร',
            fontSmall:'เล็ก', fontNormal:'ปกติ', fontLarge:'ใหญ่', fontXLarge:'ใหญ่มาก',
            autoRefresh:'รีเฟรชอัตโนมัติ', off:'ปิด', minute:'นาที',
            moduleDisplay:'การแสดงผลโมดูล',
            /* Common */
            save:'บันทึก', cancel:'ยกเลิก', confirm:'ยืนยัน', delete:'ลบ',
            edit:'แก้ไข', add:'เพิ่ม', search:'ค้นหา', export:'ส่งออก',
            print:'พิมพ์', close:'ปิด', status:'สถานะ', action:'การกระทำ',
            date:'วันที่', name:'ชื่อ', detail:'รายละเอียด',
            unit:'หน่วย', amount:'จำนวน', total:'รวม',
            machine:'เครื่องจักร', normal:'ปกติ', warning:'เตือน', danger:'อันตราย',
            /* Feedback */
            themeChanged: (t) => `เปลี่ยนธีมเป็น "${t}" เรียบร้อย`,
            fontChanged: 'เปลี่ยนขนาดตัวอักษรเรียบร้อย',
            langChanged: '🇹🇭 เปลี่ยนภาษาเป็น ภาษาไทย',
        },
        en: {
            navHome:'Home', navRefresh:'Refresh',
            navNotifications:'Notifications', navNoNotif:'No notifications',
            navViewAll:'View all', navProfile:'Profile',
            navSettings:'Settings', navReport:'Report',
            navMember:'Member since:', navLogout:'Logout',
            settings:'Settings',
            theme:'Theme', themeLight:'Light', themeDark:'Dark', themeBlue:'Blue',
            fontSize:'Font Size',
            fontSmall:'Small', fontNormal:'Normal', fontLarge:'Large', fontXLarge:'Extra Large',
            autoRefresh:'Auto Refresh', off:'Off', minute:'min',
            moduleDisplay:'Module Display',
            save:'Save', cancel:'Cancel', confirm:'Confirm', delete:'Delete',
            edit:'Edit', add:'Add', search:'Search', export:'Export',
            print:'Print', close:'Close', status:'Status', action:'Action',
            date:'Date', name:'Name', detail:'Detail',
            unit:'Unit', amount:'Amount', total:'Total',
            machine:'Machine', normal:'Normal', warning:'Warning', danger:'Danger',
            themeChanged: (t) => `Theme changed to "${t}"`,
            fontChanged: 'Font size changed',
            langChanged: '🇬🇧 Language changed to English',
        }
    };

    /* ── applyLanguage ───────────────────────────────────────────── */
    window.applyLanguage = function(lang) {
        const dict = EUMS_I18N[lang] || EUMS_I18N.th;
        document.body.setAttribute('data-lang', lang);
        document.documentElement.lang = lang;

        $('[data-i18n]').each(function() {
            const v = dict[$(this).data('i18n')];
            if (typeof v === 'string') $(this).text(v);
        });
        $('[data-i18n-placeholder]').each(function() {
            const v = dict[$(this).data('i18n-placeholder')];
            if (v) $(this).attr('placeholder', v);
        });
        $('[data-i18n-opt]').each(function() {
            const v = dict[$(this).data('i18n-opt')];
            if (v) $(this).text(v);
        });
        $('#refreshInterval option').each(function(i) {
            const labels = [dict.off, `5 ${dict.minute}`, `10 ${dict.minute}`, `15 ${dict.minute}`];
            if (labels[i] !== undefined) $(this).text(labels[i]);
        });
        $('.control-sidebar h5').first().text(dict.settings);

        // sync sidebar buttons
        $('.lang-btn').removeClass('btn-light active').addClass('btn-outline-light');
        $(`.lang-btn[data-lang="${lang}"]`).removeClass('btn-outline-light').addClass('btn-light active');

        // sync navbar toggle button
        const btn = document.getElementById('langToggleBtn');
        if (btn) btn.setAttribute('data-lang', lang);

        if (typeof moment !== 'undefined') moment.locale(lang === 'en' ? 'en' : 'th');
        localStorage.setItem('eums_language', lang);
    };

    /* ── applyThemeCSS ───────────────────────────────────────────── */
    window.applyThemeCSS = function(theme) {
        $('body')
            .attr('data-theme', theme)
            .removeClass('dark-theme blue-theme light-theme')
            .addClass(theme === 'dark' ? 'dark-theme' : theme === 'blue' ? 'blue-theme' : 'light-theme');
    };

    /* ── applyFontSize ───────────────────────────────────────────── */
    window.applyFontSize = function(size) {
        document.documentElement.style.fontSize = size;
    };

    /* ── Document Ready ──────────────────────────────────────────── */
    $(document).ready(function() {

        // Toastr defaults
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                closeButton: true, progressBar: true, newestOnTop: true,
                positionClass: 'toast-top-right', timeOut: 4000,
                showMethod: 'fadeIn', hideMethod: 'fadeOut'
            };
        }

        // Moment default locale
        if (typeof moment !== 'undefined') moment.locale('th');

        // ── Restore saved preferences ────────────────────────────
        const savedLang  = localStorage.getItem('eums_language') || 'th';
        const savedTheme = localStorage.getItem('eums_theme');
        const savedFont  = localStorage.getItem('eums_fontSize');

        applyLanguage(savedLang);
        if (savedTheme) { applyThemeCSS(savedTheme); $('#themeSelector').val(savedTheme); }
        if (savedFont)  { applyFontSize(savedFont);  $('#fontSizeSelector').val(savedFont); }

        // ── Navbar language toggle (🇹🇭 TH | 🇬🇧 EN button) ─────
        $('#langToggleBtn').on('click', function() {
            const newLang = ($(this).attr('data-lang') || 'th') === 'th' ? 'en' : 'th';
            applyLanguage(newLang);
            $(this).addClass('switched');
            setTimeout(() => $(this).removeClass('switched'), 400);
            if (typeof toastr !== 'undefined') toastr.success(EUMS_I18N[newLang].langChanged);
        });

        // ── Sidebar lang buttons ─────────────────────────────────
        $(document).on('click', '.lang-btn', function() {
            const lang = $(this).data('lang');
            applyLanguage(lang);
            if (typeof toastr !== 'undefined') toastr.success(EUMS_I18N[lang].langChanged);
        });

        // ── Theme selector ───────────────────────────────────────
        $('#themeSelector').on('change', function() {
            const theme = $(this).val();
            applyThemeCSS(theme);
            localStorage.setItem('eums_theme', theme);
            const dict = EUMS_I18N[localStorage.getItem('eums_language') || 'th'];
            if (typeof toastr !== 'undefined')
                toastr.success(dict.themeChanged($('#themeSelector option:selected').text()));
        });

        // ── Font size selector ───────────────────────────────────
        $('#fontSizeSelector').on('change', function() {
            const size = $(this).val();
            applyFontSize(size);
            localStorage.setItem('eums_fontSize', size);
            const dict = EUMS_I18N[localStorage.getItem('eums_language') || 'th'];
            if (typeof toastr !== 'undefined') toastr.success(dict.fontChanged);
        });

        // ── Auto Refresh ─────────────────────────────────────────
        let refreshTimer;
        $('#refreshInterval').on('change', function() {
            clearInterval(refreshTimer);
            const ms = parseInt($(this).val());
            if (ms > 0) {
                refreshTimer = setInterval(() => $('#refreshData').trigger('click'), ms);
                localStorage.setItem('eums_refresh_interval', ms);
            }
        });
        const savedRefresh = localStorage.getItem('eums_refresh_interval');
        if (savedRefresh && parseInt(savedRefresh) > 0) {
            $('#refreshInterval').val(savedRefresh).trigger('change');
        }
    });
}
</script>

</body>
</html>