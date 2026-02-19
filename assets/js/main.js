/**
 * EUMS Main JavaScript
 * Engineering Utility Monitoring System
 */

// Global EUMS Object
const EUMS = {
    // Configuration
    config: {
        apiUrl: '/eums/api',
        refreshInterval: 300000, // 5 minutes
        dateFormat: 'DD/MM/YYYY',
        timeFormat: 'HH:mm:ss'
    },
    
    // State management
    state: {
        currentModule: null,
        selectedDate: new Date(),
        charts: {},
        tables: {}
    },
    
    // Initialize application
    init: function() {
        console.log('EUMS initialized');
        this.initEventListeners();
        this.initCharts();
        this.initDataTables();
        this.initDatePickers();
        this.initTooltips();
        this.checkSession();
        this.loadUserPreferences();
    },
    
    // Event Listeners
    initEventListeners: function() {
        // Module navigation
        $('.module-card').on('click', function() {
            const module = $(this).data('module');
            EUMS.loadModule(module);
        });
        
        // Refresh button
        $('#refreshData').on('click', function() {
            EUMS.refreshData();
        });
        
        // Date range change
        $('#dateRange').on('change', function() {
            EUMS.updateDateRange($(this).val());
        });
        
        // Export button
        $('#exportData').on('click', function() {
            EUMS.exportData($(this).data('format'));
        });
        
        // Print button
        $('#printReport').on('click', function() {
            EUMS.printReport();
        });
    },
    
    // Charts initialization
    initCharts: function() {
        // Usage trend chart
        if ($('#usageTrendChart').length) {
            this.charts.usageTrend = new Chart($('#usageTrendChart'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'ปริมาณการใช้งาน',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0,123,255,0.1)',
                        tension: 0.4,
                        fill: true
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
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        // Comparison chart - managed by comparison.php's own script block.
        // main.js does NOT instantiate this chart to avoid double-init conflicts.
        // The canvas is initialized by renderComparisonChart() on that page.
        
        // Pie chart for distribution
        if ($('#distributionChart').length) {
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
                            '#6610f2'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    },
    
    // DataTables initialization
    initDataTables: function() {
        if ($('#dataTable').length) {
            this.tables.main = $('#dataTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Thai.json'
                },
                pageLength: 25,
                responsive: true,
                ordering: true,
                searching: true,
                processing: true,
                serverSide: false,
                dom: 'Bfrtip',
                buttons: [
                    {
                        text: '<i class="fas fa-copy"></i> คัดลอก',
                        extend: 'copy'
                    },
                    {
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        extend: 'excel'
                    },
                    {
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        extend: 'pdf'
                    },
                    {
                        text: '<i class="fas fa-print"></i> พิมพ์',
                        extend: 'print'
                    }
                ]
            });
        }
    },
    
    // Date pickers initialization
    initDatePickers: function() {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true,
            language: 'th',
            thaiyear: true
        });
        
        $('.daterangepicker').daterangepicker({
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' - ',
                applyLabel: 'ตกลง',
                cancelLabel: 'ยกเลิก',
                fromLabel: 'จาก',
                toLabel: 'ถึง',
                customRangeLabel: 'กำหนดเอง',
                daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                monthNames: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],
                firstDay: 1
            },
            ranges: {
                'วันนี้': [moment(), moment()],
                'เมื่อวาน': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                '7 วันล่าสุด': [moment().subtract(6, 'days'), moment()],
                '30 วันล่าสุด': [moment().subtract(29, 'days'), moment()],
                'เดือนนี้': [moment().startOf('month'), moment().endOf('month')],
                'เดือนที่แล้ว': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });
    },
    
    // Tooltips initialization
    initTooltips: function() {
        $('[data-toggle="tooltip"]').tooltip();
    },
    
    // Check session
    checkSession: function() {
        $.ajax({
            url: `${this.config.apiUrl}/check-session.php`,
            method: 'GET',
            success: function(response) {
                if (!response.valid) {
                    window.location.href = '/eums/login.php';
                }
            },
            error: function() {
                console.error('Session check failed');
            }
        });
    },
    
    // Load user preferences
    loadUserPreferences: function() {
        const prefs = localStorage.getItem('eums_preferences');
        if (prefs) {
            this.state.userPrefs = JSON.parse(prefs);
            this.applyUserPreferences();
        }
    },
    
    // Apply user preferences
    applyUserPreferences: function() {
        // Apply theme
        if (this.state.userPrefs?.theme) {
            $('body').attr('data-theme', this.state.userPrefs.theme);
        }
        
        // Apply font size
        if (this.state.userPrefs?.fontSize) {
            $('body').css('font-size', this.state.userPrefs.fontSize);
        }
    },
    
    // Load module
    loadModule: function(moduleName) {
        this.state.currentModule = moduleName;
        
        // Show loading
        $('#moduleContent').html('<div class="text-center"><div class="spinner-custom"></div><p class="mt-3">กำลังโหลด...</p></div>');
        
        // Load module content via AJAX
        $.ajax({
            url: `${this.config.apiUrl}/load-module.php`,
            method: 'POST',
            data: { module: moduleName },
            success: function(response) {
                $('#moduleContent').html(response);
                EUMS.updateBreadcrumb(moduleName);
                EUMS.initModuleSpecific(moduleName);
            },
            error: function() {
                $('#moduleContent').html('<div class="alert alert-danger">ไม่สามารถโหลดโมดูลได้</div>');
            }
        });
    },
    
    // Initialize module specific functions
    initModuleSpecific: function(moduleName) {
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
        }
    },
    
    // Air Compressor specific functions
    initAirCompressor: function() {
        console.log('Air Compressor module initialized');
        // Add air compressor specific logic here
    },
    
    // Energy & Water specific functions
    initEnergyWater: function() {
        console.log('Energy & Water module initialized');
        // Add energy & water specific logic here
    },
    
    // LPG specific functions
    initLPG: function() {
        console.log('LPG module initialized');
        // Add LPG specific logic here
    },
    
    // Boiler specific functions
    initBoiler: function() {
        console.log('Boiler module initialized');
        // Add boiler specific logic here
    },
    
    // Summary specific functions
    initSummary: function() {
        console.log('Summary module initialized');
        // Add summary specific logic here
    },
    
    // Update breadcrumb
    updateBreadcrumb: function(moduleName) {
        const moduleTitles = {
            'air': 'Air Compressor',
            'energy': 'Energy & Water',
            'lpg': 'LPG',
            'boiler': 'Boiler',
            'summary': 'Summary Electricity'
        };
        
        $('.breadcrumb-item.active').text(moduleTitles[moduleName] || moduleName);
    },
    
    // Refresh data
    refreshData: function() {
        // Show loading overlay
        $('#loadingOverlay').fadeIn();
        
        // Refresh charts
        this.refreshCharts();
        
        // Refresh tables
        this.refreshTables();
        
        // Hide loading overlay after 1 second
        setTimeout(function() {
            $('#loadingOverlay').fadeOut();
            EUMS.showNotification('อัปเดตข้อมูลเรียบร้อย', 'success');
        }, 1000);
    },
    
    // Refresh charts
    refreshCharts: function() {
        // Implementation depends on the current module
        console.log('Refreshing charts...');
    },
    
    // Refresh tables
    refreshTables: function() {
        if (this.tables.main) {
            this.tables.main.ajax.reload();
        }
    },
    
    // Update date range
    updateDateRange: function(range) {
        console.log('Date range updated:', range);
        this.refreshData();
    },
    
    // Export data
    exportData: function(format) {
        console.log('Exporting data in format:', format);
        
        // Show loading
        EUMS.showNotification('กำลังส่งออกข้อมูล...', 'info');
        
        // Implement export logic
        setTimeout(function() {
            EUMS.showNotification('ส่งออกข้อมูลเรียบร้อย', 'success');
        }, 2000);
    },
    
    // Print report
    printReport: function() {
        window.print();
    },
    
    // Show notification
    showNotification: function(message, type = 'info') {
        // Create notification element
        const notification = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
        
        // Add to notification container
        $('#notificationContainer').html(notification);
        
        // Auto hide after 3 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 3000);
    },
    
    // Validate form
    validateForm: function(formId) {
        let isValid = true;
        const form = $(`#${formId}`);
        
        // Clear previous errors
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').remove();
        
        // Validate required fields
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">กรุณากรอกข้อมูล</div>');
            }
        });
        
        // Validate email fields
        form.find('input[type="email"]').each(function() {
            const email = $(this).val();
            if (email && !EUMS.validateEmail(email)) {
                isValid = false;
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">รูปแบบอีเมลไม่ถูกต้อง</div>');
            }
        });
        
        // Validate number fields
        form.find('input[type="number"]').each(function() {
            const value = $(this).val();
            if (value && isNaN(value)) {
                isValid = false;
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">กรุณากรอกตัวเลข</div>');
            }
        });
        
        return isValid;
    },
    
    // Validate email
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    // Format number
    formatNumber: function(number, decimals = 2) {
        return new Intl.NumberFormat('th-TH', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    },
    
    // Format date
    formatDate: function(date, format = 'DD/MM/YYYY') {
        return moment(date).format(format);
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('th-TH', {
            style: 'currency',
            currency: 'THB'
        }).format(amount);
    }
};

// Initialize when document is ready
$(document).ready(function() {
    EUMS.init();
});

// Handle AJAX errors globally
$(document).ajaxError(function(event, jqxhr, settings, thrownError) {
    console.error('AJAX Error:', thrownError);
    EUMS.showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
});

// Handle window resize
$(window).on('resize', function() {
    // Resize charts
    if (EUMS.charts.usageTrend) {
        EUMS.charts.usageTrend.resize();
    }
    if (EUMS.charts.comparison) {
        EUMS.charts.comparison.resize();
    }
    if (EUMS.charts.distribution) {
        EUMS.charts.distribution.resize();
    }
});