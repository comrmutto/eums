/**
 * EUMS Main JavaScript
 * Engineering Utility Monitoring System
 * Version: 1.0.0
 */

// ตรวจสอบว่า jQuery โหลดหรือไม่
if (typeof jQuery === 'undefined') {
    console.error('🚨 jQuery is not loaded! Please check header.php and footer.php');
    document.write('<div class="alert alert-danger m-3">⚠️ เกิดข้อผิดพลาด: jQuery ไม่สามารถโหลดได้ กรุณารีเฟรชหน้าหรือติดต่อผู้ดูแลระบบ</div>');
} else {
    console.log('✅ jQuery loaded successfully');
    
    // Global EUMS Object
    const EUMS = {
        // Configuration
        config: {
            apiUrl: '/eums/api',
            refreshInterval: 300000, // 5 minutes
            dateFormat: 'DD/MM/YYYY',
            timeFormat: 'HH:mm:ss',
            version: '1.0.0',
            debug: true
        },
        
        // State management
        state: {
            currentModule: null,
            selectedDate: new Date(),
            charts: {},
            tables: {},
            userPrefs: {},
            notifications: [],
            isLoading: false
        },
        
        /**
         * Initialize application
         */
        init: function() {
            console.log('🚀 EUMS initialized v' + this.config.version);
            this.checkLibraries();
            this.initEventListeners();
            this.initCharts();
            this.initDataTables();
            this.initDatePickers();
            this.initSelect2();
            this.initTooltips();
            this.initPopovers();
            this.checkSession();
            this.loadUserPreferences();
            this.loadNotifications();
            this.initAutoRefresh();
            console.log('✅ EUMS initialization complete');
        },
        
        /**
         * Check if required libraries are loaded
         */
        checkLibraries: function() {
            const libraries = [
                { name: 'jQuery', check: () => typeof jQuery !== 'undefined' },
                { name: 'Bootstrap', check: () => typeof bootstrap !== 'undefined' },
                { name: 'Moment', check: () => typeof moment !== 'undefined' },
                { name: 'Chart.js', check: () => typeof Chart !== 'undefined' },
                { name: 'DataTables', check: () => typeof $.fn.DataTable !== 'undefined' },
                { name: 'Select2', check: () => typeof $.fn.select2 !== 'undefined' },
                { name: 'Datepicker', check: () => typeof $.fn.datepicker !== 'undefined' }
            ];
            
            libraries.forEach(lib => {
                if (lib.check()) {
                    console.log(`  ✅ ${lib.name} loaded`);
                } else {
                    console.warn(`  ⚠️ ${lib.name} not loaded`);
                }
            });
        },
        
        /**
         * Initialize event listeners
         */
        initEventListeners: function() {
            console.log('Initializing event listeners...');
            
            // Module navigation
            $(document).on('click', '.module-card', function() {
                const module = $(this).data('module');
                if (module) {
                    EUMS.loadModule(module);
                }
            });
            
            // Refresh button
            $('#refreshData').on('click', function(e) {
                e.preventDefault();
                EUMS.refreshData();
            });
            
            // Date range change
            $('#dateRange').on('change', function() {
                EUMS.updateDateRange($(this).val());
            });
            
            // Export button
            $('#exportData').on('click', function() {
                const format = $(this).data('format') || 'excel';
                EUMS.exportData(format);
            });
            
            // Print button
            $('#printReport').on('click', function() {
                EUMS.printReport();
            });
            
            // Search input
            $('#tableSearch').on('keyup', function() {
                EUMS.searchTable($(this).val());
            });
            
            // Theme selector
            $('#themeSelector').on('change', function() {
                EUMS.changeTheme($(this).val());
            });
            
            // Font size selector
            $('#fontSizeSelector').on('change', function() {
                EUMS.changeFontSize($(this).val());
            });
            
            // Sidebar toggle
            $('[data-widget="pushmenu"]').on('click', function() {
                setTimeout(() => {
                    $(window).trigger('resize');
                }, 200);
            });
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, skipping chart initialization');
                return;
            }
            
            console.log('Initializing charts...');
            
            // Usage trend chart
            if ($('#usageTrendChart').length) {
                try {
                    this.charts.usageTrend = new Chart($('#usageTrendChart'), {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                label: 'ปริมาณการใช้งาน',
                                data: [],
                                borderColor: '#007bff',
                                backgroundColor: 'rgba(0,123,255,0.1)',
                                borderWidth: 2,
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#007bff',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: 'rgba(0,0,0,0.8)',
                                    titleColor: '#fff',
                                    bodyColor: '#ddd',
                                    borderColor: '#007bff',
                                    borderWidth: 1
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0,0,0,0.05)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            interaction: {
                                mode: 'nearest',
                                axis: 'x',
                                intersect: false
                            }
                        }
                    });
                    console.log('  ✅ Usage trend chart initialized');
                } catch (e) {
                    console.error('Error initializing usage trend chart:', e);
                }
            }
            
            // Comparison chart
            if ($('#comparisonChart').length && !window.comparisonChartInitialized) {
                try {
                    this.charts.comparison = new Chart($('#comparisonChart'), {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: [{
                                label: 'เปรียบเทียบการใช้งาน',
                                data: [],
                                backgroundColor: [
                                    '#007bff',
                                    '#28a745',
                                    '#ffc107',
                                    '#dc3545',
                                    '#17a2b8'
                                ],
                                borderWidth: 0,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.raw.toLocaleString() + ' หน่วย';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0,0,0,0.05)'
                                    }
                                }
                            }
                        }
                    });
                    console.log('  ✅ Comparison chart initialized');
                } catch (e) {
                    console.error('Error initializing comparison chart:', e);
                }
            }
            
            // Pie chart for distribution
            if ($('#distributionChart').length) {
                try {
                    this.charts.distribution = new Chart($('#distributionChart'), {
                        type: 'doughnut',
                        data: {
                            labels: [],
                            datasets: [{
                                data: [],
                                backgroundColor: [
                                    '#007bff',
                                    '#28a745',
                                    '#ffc107',
                                    '#dc3545',
                                    '#17a2b8',
                                    '#6610f2',
                                    '#e83e8c',
                                    '#fd7e14',
                                    '#20c997',
                                    '#6c757d'
                                ],
                                borderWidth: 0,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    console.log('  ✅ Distribution chart initialized');
                } catch (e) {
                    console.error('Error initializing distribution chart:', e);
                }
            }
        },
        
        /**
         * Initialize DataTables
         */
        initDataTables: function() {
            if (typeof $.fn.DataTable === 'undefined') {
                console.warn('DataTables not loaded, skipping initialization');
                return;
            }
            
            console.log('Initializing DataTables...');
            
            // Main data table
            if ($('#dataTable').length) {
                try {
                    this.tables.main = $('#dataTable').DataTable({
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Thai.json'
                        },
                        pageLength: 25,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
                        responsive: true,
                        ordering: true,
                        searching: true,
                        processing: true,
                        serverSide: false,
                        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                             "<'row'<'col-sm-12'tr>>" +
                             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                        buttons: [
                            {
                                text: '<i class="fas fa-copy"></i> คัดลอก',
                                extend: 'copy',
                                className: 'btn btn-sm btn-secondary'
                            },
                            {
                                text: '<i class="fas fa-file-excel"></i> Excel',
                                extend: 'excel',
                                className: 'btn btn-sm btn-success'
                            },
                            {
                                text: '<i class="fas fa-file-pdf"></i> PDF',
                                extend: 'pdf',
                                className: 'btn btn-sm btn-danger'
                            },
                            {
                                text: '<i class="fas fa-print"></i> พิมพ์',
                                extend: 'print',
                                className: 'btn btn-sm btn-info'
                            }
                        ],
                        initComplete: function() {
                            console.log('  ✅ DataTable initialized');
                        }
                    });
                } catch (e) {
                    console.error('Error initializing DataTable:', e);
                }
            }
            
            // Initialize any other tables with class .datatable
            $('.datatable').each(function(index) {
                if (!$(this).hasClass('dataTable')) {
                    try {
                        const tableId = 'table_' + index;
                        $(this).attr('id', tableId);
                        EUMS.tables[tableId] = $(this).DataTable({
                            language: {
                                url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Thai.json'
                            },
                            pageLength: 10,
                            responsive: true
                        });
                    } catch (e) {
                        console.error('Error initializing table:', e);
                    }
                }
            });
        },
        
        /**
         * Initialize date pickers
         */
        initDatePickers: function() {
            if (typeof $.fn.datepicker === 'undefined') {
                console.warn('Datepicker not loaded, skipping initialization');
                return;
            }
            
            console.log('Initializing date pickers...');
            
            // Bootstrap datepicker
            $('.datepicker').each(function() {
                try {
                    $(this).datepicker({
                        format: 'dd/mm/yyyy',
                        autoclose: true,
                        todayHighlight: true,
                        language: 'th',
                        thaiyear: true
                    });
                } catch (e) {
                    console.error('Error initializing datepicker:', e);
                }
            });
            
            // Daterangepicker
            if (typeof $.fn.daterangepicker !== 'undefined') {
                $('.daterangepicker').each(function() {
                    try {
                        $(this).daterangepicker({
                            locale: {
                                format: 'DD/MM/YYYY',
                                separator: ' - ',
                                applyLabel: 'ตกลง',
                                cancelLabel: 'ยกเลิก',
                                fromLabel: 'จาก',
                                toLabel: 'ถึง',
                                customRangeLabel: 'กำหนดเอง',
                                daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                                monthNames: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],
                                firstDay: 1
                            },
                            ranges: {
                                'วันนี้': [moment(), moment()],
                                'เมื่อวาน': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                                '7 วันล่าสุด': [moment().subtract(6, 'days'), moment()],
                                '30 วันล่าสุด': [moment().subtract(29, 'days'), moment()],
                                'เดือนนี้': [moment().startOf('month'), moment().endOf('month')],
                                'เดือนที่แล้ว': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                                'ปีนี้': [moment().startOf('year'), moment().endOf('year')],
                                'ปีที่แล้ว': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
                            }
                        });
                    } catch (e) {
                        console.error('Error initializing daterangepicker:', e);
                    }
                });
            }
            
            console.log('  ✅ Date pickers initialized');
        },
        
        /**
         * Initialize Select2
         */
        initSelect2: function() {
            if (typeof $.fn.select2 === 'undefined') {
                console.warn('Select2 not loaded, skipping initialization');
                return;
            }
            
            console.log('Initializing Select2...');
            
            $('.select2').each(function() {
                try {
                    $(this).select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: $(this).data('placeholder') || 'เลือก...',
                        allowClear: true
                    });
                } catch (e) {
                    console.error('Error initializing select2:', e);
                }
            });
            
            console.log('  ✅ Select2 initialized');
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            if (typeof $.fn.tooltip === 'undefined') {
                console.warn('Bootstrap tooltip not loaded');
                return;
            }
            
            try {
                $('[data-toggle="tooltip"]').tooltip({
                    trigger: 'hover',
                    placement: 'top'
                });
            } catch (e) {
                console.error('Error initializing tooltips:', e);
            }
        },
        
        /**
         * Initialize popovers
         */
        initPopovers: function() {
            if (typeof $.fn.popover === 'undefined') {
                return;
            }
            
            try {
                $('[data-toggle="popover"]').popover({
                    trigger: 'click',
                    html: true
                });
            } catch (e) {
                console.error('Error initializing popovers:', e);
            }
        },
        
        /**
         * Check session
         */
        checkSession: function() {
            $.ajax({
                url: `${this.config.apiUrl}/check-session.php`,
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    if (!response.valid) {
                        window.location.href = '/eums/login.php';
                    }
                },
                error: function(xhr) {
                    console.error('Session check failed:', xhr.status, xhr.statusText);
                    // Don't redirect on connection error, just log
                }
            });
        },
        
        /**
         * Load user preferences
         */
        loadUserPreferences: function() {
            try {
                const prefs = localStorage.getItem('eums_preferences');
                if (prefs) {
                    this.state.userPrefs = JSON.parse(prefs);
                    this.applyUserPreferences();
                }
            } catch (e) {
                console.error('Error loading user preferences:', e);
            }
        },
        
        /**
         * Apply user preferences
         */
        applyUserPreferences: function() {
            try {
                // Apply theme
                if (this.state.userPrefs?.theme) {
                    $('body').attr('data-theme', this.state.userPrefs.theme);
                    $('#themeSelector').val(this.state.userPrefs.theme);
                }
                
                // Apply font size
                if (this.state.userPrefs?.fontSize) {
                    $('body').css('font-size', this.state.userPrefs.fontSize);
                    $('#fontSizeSelector').val(this.state.userPrefs.fontSize);
                }
                
                // Apply language
                if (this.state.userPrefs?.language) {
                    moment.locale(this.state.userPrefs.language === 'th' ? 'th' : 'en');
                }
            } catch (e) {
                console.error('Error applying user preferences:', e);
            }
        },
        
        /**
         * Save user preferences
         */
        saveUserPreferences: function(prefs) {
            try {
                this.state.userPrefs = { ...this.state.userPrefs, ...prefs };
                localStorage.setItem('eums_preferences', JSON.stringify(this.state.userPrefs));
                this.applyUserPreferences();
            } catch (e) {
                console.error('Error saving user preferences:', e);
            }
        },
        
        /**
         * Load notifications
         */
        loadNotifications: function() {
            $.ajax({
                url: `${this.config.apiUrl}/get-notifications.php`,
                method: 'GET',
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.state.notifications = response.data || [];
                        this.updateNotificationBadge();
                        this.renderNotifications();
                    }
                },
                error: (xhr) => {
                    console.error('Error loading notifications:', xhr.status);
                }
            });
        },
        
        /**
         * Update notification badge
         */
        updateNotificationBadge: function() {
            const count = this.state.notifications.length;
            $('#notificationCount').text(count > 0 ? count : '0');
            
            if (count > 0) {
                $('#notificationCount').show();
            } else {
                $('#notificationCount').hide();
            }
        },
        
        /**
         * Render notifications
         */
        renderNotifications: function() {
            const list = $('#notificationList');
            if (!list.length) return;
            
            if (this.state.notifications.length === 0) {
                list.html('<a href="#" class="dropdown-item"><i class="fas fa-info-circle mr-2"></i> ไม่มีการแจ้งเตือน</a>');
                return;
            }
            
            let html = '';
            this.state.notifications.slice(0, 5).forEach(notif => {
                html += `
                    <a href="${notif.link || '#'}" class="dropdown-item">
                        <i class="fas fa-${notif.icon || 'info-circle'} mr-2 text-${notif.type || 'info'}"></i>
                        <span class="text-truncate" style="max-width: 200px;">${notif.message}</span>
                        <small class="text-muted d-block">${moment(notif.time).fromNow()}</small>
                    </a>
                    <div class="dropdown-divider"></div>
                `;
            });
            
            list.html(html);
        },
        
        /**
         * Initialize auto refresh
         */
        initAutoRefresh: function() {
            const savedInterval = localStorage.getItem('eums_refresh_interval');
            if (savedInterval && parseInt(savedInterval) > 0) {
                $('#refreshInterval').val(savedInterval).trigger('change');
            }
        },
        
        /**
         * Load module
         */
        loadModule: function(moduleName) {
            this.state.currentModule = moduleName;
            this.state.isLoading = true;
            
            // Show loading
            $('#moduleContent').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">กำลังโหลดโมดูล ${moduleName}...</p>
                </div>
            `);
            
            // Load module content via AJAX
            $.ajax({
                url: `${this.config.apiUrl}/load-module.php`,
                method: 'POST',
                data: { module: moduleName },
                dataType: 'html',
                timeout: 30000,
                success: (response) => {
                    $('#moduleContent').html(response);
                    this.updateBreadcrumb(moduleName);
                    this.initModuleSpecific(moduleName);
                    this.state.isLoading = false;
                },
                error: (xhr) => {
                    let errorMsg = 'ไม่สามารถโหลดโมดูลได้';
                    if (xhr.status === 404) {
                        errorMsg = 'ไม่พบโมดูลที่ต้องการ';
                    } else if (xhr.status === 500) {
                        errorMsg = 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์';
                    }
                    
                    $('#moduleContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            ${errorMsg} (${xhr.status})
                        </div>
                    `);
                    this.state.isLoading = false;
                    console.error('Module load error:', xhr);
                }
            });
        },
        
        /**
         * Initialize module specific functions
         */
        initModuleSpecific: function(moduleName) {
            console.log(`Initializing module: ${moduleName}`);
            
            switch(moduleName) {
                case 'air':
                    this.initAirCompressor();
                    break;
                case 'energy':
                    this.initEnergyWater();
                    break;
                case 'lpg':
                    this.initLPG();
                    break;
                case 'boiler':
                    this.initBoiler();
                    break;
                case 'summary':
                    this.initSummary();
                    break;
                default:
                    console.log(`No specific initializer for module: ${moduleName}`);
            }
            
            // Trigger custom event
            $(document).trigger('moduleLoaded', [moduleName]);
        },
        
        /**
         * Air Compressor specific functions
         */
        initAirCompressor: function() {
            console.log('Air Compressor module initialized');
            
            // Load machine list
            if ($('#machineSelect').length) {
                $.ajax({
                    url: `${this.config.apiUrl}/get_machines.php?module=air`,
                    method: 'GET',
                    success: (response) => {
                        if (response.success) {
                            const select = $('#machineSelect');
                            response.data.forEach(machine => {
                                select.append(`<option value="${machine.id}">${machine.machine_name}</option>`);
                            });
                        }
                    }
                });
            }
        },
        
        /**
         * Energy & Water specific functions
         */
        initEnergyWater: function() {
            console.log('Energy & Water module initialized');
            
            // Calculate usage
            $('.meter-reading').on('input', function() {
                const row = $(this).closest('tr');
                const morning = parseFloat(row.find('.morning-reading').val()) || 0;
                const evening = parseFloat(row.find('.evening-reading').val()) || 0;
                
                if (evening > morning) {
                    const usage = (evening - morning).toFixed(2);
                    row.find('.usage-display').text(usage);
                    
                    if (usage > 1000) {
                        row.find('.usage-display').addClass('text-danger');
                    } else {
                        row.find('.usage-display').removeClass('text-danger');
                    }
                }
            });
        },
        
        /**
         * LPG specific functions
         */
        initLPG: function() {
            console.log('LPG module initialized');
            
            // Validate number inputs
            $('.lpg-number-input').on('input', function() {
                const value = parseFloat($(this).val());
                const standard = parseFloat($(this).data('standard'));
                
                if (!isNaN(value) && !isNaN(standard) && standard > 0) {
                    const deviation = Math.abs((value - standard) / standard * 100);
                    
                    if (deviation > 10) {
                        $(this).addClass('is-invalid');
                        $(this).removeClass('is-valid');
                    } else {
                        $(this).removeClass('is-invalid');
                        $(this).addClass('is-valid');
                    }
                }
            });
        },
        
        /**
         * Boiler specific functions
         */
        initBoiler: function() {
            console.log('Boiler module initialized');
            
            // Calculate efficiency
            $('.boiler-input').on('input', function() {
                const pressure = parseFloat($('#steamPressure').val()) || 0;
                const temp = parseFloat($('#steamTemperature').val()) || 0;
                const fuel = parseFloat($('#fuelConsumption').val()) || 0;
                const hours = parseFloat($('#operatingHours').val()) || 0;
                
                if (fuel > 0 && hours > 0) {
                    const efficiency = (pressure * temp) / (fuel * hours);
                    $('#efficiencyDisplay').text(efficiency.toFixed(2));
                }
            });
        },
        
        /**
         * Summary specific functions
         */
        initSummary: function() {
            console.log('Summary module initialized');
            
            // Calculate total cost
            $('#eeUnit, #costPerUnit').on('input', function() {
                const ee = parseFloat($('#eeUnit').val()) || 0;
                const cost = parseFloat($('#costPerUnit').val()) || 0;
                const total = (ee * cost).toFixed(2);
                $('#totalCost').val(total);
            });
        },
        
        /**
         * Update breadcrumb
         */
        updateBreadcrumb: function(moduleName) {
            const moduleTitles = {
                'air': 'Air Compressor',
                'energy': 'Energy & Water',
                'lpg': 'LPG',
                'boiler': 'Boiler',
                'summary': 'Summary Electricity'
            };
            
            const title = moduleTitles[moduleName] || moduleName;
            $('.breadcrumb-item.active').text(title);
            document.title = `EUMS - ${title}`;
        },
        
        /**
         * Refresh data
         */
        refreshData: function() {
            if (this.state.isLoading) return;
            
            // Show loading overlay
            $('#loadingOverlay').fadeIn(200);
            this.state.isLoading = true;
            
            // Refresh charts
            this.refreshCharts();
            
            // Refresh tables
            this.refreshTables();
            
            // Reload notifications
            this.loadNotifications();
            
            // Hide loading overlay after delay
            setTimeout(() => {
                $('#loadingOverlay').fadeOut(200);
                this.state.isLoading = false;
                this.showNotification('อัปเดตข้อมูลเรียบร้อย', 'success');
            }, 800);
        },
        
        /**
         * Refresh charts
         */
        refreshCharts: function() {
            // Reload chart data based on current module
            const event = new CustomEvent('refreshCharts', { 
                detail: { module: this.state.currentModule } 
            });
            document.dispatchEvent(event);
        },
        
        /**
         * Refresh tables
         */
        refreshTables: function() {
            // Reload DataTables
            Object.values(this.tables).forEach(table => {
                if (table && typeof table.ajax === 'function') {
                    table.ajax.reload(null, false);
                }
            });
        },
        
        /**
         * Update date range
         */
        updateDateRange: function(range) {
            console.log('Date range updated:', range);
            this.state.selectedDate = range;
            this.refreshData();
        },
        
        /**
         * Search table
         */
        searchTable: function(term) {
            if (this.tables.main) {
                this.tables.main.search(term).draw();
            }
        },
        
        /**
         * Export data
         */
        exportData: function(format) {
            console.log('Exporting data in format:', format);
            
            this.showNotification('กำลังส่งออกข้อมูล...', 'info');
            
            // Get current table data
            let data = [];
            if (this.tables.main) {
                data = this.tables.main.rows().data().toArray();
            }
            
            // Create export URL
            const params = new URLSearchParams({
                format: format,
                module: this.state.currentModule || '',
                data: JSON.stringify(data)
            });
            
            window.location.href = `/eums/export.php?${params.toString()}`;
            
            setTimeout(() => {
                this.showNotification('ส่งออกข้อมูลเรียบร้อย', 'success');
            }, 2000);
        },
        
        /**
         * Print report
         */
        printReport: function() {
            window.print();
        },
        
        /**
         * Change theme
         */
        changeTheme: function(theme) {
            $('body').attr('data-theme', theme);
            this.saveUserPreferences({ theme: theme });
            this.showNotification(`เปลี่ยนธีมเป็น ${theme === 'light' ? 'สว่าง' : 'มืด'}`, 'success');
        },
        
        /**
         * Change font size
         */
        changeFontSize: function(size) {
            $('body').css('font-size', size);
            this.saveUserPreferences({ fontSize: size });
            this.showNotification(`เปลี่ยนขนาดตัวอักษร`, 'success');
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            // Check if Toastr is available
            if (typeof toastr !== 'undefined') {
                toastr[type](message);
                return;
            }
            
            // Fallback to alert div
            const notification = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            
            $('#notificationContainer').html(notification);
            
            setTimeout(() => {
                $('.alert').alert('close');
            }, 5000);
        },
        
        /**
         * Validate form
         */
        validateForm: function(formId) {
            let isValid = true;
            const form = $(`#${formId}`);
            
            // Clear previous errors
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').remove();
            
            // Validate required fields
            form.find('[required]').each(function() {
                const $this = $(this);
                const value = $this.val();
                
                if (!value || value.trim() === '') {
                    isValid = false;
                    $this.addClass('is-invalid');
                    
                    const errorMsg = $this.data('error') || 'กรุณากรอกข้อมูล';
                    $this.after(`<div class="invalid-feedback">${errorMsg}</div>`);
                }
            });
            
            // Validate email fields
            form.find('input[type="email"]').each(function() {
                const $this = $(this);
                const email = $this.val();
                
                if (email && !EUMS.validateEmail(email)) {
                    isValid = false;
                    $this.addClass('is-invalid');
                    $this.after('<div class="invalid-feedback">รูปแบบอีเมลไม่ถูกต้อง</div>');
                }
            });
            
            // Validate number fields
            form.find('input[type="number"]').each(function() {
                const $this = $(this);
                const value = $this.val();
                
                if (value && isNaN(value)) {
                    isValid = false;
                    $this.addClass('is-invalid');
                    $this.after('<div class="invalid-feedback">กรุณากรอกตัวเลข</div>');
                }
                
                // Check min
                const min = $this.attr('min');
                if (min && parseFloat(value) < parseFloat(min)) {
                    isValid = false;
                    $this.addClass('is-invalid');
                    $this.after(`<div class="invalid-feedback">ค่าต้องไม่น้อยกว่า ${min}</div>`);
                }
                
                // Check max
                const max = $this.attr('max');
                if (max && parseFloat(value) > parseFloat(max)) {
                    isValid = false;
                    $this.addClass('is-invalid');
                    $this.after(`<div class="invalid-feedback">ค่าต้องไม่เกิน ${max}</div>`);
                }
            });
            
            // Validate date fields
            form.find('input[type="date"]').each(function() {
                const $this = $(this);
                const date = $this.val();
                
                if (date && !moment(date, 'YYYY-MM-DD', true).isValid()) {
                    isValid = false;
                    $this.addClass('is-invalid');
                    $this.after('<div class="invalid-feedback">รูปแบบวันที่ไม่ถูกต้อง</div>');
                }
            });
            
            return isValid;
        },
        
        /**
         * Validate email
         */
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        /**
         * Format number
         */
        formatNumber: function(number, decimals = 2) {
            if (number === null || number === undefined) return '-';
            
            return new Intl.NumberFormat('th-TH', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(number);
        },
        
        /**
         * Format date
         */
        formatDate: function(date, format = 'DD/MM/YYYY') {
            if (!date) return '-';
            return moment(date).format(format);
        },
        
        /**
         * Format datetime
         */
        formatDateTime: function(datetime, format = 'DD/MM/YYYY HH:mm') {
            if (!datetime) return '-';
            return moment(datetime).format(format);
        },
        
        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            if (amount === null || amount === undefined) return '-';
            
            return new Intl.NumberFormat('th-TH', {
                style: 'currency',
                currency: 'THB',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        },
        
        /**
         * Get URL parameter
         */
        getUrlParameter: function(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        },
        
        /**
         * Confirm action
         */
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },
        
        /**
         * Show modal
         */
        showModal: function(modalId) {
            $(modalId).modal('show');
        },
        
        /**
         * Hide modal
         */
        hideModal: function(modalId) {
            $(modalId).modal('hide');
        },
        
        /**
         * Log debug message
         */
        debug: function(...args) {
            if (this.config.debug) {
                console.log('[EUMS Debug]', ...args);
            }
        },
        
        /**
         * Handle AJAX error
         */
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            
            let message = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
            
            if (xhr.status === 401) {
                message = 'กรุณาเข้าสู่ระบบอีกครั้ง';
                setTimeout(() => {
                    window.location.href = '/eums/login.php';
                }, 2000);
            } else if (xhr.status === 403) {
                message = 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้';
            } else if (xhr.status === 404) {
                message = 'ไม่พบข้อมูลที่ต้องการ';
            } else if (xhr.status === 500) {
                message = 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์';
            }
            
            this.showNotification(message, 'danger');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EUMS.init();
    });

    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        if (EUMS && typeof EUMS.handleAjaxError === 'function') {
            EUMS.handleAjaxError(jqxhr, settings, thrownError);
        } else {
            console.error('AJAX Error:', thrownError);
        }
    });

    // Handle window resize
    $(window).on('resize', function() {
        // FIX: ใช้ EUMS.state.charts แทน EUMS.charts + guard null
        Object.values(EUMS.state.charts || {}).forEach(chart => {
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        });
    });

    // Handle before unload
    $(window).on('beforeunload', function() {
        // Clean up if needed
    });

    // Export EUMS to global scope
    window.EUMS = EUMS;
    
    console.log('✅ EUMS main.js loaded successfully');
}