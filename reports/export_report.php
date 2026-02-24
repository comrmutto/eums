<?php
/**
 * Export Report — Excel (PhpSpreadsheet)
 * Engineering Utility Monitoring System (EUMS)
 *
 * URL params:
 *   type   : daily | monthly | yearly | comparison
 *   format : excel  (pdf → export_pdf.php)
 *   date          (daily)
 *   month, year   (monthly)
 *   year          (yearly)
 *   period1_start, period1_end, period2_start, period2_end, compare_type  (comparison)
 */

// ─── Bootstrap ───────────────────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// PhpSpreadsheet — ติดตั้งผ่าน Composer:
//   composer require phpoffice/phpspreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Font, NumberFormat};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ─── Parameters ──────────────────────────────────────────────────────────────
$type   = $_GET['type']   ?? 'daily';
$format = $_GET['format'] ?? 'excel';

// หากต้องการ PDF ให้ redirect ไปที่ export_pdf.php
if ($format === 'pdf') {
    $q = http_build_query($_GET);
    header("Location: export_pdf.php?$q");
    exit();
}

$db = getDB();

// ─── Thai helper ─────────────────────────────────────────────────────────────
function thaiMonth(int $m): string {
    return ['', 'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
            'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'][$m];
}
function thaiMonthShort(int $m): string {
    return ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
            'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$m];
}
function fmt(float|null $v, int $d = 2): string {
    return $v !== null ? number_format($v, $d) : '-';
}

// ─── Style Presets ───────────────────────────────────────────────────────────
$PURPLE    = '7B68EE';   // header gradient start
$PURPLE2   = '764BA2';   // header gradient end
$BLUE_HDR  = '4361EE';   // section header
$LIGHT_BG  = 'EFF2FF';   // zebra row
$WHITE     = 'FFFFFF';
$BORDER_C  = 'D0D5E8';

function styleHeader(Worksheet $ws, string $range, string $bgColor,
                     bool $bold = true, int $size = 11,
                     string $fontColor = 'FFFFFF'): void {
    $ws->getStyle($range)->applyFromArray([
        'font'      => ['bold' => $bold, 'size' => $size,
                        'color' => ['rgb' => $fontColor],
                        'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgColor]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['rgb' => 'AAAAAA']]],
    ]);
}

function styleData(Worksheet $ws, string $range,
                   string $bg = 'FFFFFF', bool $rightAlign = false): void {
    $ws->getStyle($range)->applyFromArray([
        'font'      => ['name' => 'Arial', 'size' => 10],
        'fill'      => ['fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bg]],
        'alignment' => ['horizontal' => $rightAlign
                         ? Alignment::HORIZONTAL_RIGHT
                         : Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['rgb' => 'D0D5E8']]],
    ]);
}

// ─── Spreadsheet Factory ─────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('EUMS System')
    ->setLastModifiedBy($_SESSION['username'] ?? 'System')
    ->setTitle('EUMS Report')
    ->setSubject("EUMS $type Report")
    ->setCompany(config('app.name'));

// ─── Dispatch ────────────────────────────────────────────────────────────────
match ($type) {
    'daily'      => buildDaily($spreadsheet, $db),
    'monthly'    => buildMonthly($spreadsheet, $db),
    'yearly'     => buildYearly($spreadsheet, $db),
    'comparison' => buildComparison($spreadsheet, $db),
    default      => die('Invalid type'),
};

// ─── Stream to browser ───────────────────────────────────────────────────────
$filename = "EUMS_{$type}_" . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();


// ══════════════════════════════════════════════════════════════════════════════
// DAILY
// ══════════════════════════════════════════════════════════════════════════════
function buildDaily(Spreadsheet $sp, $db): void {
    $date        = $_GET['date'] ?? date('d/m/Y');
    // รองรับ d/m/Y หรือ Y-m-d
    $dateParsed  = strlen($date) === 10 && strpos($date, '/') !== false
                 ? DateTime::createFromFormat('d/m/Y', $date)->format('Y-m-d')
                 : $date;
    $thaiDate    = date('d', strtotime($dateParsed)) . ' '
                 . thaiMonth((int)date('m', strtotime($dateParsed))) . ' '
                 . ((int)date('Y', strtotime($dateParsed)) + 543);

    // ── Cover Sheet ──────────────────────────────────────────────────────────
    $cover = $sp->getActiveSheet()->setTitle('รายงานประจำวัน');
    writeCoverSheet($cover, 'รายงานประจำวัน', $thaiDate,
                    'Daily Report — All Modules', config('app.name'));

    // ── Sheet: Air Compressor ────────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('Air Compressor');
    writeSheetHeader($ws, '🔵 Air Compressor', $thaiDate, '4361EE');

    $stmt = $db->prepare("
        SELECT m.machine_name, s.inspection_item, r.actual_value,
               s.standard_value, s.min_value, s.max_value, r.recorded_by
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE r.record_date = ? ORDER BY m.machine_code, s.id
    ");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['เครื่องจักร','หัวข้อตรวจสอบ','ค่าจริง','ค่ามาตรฐาน','Min','Max','สถานะ','ผู้บันทึก'];
    $colWidths = [20, 30, 12, 14, 10, 10, 10, 18];
    writeTableHeader($ws, 4, $headers, $colWidths, '4361EE');

    $r = 5;
    foreach ($rows as $i => $row) {
        $isOK = isAirOK($row);
        $bg   = $i % 2 === 0 ? 'EFF2FF' : 'FFFFFF';
        $ws->setCellValue("A$r", $row['machine_name']);
        $ws->setCellValue("B$r", $row['inspection_item']);
        $ws->setCellValue("C$r", (float)$row['actual_value']);
        $ws->setCellValue("D$r", (float)$row['standard_value']);
        $ws->setCellValue("E$r", $row['min_value'] ?? '-');
        $ws->setCellValue("F$r", $row['max_value'] ?? '-');
        $ws->setCellValue("G$r", $isOK ? 'OK' : 'NG');
        $ws->setCellValue("H$r", $row['recorded_by']);
        styleData($ws, "A$r:B$r", $bg);
        styleData($ws, "C$r:F$r", $bg, true);
        $ws->getStyle("G$r")->applyFromArray([
            'font'  => ['bold' => true, 'color' => ['rgb' => $isOK ? '16A34A' : 'DC2626']],
            'fill'  => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $isOK ? 'DCFCE7' : 'FEE2E2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        styleData($ws, "H$r", $bg);
        $r++;
    }
    writeEmptyNote($ws, $r, 8, empty($rows));

    // ── Sheet: Energy & Water ────────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('Energy & Water');
    writeSheetHeader($ws, '⚡ Energy & Water', $thaiDate, 'F59E0B');

    $stmt = $db->prepare("
        SELECT m.meter_name, m.meter_type, r.morning_reading,
               r.evening_reading, r.usage_amount, r.recorded_by
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date = ? ORDER BY m.meter_type, m.meter_code
    ");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['มิเตอร์','ประเภท','อ่านเช้า','อ่านเย็น','การใช้งาน','หน่วย','ผู้บันทึก'];
    $colWidths = [25, 14, 14, 14, 14, 10, 18];
    writeTableHeader($ws, 4, $headers, $colWidths, 'F59E0B', '1E1E1E');

    $r = 5;
    foreach ($rows as $i => $row) {
        $bg   = $i % 2 === 0 ? 'FFFBEB' : 'FFFFFF';
        $unit = $row['meter_type'] === 'electricity' ? 'kWh' : 'm³';
        $ws->setCellValue("A$r", $row['meter_name']);
        $ws->setCellValue("B$r", $row['meter_type'] === 'electricity' ? 'ไฟฟ้า' : 'น้ำ');
        $ws->setCellValue("C$r", (float)$row['morning_reading']);
        $ws->setCellValue("D$r", (float)$row['evening_reading']);
        $ws->setCellValue("E$r", (float)$row['usage_amount']);
        $ws->setCellValue("F$r", $unit);
        $ws->setCellValue("G$r", $row['recorded_by']);
        styleData($ws, "A$r:B$r", $bg);
        styleData($ws, "C$r:E$r", $bg, true);
        styleData($ws, "F$r:G$r", $bg);
        $r++;
    }
    // Summary row
    if (!empty($rows)) {
        $elec = array_sum(array_column(
            array_filter($rows, fn($x) => $x['meter_type'] === 'electricity'), 'usage_amount'));
        $water = array_sum(array_column(
            array_filter($rows, fn($x) => $x['meter_type'] === 'water'), 'usage_amount'));
        $ws->setCellValue("A$r", 'รวมไฟฟ้า');
        $ws->setCellValue("E$r", $elec);
        $ws->setCellValue("F$r", 'kWh');
        styleData($ws, "A$r:G$r", 'FEF3C7', false);
        $ws->getStyle("A$r")->getFont()->setBold(true);
        $r++;
        $ws->setCellValue("A$r", 'รวมน้ำ');
        $ws->setCellValue("E$r", $water);
        $ws->setCellValue("F$r", 'm³');
        styleData($ws, "A$r:G$r", 'DBEAFE', false);
        $ws->getStyle("A$r")->getFont()->setBold(true);
    }
    writeEmptyNote($ws, $r + 1, 7, empty($rows));

    // ── Sheet: LPG ───────────────────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('LPG');
    writeSheetHeader($ws, '🔴 LPG', $thaiDate, 'EF4444');

    $stmt = $db->prepare("
        SELECT i.item_name, i.item_type,
               COALESCE(r.number_value, r.enum_value) as value, r.recorded_by
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date = ? ORDER BY i.item_no
    ");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['รายการ', 'ประเภท', 'ค่า / สถานะ', 'ผู้บันทึก'];
    $colWidths = [35, 14, 18, 18];
    writeTableHeader($ws, 4, $headers, $colWidths, 'EF4444');

    $r = 5;
    foreach ($rows as $i => $row) {
        $bg  = $i % 2 === 0 ? 'FEF2F2' : 'FFFFFF';
        $isOK = ($row['item_type'] === 'enum' && $row['value'] === 'OK');
        $isNG = ($row['item_type'] === 'enum' && $row['value'] === 'NG');
        $ws->setCellValue("A$r", $row['item_name']);
        $ws->setCellValue("B$r", $row['item_type'] === 'number' ? 'ตัวเลข' : 'OK/NG');
        $ws->setCellValue("C$r", $row['value']);
        $ws->setCellValue("D$r", $row['recorded_by']);
        styleData($ws, "A$r:D$r", $bg);
        if ($isOK || $isNG) {
            $ws->getStyle("C$r")->applyFromArray([
                'font'  => ['bold' => true, 'color' => ['rgb' => $isOK ? '16A34A' : 'DC2626']],
                'fill'  => ['fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $isOK ? 'DCFCE7' : 'FEE2E2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $r++;
    }
    writeEmptyNote($ws, $r, 4, empty($rows));

    // ── Sheet: Boiler ────────────────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('Boiler');
    writeSheetHeader($ws, '🏭 Boiler', $thaiDate, '6B7280');

    $stmt = $db->prepare("
        SELECT m.machine_name, r.steam_pressure, r.steam_temperature,
               r.fuel_consumption, r.operating_hours, r.recorded_by
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date = ? ORDER BY m.machine_code
    ");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['เครื่องจักร','แรงดันไอน้ำ (bar)','อุณหภูมิ (°C)','เชื้อเพลิง (L)','ชั่วโมงทำงาน','ผู้บันทึก'];
    $colWidths = [22, 18, 16, 16, 16, 18];
    writeTableHeader($ws, 4, $headers, $colWidths, '6B7280');

    $r = 5;
    foreach ($rows as $i => $row) {
        $bg = $i % 2 === 0 ? 'F3F4F6' : 'FFFFFF';
        $ws->setCellValue("A$r", $row['machine_name']);
        $ws->setCellValue("B$r", (float)$row['steam_pressure']);
        $ws->setCellValue("C$r", (float)$row['steam_temperature']);
        $ws->setCellValue("D$r", (float)$row['fuel_consumption']);
        $ws->setCellValue("E$r", (float)$row['operating_hours']);
        $ws->setCellValue("F$r", $row['recorded_by']);
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r:E$r", $bg, true);
        styleData($ws, "F$r", $bg);
        $r++;
    }
    writeEmptyNote($ws, $r, 6, empty($rows));

    // ── Sheet: Summary Electricity ───────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('Summary Electricity');
    writeSheetHeader($ws, '📊 Summary Electricity', $thaiDate, '10B981');

    $stmt = $db->prepare("
        SELECT ee_unit, cost_per_unit, total_cost, pe, recorded_by
        FROM electricity_summary WHERE record_date = ?
    ");
    $stmt->execute([$dateParsed]);
    $sumRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $labels = ['หน่วยไฟฟ้า (kWh)', 'ค่าไฟต่อหน่วย (บาท)', 'ค่าไฟฟ้ารวม (บาท)', 'PE', 'ผู้บันทึก'];
    $values = $sumRow
        ? [fmt($sumRow['ee_unit']), fmt($sumRow['cost_per_unit'], 4),
           fmt($sumRow['total_cost']), fmt($sumRow['pe'], 4), $sumRow['recorded_by']]
        : array_fill(0, 5, '-');

    $ws->getColumnDimension('A')->setWidth(28);
    $ws->getColumnDimension('B')->setWidth(24);
    writeTableHeader($ws, 4, ['รายการ', 'ค่า'], [28, 24], '10B981');

    foreach ($labels as $i => $label) {
        $r = 5 + $i;
        $ws->setCellValue("A$r", $label);
        $ws->setCellValue("B$r", $values[$i]);
        $bg = $i % 2 === 0 ? 'ECFDF5' : 'FFFFFF';
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r", $bg, true);
    }
    writeEmptyNote($ws, 11, 2, $sumRow === false);

    // ── set active sheet ─────────────────────────────────────────────────────
    $sp->setActiveSheetIndex(0);
}


// ══════════════════════════════════════════════════════════════════════════════
// MONTHLY
// ══════════════════════════════════════════════════════════════════════════════
function buildMonthly(Spreadsheet $sp, $db): void {
    $month     = (int)($_GET['month'] ?? date('m'));
    $year      = (int)($_GET['year']  ?? date('Y'));
    $thaiYear  = $year + 543;
    $period    = thaiMonth($month) . ' ' . $thaiYear;
    $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate   = date('Y-m-t', strtotime($startDate));

    // ── Cover Sheet ──────────────────────────────────────────────────────────
    $cover = $sp->getActiveSheet()->setTitle('รายงานประจำเดือน');
    writeCoverSheet($cover, 'รายงานประจำเดือน', $period,
                    'Monthly Summary Report', config('app.name'));

    // ── Sheet: Monthly Summary ───────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('สรุปรายเดือน');
    writeSheetHeader($ws, '📋 สรุปทุกโมดูล', $period, '4361EE');

    // fetch all module summaries
    $air    = queryOne($db, "SELECT COUNT(DISTINCT r.record_date) as days,
                COUNT(r.id) as records, COUNT(DISTINCT r.machine_id) as machines,
                SUM(r.actual_value) as total, AVG(r.actual_value) as avg,
                SUM(CASE WHEN (s.min_value IS NOT NULL AND
                    (r.actual_value<s.min_value OR r.actual_value>s.max_value))
                    OR (s.min_value IS NULL AND ABS(r.actual_value-s.standard_value)>s.standard_value*0.1)
                    THEN 1 ELSE 0 END) as ng
             FROM air_daily_records r
             JOIN air_inspection_standards s ON r.inspection_item_id=s.id
             WHERE r.record_date BETWEEN ? AND ?", [$startDate, $endDate]);

    $energy = queryOne($db, "SELECT COUNT(DISTINCT r.record_date) as days,
                COUNT(r.id) as records,
                SUM(CASE WHEN m.meter_type='electricity' THEN r.usage_amount ELSE 0 END) as elec,
                SUM(CASE WHEN m.meter_type='water' THEN r.usage_amount ELSE 0 END) as water,
                AVG(r.usage_amount) as avg
             FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
             WHERE r.record_date BETWEEN ? AND ?", [$startDate, $endDate]);

    $lpg    = queryOne($db, "SELECT COUNT(DISTINCT r.record_date) as days,
                COUNT(r.id) as records,
                SUM(CASE WHEN i.item_type='number' THEN r.number_value ELSE 0 END) as total,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='OK' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
             FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
             WHERE r.record_date BETWEEN ? AND ?", [$startDate, $endDate]);

    $boiler = queryOne($db, "SELECT COUNT(DISTINCT record_date) as days,
                COUNT(id) as records, COUNT(DISTINCT machine_id) as machines,
                SUM(fuel_consumption) as fuel, SUM(operating_hours) as hours,
                AVG(steam_pressure) as avg_pressure, AVG(steam_temperature) as avg_temp
             FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?",
             [$startDate, $endDate]);

    $elecSum = queryOne($db, "SELECT COUNT(*) as days,
                SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost,
                AVG(cost_per_unit) as avg_rate, AVG(ee_unit) as avg_ee
             FROM electricity_summary WHERE record_date BETWEEN ? AND ?",
             [$startDate, $endDate]);

    // write summary table
    $ws->getColumnDimension('A')->setWidth(28);
    $ws->getColumnDimension('B')->setWidth(14);
    $ws->getColumnDimension('C')->setWidth(18);
    $ws->getColumnDimension('D')->setWidth(18);
    $ws->getColumnDimension('E')->setWidth(18);
    $ws->getColumnDimension('F')->setWidth(18);

    writeTableHeader($ws, 4, ['โมดูล','วันที่มีข้อมูล','บันทึกทั้งหมด','ค่ารวม','ค่าเฉลี่ย','หมายเหตุ'],
                     [28,14,18,18,18,22], '4361EE');

    $summaries = [
        ['🔵 Air Compressor',    $air['days'],    $air['records'],    fmt($air['total']),    fmt($air['avg']),   "NG: ".($air['ng']??0)." รายการ"],
        ['⚡ ไฟฟ้า (kWh)',       $energy['days'], $energy['records'], fmt($energy['elec']),  '-',                'ไฟฟ้า'],
        ['💧 น้ำ (m³)',          $energy['days'], '-',                fmt($energy['water']), '-',                'น้ำประปา'],
        ['🔴 LPG',               $lpg['days'],    $lpg['records'],    fmt($lpg['total']),    '-',                "OK:{$lpg['ok']} NG:{$lpg['ng']}"],
        ['🏭 Boiler (ชม.)',      $boiler['days'], $boiler['records'], fmt($boiler['hours']), fmt($boiler['avg_pressure']),'เชื้อเพลิง: '.fmt($boiler['fuel']).' L'],
        ['📊 ค่าไฟฟ้า (บาท)',   $elecSum['days'],$elecSum['days'],   fmt($elecSum['total_cost']), fmt($elecSum['avg_ee']).' kWh/วัน', 'อัตรา '.fmt($elecSum['avg_rate'],4).' บาท/หน่วย'],
    ];

    foreach ($summaries as $i => [$mod, $days, $recs, $total, $avg, $note]) {
        $r  = 5 + $i;
        $bg = $i % 2 === 0 ? 'EFF2FF' : 'FFFFFF';
        $ws->setCellValueExplicit("A$r", $mod, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->setCellValue("B$r", $days  ?? '-');
        $ws->setCellValue("C$r", $recs  ?? '-');
        $ws->setCellValue("D$r", $total ?? '-');
        $ws->setCellValue("E$r", $avg   ?? '-');
        $ws->setCellValue("F$r", $note  ?? '-');
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r:E$r", $bg, true);
        styleData($ws, "F$r", $bg);
    }

    // ── Sheet: Daily Breakdown ───────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('รายวัน');
    writeSheetHeader($ws, '📅 รายละเอียดรายวัน', $period, '7C3AED');

    $stmt = $db->prepare("
        SELECT
            r.record_date,
            SUM(r.usage_amount) as energy_total,
            SUM(CASE WHEN m.meter_type='electricity' THEN r.usage_amount ELSE 0 END) as elec,
            SUM(CASE WHEN m.meter_type='water'       THEN r.usage_amount ELSE 0 END) as water
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
        GROUP BY r.record_date ORDER BY r.record_date
    ");
    $stmt->execute([$startDate, $endDate]);
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $elecSumDaily = $db->prepare("
        SELECT record_date, ee_unit, cost_per_unit, total_cost, pe
        FROM electricity_summary WHERE record_date BETWEEN ? AND ? ORDER BY record_date
    ");
    $elecSumDaily->execute([$startDate, $endDate]);
    $elecRows = [];
    foreach ($elecSumDaily->fetchAll(PDO::FETCH_ASSOC) as $er) {
        $elecRows[$er['record_date']] = $er;
    }

    $headers = ['วันที่','ไฟฟ้า (kWh)','น้ำ (m³)','หน่วย EE','ค่าไฟ/หน่วย','ค่าไฟรวม (บาท)','PE'];
    $colW    = [16, 14, 12, 14, 14, 18, 12];
    writeTableHeader($ws, 4, $headers, $colW, '7C3AED');

    $r = 5;
    foreach ($daily as $i => $row) {
        $bg = $i % 2 === 0 ? 'F5F3FF' : 'FFFFFF';
        $d  = $row['record_date'];
        $e  = $elecRows[$d] ?? [];
        $ws->setCellValue("A$r", date('d/m/', strtotime($d)) . ((int)date('Y', strtotime($d)) + 543));
        $ws->setCellValue("B$r", (float)$row['elec']);
        $ws->setCellValue("C$r", (float)$row['water']);
        $ws->setCellValue("D$r", isset($e['ee_unit']) ? (float)$e['ee_unit'] : '-');
        $ws->setCellValue("E$r", isset($e['cost_per_unit']) ? (float)$e['cost_per_unit'] : '-');
        $ws->setCellValue("F$r", isset($e['total_cost']) ? (float)$e['total_cost'] : '-');
        $ws->setCellValue("G$r", isset($e['pe']) ? (float)$e['pe'] : '-');
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r:G$r", $bg, true);
        $r++;
    }
    // Total row
    $ws->setCellValue("A$r", 'รวม');
    $ws->setCellValue("B$r", "=SUM(B5:B" . ($r-1) . ")");
    $ws->setCellValue("C$r", "=SUM(C5:C" . ($r-1) . ")");
    $ws->setCellValue("F$r", "=SUM(F5:F" . ($r-1) . ")");
    styleHeader($ws, "A$r:G$r", '4361EE');

    $sp->setActiveSheetIndex(0);
}


// ══════════════════════════════════════════════════════════════════════════════
// YEARLY
// ══════════════════════════════════════════════════════════════════════════════
function buildYearly(Spreadsheet $sp, $db): void {
    $year      = (int)($_GET['year'] ?? date('Y'));
    $thaiYear  = $year + 543;
    $startDate = "$year-01-01";
    $endDate   = "$year-12-31";

    $cover = $sp->getActiveSheet()->setTitle('รายงานประจำปี');
    writeCoverSheet($cover, 'รายงานประจำปี', "พ.ศ. $thaiYear (ค.ศ. $year)",
                    'Yearly Summary Report', config('app.name'));

    // ── Sheet: Monthly Breakdown ─────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('รายเดือน');
    writeSheetHeader($ws, "📅 รายละเอียดรายเดือน ปี $thaiYear", "พ.ศ. $thaiYear", '4361EE');

    $headers = ['เดือน','ไฟฟ้า (kWh)','น้ำ (m³)','LPG (หน่วย)','Boiler (ชม.)','ค่าไฟ (บาท)','EE (หน่วย)'];
    $colW    = [16, 14, 12, 14, 14, 18, 14];
    writeTableHeader($ws, 4, $headers, $colW, '4361EE');

    $r = 5;
    for ($m = 1; $m <= 12; $m++) {
        $ms = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . '-01';
        $me = date('Y-m-t', strtotime($ms));

        $elec  = queryOne($db, "SELECT SUM(usage_amount) as v FROM meter_daily_readings r
                    JOIN mc_mdb_water m ON r.meter_id=m.id
                    WHERE m.meter_type='electricity' AND r.record_date BETWEEN ? AND ?",
                    [$ms, $me])['v'] ?? 0;
        $water = queryOne($db, "SELECT SUM(usage_amount) as v FROM meter_daily_readings r
                    JOIN mc_mdb_water m ON r.meter_id=m.id
                    WHERE m.meter_type='water' AND r.record_date BETWEEN ? AND ?",
                    [$ms, $me])['v'] ?? 0;
        $lpg   = queryOne($db, "SELECT SUM(r.number_value) as v FROM lpg_daily_records r
                    JOIN lpg_inspection_items i ON r.item_id=i.id
                    WHERE i.item_type='number' AND r.record_date BETWEEN ? AND ?",
                    [$ms, $me])['v'] ?? 0;
        $boil  = queryOne($db, "SELECT SUM(operating_hours) as v FROM boiler_daily_records
                    WHERE record_date BETWEEN ? AND ?", [$ms, $me])['v'] ?? 0;
        $cost  = queryOne($db, "SELECT SUM(total_cost) as v, SUM(ee_unit) as ee
                    FROM electricity_summary WHERE record_date BETWEEN ? AND ?",
                    [$ms, $me]);

        $bg = $m % 2 === 0 ? 'EFF2FF' : 'FFFFFF';
        $ws->setCellValue("A$r", thaiMonth($m));
        $ws->setCellValue("B$r", (float)$elec);
        $ws->setCellValue("C$r", (float)$water);
        $ws->setCellValue("D$r", (float)$lpg);
        $ws->setCellValue("E$r", (float)$boil);
        $ws->setCellValue("F$r", (float)($cost['v'] ?? 0));
        $ws->setCellValue("G$r", (float)($cost['ee'] ?? 0));
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r:G$r", $bg, true);
        $r++;
    }
    // Total row
    $ws->setCellValue("A$r", 'รวมทั้งปี');
    foreach (['B','C','D','E','F','G'] as $col) {
        $ws->setCellValue("{$col}{$r}", "=SUM({$col}5:{$col}" . ($r-1) . ")");
    }
    styleHeader($ws, "A$r:G$r", '1D4ED8');

    // ── Sheet: Yearly KPI ────────────────────────────────────────────────────
    $ws = $sp->createSheet()->setTitle('KPI ประจำปี');
    writeSheetHeader($ws, "🏆 KPI ประจำปี $thaiYear", "พ.ศ. $thaiYear", '059669');

    $kpis = [
        ['ไฟฟ้ารวม', queryOne($db,"SELECT SUM(usage_amount) as v FROM meter_daily_readings r
            JOIN mc_mdb_water m ON r.meter_id=m.id WHERE m.meter_type='electricity'
            AND r.record_date BETWEEN ? AND ?", [$startDate, $endDate])['v'] ?? 0, 'kWh'],
        ['น้ำรวม',   queryOne($db,"SELECT SUM(usage_amount) as v FROM meter_daily_readings r
            JOIN mc_mdb_water m ON r.meter_id=m.id WHERE m.meter_type='water'
            AND r.record_date BETWEEN ? AND ?", [$startDate, $endDate])['v'] ?? 0, 'm³'],
        ['LPG รวม',  queryOne($db,"SELECT SUM(r.number_value) as v FROM lpg_daily_records r
            JOIN lpg_inspection_items i ON r.item_id=i.id WHERE i.item_type='number'
            AND r.record_date BETWEEN ? AND ?", [$startDate, $endDate])['v'] ?? 0, 'หน่วย'],
        ['ค่าไฟรวม', queryOne($db,"SELECT SUM(total_cost) as v FROM electricity_summary
            WHERE record_date BETWEEN ? AND ?", [$startDate, $endDate])['v'] ?? 0, 'บาท'],
        ['Boiler ชั่วโมงรวม', queryOne($db,"SELECT SUM(operating_hours) as v FROM boiler_daily_records
            WHERE record_date BETWEEN ? AND ?", [$startDate, $endDate])['v'] ?? 0, 'ชั่วโมง'],
    ];

    $ws->getColumnDimension('A')->setWidth(28);
    $ws->getColumnDimension('B')->setWidth(20);
    $ws->getColumnDimension('C')->setWidth(14);
    writeTableHeader($ws, 4, ['รายการ', 'ค่า', 'หน่วย'], [28, 20, 14], '059669');

    foreach ($kpis as $i => [$label, $val, $unit]) {
        $r  = 5 + $i;
        $bg = $i % 2 === 0 ? 'ECFDF5' : 'FFFFFF';
        $ws->setCellValue("A$r", $label);
        $ws->setCellValue("B$r", (float)$val);
        $ws->setCellValue("C$r", $unit);
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r", $bg, true);
        styleData($ws, "C$r", $bg);
    }

    $sp->setActiveSheetIndex(0);
}


// ══════════════════════════════════════════════════════════════════════════════
// COMPARISON
// ══════════════════════════════════════════════════════════════════════════════
function buildComparison(Spreadsheet $sp, $db): void {
    $p1s  = $_GET['period1_start'] ?? date('Y-m-01');
    $p1e  = $_GET['period1_end']   ?? date('Y-m-d');
    $p2s  = $_GET['period2_start'] ?? date('Y-m-d', strtotime('-1 month'));
    $p2e  = $_GET['period2_end']   ?? date('Y-m-d', strtotime('-1 day'));
    $type = $_GET['compare_type']  ?? 'modules';

    $fp1  = date('d/m/Y', strtotime($p1s)) . ' — ' . date('d/m/Y', strtotime($p1e));
    $fp2  = date('d/m/Y', strtotime($p2s)) . ' — ' . date('d/m/Y', strtotime($p2e));

    $cover = $sp->getActiveSheet()->setTitle('รายงานเปรียบเทียบ');
    writeCoverSheet($cover, 'รายงานเปรียบเทียบ', "ช่วง 1: $fp1",
                    "Comparison Report | ช่วง 2: $fp2", config('app.name'));

    $ws = $sp->createSheet()->setTitle('เปรียบเทียบ');
    writeSheetHeader($ws, "🔄 เปรียบเทียบ 2 ช่วงเวลา", '', '7C3AED');

    // Period labels row
    $ws->mergeCells('A4:A5');
    $ws->setCellValue('A4', 'โมดูล / รายการ');
    $ws->mergeCells('B4:D4');
    $ws->setCellValue('B4', "ช่วงที่ 1: $fp1");
    $ws->mergeCells('E4:G4');
    $ws->setCellValue('E4', "ช่วงที่ 2: $fp2");
    $ws->mergeCells('H4:H5');
    $ws->setCellValue('H4', 'เปลี่ยนแปลง (%)');
    styleHeader($ws, 'A4:H5', '7C3AED');

    // Sub-headers
    foreach (['B5' => 'รวม', 'C5' => 'เฉลี่ย/วัน', 'D5' => 'NG/งาน',
              'E5' => 'รวม', 'F5' => 'เฉลี่ย/วัน', 'G5' => 'NG/งาน'] as $cell => $label) {
        $ws->setCellValue($cell, $label);
    }

    $cols = ['A' => 28, 'B' => 16, 'C' => 16, 'D' => 14, 'E' => 16, 'F' => 16, 'G' => 14, 'H' => 16];
    foreach ($cols as $col => $w) $ws->getColumnDimension($col)->setWidth($w);

    // Module rows
    $modules = [
        'Air Compressor' => [
            fn($s,$e) => queryOne($db, "SELECT SUM(r.actual_value) as tot,
                AVG(r.actual_value) as avg,
                SUM(CASE WHEN (s.min_value IS NOT NULL AND (r.actual_value<s.min_value OR r.actual_value>s.max_value))
                    OR (s.min_value IS NULL AND ABS(r.actual_value-s.standard_value)>s.standard_value*0.1) THEN 1 ELSE 0 END) as ng
                FROM air_daily_records r JOIN air_inspection_standards s ON r.inspection_item_id=s.id
                WHERE r.record_date BETWEEN ? AND ?", [$s,$e]),
        ],
        'ไฟฟ้า (kWh)' => [
            fn($s,$e) => queryOne($db, "SELECT SUM(r.usage_amount) as tot, AVG(r.usage_amount) as avg, 0 as ng
                FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
                WHERE m.meter_type='electricity' AND r.record_date BETWEEN ? AND ?", [$s,$e]),
        ],
        'น้ำ (m³)' => [
            fn($s,$e) => queryOne($db, "SELECT SUM(r.usage_amount) as tot, AVG(r.usage_amount) as avg, 0 as ng
                FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
                WHERE m.meter_type='water' AND r.record_date BETWEEN ? AND ?", [$s,$e]),
        ],
        'LPG' => [
            fn($s,$e) => queryOne($db, "SELECT SUM(r.number_value) as tot, AVG(r.number_value) as avg,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
                FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
                WHERE r.record_date BETWEEN ? AND ?", [$s,$e]),
        ],
        'Boiler (ชม.)' => [
            fn($s,$e) => queryOne($db, "SELECT SUM(operating_hours) as tot, AVG(operating_hours) as avg, 0 as ng
                FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?", [$s,$e]),
        ],
        'ค่าไฟฟ้า (บาท)' => [
            fn($s,$e) => queryOne($db, "SELECT SUM(total_cost) as tot, AVG(total_cost) as avg, 0 as ng
                FROM electricity_summary WHERE record_date BETWEEN ? AND ?", [$s,$e]),
        ],
    ];

    $r = 6;
    foreach ($modules as $label => [$fetchFn]) {
        $d1  = $fetchFn($p1s, $p1e);
        $d2  = $fetchFn($p2s, $p2e);        $bg  = ($r % 2 === 0) ? 'F5F3FF' : 'FFFFFF';
        $chg = ($d1['tot'] && $d2['tot'])
             ? round(($d2['tot'] - $d1['tot']) / $d1['tot'] * 100, 2)
             : null;

        $ws->setCellValue("A$r", $label);
        $ws->setCellValue("B$r", fmt($d1['tot']));
        $ws->setCellValue("C$r", fmt($d1['avg']));
        $ws->setCellValue("D$r", $d1['ng'] ?? '-');
        $ws->setCellValue("E$r", fmt($d2['tot']));
        $ws->setCellValue("F$r", fmt($d2['avg']));
        $ws->setCellValue("G$r", $d2['ng'] ?? '-');
        $ws->setCellValue("H$r", $chg !== null ? ($chg > 0 ? "+$chg%" : "$chg%") : '-');
        styleData($ws, "A$r", $bg);
        styleData($ws, "B$r:H$r", $bg, true);

        // highlight change
        if ($chg !== null) {
            $chgColor = $chg > 5 ? 'FEE2E2' : ($chg < -5 ? 'DCFCE7' : 'FEF9C3');
            $ws->getStyle("H$r")->getFill()
               ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($chgColor);
        }
        $r++;
    }

    $sp->setActiveSheetIndex(0);
}


// ══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ══════════════════════════════════════════════════════════════════════════════

function writeCoverSheet(Worksheet $ws, string $title, string $period,
                         string $subtitle, string $company): void {
    $ws->getColumnDimension('A')->setWidth(10);
    $ws->getColumnDimension('B')->setWidth(50);
    $ws->getColumnDimension('C')->setWidth(20);

    // Logo row
    $ws->mergeCells('A1:C1');
    $ws->setCellValue('A1', $company . ' — EUMS');
    $ws->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7B68EE']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
    ]);
    $ws->getRowDimension(1)->setRowHeight(45);

    $ws->mergeCells('A2:C2');
    $ws->setCellValue('A2', $title);
    $ws->getStyle('A2')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 20, 'color' => ['rgb' => '4361EE'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
    ]);
    $ws->getRowDimension(2)->setRowHeight(40);

    $ws->mergeCells('A3:C3');
    $ws->setCellValue('A3', $period);
    $ws->getStyle('A3')->applyFromArray([
        'font'      => ['size' => 13, 'color' => ['rgb' => '555555'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $ws->mergeCells('A4:C4');
    $ws->setCellValue('A4', $subtitle);
    $ws->getStyle('A4')->applyFromArray([
        'font'      => ['size' => 11, 'italic' => true, 'color' => ['rgb' => '888888']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $ws->setCellValue('A6', 'สร้างเมื่อ:');
    $ws->setCellValue('B6', date('d/m/Y H:i:s'));
    $ws->setCellValue('A7', 'สร้างโดย:');
    $ws->setCellValue('B7', $_SESSION['username'] ?? 'System');
    $ws->getStyle('A6:A7')->getFont()->setBold(true);
}

function writeSheetHeader(Worksheet $ws, string $title, string $period,
                          string $color = '4361EE'): void {
    $ws->mergeCells('A1:H1');
    $ws->setCellValue('A1', $title);
    $ws->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
    ]);
    $ws->getRowDimension(1)->setRowHeight(32);

    $ws->mergeCells('A2:H2');
    $ws->setCellValue('A2', $period ? "ช่วงเวลา: $period" : 'สร้างเมื่อ: ' . date('d/m/Y H:i'));
    $ws->getStyle('A2')->applyFromArray([
        'font'      => ['size' => 10, 'color' => ['rgb' => '555555']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $ws->mergeCells('A3:H3'); // spacer
}

function writeTableHeader(Worksheet $ws, int $row, array $headers,
                          array $widths, string $color = '4361EE',
                          string $fontColor = 'FFFFFF'): void {
    $col = 'A';
    foreach ($headers as $i => $h) {
        $ws->setCellValue("{$col}{$row}", $h);
        if (isset($widths[$i])) {
            $ws->getColumnDimension($col)->setWidth($widths[$i]);
        }
        $col++;
    }
    $lastCol = chr(ord('A') + count($headers) - 1);
    styleHeader($ws, "A{$row}:{$lastCol}{$row}", $color, true, 10, $fontColor);
    $ws->getRowDimension($row)->setRowHeight(20);
}

function writeEmptyNote(Worksheet $ws, int $row, int $cols, bool $empty): void {
    if ($empty) {
        $lastCol = chr(ord('A') + $cols - 1);
        $ws->mergeCells("A{$row}:{$lastCol}{$row}");
        $ws->setCellValue("A{$row}", '⚠️ ไม่มีข้อมูลในช่วงเวลาที่เลือก');
        $ws->getStyle("A{$row}")->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '888888']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBEB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}

function isAirOK(array $row): bool {
    $v = (float)$row['actual_value'];
    if ($row['min_value'] !== null) {
        return $v >= (float)$row['min_value'] && $v <= (float)$row['max_value'];
    }
    $std = (float)$row['standard_value'];
    return $std === 0.0 || abs($v - $std) / $std <= 0.1;
}

function queryOne($db, string $sql, array $params = []): array|false {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}