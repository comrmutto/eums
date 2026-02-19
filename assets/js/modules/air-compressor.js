/**
 * Air Compressor Module JavaScript
 */

const AirCompressorModule = {
    // Initialize module
    init: function() {
        console.log('Air Compressor Module loaded');
        this.initEventListeners();
        this.loadMachines();
        this.loadInspectionStandards();
        this.initCharts();
    },
    
    // Event listeners
    initEventListeners: function() {
        // Add machine button
        $('#addMachineBtn').on('click', function() {
            AirCompressorModule.showMachineModal();
        });
        
        // Add inspection item button
        $('#addInspectionItemBtn').on('click', function() {
            AirCompressorModule.showInspectionModal();
        });
        
        // Save machine
        $('#saveMachineBtn').on('click', function() {
            AirCompressorModule.saveMachine();
        });
        
        // Save inspection item
        $('#saveInspectionItemBtn').on('click', function() {
            AirCompressorModule.saveInspectionItem();
        });
        
        // Date change
        $('#recordDate').on('change', function() {
            AirCompressorModule.loadDailyRecords($(this).val());
        });
    },
    
    // Load machines
    loadMachines: function() {
        $.ajax({
            url: `${EUMS.config.apiUrl}/air/get-machines.php`,
            method: 'GET',
            success: function(response) {
                let html = '';
                response.forEach(function(machine) {
                    html += `
                        <tr>
                            <td>${machine.machine_code}</td>
                            <td>${machine.machine_name}</td>
                            <td>${machine.brand}</td>
                            <td>${machine.model}</td>
                            <td>${EUMS.formatNumber(machine.capacity)} ${machine.unit}</td>
                            <td>
                                <span class="badge badge-${machine.status ? 'success' : 'danger'}">
                                    ${machine.status ? 'ใช้งาน' : 'ไม่ใช้งาน'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="AirCompressorModule.editMachine(${machine.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="AirCompressorModule.deleteMachine(${machine.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#machinesTable tbody').html(html);
            }
        });
    },
    
    // Load inspection standards
    loadInspectionStandards: function() {
        $.ajax({
            url: `${EUMS.config.apiUrl}/air/get-inspection-standards.php`,
            method: 'GET',
            success: function(response) {
                let html = '';
                response.forEach(function(item) {
                    html += `
                        <tr>
                            <td>${item.machine_name}</td>
                            <td>${item.inspection_item}</td>
                            <td>${EUMS.formatNumber(item.standard_value)} ${item.unit}</td>
                            <td>${EUMS.formatNumber(item.min_value)} - ${EUMS.formatNumber(item.max_value)}</td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="AirCompressorModule.editInspectionItem(${item.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="AirCompressorModule.deleteInspectionItem(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#inspectionTable tbody').html(html);
            }
        });
    },
    
    // Load daily records
    loadDailyRecords: function(date) {
        $.ajax({
            url: `${EUMS.config.apiUrl}/air/get-daily-records.php`,
            method: 'POST',
            data: { date: date },
            success: function(response) {
                // Update table
                let html = '';
                response.records.forEach(function(record) {
                    html += `
                        <tr>
                            <td>${record.machine_name}</td>
                            <td>${record.inspection_item}</td>
                            <td>${EUMS.formatNumber(record.actual_value)}</td>
                            <td>${EUMS.formatNumber(record.standard_value)}</td>
                            <td>
                                <span class="badge badge-${record.status === 'ok' ? 'success' : 'danger'}">
                                    ${record.status === 'ok' ? 'ผ่าน' : 'ไม่ผ่าน'}
                                </span>
                            </td>
                            <td>${record.remarks || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="AirCompressorModule.editRecord(${record.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#dailyRecordsTable tbody').html(html);
                
                // Update chart
                AirCompressorModule.updateChart(response.chartData);
            }
        });
    },
    
    // Save machine
    saveMachine: function() {
        if (!EUMS.validateForm('machineForm')) {
            return;
        }
        
        const formData = $('#machineForm').serialize();
        
        $.ajax({
            url: `${EUMS.config.apiUrl}/air/save-machine.php`,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#machineModal').modal('hide');
                    EUMS.showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                    AirCompressorModule.loadMachines();
                } else {
                    EUMS.showNotification(response.message, 'danger');
                }
            }
        });
    },
    
    // Save inspection item
    saveInspectionItem: function() {
        if (!EUMS.validateForm('inspectionForm')) {
            return;
        }
        
        const formData = $('#inspectionForm').serialize();
        
        $.ajax({
            url: `${EUMS.config.apiUrl}/air/save-inspection-item.php`,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#inspectionModal').modal('hide');
                    EUMS.showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                    AirCompressorModule.loadInspectionStandards();
                } else {
                    EUMS.showNotification(response.message, 'danger');
                }
            }
        });
    },
    
    // Show machine modal
    showMachineModal: function(machineId = null) {
        if (machineId) {
            // Load machine data for edit
            $.ajax({
                url: `${EUMS.config.apiUrl}/air/get-machine.php`,
                method: 'GET',
                data: { id: machineId },
                success: function(response) {
                    $('#machineForm input[name="id"]').val(response.id);
                    $('#machineForm input[name="machine_code"]').val(response.machine_code);
                    $('#machineForm input[name="machine_name"]').val(response.machine_name);
                    $('#machineForm input[name="brand"]').val(response.brand);
                    $('#machineForm input[name="model"]').val(response.model);
                    $('#machineForm input[name="capacity"]').val(response.capacity);
                    $('#machineForm select[name="unit"]').val(response.unit);
                    $('#machineForm select[name="status"]').val(response.status);
                    
                    $('#machineModal .modal-title').text('แก้ไขเครื่องจักร');
                    $('#machineModal').modal('show');
                }
            });
        } else {
            // Clear form for new machine
            $('#machineForm')[0].reset();
            $('#machineForm input[name="id"]').val('');
            $('#machineModal .modal-title').text('เพิ่มเครื่องจักร');
            $('#machineModal').modal('show');
        }
    },
    
    // Show inspection modal
    showInspectionModal: function(itemId = null) {
        if (itemId) {
            // Load inspection item for edit
            $.ajax({
                url: `${EUMS.config.apiUrl}/air/get-inspection-item.php`,
                method: 'GET',
                data: { id: itemId },
                success: function(response) {
                    $('#inspectionForm input[name="id"]').val(response.id);
                    $('#inspectionForm select[name="machine_id"]').val(response.machine_id);
                    $('#inspectionForm input[name="inspection_item"]').val(response.inspection_item);
                    $('#inspectionForm input[name="standard_value"]').val(response.standard_value);
                    $('#inspectionForm select[name="unit"]').val(response.unit);
                    $('#inspectionForm input[name="min_value"]').val(response.min_value);
                    $('#inspectionForm input[name="max_value"]').val(response.max_value);
                    
                    $('#inspectionModal .modal-title').text('แก้ไขหัวข้อตรวจสอบ');
                    $('#inspectionModal').modal('show');
                }
            });
        } else {
            // Clear form for new item
            $('#inspectionForm')[0].reset();
            $('#inspectionForm input[name="id"]').val('');
            $('#inspectionModal .modal-title').text('เพิ่มหัวข้อตรวจสอบ');
            $('#inspectionModal').modal('show');
        }
    },
    
    // Delete machine
    deleteMachine: function(machineId) {
        if (confirm('คุณต้องการลบเครื่องจักรนี้ใช่หรือไม่?')) {
            $.ajax({
                url: `${EUMS.config.apiUrl}/air/delete-machine.php`,
                method: 'POST',
                data: { id: machineId },
                success: function(response) {
                    if (response.success) {
                        EUMS.showNotification('ลบข้อมูลเรียบร้อย', 'success');
                        AirCompressorModule.loadMachines();
                    } else {
                        EUMS.showNotification(response.message, 'danger');
                    }
                }
            });
        }
    },
    
    // Delete inspection item
    deleteInspectionItem: function(itemId) {
        if (confirm('คุณต้องการลมหัวข้อตรวจสอบนี้ใช่หรือไม่?')) {
            $.ajax({
                url: `${EUMS.config.apiUrl}/air/delete-inspection-item.php`,
                method: 'POST',
                data: { id: itemId },
                success: function(response) {
                    if (response.success) {
                        EUMS.showNotification('ลบข้อมูลเรียบร้อย', 'success');
                        AirCompressorModule.loadInspectionStandards();
                    } else {
                        EUMS.showNotification(response.message, 'danger');
                    }
                }
            });
        }
    },
    
    // Update chart
    updateChart: function(data) {
        if (EUMS.charts.usageTrend) {
            EUMS.charts.usageTrend.data.labels = data.labels;
            EUMS.charts.usageTrend.data.datasets[0].data = data.values;
            EUMS.charts.usageTrend.update();
        }
    }
};

// Initialize when document is ready
$(document).ready(function() {
    AirCompressorModule.init();
});