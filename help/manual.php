<?php
/**
 * User Manual
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

// Set page title
$pageTitle = 'คู่มือการใช้งาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'ช่วยเหลือ', 'link' => '#'],
    ['title' => 'คู่มือการใช้งาน', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Get current user role for permission-based help
$userRole = $_SESSION['user_role'] ?? 'viewer';
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Search Box -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-search"></i>
                    ค้นหาคู่มือ
                </h3>
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="text" class="form-control form-control-lg" id="searchManual" 
                           placeholder="พิมพ์คำที่ต้องการค้นหา...">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" onclick="searchManual()">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-link"></i>
                            เนื้อหาที่พบบ่อย
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <a href="#getting-started" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-rocket"></i> เริ่มต้นใช้งาน
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#dashboard" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#air-compressor" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-compress"></i> Air Compressor
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#energy-water" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-bolt"></i> Energy & Water
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#lpg" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-fire"></i> LPG
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#boiler" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-industry"></i> Boiler
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#summary" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-chart-line"></i> Summary Electricity
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="#reports" class="btn btn-default btn-block mb-2">
                                    <i class="fas fa-chart-bar"></i> รายงาน
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Content -->
        <div class="row" id="manual-content">
            <div class="col-md-3">
                <!-- Table of Contents -->
                <div class="card card-primary card-outline sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i>
                            สารบัญ
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" id="toc">
                            <a href="#getting-started" class="list-group-item list-group-item-action">
                                <i class="fas fa-rocket"></i> เริ่มต้นใช้งาน
                            </a>
                            <a href="#system-requirements" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-desktop"></i> ความต้องการของระบบ
                            </a>
                            <a href="#login" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-sign-in-alt"></i> การเข้าสู่ระบบ
                            </a>
                            <a href="#dashboard" class="list-group-item list-group-item-action">
                                <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
                            </a>
                            <a href="#air-compressor" class="list-group-item list-group-item-action">
                                <i class="fas fa-compress"></i> Air Compressor
                            </a>
                            <a href="#ac-recording" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-pen"></i> การบันทึกข้อมูล
                            </a>
                            <a href="#ac-machines" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-cog"></i> การจัดการเครื่องจักร
                            </a>
                            <a href="#ac-standards" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-clipboard-check"></i> การตั้งค่ามาตรฐาน
                            </a>
                            <a href="#energy-water" class="list-group-item list-group-item-action">
                                <i class="fas fa-bolt"></i> Energy & Water
                            </a>
                            <a href="#ew-recording" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-pen"></i> การบันทึกค่ามิเตอร์
                            </a>
                            <a href="#ew-meters" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-cog"></i> การจัดการมิเตอร์
                            </a>
                            <a href="#lpg" class="list-group-item list-group-item-action">
                                <i class="fas fa-fire"></i> LPG
                            </a>
                            <a href="#lpg-recording" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-pen"></i> การบันทึกข้อมูล
                            </a>
                            <a href="#lpg-settings" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-cog"></i> การตั้งค่าหัวข้อตรวจสอบ
                            </a>
                            <a href="#boiler" class="list-group-item list-group-item-action">
                                <i class="fas fa-industry"></i> Boiler
                            </a>
                            <a href="#boiler-recording" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-pen"></i> การบันทึกข้อมูล
                            </a>
                            <a href="#boiler-machines" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-cog"></i> การจัดการเครื่องจักร
                            </a>
                            <a href="#summary" class="list-group-item list-group-item-action">
                                <i class="fas fa-chart-line"></i> Summary Electricity
                            </a>
                            <a href="#summary-recording" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-pen"></i> การบันทึกข้อมูล
                            </a>
                            <a href="#reports" class="list-group-item list-group-item-action">
                                <i class="fas fa-chart-bar"></i> รายงาน
                            </a>
                            <a href="#daily-report" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-calendar-day"></i> รายงานประจำวัน
                            </a>
                            <a href="#monthly-report" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-calendar-alt"></i> รายงานประจำเดือน
                            </a>
                            <a href="#yearly-report" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-calendar"></i> รายงานประจำปี
                            </a>
                            <a href="#comparison-report" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-chart-bar"></i> รายงานเปรียบเทียบ
                            </a>
                            <a href="#settings" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog"></i> การตั้งค่าระบบ
                            </a>
                            <a href="#user-management" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-users"></i> จัดการผู้ใช้งาน
                            </a>
                            <a href="#documents" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-file-alt"></i> จัดการเอกสาร
                            </a>
                            <a href="#backup" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-database"></i> สำรองข้อมูล
                            </a>
                            <a href="#profile" class="list-group-item list-group-item-action pl-4">
                                <i class="fas fa-user"></i> โปรไฟล์
                            </a>
                            <a href="#faq" class="list-group-item list-group-item-action">
                                <i class="fas fa-question-circle"></i> คำถามที่พบบ่อย
                            </a>
                            <a href="#troubleshooting" class="list-group-item list-group-item-action">
                                <i class="fas fa-exclamation-triangle"></i> การแก้ไขปัญหาเบื้องต้น
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <!-- Getting Started -->
                <div class="card" id="getting-started">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-rocket"></i>
                            เริ่มต้นใช้งานระบบ EUMS
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>ระบบ Engineering Utility Monitoring System (EUMS) เป็นระบบสำหรับติดตามและบันทึกข้อมูลสาธารณูปโภคต่างๆ ในโรงงาน เช่น ระบบอัดอากาศ ระบบไฟฟ้าและน้ำ ระบบ LPG ระบบ Boiler และสรุปการใช้ไฟฟ้า</p>
                        
                        <h5 id="system-requirements" class="mt-4">ความต้องการของระบบ</h5>
                        <ul>
                            <li><strong>เบราว์เซอร์:</strong> Chrome (เวอร์ชัน 80 ขึ้นไป), Firefox (เวอร์ชัน 75 ขึ้นไป), Edge (เวอร์ชัน 80 ขึ้นไป)</li>
                            <li><strong>ความละเอียดหน้าจอ:</strong> ขั้นต่ำ 1366x768 พิกเซล</li>
                            <li><strong>การเชื่อมต่ออินเทอร์เน็ต:</strong> จำเป็นสำหรับการโหลดทรัพยากรภายนอก (CDN)</li>
                        </ul>
                        
                        <h5 id="login" class="mt-4">การเข้าสู่ระบบ</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <ol>
                                    <li>เปิดเบราว์เซอร์และไปที่ URL ของระบบ EUMS</li>
                                    <li>กรอกชื่อผู้ใช้ (Username) และรหัสผ่าน (Password) ที่ได้รับจากผู้ดูแลระบบ</li>
                                    <li>เลือก "จดจำฉัน" หากต้องการให้ระบบจดจำการเข้าสู่ระบบในครั้งต่อไป</li>
                                    <li>คลิกปุ่ม "เข้าสู่ระบบ"</li>
                                </ol>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ
                                </div>
                            </div>
                            <div class="col-md-4">
                                <img src="/eums/assets/images/help/login.jpg" class="img-fluid img-thumbnail" alt="หน้าจอเข้าสู่ระบบ">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard -->
                <div class="card" id="dashboard">
                    <div class="card-header bg-success text-white">
                        <h3 class="card-title">
                            <i class="fas fa-tachometer-alt"></i>
                            แดชบอร์ด
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>หน้าแดชบอร์ดแสดงภาพรวมของข้อมูลทั้งหมดในระบบ ประกอบด้วย:</p>
                        
                        <h5>ส่วนประกอบของแดชบอร์ด</h5>
                        <ul>
                            <li><strong>Info Boxes:</strong> แสดงวันที่ปัจจุบัน จำนวนบันทึก การแจ้งเตือน และเวลาปัจจุบัน</li>
                            <li><strong>Module Cards:</strong> แสดงสรุปข้อมูลแต่ละโมดูล พร้อมลิงก์ไปยังหน้านั้นๆ</li>
                            <li><strong>กราฟสรุปรายเดือน:</strong> แสดงแนวโน้มการใช้งานของทุกโมดูล</li>
                            <li><strong>กราฟวงกลม:</strong> แสดงสัดส่วนการใช้งานในเดือนปัจจุบัน</li>
                            <li><strong>การแจ้งเตือน:</strong> แสดงรายการที่ผิดปกติ (Air, LPG, Boiler)</li>
                            <li><strong>กิจกรรมล่าสุด:</strong> แสดงประวัติการบันทึกล่าสุดจากทุกโมดูล</li>
                            <li><strong>เอกสารล่าสุด:</strong> แสดงเอกสารที่สร้างล่าสุด</li>
                        </ul>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>การแจ้งเตือน:</strong> จะแสดงเมื่อมีค่าที่บันทึกอยู่นอกเกณฑ์มาตรฐาน
                        </div>
                    </div>
                </div>

                <!-- Air Compressor Module -->
                <div class="card" id="air-compressor">
                    <div class="card-header bg-info text-white">
                        <h3 class="card-title">
                            <i class="fas fa-compress"></i>
                            โมดูล Air Compressor
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>โมดูลสำหรับบันทึกข้อมูลระบบอัดอากาศ รองรับการบันทึกค่าตามหัวข้อตรวจสอบที่กำหนด</p>
                        
                        <h5 id="ac-recording">การบันทึกข้อมูล</h5>
                        <ol>
                            <li>เลือกวันที่ต้องการบันทึก (ค่าเริ่มต้นเป็นวันปัจจุบัน)</li>
                            <li>เลือกเครื่องจักรที่ต้องการบันทึก</li>
                            <li>ระบบจะแสดงหัวข้อตรวจสอบของเครื่องนั้นๆ</li>
                            <li>กรอกค่าที่วัดได้ในแต่ละหัวข้อ</li>
                            <li>ระบบจะตรวจสอบค่าที่กรอกกับค่ามาตรฐาน (ถ้าค่านอกเกณฑ์จะขึ้นแถบสีแดง)</li>
                            <li>คลิก "บันทึกข้อมูล" เพื่อบันทึก</li>
                        </ol>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5 id="ac-machines">การจัดการเครื่องจักร</h5>
                                <p>สามารถเพิ่ม/แก้ไข/ลบ เครื่องจักรได้ที่เมนู "จัดการเครื่องจักร"</p>
                                <ul>
                                    <li><strong>รหัสเครื่อง:</strong> ต้องไม่ซ้ำกัน</li>
                                    <li><strong>ชื่อเครื่อง:</strong> ชื่อแสดงของเครื่อง</li>
                                    <li><strong>ยี่ห้อ/รุ่น:</strong> ข้อมูลจำเพาะของเครื่อง</li>
                                    <li><strong>ความจุ:</strong> ความสามารถของเครื่อง</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5 id="ac-standards">การตั้งค่ามาตรฐาน</h5>
                                <p>กำหนดหัวข้อตรวจสอบและค่ามาตรฐานได้ที่เมนู "ตั้งค่าการตรวจสอบ"</p>
                                <ul>
                                    <li><strong>ลำดับ:</strong> เรียงลำดับการแสดงผล</li>
                                    <li><strong>หัวข้อตรวจสอบ:</strong> ชื่อรายการที่ต้องการตรวจวัด</li>
                                    <li><strong>ค่ามาตรฐาน:</strong> ค่าที่ใช้เปรียบเทียบ</li>
                                    <li><strong>ค่าต่ำสุด-สูงสุด:</strong> ช่วงค่าที่ยอมรับได้</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Energy & Water Module -->
                <div class="card" id="energy-water">
                    <div class="card-header bg-warning">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i>
                            โมดูล Energy & Water
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>โมดูลสำหรับบันทึกค่ามิเตอร์ไฟฟ้าและน้ำ โดยบันทึกวันละ 2 เวลา (เช้าและเย็น)</p>
                        
                        <h5 id="ew-recording">การบันทึกค่ามิเตอร์</h5>
                        <ol>
                            <li>เลือกวันที่ต้องการบันทึก</li>
                            <li>กรอกค่าเช้าและค่าเย็นของแต่ละมิเตอร์</li>
                            <li>ระบบจะคำนวณปริมาณการใช้อัตโนมัติ (ค่าเย็น - ค่าเช้า)</li>
                            <li>ตรวจสอบค่าที่คำนวณได้ (ถ้าค่าเย็นน้อยกว่าค่าเช้าจะแจ้งเตือน)</li>
                            <li>คลิก "บันทึกข้อมูล" เพื่อบันทึก</li>
                        </ol>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>หมายเหตุ:</strong> ค่าเย็นต้องมากกว่าค่าเช้าเสมอ มิฉะนั้นระบบจะไม่อนุญาตให้บันทึก
                        </div>
                        
                        <h5 id="ew-meters" class="mt-3">การจัดการมิเตอร์</h5>
                        <p>สามารถเพิ่ม/แก้ไข/ลบ มิเตอร์ได้ที่เมนู "จัดการมิเตอร์"</p>
                        <ul>
                            <li><strong>ประเภทมิเตอร์:</strong> เลือกระหว่างมิเตอร์ไฟฟ้าหรือมิเตอร์น้ำ</li>
                            <li><strong>รหัสมิเตอร์:</strong> ต้องไม่ซ้ำกัน</li>
                            <li><strong>ชื่อมิเตอร์:</strong> ชื่อแสดงของมิเตอร์</li>
                            <li><strong>ตำแหน่งที่ติดตั้ง:</strong> บอกตำแหน่งของมิเตอร์</li>
                            <li><strong>ค่าเริ่มต้น:</strong> ค่ามิเตอร์เริ่มต้นก่อนเริ่มบันทึก</li>
                        </ul>
                    </div>
                </div>

                <!-- LPG Module -->
                <div class="card" id="lpg">
                    <div class="card-header bg-danger text-white">
                        <h3 class="card-title">
                            <i class="fas fa-fire"></i>
                            โมดูล LPG
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>โมดูลสำหรับบันทึกข้อมูล LPG รองรับทั้งการบันทึกแบบตัวเลขและแบบ OK/NG</p>
                        
                        <h5 id="lpg-recording">การบันทึกข้อมูล</h5>
                        <ol>
                            <li>เลือกวันที่ต้องการบันทึก</li>
                            <li>สำหรับหัวข้อแบบตัวเลข: กรอกค่าที่วัดได้</li>
                            <li>สำหรับหัวข้อแบบ OK/NG: เลือกสถานะ (OK/NG)</li>
                            <li>ระบบจะตรวจสอบค่าที่กรอกกับค่ามาตรฐาน</li>
                            <li>คลิก "บันทึกข้อมูล" เพื่อบันทึก</li>
                        </ol>
                        
                        <h5 id="lpg-settings">การตั้งค่าหัวข้อตรวจสอบ</h5>
                        <p>สามารถเพิ่ม/แก้ไข/ลบ หัวข้อตรวจสอบได้ที่เมนู "ตั้งค่า"</p>
                        <ul>
                            <li><strong>ลำดับ:</strong> เรียงลำดับการแสดงผล</li>
                            <li><strong>ประเภท:</strong> เลือกระหว่าง "แบบตัวเลข" หรือ "แบบ OK/NG"</li>
                            <li><strong>หัวข้อตรวจสอบ:</strong> ชื่อรายการ</li>
                            <li><strong>ค่ามาตรฐาน:</strong> ค่าที่ใช้เปรียบเทียบ</li>
                            <li><strong>หน่วย:</strong> สำหรับแบบตัวเลข</li>
                            <li><strong>ตัวเลือก:</strong> สำหรับแบบ OK/NG (ค่าเริ่มต้นคือ OK,NG)</li>
                        </ul>
                    </div>
                </div>

                <!-- Boiler Module -->
                <div class="card" id="boiler">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-industry"></i>
                            โมดูล Boiler
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>โมดูลสำหรับบันทึกข้อมูล Boiler ประกอบด้วยค่าต่างๆ ดังนี้</p>
                        
                        <h5 id="boiler-recording">การบันทึกข้อมูล</h5>
                        <ol>
                            <li>เลือกวันที่ต้องการบันทึก</li>
                            <li>เลือกเครื่อง Boiler ที่ต้องการบันทึก</li>
                            <li>กรอกข้อมูลตามฟิลด์ต่างๆ:
                                <ul>
                                    <li><strong>แรงดันไอน้ำ (bar):</strong> ค่ามาตรฐาน 8-12 bar</li>
                                    <li><strong>อุณหภูมิไอน้ำ (°C):</strong> ค่ามาตรฐาน 170-190 °C</li>
                                    <li><strong>ระดับน้ำในหม้อ (m):</strong> ค่ามาตรฐาน 0.5-1.5 m</li>
                                    <li><strong>ปริมาณเชื้อเพลิง (L):</strong> ปริมาณที่ใช้</li>
                                    <li><strong>ชั่วโมงการทำงาน (hr):</strong> จำนวนชั่วโมงที่ทำงาน</li>
                                </ul>
                            </li>
                            <li>ระบบจะตรวจสอบค่าที่กรอกกับค่ามาตรฐาน</li>
                            <li>คลิก "บันทึกข้อมูล" เพื่อบันทึก</li>
                        </ol>
                        
                        <h5 id="boiler-machines">การจัดการเครื่องจักร</h5>
                        <p>สามารถเพิ่ม/แก้ไข/ลบ เครื่อง Boiler ได้ที่เมนู "จัดการเครื่องจักร"</p>
                        <ul>
                            <li><strong>รหัสเครื่อง:</strong> ต้องไม่ซ้ำกัน</li>
                            <li><strong>ชื่อเครื่อง:</strong> ชื่อแสดงของเครื่อง</li>
                            <li><strong>ยี่ห้อ/รุ่น:</strong> ข้อมูลจำเพาะ</li>
                            <li><strong>ความจุ:</strong> ความสามารถในการผลิตไอน้ำ (T/hr)</li>
                            <li><strong>แรงดันสูงสุด:</strong> ค่าแรงดันสูงสุดที่เครื่องรองรับ</li>
                        </ul>
                    </div>
                </div>

                <!-- Summary Electricity Module -->
                <div class="card" id="summary">
                    <div class="card-header bg-success text-white">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            โมดูล Summary Electricity
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>โมดูลสำหรับบันทึกข้อมูลสรุปการใช้ไฟฟ้า คำนวณค่าไฟฟ้าอัตโนมัติ</p>
                        
                        <h5 id="summary-recording">การบันทึกข้อมูล</h5>
                        <ol>
                            <li>เลือกวันที่ต้องการบันทึก</li>
                            <li>กรอกหน่วยไฟฟ้าที่ใช้ (EE - kWh)</li>
                            <li>กรอกค่าไฟต่อหน่วย (บาท)</li>
                            <li>ระบบจะคำนวณค่าไฟฟ้าอัตโนมัติ (หน่วยไฟฟ้า × ค่าไฟต่อหน่วย)</li>
                            <li>กรอก PE (Power Factor) ถ้ามี</li>
                            <li>คลิก "บันทึกข้อมูล" เพื่อบันทึก</li>
                        </ol>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-calculator"></i>
                            <strong>การคำนวณ:</strong> ค่าไฟฟ้า = หน่วยไฟฟ้า × ค่าไฟต่อหน่วย
                        </div>
                    </div>
                </div>

                <!-- Reports -->
                <div class="card" id="reports">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            รายงาน
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>ระบบมีรายงานหลายรูปแบบให้เลือกใช้งาน</p>
                        
                        <h5 id="daily-report">รายงานประจำวัน</h5>
                        <p>แสดงข้อมูลทั้งหมดของวันที่เลือก สามารถดูข้อมูลแยกตามโมดูลได้</p>
                        <ul>
                            <li>เลือกวันที่ต้องการดูรายงาน</li>
                            <li>ระบบจะแสดงสรุปการบันทึกของทุกโมดูล</li>
                            <li>สามารถดูรายละเอียดแต่ละรายการได้</li>
                            <li>ส่งออกเป็น Excel หรือ PDF ได้</li>
                        </ul>
                        
                        <h5 id="monthly-report">รายงานประจำเดือน</h5>
                        <p>สรุปข้อมูลรายเดือน แสดงกราฟและสถิติ</p>
                        <ul>
                            <li>เลือกเดือนและปีที่ต้องการ</li>
                            <li>แสดงกราฟปริมาณการใช้งานรายวัน</li>
                            <li>สรุปสถิติแยกตามโมดูล</li>
                        </ul>
                        
                        <h5 id="yearly-report">รายงานประจำปี</h5>
                        <p>สรุปข้อมูลรายปี พร้อมเปรียบเทียบกับปีก่อน</p>
                        <ul>
                            <li>เลือกปีที่ต้องการ</li>
                            <li>แสดงกราฟปริมาณการใช้งานรายเดือน</li>
                            <li>เปรียบเทียบกับปีก่อนหน้า</li>
                            <li>แสดงอัตราการเติบโต</li>
                        </ul>
                        
                        <h5 id="comparison-report">รายงานเปรียบเทียบ</h5>
                        <p>เปรียบเทียบข้อมูลระหว่าง 2 ช่วงเวลา</p>
                        <ul>
                            <li>เลือกช่วงเวลาที่ 1 และช่วงเวลาที่ 2</li>
                            <li>ระบบจะเปรียบเทียบข้อมูลทุกโมดูล</li>
                            <li>แสดงความแตกต่างและเปอร์เซ็นต์การเปลี่ยนแปลง</li>
                            <li>มีปุ่มลัดสำหรับเปรียบเทียบเดือน/ปี</li>
                        </ul>
                    </div>
                </div>

                <!-- Settings -->
                <div class="card" id="settings">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-cog"></i>
                            การตั้งค่าระบบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <h5 id="user-management">จัดการผู้ใช้งาน</h5>
                        <p>เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถจัดการผู้ใช้งานได้</p>
                        <ul>
                            <li><strong>เพิ่มผู้ใช้:</strong> กรอกชื่อผู้ใช้, ชื่อ-นามสกุล, อีเมล, เลือกบทบาท</li>
                            <li><strong>แก้ไขผู้ใช้:</strong> แก้ไขข้อมูลผู้ใช้ (ยกเว้นชื่อผู้ใช้)</li>
                            <li><strong>ลบผู้ใช้:</strong> ลบผู้ใช้ (ไม่สามารถลบตัวเองได้)</li>
                            <li><strong>เปลี่ยนรหัสผ่าน:</strong> ผู้ใช้สามารถเปลี่ยนรหัสผ่านได้เองที่หน้าโปรไฟล์</li>
                        </ul>
                        
                        <h5 id="documents">จัดการเอกสาร</h5>
                        <p>จัดการเอกสารประจำเดือนของแต่ละโมดูล</p>
                        <ul>
                            <li><strong>เพิ่มเอกสาร:</strong> ระบุเลขที่เอกสาร, โมดูล, วันที่เริ่มใช้</li>
                            <li><strong>แก้ไขเอกสาร:</strong> แก้ไขข้อมูลเอกสาร</li>
                            <li><strong>ลบเอกสาร:</strong> ลบเอกสาร (ต้องไม่มีข้อมูลใช้งานอยู่)</li>
                        </ul>
                        
                        <h5 id="backup">สำรองข้อมูล</h5>
                        <p>สำรองและกู้คืนฐานข้อมูล</p>
                        <ul>
                            <li><strong>สำรองข้อมูล:</strong> สร้างไฟล์สำรองฐานข้อมูล (SQL)</li>
                            <li><strong>กู้คืนข้อมูล:</strong> อัปโหลดไฟล์สำรองเพื่อกู้คืน</li>
                            <li><strong>ตั้งค่าสำรองอัตโนมัติ:</strong> กำหนดเวลาสำรองข้อมูลอัตโนมัติ</li>
                            <li><strong>ลบไฟล์เก่า:</strong> ลบไฟล์สำรองที่เก่ากว่าจำนวนวันที่กำหนด</li>
                        </ul>
                        
                        <h5 id="profile">โปรไฟล์</h5>
                        <p>จัดการข้อมูลส่วนตัว</p>
                        <ul>
                            <li><strong>แก้ไขโปรไฟล์:</strong> เปลี่ยนชื่อ-นามสกุล, อีเมล</li>
                            <li><strong>เปลี่ยนรหัสผ่าน:</strong> เปลี่ยนรหัสผ่าน</li>
                            <li><strong>ตั้งค่า:</strong> กำหนดค่าการแจ้งเตือน, ภาษา, ธีม</li>
                            <li><strong>ดูประวัติ:</strong> ดูประวัติการเข้าใช้และกิจกรรม</li>
                        </ul>
                    </div>
                </div>

<!-- FAQ -->
<div class="card" id="faq">
    <div class="card-header bg-info text-white">
        <h3 class="card-title">
            <i class="fas fa-question-circle"></i>
            คำถามที่พบบ่อย (FAQ)
        </h3>
    </div>
    <div class="card-body">
        <div class="accordion" id="faqAccordion">
            <!-- คำถามที่ 1: การลืมรหัสผ่าน -->
            <div class="card">
                <div class="card-header" id="faq1">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true">
                            <i class="fas fa-key text-warning"></i>
                            ลืมรหัสผ่านทำอย่างไร?
                        </button>
                    </h5>
                </div>
                <div id="collapse1" class="collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p><strong>วิธีการแก้ไข:</strong></p>
                                <ol>
                                    <li>คลิกลิงก์ "ลืมรหัสผ่าน?" ที่หน้าเข้าสู่ระบบ</li>
                                    <li>กรอกอีเมลที่ลงทะเบียนไว้กับระบบ</li>
                                    <li>ระบบจะส่งลิงก์สำหรับรีเซ็ตรหัสผ่านไปยังอีเมลของคุณ</li>
                                    <li>คลิกลิงก์ในอีเมลและตั้งรหัสผ่านใหม่</li>
                                    <li>หากไม่ได้รับอีเมล กรุณาตรวจสอบโฟลเดอร์ขยะ (Spam/Junk)</li>
                                </ol>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>หมายเหตุ:</strong> หากยังไม่สามารถรีเซ็ตรหัสผ่านได้ กรุณาติดต่อผู้ดูแลระบบที่
                                    <a href="mailto:admin@eums.local">admin@eums.local</a> หรือเบอร์โทร 02-XXX-XXXX
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="fas fa-envelope fa-3x text-primary mb-2"></i>
                                        <h6>ติดต่อผู้ดูแลระบบ</h6>
                                        <p class="mb-1"><i class="fas fa-phone"></i> 02-123-4567</p>
                                        <p class="mb-0"><i class="fas fa-envelope"></i> admin@eums.local</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 2: การติดต่อผู้ดูแลระบบ -->
            <div class="card">
                <div class="card-header" id="faq2">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false">
                            <i class="fas fa-headset text-info"></i>
                            จะติดต่อผู้ดูแลระบบได้ช่องทางใดบ้าง?
                        </button>
                    </h5>
                </div>
                <div id="collapse2" class="collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>ช่องทางการติดต่อผู้ดูแลระบบ:</strong></p>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th><i class="fas fa-envelope text-primary"></i> อีเมล</th>
                                        <td>
                                            admin@eums.local<br>
                                            support@eums.local
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-phone text-success"></i> โทรศัพท์</th>
                                        <td>02-123-4567 ต่อ 1234<br>08-1234-5678 (มือถือ)</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th><i class="fas fa-clock text-warning"></i> เวลาทำการ</th>
                                        <td>
                                            จันทร์ - ศุกร์: 08:30 - 17:30 น.<br>
                                            เสาร์ - อาทิตย์: ปิดทำการ
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-map-marker-alt text-danger"></i> ที่อยู่</th>
                                        <td>
                                            ฝ่ายวิศวกรรม อาคารปฏิบัติการ<br>
                                            นิคมอุตสาหกรรมบางชัน
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="alert alert-info mt-2">
                            <i class="fas fa-info-circle"></i>
                            <strong>กรณีฉุกเฉินนอกเวลาทำการ:</strong> ส่งข้อความทางอีเมลพร้อมระบุ "ด่วน" ในหัวข้อ หรือโทร 08-1234-5678
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 3: บทบาทผู้ดูแลระบบ -->
            <div class="card">
                <div class="card-header" id="faq3">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false">
                            <i class="fas fa-user-shield text-danger"></i>
                            ผู้ดูแลระบบ (Admin) มีหน้าที่และความรับผิดชอบอะไรบ้าง?
                        </button>
                    </h5>
                </div>
                <div id="collapse3" class="collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>บทบาทและหน้าที่ของผู้ดูแลระบบ:</strong></p>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success"></i> ด้านการจัดการผู้ใช้</h6>
                                <ul>
                                    <li>เพิ่ม แก้ไข ลบ ผู้ใช้งานระบบ</li>
                                    <li>กำหนดบทบาทและสิทธิ์การเข้าถึง</li>
                                    <li>รีเซ็ตรหัสผ่านให้ผู้ใช้</li>
                                    <li>ระงับการใช้งานผู้ใช้ที่ไม่ปฏิบัติตามข้อกำหนด</li>
                                </ul>
                                
                                <h6 class="mt-3"><i class="fas fa-check-circle text-success"></i> ด้านความปลอดภัย</h6>
                                <ul>
                                    <li>ตรวจสอบ Log การเข้าใช้ระบบ</li>
                                    <li>ติดตามกิจกรรมที่น่าสงสัย</li>
                                    <li>จัดการเรื่องการสำรองข้อมูล</li>
                                    <li>อัปเดตระบบและปลั๊กอิน</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success"></i> ด้านการตั้งค่าระบบ</h6>
                                <ul>
                                    <li>กำหนดค่าพื้นฐานของระบบ</li>
                                    <li>จัดการเอกสารและมาตรฐาน</li>
                                    <li>ตั้งค่าการแจ้งเตือน</li>
                                    <li>กำหนดรูปแบบรายงาน</li>
                                </ul>
                                
                                <h6 class="mt-3"><i class="fas fa-check-circle text-success"></i> ด้านสนับสนุนผู้ใช้</h6>
                                <ul>
                                    <li>ให้คำปรึกษาการใช้งาน</li>
                                    <li>แก้ไขปัญหาทางเทคนิค</li>
                                    <li>ตอบข้อซักถาม</li>
                                    <li>อบรมการใช้งานเบื้องต้น</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 4: การแจ้งปัญหา -->
            <div class="card">
                <div class="card-header" id="faq4">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false">
                            <i class="fas fa-exclamation-circle text-danger"></i>
                            ต้องการแจ้งปัญหาหรือข้อผิดพลาดของระบบ ต้องทำอย่างไร?
                        </button>
                    </h5>
                </div>
                <div id="collapse4" class="collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>ช่องทางการแจ้งปัญหา:</strong></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-lightbulb"></i>
                            <strong>คำแนะนำ:</strong> กรุณาแจ้งรายละเอียดให้ครบถ้วนเพื่อให้ทีมงานสามารถแก้ไขปัญหาได้รวดเร็ว
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <h6>ข้อมูลที่ควรระบุเมื่อแจ้งปัญหา:</h6>
                                <ul>
                                    <li><strong>หัวข้อปัญหา:</strong> สรุปสั้นๆ เกี่ยวกับปัญหา</li>
                                    <li><strong>วันที่และเวลา:</strong> วันที่และเวลาที่เกิดปัญหา</li>
                                    <li><strong>โมดูลที่พบปัญหา:</strong> เช่น Air Compressor, LPG, รายงาน ฯลฯ</li>
                                    <li><strong>ขั้นตอนที่ทำ:</strong> อธิบายขั้นตอนที่ทำให้เกิดปัญหา</li>
                                    <li><strong>ข้อความแสดงข้อผิดพลาด:</strong> ข้อความ error ที่ปรากฏ (ถ้ามี)</li>
                                    <li><strong>รูปภาพ:</strong> แนบภาพหน้าจอประกอบ</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="text-center">ตัวอย่างการแจ้งปัญหา</h6>
                                        <hr>
                                        <p><strong>หัวข้อ:</strong> บันทึกข้อมูล LPG ไม่ได้</p>
                                        <p><strong>โมดูล:</strong> LPG</p>
                                        <p><strong>ปัญหา:</strong> กดบันทึกแล้วไม่มีการตอบสนอง</p>
                                        <p><strong>เบราว์เซอร์:</strong> Chrome เวอร์ชัน 120</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <p><strong>ช่องทางการแจ้งปัญหา:</strong></p>
                            <div class="btn-group">
                                <button class="btn btn-primary" onclick="window.location.href='mailto:w_krissada@marugo-rubber.co.th?subject=แจ้งปัญหาระบบ EUMS'">
                                    <i class="fas fa-envelope"></i> ส่งอีเมล
                                </button>
                                <button class="btn btn-info" onclick="window.open('http://sys.marugo-rubber.co.th/its/')">
                                    <i class="fas fa-ticket-alt"></i> ระบบ Helpdesk
                                </button>
                                <button class="btn btn-success" onclick="window.location.href='tel:0649310866'">
                                    <i class="fas fa-phone"></i> โทรแจ้ง
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 5: การขอเพิ่มสิทธิ์ -->
            <div class="card">
                <div class="card-header" id="faq5">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false">
                            <i class="fas fa-lock-open text-success"></i>
                            ต้องการขอเพิ่มสิทธิ์การเข้าถึง ต้องทำอย่างไร?
                        </button>
                    </h5>
                </div>
                <div id="collapse5" class="collapse" aria-labelledby="faq5" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>ขั้นตอนการขอเพิ่มสิทธิ์:</strong></p>
                        <ol>
                            <li>จัดทำบันทึกขอเพิ่มสิทธิ์การเข้าถึงระบบ EUMS ผ่านระบบ IT Helpdesk</li>
                            <li>ระบุเหตุผลความจำเป็นและสิทธิ์ที่ต้องการเพิ่ม</li>
                            <li>ส่งบันทึกถึงผู้บังคับบัญชาเพื่อพิจารณา</li>
                            <li>เมื่อได้รับการอนุมัติ ให้ส่งต่อให้ผู้ดูแลระบบดำเนินการ</li>
                            <li>ผู้ดูแลระบบจะดำเนินการปรับสิทธิ์ภายใน 1-2 วันทำการ</li>
                        </ol>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-download"></i>
                            <a href="http://sys.marugo-rubber.co.th/its/" class="alert-link">ขอเพิ่มสิทธิ์ผ่านระบบ IT Helpdesk</a>
                        </div>
                        
                        <p><strong>สิทธิ์การเข้าถึงในระบบ:</strong></p>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>บทบาท</th>
                                    <th>สิทธิ์</th>
                                    <th>คำอธิบาย</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-danger">ผู้ดูแลระบบ</span></td>
                                    <td>ทั้งหมด</td>
                                    <td>จัดการผู้ใช้ ตั้งค่าระบบ ดูรายงานทั้งหมด</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-warning">ผู้ปฏิบัติงาน</span></td>
                                    <td>บันทึก แก้ไข ดูข้อมูล</td>
                                    <td>บันทึกข้อมูลประจำวัน แก้ไขข้อมูลที่ตนเองบันทึก</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-secondary">ผู้ดู</span></td>
                                    <td>ดูอย่างเดียว</td>
                                    <td>ดูแดชบอร์ดและรายงาน ไม่สามารถแก้ไขข้อมูล</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 6: การสำรองข้อมูล -->
            <div class="card">
                <div class="card-header" id="faq6">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false">
                            <i class="fas fa-database text-primary"></i>
                            ระบบมีการสำรองข้อมูลอย่างไร?
                        </button>
                    </h5>
                </div>
                <div id="collapse6" class="collapse" aria-labelledby="faq6" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>นโยบายการสำรองข้อมูล:</strong></p>
                        <ul>
                            <li>สำรองข้อมูลอัตโนมัติทุกวัน เวลา 02:00 น.</li>
                            <li>เก็บไฟล์สำรองย้อนหลัง 30 วัน</li>
                            <li>ไฟล์สำรองถูกบีบอัด (.gz) เพื่อประหยัดพื้นที่</li>
                            <li>สามารถดาวน์โหลดไฟล์สำรองได้ที่เมนู "ตั้งค่า > สำรองข้อมูล"</li>
                        </ul>
                        
                        <p><strong>ผู้ที่สามารถสำรองข้อมูลได้:</strong> เฉพาะผู้ดูแลระบบเท่านั้น</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>ข้อควรทราบ:</strong> การกู้คืนข้อมูลจะเขียนทับข้อมูลปัจจุบันทั้งหมด ควรสำรองข้อมูลก่อนการกู้คืนทุกครั้ง
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 7: การอัปเดตระบบ -->
            <div class="card">
                <div class="card-header" id="faq7">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7" aria-expanded="false">
                            <i class="fas fa-sync-alt text-info"></i>
                            ระบบมีการอัปเดตบ่อยแค่ไหน?
                        </button>
                    </h5>
                </div>
                <div id="collapse7" class="collapse" aria-labelledby="faq7" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>กำหนดการอัปเดตระบบ:</strong></p>
                        <ul>
                            <li><strong>อัปเดตเล็กน้อย:</strong> ทุกเดือน (แก้ไขบัค, ปรับปรุงประสิทธิภาพ)</li>
                            <li><strong>อัปเดตใหญ่:</strong> ทุก 6 เดือน (เพิ่มฟีเจอร์ใหม่)</li>
                            <li><strong>อัปเดตฉุกเฉิน:</strong> ตามความจำเป็น (แก้ปัญหาสำคัญ)</li>
                        </ul>
                        
                        <p><strong>การแจ้งเตือน:</strong> ผู้ใช้จะได้รับการแจ้งเตือนผ่านอีเมลล่วงหน้า 1 สัปดาห์ก่อนการอัปเดต</p>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>เวอร์ชันปัจจุบัน:</strong> <?php echo config('app.version', '1.0.0'); ?> (อัปเดตล่าสุด <?php echo date('d/m/Y', filemtime(__DIR__ . '/../index.php')); ?>)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 8: การใช้งานบนมือถือ -->
            <div class="card">
                <div class="card-header" id="faq8">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8" aria-expanded="false">
                            <i class="fas fa-mobile-alt text-success"></i>
                            ใช้งานบนมือถือได้หรือไม่?
                        </button>
                    </h5>
                </div>
                <div id="collapse8" class="collapse" aria-labelledby="faq8" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>การรองรับอุปกรณ์:</strong></p>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success"></i> อุปกรณ์ที่รองรับ</h6>
                                <ul>
                                    <li>คอมพิวเตอร์ (แนะนำ)</li>
                                    <li>แท็บเล็ต (รองรับบางฟังก์ชัน)</li>
                                    <li>สมาร์ทโฟน (ดูข้อมูลและรายงานได้)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-exclamation-triangle text-warning"></i> ข้อจำกัดบนมือถือ</h6>
                                <ul>
                                    <li>การบันทึกข้อมูลอาจไม่สะดวก</li>
                                    <li>กราฟแสดงผลย่อขนาดลง</li>
                                    <li>ตารางอาจต้องเลื่อนดู</li>
                                </ul>
                            </div>
                        </div>
                        
                        <p class="mt-2"><strong>แนะนำ:</strong> ใช้งานบนคอมพิวเตอร์เพื่อประสิทธิภาพสูงสุด โดยเฉพาะการบันทึกข้อมูล</p>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 9: ความปลอดภัยของข้อมูล -->
            <div class="card">
                <div class="card-header" id="faq9">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse9" aria-expanded="false">
                            <i class="fas fa-shield-alt text-danger"></i>
                            ระบบมีความปลอดภัยอย่างไร?
                        </button>
                    </h5>
                </div>
                <div id="collapse9" class="collapse" aria-labelledby="faq9" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>มาตรการรักษาความปลอดภัย:</strong></p>
                        <ul>
                            <li>เข้ารหัสรหัสผ่านด้วยเทคโนโลยีที่ได้มาตรฐาน</li>
                            <li>จำกัดจำนวนครั้งในการเข้าระบบผิดพลาด (ล็อค 15 นาที)</li>
                            <li>บันทึกประวัติการเข้าใช้และการดำเนินการทั้งหมด</li>
                            <li>แยกสิทธิ์การเข้าถึงตามบทบาทหน้าที่</li>
                            <li>ป้องกันการโจมตีแบบ SQL Injection และ XSS</li>
                            <li>ใช้ HTTPS ในการส่งข้อมูล (ถ้าติดตั้ง SSL)</li>
                        </ul>
                        
                        <p><strong>คำแนะนำสำหรับผู้ใช้:</strong></p>
                        <ul>
                            <li>เปลี่ยนรหัสผ่านทุก 3-6 เดือน</li>
                            <li>ไม่แชร์รหัสผ่านกับผู้อื่น</li>
                            <li>ออกจากระบบทุกครั้งเมื่อเลิกใช้งาน</li>
                            <li>แจ้งผู้ดูแลระบบทันทีเมื่อสงสัยว่ามีการใช้งานโดยไม่ได้รับอนุญาต</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- คำถามที่ 10: การฝึกอบรมการใช้งาน -->
            <div class="card">
                <div class="card-header" id="faq10">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse10" aria-expanded="false">
                            <i class="fas fa-chalkboard-teacher text-primary"></i>
                            มีการอบรมการใช้งานหรือไม่?
                        </button>
                    </h5>
                </div>
                <div id="collapse10" class="collapse" aria-labelledby="faq10" data-bs-parent="#faqAccordion">
                    <div class="card-body">
                        <p><strong>หลักสูตรการอบรม:</strong></p>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>หลักสูตร</th>
                                    <th>ระยะเวลา</th>
                                    <th>กลุ่มเป้าหมาย</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>การใช้งานพื้นฐานสำหรับผู้ปฏิบัติงาน</td>
                                    <td>3 ชั่วโมง</td>
                                    <td>ผู้ปฏิบัติงานใหม่</td>
                                </tr>
                                <tr>
                                    <td>การใช้งานสำหรับหัวหน้างาน</td>
                                    <td>2 ชั่วโมง</td>
                                    <td>หัวหน้างาน, วิศวกร</td>
                                </tr>
                                <tr>
                                    <td>การดูแลระบบสำหรับ Admin</td>
                                    <td>6 ชั่วโมง</td>
                                    <td>ผู้ดูแลระบบ</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p><strong>ช่องทางการสมัคร:</strong> ติดต่อฝ่ายทรัพยากรบุคคล หรือผู้ดูแลระบบ</p>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-video"></i>
                            <strong>สื่อการเรียนรู้:</strong> สามารถดาวน์โหลดคู่มือและวิดีโอการสอนได้ที่ 
                            <a href="/eums/help/training">/eums/help/training</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                <!-- Troubleshooting -->
                <div class="card" id="troubleshooting">
                    <div class="card-header bg-danger text-white">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            การแก้ไขปัญหาเบื้องต้น
                        </h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ปัญหา</th>
                                    <th>สาเหตุ</th>
                                    <th>วิธีการแก้ไข</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>ไม่สามารถเข้าสู่ระบบได้</td>
                                    <td>
                                        - ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง<br>
                                        - บัญชีถูกล็อค (พยายามผิดหลายครั้ง)<br>
                                        - บัญชีถูกระงับการใช้งาน
                                    </td>
                                    <td>
                                        - ตรวจสอบชื่อผู้ใช้และรหัสผ่านอีกครั้ง<br>
                                        - รอ 15 นาทีแล้วลองใหม่<br>
                                        - ติดต่อผู้ดูแลระบบ
                                    </td>
                                </tr>
                                <tr>
                                    <td>บันทึกข้อมูลไม่ได้</td>
                                    <td>
                                        - ข้อมูลไม่ครบถ้วน<br>
                                        - ค่าที่กรอกไม่ถูกต้อง<br>
                                        - มีข้อมูลซ้ำ (บันทึกไปแล้ว)
                                    </td>
                                    <td>
                                        - ตรวจสอบเครื่องหมาย * ว่ากรอกครบหรือไม่<br>
                                        - ตรวจสอบค่าที่กรอก (เช่น ค่าเย็นต้องมากกว่าค่าเช้า)<br>
                                        - ตรวจสอบว่าวันที่นี้บันทึกไปแล้วหรือยัง
                                    </td>
                                </tr>
                                <tr>
                                    <td>กราฟไม่แสดง</td>
                                    <td>
                                        - ไม่มีข้อมูลในช่วงเวลาที่เลือก<br>
                                        - ปัญหาเครือข่าย<br>
                                        - เบราว์เซอร์ไม่รองรับ
                                    </td>
                                    <td>
                                        - เลือกช่วงเวลาที่มีข้อมูล<br>
                                        - รีเฟรชหน้า<br>
                                        - อัปเดตเบราว์เซอร์
                                    </td>
                                </tr>
                                <tr>
                                    <td>ระบบช้า</td>
                                    <td>
                                        - ข้อมูลมีปริมาณมาก<br>
                                        - การเชื่อมต่ออินเทอร์เน็ตช้า<br>
                                        - เบราว์เซอร์ทำงานหนัก
                                    </td>
                                    <td>
                                        - ลดช่วงเวลาที่เลือกในรายงาน<br>
                                        - ปิดแท็บที่ไม่ใช้งาน<br>
                                        - ล้างแคชของเบราว์เซอร์
                                    </td>
                                </tr>
                                <tr>
                                    <td>ส่งออกรายงานไม่ได้</td>
                                    <td>
                                        - ข้อมูลมีปริมาณมากเกินไป<br>
                                        - เบราว์เซอร์บล็อกป๊อปอัป<br>
                                        - ปัญหาไฟล์ชั่วคราว
                                    </td>
                                    <td>
                                        - ลดช่วงเวลาที่เลือก<br>
                                        - อนุญาตป๊อปอัปในเบราว์เซอร์<br>
                                        - ลองส่งออกใหม่
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Version Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-code-branch"></i>
                            ข้อมูลเวอร์ชัน
                        </h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 30%">เวอร์ชันปัจจุบัน</th>
                                <td><?php echo config('app.version', '1.0.0'); ?></td>
                            </tr>
                            <tr>
                                <th>วันที่อัปเดตล่าสุด</th>
                                <td><?php echo date('d/m/Y', filemtime(__DIR__ . '/../index.php')); ?></td>
                            </tr>
                            <tr>
                                <th>ผู้พัฒนา</th>
                                <td>W.KRISSADA</td>
                            </tr>
                            <tr>
                                <th>ติดต่อ</th>
                                <td><a href="mailto:w_krissada@marugo-rubber.co.th">w_krissada@marugo-rubber.co.th</a></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Smooth scroll for anchor links
    $('a[href*="#"]').on('click', function(e) {
        e.preventDefault();
        
        var target = this.hash;
        var $target = $(target);
        
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 70
            }, 500, function() {
                window.location.hash = target;
            });
        }
    });
    
    // Highlight current section in TOC
    $(window).on('scroll', function() {
        var scrollPos = $(document).scrollTop() + 100;
        
        $('#toc a').each(function() {
            var currLink = $(this);
            var refElement = $(currLink.attr('href'));
            
            if (refElement.length) {
                var offset = refElement.offset().top;
                
                if (offset <= scrollPos && offset + refElement.height() > scrollPos) {
                    $('#toc a').removeClass('active');
                    currLink.addClass('active');
                } else {
                    currLink.removeClass('active');
                }
            }
        });
    });
});

function searchManual() {
    var searchTerm = $('#searchManual').val().toLowerCase();
    
    if (searchTerm.length < 3) {
        showNotification('กรุณาพิมพ์คำค้นหาอย่างน้อย 3 ตัวอักษร', 'warning');
        return;
    }
    
    // Remove existing highlights
    $('.highlight').removeClass('highlight');
    
    // Search in content
    var found = false;
    $('#manual-content .card-body').each(function() {
        var content = $(this).text().toLowerCase();
        if (content.indexOf(searchTerm) !== -1) {
            $(this).closest('.card').addClass('border-primary');
            
            // Highlight search terms (simple version)
            var regex = new RegExp('(' + searchTerm + ')', 'gi');
            var html = $(this).html().replace(regex, '<span class="highlight bg-warning">$1</span>');
            $(this).html(html);
            
            found = true;
        }
    });
    
    if (!found) {
        showNotification('ไม่พบข้อมูลที่ค้นหา', 'info');
    }
    
    // Scroll to first result
    $('html, body').animate({
        scrollTop: $('.border-primary').first().offset().top - 100
    }, 500);
}
</script>

<style>
.highlight {
    background-color: #ffc107;
    padding: 2px;
    border-radius: 3px;
}

#toc {
    max-height: 500px;
    overflow-y: auto;
}

#toc .list-group-item.active {
    background-color: #007bff;
    border-color: #007bff;
}

.sticky-top {
    z-index: 100;
}

.card-header .btn-link {
    color: #fff;
    text-decoration: none;
}

.card-header .btn-link:hover {
    text-decoration: underline;
}

@media print {
    #toc, .btn, .main-header, .main-sidebar {
        display: none !important;
    }
    .content-wrapper {
        margin-left: 0 !important;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>