<?php
/**
 * Export PDF Report
 * Engineering Utility Monitoring System (EUMS)
 *
 * Requires: composer require dompdf/dompdf phpoffice/phpspreadsheet
 *
 * URL params: (same as export_report.php)
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

// ─── Helper functions ─────────────────────────────────────────────────────────
function thaiMonth(int $m): string {
    return ['','January','February','March','April','May','June',
            'July','August','September','October','November','December'][$m];
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
    $stmt = $db->prepare($sql); 
    $stmt->execute($p);
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
$options->set('defaultFont', 'DejaVu Sans');
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
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; color: #1e1e2e; }

        /* Font Awesome icon fallbacks */
        .fas, .far, .fal { font-family: "DejaVu Sans", "Font Awesome 6 Free", sans-serif; }
        .fa-bolt:before { content: "⚡"; }
        .fa-water:before { content: "💧"; }
        .fa-sun:before { content: "☀️"; }
        .fa-moon:before { content: "🌙"; }
        .fa-chart-line:before { content: "📈"; }
        .fa-weight-hanging:before { content: "⚖️"; }
        .fa-user:before { content: "👤"; }
        .fa-fire:before { content: "🔥"; }
        .fa-industry:before { content: "🏭"; }
        .fa-clock:before { content: "🕐"; }
        .fa-temperature-high:before { content: "🌡️"; }
        .fa-gauge:before { content: "📊"; }
        .fa-gauge-high:before { content: "📊"; }
        .fa-check-circle:before { content: "✅"; }
        .fa-times-circle:before { content: "❌"; }
        .fa-money-bill-wave:before { content: "💰"; }
        .fa-coins:before { content: "🪙"; }
        .fa-chart-pie:before { content: "📊"; }
        .fa-calculator:before { content: "🧮"; }
        .fa-clipboard-list:before { content: "📋"; }
        .fa-tag:before { content: "🏷️"; }
        .fa-ruler:before { content: "📏"; }
        .fa-bullseye:before { content: "🎯"; }
        .fa-user-check:before { content: "✓"; }
        .fa-box:before { content: "📦"; }
        .fa-cog:before { content: "⚙️"; }
        .fa-oil-can:before { content: "🛢️"; }
        .fa-gas-pump:before { content: "⛽"; }
        .fa-tint:before { content: "💧"; }
        .fa-grip:before { content: "≡"; }
        .fa-chart-bar:before { content: "📊"; }

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
        .ok  { background:#dcfce7; color:#16a34a; padding:2px 8px;
               border-radius:12px; font-weight:bold; font-size:8pt; display:inline-block; }
        .ng  { background:#fee2e2; color:#dc2626; padding:2px 8px;
               border-radius:12px; font-weight:bold; font-size:8pt; display:inline-block; }
        .elec-badge { background:#fef3c7; color:#92400e; padding:2px 8px;
               border-radius:12px; font-size:8pt; display:inline-block; }
        .water-badge { background:#dbeafe; color:#1e40af; padding:2px 8px;
               border-radius:12px; font-size:8pt; display:inline-block; }

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
            
        /* ── Module headers ── */
        .module-header { display:flex; align-items:center; gap:8px; margin-bottom:5px; }
        .module-badge { padding:4px 10px; border-radius:20px; font-size:9pt; color:white; }
    </style>
    </head>
    <body>
    <div class="pdf-header">
        <h1>{$title}</h1>
        <div class="sub">{$period}</div>
        <div class="meta">{$app} &nbsp;|&nbsp; Generated: {$generated} &nbsp;|&nbsp; By: {$user}</div>
    </div>
    {$body}
    <div class="pdf-footer">
        Engineering Utility Monitoring System (EUMS) &mdash; {$app} &mdash; Printed on {$generated}
    </div>
    </body>
    </html>
    HTML;
}

function sectionTitle(string $t): string {
    return "<div class=\"section-title\">$t</div>";
}

function noData(): string {
    return '<div class="no-data">⚠️ No data in the selected period</div>';
}


// ══════════════════════════════════════════════════════════════════════════════
// DAILY HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildDailyHTML($db): string {
    $date       = $_GET['date'] ?? date('d/m/Y');
    $dateParsed = strlen($date) === 10 && strpos($date, '/') !== false
                ? DateTime::createFromFormat('d/m/Y', $date)->format('Y-m-d')
                : $date;
    $displayDate = date('d', strtotime($dateParsed)) . ' '
                . thaiMonth((int)date('m', strtotime($dateParsed))) . ' '
                . date('Y', strtotime($dateParsed));

    // ── Summary cards ────────────────────────────────────────────────────────
    $airSum  = qOne($db,"SELECT COUNT(*) as n, SUM(actual_value) as tot FROM air_daily_records WHERE record_date=?", [$dateParsed]);
    $engSum  = qOne($db,"SELECT SUM(CASE WHEN m.meter_type='electricity' THEN r.usage_amount ELSE 0 END) as elec,
                SUM(CASE WHEN m.meter_type='water' THEN r.usage_amount ELSE 0 END) as water
                FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id WHERE r.record_date=?", [$dateParsed]);
    $lpgSum  = qOne($db,"SELECT SUM(CASE WHEN i.item_type='enum' AND r.enum_value='OK' THEN 1 ELSE 0 END) as ok,
                SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
                FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id WHERE r.record_date=?", [$dateParsed]);
    $elecRow = qOne($db,"SELECT ee_unit, total_cost FROM electricity_summary WHERE record_date=?", [$dateParsed]);

    $body = '<table style="margin-bottom:15px;"><tr>';
    $cards = [
        ['🔵 Air Compressor', fmt($airSum['tot']), 'units', '#4361ee', 'fa-compress'],
        ['⚡ Electricity', fmt($engSum['elec']), 'kWh', '#f59e0b', 'fa-bolt'],
        ['💧 Water', fmt($engSum['water']), 'm³', '#3b82f6', 'fa-water'],
        ['💰 Electricity Cost', fmt($elecRow['total_cost'] ?? null), 'Baht', '#10b981', 'fa-money-bill-wave'],
    ];
    foreach ($cards as [$label,$val,$unit,$color,$icon]) {
        $body .= "<td style='padding:8px; background:{$color}11; border:1px solid {$color}44;
                    border-radius:6px; text-align:center; width:25%;'>
                    <div style='font-size:7.5pt;color:{$color};'><i class='fas {$icon}'></i> {$label}</div>
                    <div style='font-size:14pt;font-weight:bold;color:{$color};'>{$val}</div>
                    <div style='font-size:7.5pt;color:#9ca3af;'>{$unit}</div>
                  </td>";
    }
    $body .= '</tr></table>';

    // ── Air Compressor ────────────────────────────────────────────────────────
    $body .= sectionTitle('🔵 Air Compressor');
    $body .= '<div class="module-header"><span class="module-badge" style="background:#4361ee;"><i class="fas fa-compress"></i> Compressor Details</span></div>';
    
    $stmt = $db->prepare("SELECT m.machine_name, s.inspection_item, r.actual_value,
            s.standard_value, s.min_value, s.max_value, r.recorded_by
        FROM air_daily_records r JOIN mc_air m ON r.machine_id=m.id
        JOIN air_inspection_standards s ON r.inspection_item_id=s.id
        WHERE r.record_date=? ORDER BY m.machine_code");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $body .= '<table><thead><tr>
            <th><i class="fas fa-grip"></i> Machine</th>
            <th><i class="fas fa-clipboard-list"></i> Inspection Item</th>
            <th class="text-right"><i class="fas fa-ruler"></i> Actual</th>
            <th class="text-right"><i class="fas fa-bullseye"></i> Standard</th>
            <th class="text-right"><i class="fas fa-arrow-down"></i> Min</th>
            <th class="text-right"><i class="fas fa-arrow-up"></i> Max</th>
            <th class="text-center"><i class="fas fa-check-circle"></i> Status</th>
            <th><i class="fas fa-user"></i> Recorded By</th>
        </tr></thead><tbody>';
        
        $ngCount = 0;
        foreach ($rows as $row) {
            $ok  = isAirOK($row);
            if (!$ok) $ngCount++;
            $cls = $ok ? 'ok' : 'ng';
            $icon = $ok ? 'fa-check-circle' : 'fa-times-circle';
            $body .= "<tr>
                <td><i class='fas fa-cog' style='color:#4361ee;'></i> {$row['machine_name']}</td>
                <td>{$row['inspection_item']}</td>
                <td class='text-right'>" . fmt($row['actual_value']) . "</td>
                <td class='text-right'>" . fmt($row['standard_value']) . "</td>
                <td class='text-right'>" . ($row['min_value'] ?? '-') . "</td>
                <td class='text-right'>" . ($row['max_value'] ?? '-') . "</td>
                <td class='text-center'><span class='{$cls}'><i class='fas {$icon}'></i> " . ($ok ? 'OK' : 'NG') . "</span></td>
                <td><i class='fas fa-user-check'></i> {$row['recorded_by']}</td>
            </tr>";
        }
        
        // Summary row
        $body .= "<tr style='background:#f0f9ff; border-top:2px solid #4361ee;'>
            <td colspan='6' style='text-align:right; font-weight:bold;'><i class='fas fa-chart-pie'></i> Summary:</td>
            <td class='text-center'><span class='ok'><i class='fas fa-check-circle'></i> OK: " . (count($rows)-$ngCount) . "</span> <span class='ng'><i class='fas fa-times-circle'></i> NG: {$ngCount}</span></td>
            <td></td>
        </tr>";
        
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

    // ── Energy & Water ────────────────────────────────────────────────────────
    $body .= sectionTitle('⚡ Energy & Water');
    
    $stmt = $db->prepare("SELECT m.meter_name, m.meter_type,
            r.morning_reading, r.evening_reading, r.usage_amount, r.recorded_by
        FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
        WHERE r.record_date=? ORDER BY m.meter_type, m.meter_code");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        // Module header with icons
        $body .= '<div class="module-header">';
        $body .= '<span class="module-badge" style="background:#f59e0b;"><i class="fas fa-bolt"></i> Electricity Meters</span>';
        $body .= '<span class="module-badge" style="background:#3b82f6;"><i class="fas fa-water"></i> Water Meters</span>';
        $body .= '</div>';
        
        $body .= '<table><thead><tr>
            <th><i class="fas fa-grip"></i> Meter</th>
            <th><i class="fas fa-tag"></i> Type</th>
            <th class="text-right"><i class="fas fa-sun"></i> Morning</th>
            <th class="text-right"><i class="fas fa-moon"></i> Evening</th>
            <th class="text-right"><i class="fas fa-chart-line"></i> Usage</th>
            <th><i class="fas fa-weight-hanging"></i> Unit</th>
            <th><i class="fas fa-user"></i> Recorded By</th>
        </tr></thead><tbody>';
        
        $totalElec = $totalWater = 0;
        $elecCount = $waterCount = 0;
        
        foreach ($rows as $row) {
            $isElec = $row['meter_type'] === 'electricity';
            $badge  = $isElec ? 'elec-badge' : 'water-badge';
            $label  = $isElec ? '⚡ Electricity' : '💧 Water';
            $icon   = $isElec ? 'fa-bolt' : 'fa-water';
            $unit   = $isElec ? 'kWh' : 'm³';
            $color  = $isElec ? '#f59e0b' : '#3b82f6';
            
            if ($isElec) {
                $totalElec += $row['usage_amount'];
                $elecCount++;
            } else {
                $totalWater += $row['usage_amount'];
                $waterCount++;
            }
            
            $body .= "<tr>
                <td><i class='fas fa-gauge-high' style='color:{$color};'></i> {$row['meter_name']}</td>
                <td class='text-center'><span class='{$badge}'><i class='fas {$icon}'></i> {$label}</span></td>
                <td class='text-right'>" . fmt($row['morning_reading']) . "</td>
                <td class='text-right'>" . fmt($row['evening_reading']) . "</td>
                <td class='text-right'><strong>" . fmt($row['usage_amount']) . "</strong></td>
                <td>{$unit}</td>
                <td><i class='fas fa-user-check'></i> {$row['recorded_by']}</td>
            </tr>";
        }
        
        // Summary rows - exactly as requested in the image
        $body .= "<tr style='background:#f0f9ff; border-top:2px solid #f59e0b;'>
            <td colspan='4' style='text-align:right; font-weight:bold;'>
                <i class='fas fa-calculator' style='margin-right:6px;'></i> Total Electricity ({$elecCount} meters):
            </td>
            <td class='text-right' style='font-weight:bold; color:#f59e0b; font-size:10pt;'>
                " . fmt($totalElec) . " kWh
            </td>
            <td colspan='2'></td>
        </tr>";
        
        if ($waterCount > 0) {
            $body .= "<tr style='background:#eff6ff;'>
                <td colspan='4' style='text-align:right; font-weight:bold;'>
                    <i class='fas fa-calculator' style='margin-right:6px;'></i> Total Water ({$waterCount} meters):
                </td>
                <td class='text-right' style='font-weight:bold; color:#3b82f6; font-size:10pt;'>
                    " . fmt($totalWater) . " m³
                </td>
                <td colspan='2'></td>
            </tr>";
        }
        
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

    // ── LPG ──────────────────────────────────────────────────────────────────
    $body .= sectionTitle('🔴 LPG');
    $body .= '<div class="module-header"><span class="module-badge" style="background:#dc2626;"><i class="fas fa-fire"></i> LPG Inspection</span></div>';
    
    $stmt = $db->prepare("SELECT i.item_name, i.item_type,
            COALESCE(r.number_value, r.enum_value) as value, r.recorded_by,
            i.unit, i.standard_value
        FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
        WHERE r.record_date=? ORDER BY i.item_no");
    $stmt->execute([$dateParsed]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $body .= '<table><thead><tr>
            <th><i class="fas fa-clipboard-list"></i> Item</th>
            <th><i class="fas fa-tag"></i> Type</th>
            <th class="text-right"><i class="fas fa-ruler"></i> Value</th>
            <th><i class="fas fa-weight-hanging"></i> Unit</th>
            <th class="text-right"><i class="fas fa-bullseye"></i> Standard</th>
            <th><i class="fas fa-user"></i> Recorded By</th>
        </tr></thead><tbody>';
        
        $okCount = $ngCount = 0;
        
        foreach ($rows as $row) {
            $isEnum = $row['item_type'] === 'enum';
            $status = '';
            $statusClass = '';
            
            if ($isEnum) {
                $status = $row['value'];
                $statusClass = $row['value'] === 'OK' ? 'ok' : 'ng';
                if ($row['value'] === 'OK') $okCount++;
                else $ngCount++;
            }
            
            $valHtml = $isEnum
                ? "<span class='{$statusClass}'><i class='fas " . ($status === 'OK' ? 'fa-check-circle' : 'fa-times-circle') . "'></i> {$status}</span>"
                : fmt($row['value']);
            
            $standard = $row['standard_value'] ? fmt($row['standard_value']) : '-';
            
            $body .= "<tr>
                <td><i class='fas fa-box' style='color:#dc2626;'></i> {$row['item_name']}</td>
                <td class='text-center'>" . ($isEnum ? '<span class="elec-badge">OK/NG</span>' : '<span class="water-badge">Number</span>') . "</td>
                <td class='text-right'>{$valHtml}</td>
                <td>" . ($row['unit'] ?? '-') . "</td>
                <td class='text-right'>{$standard}</td>
                <td><i class='fas fa-user-check'></i> {$row['recorded_by']}</td>
            </tr>";
        }
        
        // Summary row
        $body .= "<tr style='background:#fff1f0; border-top:2px solid #dc2626;'>
            <td colspan='2' style='text-align:right; font-weight:bold;'>
                <i class='fas fa-chart-pie'></i> Summary:
            </td>
            <td colspan='4'>
                <span class='ok'><i class='fas fa-check-circle'></i> OK: {$okCount}</span>
                <span class='ng' style='margin-left:8px;'><i class='fas fa-times-circle'></i> NG: {$ngCount}</span>
            </td>
        </tr>";
        
        $body .= '</tbody></table>';
    } else { $body .= noData(); }

// ── Boiler ───────────────────────────────────────────────────────────────
$body .= sectionTitle('🏭 Boiler');
$body .= '<div class="module-header"><span class="module-badge" style="background:#6b7280;"><i class="fas fa-industry"></i> Boiler Operations</span></div>';

$stmt = $db->prepare("SELECT m.machine_name, r.steam_pressure,
        r.steam_temperature, r.fuel_consumption, r.operating_hours, r.recorded_by
    FROM boiler_daily_records r JOIN mc_boiler m ON r.machine_id=m.id
    WHERE r.record_date=? ORDER BY m.machine_code");
$stmt->execute([$dateParsed]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows) {
    $body .= '<table><thead><tr>
        <th><i class="fas fa-cog"></i> Machine</th>
        <th class="text-right"><i class="fas fa-gauge"></i> Pressure (bar)</th>
        <th class="text-right"><i class="fas fa-temperature-high"></i> Temp (°C)</th>
        <th class="text-right"><i class="fas fa-oil-can"></i> Fuel (L)</th>
        <th class="text-right"><i class="fas fa-clock"></i> Hours</th>
        <th><i class="fas fa-user"></i> Recorded By</th>
    </tr></thead><tbody>';
    
    $totalFuel = $totalHours = 0;
    
    foreach ($rows as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
        
        $body .= "<tr>
            <td><i class='fas fa-fire' style='color:#ef4444;'></i> {$row['machine_name']}</td>
            <td class='text-right'>" . fmt($row['steam_pressure']) . "</td>
            <td class='text-right'>" . fmt($row['steam_temperature'], 1) . "</td>
            <td class='text-right'><strong>" . fmt($row['fuel_consumption']) . "</strong></td>
            <td class='text-right'>" . fmt($row['operating_hours'], 1) . "</td>
            <td><i class='fas fa-user-check'></i> {$row['recorded_by']}</td>
        </tr>";
    }
    
    // Summary row
    $body .= "<tr style='background:#f3f4f6; border-top:2px solid #6b7280;'>
        <td colspan='2' style='text-align:right; font-weight:bold;'>
            <i class='fas fa-chart-line'></i> Total:
        </td>
        <td class='text-right' style='font-weight:bold; color:#ef4444;'>" . fmt($totalFuel) . " L</td>
        <td class='text-right' style='font-weight:bold; color:#3b82f6;'>" . fmt($totalHours, 1) . " hrs</td>
        <td colspan='2'></td>
    </tr>";
    
    $body .= '</tbody></table>';
} else { $body .= noData(); }

    // ── Summary Electricity ───────────────────────────────────────────────────
    $body .= sectionTitle('📊 Summary Electricity');
    $body .= '<div class="module-header"><span class="module-badge" style="background:#10b981;"><i class="fas fa-chart-bar"></i> Electrical Summary</span></div>';
    
    $elec = qOne($db, "SELECT ee_unit, cost_per_unit, total_cost, pe, recorded_by
        FROM electricity_summary WHERE record_date=?", [$dateParsed]);

    if ($elec) {
        $body .= '<table style="width:100%; margin-bottom:10px;">
            <tr>
                <td style="width:50%; vertical-align:top; padding-right:10px;">
                    <table style="width:100%;">
                        <tr>
                            <td style="border:none; padding:8px; background:#f0f9ff;">
                                <i class="fas fa-bolt" style="color:#f59e0b;"></i> 
                                <strong>Electricity Usage:</strong>
                            </td>
                            <td style="border:none; text-align:right; padding:8px; background:#f0f9ff;">
                                ' . fmt($elec['ee_unit']) . ' kWh
                            </td>
                        </tr>
                        <tr>
                            <td style="border:none; padding:8px;">
                                <i class="fas fa-coins" style="color:#f59e0b;"></i> 
                                <strong>Cost per Unit:</strong>
                            </td>
                            <td style="border:none; text-align:right; padding:8px;">
                                ' . fmt($elec['cost_per_unit'], 4) . ' Baht/kWh
                            </td>
                        </tr>
                        <tr>
                            <td style="border:none; padding:8px; background:#fef2f2;">
                                <i class="fas fa-money-bill-wave" style="color:#dc2626;"></i> 
                                <strong>Total Cost:</strong>
                            </td>
                            <td style="border:none; text-align:right; padding:8px; background:#fef2f2; font-weight:bold; color:#dc2626;">
                                ' . fmt($elec['total_cost']) . ' Baht
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width:50%; vertical-align:top;">
                    <table style="width:100%;">
                        <tr>
                            <td style="border:none; padding:8px; background:#f0f9ff;">
                                <i class="fas fa-chart-pie" style="color:#3b82f6;"></i> 
                                <strong>PE (Performance):</strong>
                            </td>
                            <td style="border:none; text-align:right; padding:8px; background:#f0f9ff;">
                                ' . fmt($elec['pe'], 4) . '
                            </td>
                        </tr>
                        <tr>
                            <td style="border:none; padding:8px;">
                                <i class="fas fa-user" style="color:#6b7280;"></i> 
                                <strong>Recorded By:</strong>
                            </td>
                            <td style="border:none; text-align:right; padding:8px;">
                                <i class="fas fa-user-check" style="color:#10b981;"></i> ' . htmlspecialchars($elec['recorded_by']) . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
        
        // Footer note
        $body .= '<div style="margin-top:5px; text-align:center; font-size:8pt; color:#6b7280; border-top:1px dashed #e5e7eb; padding-top:5px;">
            <i class="fas fa-chart-line"></i> Electricity summary for ' . $displayDate . '
        </div>';
        
    } else { $body .= noData(); }

    return htmlWrap('Daily Report', "Date: $displayDate", $body);
}


// ══════════════════════════════════════════════════════════════════════════════
// MONTHLY HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildMonthlyHTML($db): string {
    $month     = (int)($_GET['month'] ?? date('m'));
    $year      = (int)($_GET['year']  ?? date('Y'));
    $period    = thaiMonth($month) . ' ' . $year;
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

    $body  = sectionTitle('📋 Monthly Summary — All Modules');
    $body .= '<table><thead><tr>
        <th><i class="fas fa-cube"></i> Module</th>
        <th class="text-right"><i class="fas fa-calendar"></i> Days</th>
        <th class="text-right"><i class="fas fa-database"></i> Records</th>
        <th class="text-right"><i class="fas fa-chart-line"></i> Total</th>
        <th class="text-right"><i class="fas fa-chart-bar"></i> Daily Avg</th>
        <th><i class="fas fa-info-circle"></i> Notes</th>
    </tr></thead><tbody>';

    $summaries = [
        ['🔵 Air Compressor', $air['days']??0, $air['recs']??0,
         fmt($air['tot']), fmt(($air['tot']??0)/max($air['days']??1,1)),
         'NG: '.($air['ng']??0).' items', '#4361ee'],
        ['⚡ Electricity', $energy['days']??0, '-',
         fmt($energy['elec']).' kWh', fmt(($energy['elec']??0)/max($energy['days']??1,1)).' kWh',
         'Total usage', '#f59e0b'],
        ['💧 Water', $energy['days']??0, '-',
         fmt($energy['water']).' m³', fmt(($energy['water']??0)/max($energy['days']??1,1)).' m³',
         'Total usage', '#3b82f6'],
        ['🔴 LPG', $lpg['days']??0, '-',
         fmt($lpg['tot']).' units', '-',
         'OK:'.($lpg['ok']??0).' NG:'.($lpg['ng']??0), '#dc2626'],
        ['🏭 Boiler', $boiler['days']??0, '-',
         fmt($boiler['hours']).' hrs', fmt($boiler['avg_p']).' bar',
         'Fuel: '.fmt($boiler['fuel']).' L', '#6b7280'],
        ['📊 Summary Electricity', $elec['days']??0, '-',
         fmt($elec['cost']??0).' Baht', fmt($elec['ee']).' kWh',
         'Rate: '.fmt($elec['rate'],4).' Baht/unit', '#10b981'],
    ];
    
    foreach ($summaries as $i => [$mod,$days,$recs,$tot,$avg,$note,$color]) {
        $bg = $i % 2 === 0 ? '' : " style='background:#ffffff'";
        $body .= "<tr{$bg}>
            <td><strong style='color:{$color};'><i class='fas fa-square' style='color:{$color};'></i> {$mod}</strong></td>
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
    $body .= sectionTitle('📅 Daily Breakdown');
    
    $body .= '<div class="module-header"><span class="module-badge" style="background:#4361ee;"><i class="fas fa-calendar-alt"></i> Daily Details</span></div>';

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
        <th><i class="fas fa-calendar"></i> Date</th>
        <th class="text-right"><i class="fas fa-bolt"></i> Electricity (kWh)</th>
        <th class="text-right"><i class="fas fa-water"></i> Water (m³)</th>
        <th class="text-right"><i class="fas fa-chart-line"></i> EE (Units)</th>
        <th class="text-right"><i class="fas fa-money-bill"></i> Cost (Baht)</th>
        <th class="text-right"><i class="fas fa-chart-pie"></i> PE</th>
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
        $dateDisp = $d . '/' . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $year;
        $hasData = $dr || $er;
        $style   = $hasData ? '' : " style='color:#c7d2fe;'";
        $body   .= "<tr{$style}>
            <td>{$dateDisp}</td>
            <td class='text-right'>" . ($elecV ? fmt($elecV) : '-') . "</td>
            <td class='text-right'>" . ($waterV ? fmt($waterV) : '-') . "</td>
            <td class='text-right'>" . ($eeV ? fmt($eeV) : '-') . "</td>
            <td class='text-right'>" . ($costV ? fmt($costV) : '-') . "</td>
            <td class='text-right'>{$peV}</td>
        </tr>";
    }
    $body .= "<tr class='total-row'>
        <td><strong>Monthly Total</strong></td>
        <td class='text-right'><strong>" . fmt($totElec) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totWater) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totEE) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totCost) . "</strong></td>
        <td>-</td>
    </tr>";
    $body .= '</tbody></table>';

    return htmlWrap('Monthly Report', $period, $body);
}


// ══════════════════════════════════════════════════════════════════════════════
// YEARLY HTML
// ══════════════════════════════════════════════════════════════════════════════
function buildYearlyHTML($db): string {
    $year      = (int)($_GET['year'] ?? date('Y'));
    $startDate = "$year-01-01";
    $endDate   = "$year-12-31";

    $body  = sectionTitle("📅 Monthly Summary - Year $year");
    $body .= '<div class="module-header"><span class="module-badge" style="background:#4361ee;"><i class="fas fa-calendar-alt"></i> Monthly Breakdown</span></div>';
    
    $body .= '<table><thead><tr>
        <th><i class="fas fa-calendar"></i> Month</th>
        <th class="text-right"><i class="fas fa-bolt"></i> Electricity (kWh)</th>
        <th class="text-right"><i class="fas fa-water"></i> Water (m³)</th>
        <th class="text-right"><i class="fas fa-fire"></i> LPG</th>
        <th class="text-right"><i class="fas fa-industry"></i> Boiler (hrs)</th>
        <th class="text-right"><i class="fas fa-money-bill"></i> Electricity Cost</th>
        <th class="text-right"><i class="fas fa-chart-line"></i> EE (Units)</th>
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
            <td><strong>" . thaiMonth($m) . "</strong></td>
            <td class='text-right'>" . ($elec ? fmt($elec) : '-') . "</td>
            <td class='text-right'>" . ($water ? fmt($water) : '-') . "</td>
            <td class='text-right'>" . ($lpg   ? fmt($lpg)   : '-') . "</td>
            <td class='text-right'>" . ($boil  ? fmt($boil, 1)  : '-') . "</td>
            <td class='text-right'>" . ($cost  ? fmt($cost)  : '-') . "</td>
            <td class='text-right'>" . ($ee    ? fmt($ee)    : '-') . "</td>
        </tr>";
    }
    $body .= "<tr class='total-row'>
        <td><strong>Yearly Total</strong></td>
        <td class='text-right'><strong>" . fmt($totals[0]) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totals[1]) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totals[2]) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totals[3], 1) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totals[4]) . "</strong></td>
        <td class='text-right'><strong>" . fmt($totals[5]) . "</strong></td>
    </tr></tbody></table>";

    // ── KPI block ─────────────────────────────────────────────────────────────
    $body .= sectionTitle('🏆 Yearly KPIs');
    $body .= '<div class="module-header"><span class="module-badge" style="background:#10b981;"><i class="fas fa-trophy"></i> Key Performance Indicators</span></div>';
    
    $body .= '<table><thead><tr>
        <th><i class="fas fa-list"></i> Item</th>
        <th class="text-right"><i class="fas fa-chart-line"></i> Value</th>
        <th><i class="fas fa-weight-hanging"></i> Unit</th>
    </tr></thead><tbody>';
    
    $kpis = [
        ['Total Electricity', fmt($totals[0]), 'kWh'],
        ['Total Water',   fmt($totals[1]), 'm³'],
        ['Total LPG',  fmt($totals[2]), 'units'],
        ['Total Electricity Cost',    fmt($totals[4]), 'Baht'],
        ['Boiler Total Hours', fmt($totals[3], 1), 'hours'],
        ['Total EE Units',         fmt($totals[5]), 'units'],
    ];
    
    foreach ($kpis as $i => [$l,$v,$u]) {
        $bg = $i % 2 === 0 ? '' : " style='background:#ffffff'";
        $body .= "<tr{$bg}><td><i class='fas fa-circle' style='color:#10b981; font-size:6pt;'></i> {$l}</td><td class='text-right'><strong>{$v}</strong></td><td>{$u}</td></tr>";
    }
    $body .= '</tbody></table>';

    return htmlWrap('Yearly Report', "Year $year", $body);
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

    $body  = sectionTitle("🔄 Comparison of 2 Periods");
    $body .= '<div class="module-header">';
    $body .= '<span class="module-badge" style="background:#4361ee;"><i class="fas fa-calendar"></i> Period 1: ' . $fp1 . '</span>';
    $body .= '<span class="module-badge" style="background:#7c3aed;"><i class="fas fa-calendar"></i> Period 2: ' . $fp2 . '</span>';
    $body .= '</div>';
    
    $body .= "<table><thead><tr>
        <th rowspan='2'><i class='fas fa-cube'></i> Module</th>
        <th colspan='3' style='background:#4361ee;'>Period 1</th>
        <th colspan='3' style='background:#7c3aed;'>Period 2</th>
        <th rowspan='2'><i class='fas fa-chart-line'></i> Change (%)</th>
    </tr><tr>
        <th>Total</th><th>Daily Avg</th><th>NG/Count</th>
        <th>Total</th><th>Daily Avg</th><th>NG/Count</th>
    </tr></thead><tbody>";

    $modules = [
        'Air Compressor' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.actual_value) as tot, AVG(r.actual_value) as avg,
             SUM(CASE WHEN (s.min_value IS NOT NULL AND(r.actual_value<s.min_value OR r.actual_value>s.max_value))
             OR(s.min_value IS NULL AND ABS(r.actual_value-s.standard_value)>s.standard_value*0.1) THEN 1 ELSE 0 END) as ng
             FROM air_daily_records r JOIN air_inspection_standards s ON r.inspection_item_id=s.id
             WHERE r.record_date BETWEEN ? AND ?", [$s,$e]),
        'Electricity (kWh)' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.usage_amount) as tot, AVG(r.usage_amount) as avg, 0 as ng
             FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
             WHERE m.meter_type='electricity' AND r.record_date BETWEEN ? AND ?", [$s,$e]),
        'Water (m³)' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.usage_amount) as tot, AVG(r.usage_amount) as avg, 0 as ng
             FROM meter_daily_readings r JOIN mc_mdb_water m ON r.meter_id=m.id
             WHERE m.meter_type='water' AND r.record_date BETWEEN ? AND ?", [$s,$e]),
        'LPG' => fn($s,$e) => qOne($db,
            "SELECT SUM(r.number_value) as tot, AVG(r.number_value) as avg,
             SUM(CASE WHEN i.item_type='enum' AND r.enum_value='NG' THEN 1 ELSE 0 END) as ng
             FROM lpg_daily_records r JOIN lpg_inspection_items i ON r.item_id=i.id
             WHERE r.record_date BETWEEN ? AND ?", [$s,$e]),
        'Boiler (hrs)' => fn($s,$e) => qOne($db,
            "SELECT SUM(operating_hours) as tot, AVG(operating_hours) as avg, 0 as ng
             FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?", [$s,$e]),
        'Electricity Cost (Baht)' => fn($s,$e) => qOne($db,
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
              . ($chg > 0 ? "↑ +$chg%" : "↓ $chg%") . "</span>"
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

    return htmlWrap('Comparison Report', "Period 1: {$fp1} | Period 2: {$fp2}", $body);
}