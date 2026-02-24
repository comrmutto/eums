<?php
/**
 * Export PDF Report
 * Engineering Utility Monitoring System (EUMS)
 *
 * ต้องติดตั้ง: composer require dompdf/dompdf phpoffice/phpspreadsheet
 *
 * URL params: (เหมือนกับ export_report.php)
 *   type   : daily | monthly | yearly | comparison
 *   date, month, year, period1_*, period2_*, compare_type
 */

// ─── Bootstrap ───────────────────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ─── Parameters ──────────────────────────────────────────────────────────────
$type = $_GET['type'] ?? 'daily';
$db   = getDB();

// ─── Thai helpers ─────────────────────────────────────────────────────────────
function thaiMonth(int $m): string {
    return ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
            'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'][$m];
}
function fmt($v, int $d = 2): string {
    return ($v !== null && $v !== '') ? number_format((float)$v, $d) : '-';
}
function isAirOK(array $row): bool {
    $v = (float)$row['actual_value'];
    if ($row['min_value'] !== null)
        return $v >= (float)$row['min_value'] && $v <= (float)$row['max_value'];
    $std = (float)$row['standard_value'];
    return $std === 0.0 || abs($v - $std) / $std <= 0.1;
}
function qOne($db, string $sql, array $p = []): array|false {
    $stmt = $db->prepare($sql); $stmt->execute($p);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ─── Build HTML ───────────────────────────────────────────────────────────────
$html = match ($type) {
    'daily'      => buildDailyHTML($db),
    'monthly'    => buildMonthlyHTML($db),
    'yearly'     => buildYearlyHTML($db),
    'comparison' => buildComparisonHTML($db),
    default      => '<p>Invalid report type</p>',
};

$filename = "EUMS_{$type}_" . date('Ymd_His') . '.pdf';

// ─── Dompdf render ───────────────────────────────────────────────────────────
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');   // รองรับ UTF-8 / ภาษาไทยบางส่วน
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/..'));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$dompdf->stream($filename, ['Attachment' => true]);
exit();


// ══════════════════════════════════════════════════════════════════════════════
// HTML BUILDER — SHARED WRAPPER
// ══════════════════════════════════════════════════════════════════════════════
function htmlWrap(string $title, string $period, string $body): string {
    $app      = config('app.name') ?? 'EUMS';
    $generated = date('d/m/Y H:i:s');
    $user      = $_SESSION['username'] ?? 'System';

    return <<<HTML
    <!DOCTYPE html>
    <html lang="th">
    <head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; color: #1e1e2e; }

        /* ── Header banner ── */
        .pdf-header { background: linear-gradient(135deg,#667eea,#764ba2);
            color:#fff; padding:10px 16px; border-radius:8px; margin-bottom:10px; }
        .pdf-header h1 { font-size:15pt; margin-bottom:2px; }
        .pdf-header .sub { font-size:9pt; opacity:.85; }
        .pdf-header .meta { font-size:8pt; opacity:.7; margin-top:4px; }

        /* ── Section title ── */
        .section-title { background:#4361ee; color:#fff; padding:5px 10px;
            font-size:10pt; font-weight:bold; border-radius:4px;
            margin: 10px 0 4px; }

        /* ── Tables ── */
        table { width:100%; border-collapse:collapse; margin-bottom:8px; }
        th { background:#4361ee; color:#fff; padding:5px 7px;
             font-size:8.5pt; text-align:center; border:1px solid #3050cc; }
        td { padding:4px 7px; font-size:8.5pt; border:1px solid #d0d5e8; }
        tr:nth-child(even) td { background:#eff2ff; }
        tr:nth-child(odd)  td { background:#ffffff; }
        .text-right { text-align:right; }
        .text-center { text-align:center; }

        /* ── Status badges ── */
        .ok  { background:#dcfce7; color:#16a34a; padding:1px 6px;
               border-radius:4px; font-weight:bold; font-size:8pt; }
        .ng  { background:#fee2e2; color:#dc2626; padding:1px 6px;
               border-radius:4px; font-weight:bold; font-size:8pt; }
        .elec-badge { background:#fef3c7; color:#92400e; padding:1px 5px;
               border-radius:4px; font-size:8pt; }
        .water-badge { background:#dbeafe; color:#1e40af; padding:1px 5px;
               border-radius:4px; font-size:8pt; }

        /* ── Summary box ── */
        .summary-grid { display:table; width:100%; margin-bottom:8px; }
        .summary-cell { display:table-cell; width:25%; padding:6px;
            background:#eff2ff; border:1px solid #c7d2fe; border-radius:6px; }
        .summary-cell .label { font-size:7.5pt; color:#6b7280; }
        .summary-cell .value { font-size:12pt; font-weight:bold; color:#4361ee; }
        .summary-cell .unit  { font-size:7.5pt; color:#9ca3af; }

        /* ── Change % ── */
        .chg-pos { color:#dc2626; font-weight:bold; }
        .chg-neg { color:#16a34a; font-weight:bold; }
        .chg-zero { color:#6b7280; }

        /* ── Footer ── */
        .pdf-footer { margin-top:10px; border-top:1px solid #e5e7eb;
            padding-top:5px; font-size:7.5pt; color:#9ca3af; }

        /* ── Page break ── */
        .page-break { page-break-before:always; }

        /* ── No data ── */
        .no-data { text-align:center; padding:12px; color:#9ca3af;
            font-style:italic; border:1px dashed #d0d5e8; border-radius:4px; }

        /* ── Total row ── */
        .total-row td { background:#4361ee !important; color:#fff !important;
            font-weight:bold; }
    </style>
    </head>
    <body>
    <div class="pdf-header">
        <h1>{$title}</h1>
        <div class="sub">{$period}</div>
        <div class="meta">{$app} &nbsp;|&nbsp; สร้างเมื่อ: {$generated} &nbsp;|&nbsp; โดย: {$user}</div>
    </div>
    {$body}
    <div class="pdf-footer">
        Engineering Utility Monitoring System (EUMS) &mdash; {$app} &mdash; พิมพ์เมื่อ {$generated}
    </div>
    </body>
    </html>
    HTML;
}

function sectionTitle(string $t): string {
    return "<div class=\"section-title\">$t</div>";
}
function noData(): string {
    return '<div class="no-data">⚠️ ไม่มีข้อมูลในช่วงเวลาที่เลือก</div>';
}


// ══════════════════════════════════════════════════════════════════════════════
// DAILY HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildDailyHTML($db): string {
    $date       = $_GET['date'] ?? date('d/m/Y');
    $dateParsed = strlen($date) === 10 && strpos($date, '/') !== false
                ? DateTime::createFromFormat('d/m/Y', $date)->format('Y-m-d')
                : $date;
    $thaiDate   = date('d', strtotime($dateParsed)) . ' '
                . thaiMonth((int)date('m', strtotime($dateParsed))) . ' '
                . ((int)date('Y', strtotime($dateParsed)) + 543);

    // ── Summary counts ────────────────────────────────────────────────────────
    $airSum  = qOne($db,"SELECT COUNT(*) as n, SUM(actual_value) as tot FROM air_daily_records WHERE record_date=?", [$dateParsed]);
    $engSum  = qOne($db,"SELECT SUM(CASE WHEN m.meter_type='electricity' THEN r.usage_amount ELSE 0 END) as elec,
                SUM(CASE WHEN m.meter_type='water' THEN r.usage_amount ELSE 0 END) as water
                FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id WHERE r.record_date=?", [$dateParsed]);
    $lpgSum  = qOne($db,"SELECT SUM(CASE WHEN i.item_type='enum' AND r.enum_value='OK' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
                FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id WHERE r.record_date=?", [$dateParsed]);
    $elecRow = qOne($db,"SELECT ee_unit, total_cost FROM electricity_summary WHERE record_date=?", [$dateParsed]);

    $body  = '<table><tr>';
    $cards = [
        ['🔵 Air Compressor', fmt($airSum['tot']), 'หน่วย', '#4361ee'],
        ['⚡ ไฟฟ้า', fmt($engSum['elec']), 'kWh', '#f59e0b'],
        ['💧 น้ำ', fmt($engSum['water']), 'm³', '#3b82f6'],
        ['📊 ค่าไฟ', fmt($elecRow['total_cost'] ?? null), 'บาท', '#10b981'],
    ];
    foreach ($cards as [$label,$val,$unit,$color]) {
        $body .= "<td style='padding:8px; background:{$color}11; border:1px solid {$color}44;
                    border-radius:6px; text-align:center; width:25%;'>
                    <div style='font-size:7.5pt;color:{$color};'>{$label}</div>
                    <div style='font-size:14pt;font-weight:bold;color:{$color};'>{$val}</div>
                    <div style='font-size:7.5pt;color:#9ca3af;'>{$unit}</div>
                  </td>";
    }
    $body .= '</tr></table>';

    // ── Air Compressor ────────────────────────────────────────────────────────
    $body .= sectionTitle('🔵 Air Compressor');
    $stmt = $db->prepare("SELECT m.machine_name, s.inspection_item, r.actual_value,
            s.standard_value, s.min_value, s.max_value, r.recorded_by
        FROM air_daily_records r JOIN mc_air m ON r.machine_id=m.id
        JOIN air_inspection_standards s ON r.inspection_item_id=s.id
        WHERE r.record_date=? ORDER BY m.machine_code");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $body .= '<table><thead><tr>
            <th>เครื่องจักร</th><th>หัวข้อตรวจสอบ</th>
            <th class="text-right">ค่าจริง</th><th class="text-right">มาตรฐาน</th>
            <th class="text-right">Min</th><th class="text-right">Max</th>
            <th class="text-center">สถานะ</th><th>ผู้บันทึก</th>
        </tr></thead><tbody>';
        foreach ($rows as $row) {
            $ok  = isAirOK($row);
            $cls = $ok ? 'ok' : 'ng';
            $body .= "<tr>
                <td>{$row['machine_name']}</td>
                <td>{$row['inspection_item']}</td>
                <td class='text-right'>" . fmt($row['actual_value']) . "</td>
                <td class='text-right'>" . fmt($row['standard_value']) . "</td>
                <td class='text-right'>" . ($row['min_value'] ?? '-') . "</td>
                <td class='text-right'>" . ($row['max_value'] ?? '-') . "</td>
                <td class='text-center'><span class='{$cls}'>" . ($ok ? 'OK' : 'NG') . "</span></td>
                <td>{$row['recorded_by']}</td>
            </tr>";
        }
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

    // ── Energy & Water ────────────────────────────────────────────────────────
    $body .= sectionTitle('⚡ Energy &amp; Water');
    $stmt = $db->prepare("SELECT m.meter_name, m.meter_type,
            r.morning_reading, r.evening_reading, r.usage_amount, r.recorded_by
        FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
        WHERE r.record_date=? ORDER BY m.meter_type, m.meter_code");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $body .= '<table><thead><tr>
            <th>มิเตอร์</th><th>ประเภท</th>
            <th class="text-right">อ่านเช้า</th><th class="text-right">อ่านเย็น</th>
            <th class="text-right">การใช้งาน</th><th>หน่วย</th><th>ผู้บันทึก</th>
        </tr></thead><tbody>';
        $totalElec = $totalWater = 0;
        foreach ($rows as $row) {
            $isElec = $row['meter_type'] === 'electricity';
            $badge  = $isElec ? 'elec-badge' : 'water-badge';
            $label  = $isElec ? 'ไฟฟ้า' : 'น้ำ';
            $unit   = $isElec ? 'kWh' : 'm³';
            if ($isElec) $totalElec  += $row['usage_amount'];
            else         $totalWater += $row['usage_amount'];
            $body .= "<tr>
                <td>{$row['meter_name']}</td>
                <td class='text-center'><span class='{$badge}'>{$label}</span></td>
                <td class='text-right'>" . fmt($row['morning_reading']) . "</td>
                <td class='text-right'>" . fmt($row['evening_reading']) . "</td>
                <td class='text-right'>" . fmt($row['usage_amount']) . "</td>
                <td>{$unit}</td>
                <td>{$row['recorded_by']}</td>
            </tr>";
        }
        $body .= "<tr class='total-row'>
            <td colspan='4'>รวมไฟฟ้า</td>
            <td class='text-right'>" . fmt($totalElec) . "</td><td>kWh</td><td>-</td>
        </tr>
        <tr class='total-row'>
            <td colspan='4'>รวมน้ำ</td>
            <td class='text-right'>" . fmt($totalWater) . "</td><td>m³</td><td>-</td>
        </tr>";
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

    // ── LPG ──────────────────────────────────────────────────────────────────
    $body .= sectionTitle('🔴 LPG');
    $stmt = $db->prepare("SELECT i.item_name, i.item_type,
            COALESCE(r.number_value, r.enum_value) as value, r.recorded_by
        FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
        WHERE r.record_date=? ORDER BY i.item_no");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $body .= '<table><thead><tr>
            <th>รายการ</th><th>ประเภท</th><th class="text-right">ค่า / สถานะ</th><th>ผู้บันทึก</th>
        </tr></thead><tbody>';
        foreach ($rows as $row) {
            $valHtml = $row['item_type'] === 'enum'
                ? "<span class='" . ($row['value'] === 'OK' ? 'ok' : 'ng') . "'>{$row['value']}</span>"
                : fmt($row['value']);
            $body .= "<tr>
                <td>{$row['item_name']}</td>
                <td class='text-center'>" . ($row['item_type'] === 'number' ? 'ตัวเลข' : 'OK/NG') . "</td>
                <td class='text-right'>{$valHtml}</td>
                <td>{$row['recorded_by']}</td>
            </tr>";
        }
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

    // ── Boiler ───────────────────────────────────────────────────────────────
    $body .= sectionTitle('🏭 Boiler');
    $stmt = $db->prepare("SELECT m.machine_name, r.steam_pressure,
            r.steam_temperature, r.fuel_consumption, r.operating_hours, r.recorded_by
        FROM boiler_daily_records r JOIN mc_boiler m ON r.machine_id=m.id
        WHERE r.record_date=? ORDER BY m.machine_code");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $body .= '<table><thead><tr>
            <th>เครื่องจักร</th>
            <th class="text-right">แรงดัน (bar)</th>
            <th class="text-right">อุณหภูมิ (°C)</th>
            <th class="text-right">เชื้อเพลิง (L)</th>
            <th class="text-right">ชั่วโมงทำงาน</th>
            <th>ผู้บันทึก</th>
        </tr></thead><tbody>';
        foreach ($rows as $row) {
            $body .= "<tr>
                <td>{$row['machine_name']}</td>
                <td class='text-right'>" . fmt($row['steam_pressure']) . "</td>
                <td class='text-right'>" . fmt($row['steam_temperature'], 1) . "</td>
                <td class='text-right'>" . fmt($row['fuel_consumption']) . "</td>
                <td class='text-right'>" . fmt($row['operating_hours'], 1) . "</td>
                <td>{$row['recorded_by']}</td>
            </tr>";
        }
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

    // ── Summary Electricity ───────────────────────────────────────────────────
    $body .= sectionTitle('📊 Summary Electricity');
    $elec = qOne($db, "SELECT ee_unit, cost_per_unit, total_cost, pe, recorded_by
        FROM electricity_summary WHERE record_date=?", [$dateParsed]);

    if ($elec) {
        $body .= '<table><thead><tr>
            <th>หน่วยไฟฟ้า (kWh)</th><th>ค่าไฟ/หน่วย (บาท)</th>
            <th>ค่าไฟรวม (บาท)</th><th>PE</th><th>ผู้บันทึก</th>
        </tr></thead><tbody><tr>
            <td class="text-right">' . fmt($elec['ee_unit']) . '</td>
            <td class="text-right">' . fmt($elec['cost_per_unit'], 4) . '</td>
            <td class="text-right">' . fmt($elec['total_cost']) . '</td>
            <td class="text-right">' . fmt($elec['pe'], 4) . '</td>
            <td>' . htmlspecialchars($elec['recorded_by']) . '</td>
        </tr></tbody></table>';
    } else { $body .= noData(); }

    return htmlWrap('รายงานประจำวัน', "วันที่ $thaiDate", $body);
}


// ══════════════════════════════════════════════════════════════════════════════
// MONTHLY HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildMonthlyHTML($db): string {
    $month     = (int)($_GET['month'] ?? date('m'));
    $year      = (int)($_GET['year']  ?? date('Y'));
    $thaiYear  = $year + 543;
    $period    = thaiMonth($month) . ' พ.ศ. ' . $thaiYear;
    $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate   = date('Y-m-t', strtotime($startDate));
    $daysInMonth = (int)date('t', strtotime($startDate));

    // Summaries
    $air    = qOne($db,"SELECT COUNT(DISTINCT r.record_date) as days,COUNT(r.id) as recs,
                SUM(r.actual_value) as tot,AVG(r.actual_value) as avg,
                SUM(CASE WHEN (s.min_value IS NOT NULL AND (r.actual_value<s.min_value OR r.actual_value>s.max_value))
                    OR (s.min_value IS NULL AND ABS(r.actual_value-s.standard_value)>s.standard_value*0.1) THEN 1 ELSE 0 END) as ng
                FROM air_daily_records r JOIN air_inspection_standards s ON r.inspection_item_id=s.id
                WHERE r.record_date BETWEEN ? AND ?", [$startDate,$endDate]);
    $energy = qOne($db,"SELECT COUNT(DISTINCT r.record_date) as days,
                SUM(CASE WHEN m.meter_type='electricity' THEN r.usage_amount ELSE 0 END) as elec,
                SUM(CASE WHEN m.meter_type='water' THEN r.usage_amount ELSE 0 END) as water
                FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
                WHERE r.record_date BETWEEN ? AND ?", [$startDate,$endDate]);
    $lpg    = qOne($db,"SELECT COUNT(DISTINCT r.record_date) as days,
                SUM(CASE WHEN i.item_type='number' THEN r.number_value ELSE 0 END) as tot,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='OK' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
                FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
                WHERE r.record_date BETWEEN ? AND ?", [$startDate,$endDate]);
    $boiler = qOne($db,"SELECT COUNT(DISTINCT record_date) as days,
                SUM(fuel_consumption) as fuel, SUM(operating_hours) as hours,
                AVG(steam_pressure) as avg_p, AVG(steam_temperature) as avg_t
                FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?",
                [$startDate,$endDate]);
    $elec   = qOne($db,"SELECT COUNT(*) as days, SUM(ee_unit) as ee,
                SUM(total_cost) as cost, AVG(cost_per_unit) as rate
                FROM electricity_summary WHERE record_date BETWEEN ? AND ?",
                [$startDate,$endDate]);

    $body  = sectionTitle('📋 สรุปรายเดือน — ทุกโมดูล');
    $body .= '<table><thead><tr>
        <th>โมดูล</th><th class="text-right">วันที่มีข้อมูล</th>
        <th class="text-right">บันทึกทั้งหมด</th><th class="text-right">ค่ารวม</th>
        <th class="text-right">ค่าเฉลี่ย/วัน</th><th>หมายเหตุ</th>
    </tr></thead><tbody>';

    $summaries = [
        ['🔵 Air Compressor', $air['days']??0, $air['recs']??0,
         fmt($air['tot']), fmt(($air['tot']??0)/max($air['days']??1,1)),
         'NG: '.($air['ng']??0).' รายการ | หน่วย'],
        ['⚡ ไฟฟ้า', $energy['days']??0, '-',
         fmt($energy['elec']), fmt(($energy['elec']??0)/max($energy['days']??1,1)), 'kWh'],
        ['💧 น้ำ', $energy['days']??0, '-',
         fmt($energy['water']), fmt(($energy['water']??0)/max($energy['days']??1,1)), 'm³'],
        ['🔴 LPG', $lpg['days']??0, '-',
         fmt($lpg['tot']), '-', 'OK:'.($lpg['ok']??0).' NG:'.($lpg['ng']??0)],
        ['🏭 Boiler', $boiler['days']??0, '-',
         fmt($boiler['hours']).' ชม.', fmt($boiler['avg_p']).' bar',
         'เชื้อเพลิง '.fmt($boiler['fuel']).' L'],
        ['📊 Summary Electricity', $elec['days']??0, '-',
         number_format($elec['cost']??0,2).' บาท', fmt($elec['ee']).' kWh',
         'อัตรา '.fmt($elec['rate'],4).' บาท/หน่วย'],
    ];
    foreach ($summaries as $i => [$mod,$days,$recs,$tot,$avg,$note]) {
        $bg = $i % 2 === 0 ? '' : " style='background:#ffffff'";
        $body .= "<tr{$bg}>
            <td><strong>{$mod}</strong></td>
            <td class='text-right'>{$days}</td>
            <td class='text-right'>{$recs}</td>
            <td class='text-right'>{$tot}</td>
            <td class='text-right'>{$avg}</td>
            <td>{$note}</td>
        </tr>";
    }
    $body .= '</tbody></table>';

    // ── Daily breakdown table ─────────────────────────────────────────────────
    $body .= '<div class="page-break"></div>';
    $body .= sectionTitle('📅 รายละเอียดรายวัน');

    $stmt = $db->prepare("SELECT r.record_date,
        SUM(CASE WHEN m.meter_type='electricity' THEN r.usage_amount ELSE 0 END) as elec,
        SUM(CASE WHEN m.meter_type='water' THEN r.usage_amount ELSE 0 END) as water
        FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
        WHERE r.record_date BETWEEN ? AND ? GROUP BY r.record_date ORDER BY r.record_date");
    $stmt->execute([$startDate, $endDate]);
    $dailyRows = []; foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $dr) $dailyRows[$dr['record_date']] = $dr;

    $elecDailyStmt = $db->prepare("SELECT record_date, ee_unit, total_cost, pe
        FROM electricity_summary WHERE record_date BETWEEN ? AND ? ORDER BY record_date");
    $elecDailyStmt->execute([$startDate,$endDate]);
    $elecDaily = []; foreach ($elecDailyStmt->fetchAll(PDO::FETCH_ASSOC) as $ed) $elecDaily[$ed['record_date']] = $ed;

    $body .= '<table><thead><tr>
        <th>วันที่</th><th class="text-right">ไฟฟ้า (kWh)</th>
        <th class="text-right">น้ำ (m³)</th><th class="text-right">EE (หน่วย)</th>
        <th class="text-right">ค่าไฟ (บาท)</th><th class="text-right">PE</th>
    </tr></thead><tbody>';

    $totElec = $totWater = $totEE = $totCost = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateKey = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        $dr      = $dailyRows[$dateKey] ?? null;
        $er      = $elecDaily[$dateKey] ?? null;
        $elecV   = $dr ? (float)$dr['elec']   : 0;
        $waterV  = $dr ? (float)$dr['water']  : 0;
        $eeV     = $er ? (float)$er['ee_unit'] : 0;
        $costV   = $er ? (float)$er['total_cost'] : 0;
        $peV     = $er ? fmt($er['pe'], 4) : '-';
        $totElec += $elecV; $totWater += $waterV; $totEE += $eeV; $totCost += $costV;
        $thaiD   = $d . '/' . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $thaiYear;
        $hasData = $dr || $er;
        $style   = $hasData ? '' : " style='color:#c7d2fe;'";
        $body   .= "<tr{$style}>
            <td>{$thaiD}</td>
            <td class='text-right'>" . ($elecV ? fmt($elecV) : '-') . "</td>
            <td class='text-right'>" . ($waterV ? fmt($waterV) : '-') . "</td>
            <td class='text-right'>" . ($eeV ? fmt($eeV) : '-') . "</td>
            <td class='text-right'>" . ($costV ? fmt($costV) : '-') . "</td>
            <td class='text-right'>{$peV}</td>
        </tr>";
    }
    $body .= "<tr class='total-row'>
        <td>รวมทั้งเดือน</td>
        <td class='text-right'>" . fmt($totElec) . "</td>
        <td class='text-right'>" . fmt($totWater) . "</td>
        <td class='text-right'>" . fmt($totEE) . "</td>
        <td class='text-right'>" . fmt($totCost) . "</td>
        <td>-</td>
    </tr>";
    $body .= '</tbody></table>';

    return htmlWrap('รายงานประจำเดือน', $period, $body);
}


// ══════════════════════════════════════════════════════════════════════════════
// YEARLY HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildYearlyHTML($db): string {
    $year      = (int)($_GET['year'] ?? date('Y'));
    $thaiYear  = $year + 543;
    $startDate = "$year-01-01";
    $endDate   = "$year-12-31";

    $body  = sectionTitle("📅 สรุปรายเดือน ประจำปี พ.ศ. $thaiYear");
    $body .= '<table><thead><tr>
        <th>เดือน</th>
        <th class="text-right">ไฟฟ้า (kWh)</th>
        <th class="text-right">น้ำ (m³)</th>
        <th class="text-right">LPG</th>
        <th class="text-right">Boiler (ชม.)</th>
        <th class="text-right">ค่าไฟ (บาท)</th>
        <th class="text-right">EE (หน่วย)</th>
    </tr></thead><tbody>';

    $totals = array_fill(0, 6, 0.0);
    for ($m = 1; $m <= 12; $m++) {
        $ms = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . '-01';
        $me = date('Y-m-t', strtotime($ms));

        $elec  = (float)(qOne($db,"SELECT SUM(r.usage_amount) as v FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id WHERE m.meter_type='electricity' AND r.record_date BETWEEN ? AND ?", [$ms,$me])['v'] ?? 0);
        $water = (float)(qOne($db,"SELECT SUM(r.usage_amount) as v FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id WHERE m.meter_type='water' AND r.record_date BETWEEN ? AND ?", [$ms,$me])['v'] ?? 0);
        $lpg   = (float)(qOne($db,"SELECT SUM(r.number_value) as v FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id WHERE i.item_type='number' AND r.record_date BETWEEN ? AND ?", [$ms,$me])['v'] ?? 0);
        $boil  = (float)(qOne($db,"SELECT SUM(operating_hours) as v FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?", [$ms,$me])['v'] ?? 0);
        $cRow  = qOne($db,"SELECT SUM(total_cost) as c, SUM(ee_unit) as ee FROM electricity_summary WHERE record_date BETWEEN ? AND ?", [$ms,$me]);
        $cost  = (float)($cRow['c'] ?? 0);
        $ee    = (float)($cRow['ee'] ?? 0);

        $vals = [$elec, $water, $lpg, $boil, $cost, $ee];
        foreach ($vals as $i => $v) $totals[$i] += $v;

        $bg = $m % 2 === 0 ? '' : " style='background:#ffffff'";
        $body .= "<tr{$bg}>
            <td>" . thaiMonth($m) . "</td>
            <td class='text-right'>" . ($elec ? fmt($elec) : '-') . "</td>
            <td class='text-right'>" . ($water ? fmt($water) : '-') . "</td>
            <td class='text-right'>" . ($lpg   ? fmt($lpg)   : '-') . "</td>
            <td class='text-right'>" . ($boil  ? fmt($boil, 1)  : '-') . "</td>
            <td class='text-right'>" . ($cost  ? fmt($cost)  : '-') . "</td>
            <td class='text-right'>" . ($ee    ? fmt($ee)    : '-') . "</td>
        </tr>";
    }
    $body .= "<tr class='total-row'>
        <td>รวมทั้งปี</td>
        <td class='text-right'>" . fmt($totals[0]) . "</td>
        <td class='text-right'>" . fmt($totals[1]) . "</td>
        <td class='text-right'>" . fmt($totals[2]) . "</td>
        <td class='text-right'>" . fmt($totals[3], 1) . "</td>
        <td class='text-right'>" . fmt($totals[4]) . "</td>
        <td class='text-right'>" . fmt($totals[5]) . "</td>
    </tr></tbody></table>";

    // ── KPI block ─────────────────────────────────────────────────────────────
    $body .= sectionTitle('🏆 KPI ประจำปี');
    $body .= '<table><thead><tr><th>รายการ</th><th class="text-right">ค่า</th><th>หน่วย</th></tr></thead><tbody>';
    $kpis = [
        ['ไฟฟ้ารวมทั้งปี', fmt($totals[0]), 'kWh'],
        ['น้ำรวมทั้งปี',   fmt($totals[1]), 'm³'],
        ['LPG รวมทั้งปี',  fmt($totals[2]), 'หน่วย'],
        ['ค่าไฟฟ้ารวม',    fmt($totals[4]), 'บาท'],
        ['Boiler ชั่วโมงรวม', fmt($totals[3], 1), 'ชั่วโมง'],
        ['EE รวม',         fmt($totals[5]), 'หน่วย'],
    ];
    foreach ($kpis as $i => [$l,$v,$u]) {
        $bg = $i % 2 === 0 ? '' : " style='background:#ffffff'";
        $body .= "<tr{$bg}><td>{$l}</td><td class='text-right'>{$v}</td><td>{$u}</td></tr>";
    }
    $body .= '</tbody></table>';

    return htmlWrap('รายงานประจำปี', "ปี พ.ศ. $thaiYear (ค.ศ. $year)", $body);
}


// ══════════════════════════════════════════════════════════════════════════════
// COMPARISON HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildComparisonHTML($db): string {
    $p1s = $_GET['period1_start'] ?? date('Y-m-01');
    $p1e = $_GET['period1_end']   ?? date('Y-m-d');
    $p2s = $_GET['period2_start'] ?? date('Y-m-d', strtotime('-1 month'));
    $p2e = $_GET['period2_end']   ?? date('Y-m-d', strtotime('-1 day'));
    $fp1 = date('d/m/Y', strtotime($p1s)) . ' — ' . date('d/m/Y', strtotime($p1e));
    $fp2 = date('d/m/Y', strtotime($p2s)) . ' — ' . date('d/m/Y', strtotime($p2e));

    $body  = sectionTitle("🔄 เปรียบเทียบ 2 ช่วงเวลา");
    $body .= "<table><thead><tr>
        <th rowspan='2'>โมดูล / รายการ</th>
        <th colspan='3' style='background:#4361ee;'>ช่วงที่ 1: {$fp1}</th>
        <th colspan='3' style='background:#7c3aed;'>ช่วงที่ 2: {$fp2}</th>
        <th rowspan='2'>เปลี่ยนแปลง (%)</th>
    </tr><tr>
        <th>รวม</th><th>เฉลี่ย/วัน</th><th>NG/งาน</th>
        <th>รวม</th><th>เฉลี่ย/วัน</th><th>NG/งาน</th>
    </tr></thead><tbody>";

    $modules = [
        'Air Compressor' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.actual_value) as tot, AVG(r.actual_value) as avg,
             SUM(CASE WHEN (s.min_value IS NOT NULL AND(r.actual_value<s.min_value OR r.actual_value>s.max_value))
             OR(s.min_value IS NULL AND ABS(r.actual_value-s.standard_value)>s.standard_value*0.1) THEN 1 ELSE 0 END) as ng
             FROM air_daily_records r JOIN air_inspection_standards s ON r.inspection_item_id=s.id
             WHERE r.record_date BETWEEN ? AND ?", [$s,$e]),
        'ไฟฟ้า (kWh)' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.usage_amount) as tot, AVG(r.usage_amount) as avg, 0 as ng
             FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
             WHERE m.meter_type='electricity' AND r.record_date BETWEEN ? AND ?", [$s,$e]),
        'น้ำ (m³)' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.usage_amount) as tot, AVG(r.usage_amount) as avg, 0 as ng
             FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
             WHERE m.meter_type='water' AND r.record_date BETWEEN ? AND ?", [$s,$e]),
        'LPG' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.number_value) as tot, AVG(r.number_value) as avg,
             SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
             FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
             WHERE r.record_date BETWEEN ? AND ?", [$s,$e]),
        'Boiler (ชม.)' => fn($s,$e) => qOne($db,
            "SELECT SUM(operating_hours) as tot, AVG(operating_hours) as avg, 0 as ng
             FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?", [$s,$e]),
        'ค่าไฟฟ้า (บาท)' => fn($s,$e) => qOne($db,
            "SELECT SUM(total_cost) as tot, AVG(total_cost) as avg, 0 as ng
             FROM electricity_summary WHERE record_date BETWEEN ? AND ?", [$s,$e]),
    ];

    foreach ($modules as $label => $fetchFn) {
        $d1  = $fetchFn($p1s, $p1e);
        $d2  = $fetchFn($p2s, $p2e);
        $t1  = (float)($d1['tot'] ?? 0);
        $t2  = (float)($d2['tot'] ?? 0);
        $chg = $t1 > 0 ? round(($t2 - $t1) / $t1 * 100, 2) : null;
        $chgHtml = $chg !== null
            ? "<span class='" . ($chg > 5 ? 'chg-pos' : ($chg < -5 ? 'chg-neg' : 'chg-zero')) . "'>"
              . ($chg > 0 ? "+$chg%" : "$chg%") . "</span>"
            : '-';
        $bgIdx   = array_search($label, array_keys($modules));
        $bg      = ($bgIdx % 2 === 0) ? '' : " style='background:#ffffff'";
        $body .= "<tr{$bg}>
            <td><strong>{$label}</strong></td>
            <td class='text-right'>" . fmt($t1) . "</td>
            <td class='text-right'>" . fmt($d1['avg']) . "</td>
            <td class='text-center'>" . ($d1['ng'] ?? '-') . "</td>
            <td class='text-right'>" . fmt($t2) . "</td>
            <td class='text-right'>" . fmt($d2['avg']) . "</td>
            <td class='text-center'>" . ($d2['ng'] ?? '-') . "</td>
            <td class='text-center'>{$chgHtml}</td>
        </tr>";
    }
    $body .= '</tbody></table>';

    return htmlWrap('รายงานเปรียบเทียบ', "ช่วง 1: {$fp1} | ช่วง 2: {$fp2}", $body);
}