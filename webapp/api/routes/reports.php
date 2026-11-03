<?php
/**
 * Reports API — tính EVM từ dữ liệu đã persist (baseline snapshot + actuals).
 * GET /api/index.php?route=reports/evm&id=<projectId>&bl=<number?>
 *
 * Quy ước ProjectLibre:
 *  PV (BCWS) = planned cost phân bổ theo baseline tới status date
 *  EV (BCWP) = BAC_parcial * %complete (dùng baseline cost của task)
 *  AC (ACWP) = actual cost thực tế (assignment actual_work*rate + manual)
 *  EAC = BAC/CPI ; ETC = EAC - AC ; VAC = BAC - EAC
 */
function handle(string $route): void {
    if ($route !== 'reports/evm') fail('Route reports chưa xử lý: ' . $route, 404);
    $pid = (int)($_GET['id'] ?? 0);
    require_role($pid, 'viewer');

    $pdo = db();
    $proj = $pdo->prepare('SELECT * FROM projects WHERE id=?'); $proj->execute([$pid]);
    $P = $proj->fetch(); if (!$P) fail('Không tìm thấy dự án.', 404);
    $statusDate = $_GET['asOf'] ?? ($P['current_date'] ?: date('Y-m-d'));

    // Baseline mới nhất (hoặc chỉ định bl=)
    $bsSql = 'SELECT * FROM baselines WHERE project_id=?' . (isset($_GET['bl']) ? ' AND bl_number=?' : ' ORDER BY bl_number DESC LIMIT 1');
    $bs = $pdo->prepare($bsSql);
    $bs->execute(isset($_GET['bl']) ? [$pid, (int)$_GET['bl']] : [$pid]);
    $B = $bs->fetch();
    if (!$B) out(['error' => 'Chưa có baseline. Hãy lưu Baseline trước khi xem báo cáo EVM.', 'hint' => 'POST /api/index.php?route=baseline']);

    $snap = json_decode($B['snapshot'], true) ?: [];   // {uid:{s,f,d,w,c}} — s/f là ISO date hoặc số giờ axis
    $tasks = $pdo->prepare('SELECT id_uid,percent_complete,actual_work_h,total_cost,start_h,finish_h,is_summary FROM tasks WHERE project_id=? AND is_summary=0');
    $tasks->execute([$pid]);
    $T = []; foreach ($tasks->fetchAll() as $t) $T[(int)$t['id_uid']] = $t;

    // quy đổi giờ-axis sang ngày để so status date
    $startTs = strtotime($P['start_date']);
    $dayH = max(1.0, (float)$P['work_day_hours']);
    $h2date = fn($h) => date('Y-m-d', $startTs + (int)floor(((float)$h) / $dayH) * 86400);

    $BAC = $PV = $EV = $AC = 0.0;
    $perTask = [];
    foreach ($snap as $uid => $bl) {
        $uid = (int)$uid;
        if (!isset($T[$uid])) continue;
        $bc = (float)($bl['c'] ?? 0);          // baseline cost = planned value total
        $bs_s = (float)($bl['s'] ?? 0);        // hours axis
        $bs_f = (float)($bl['f'] ?? $bs_s);
        $BAC += $bc;
        // PV: linear proration theo thời gian baseline vs status date
        $sd = strtotime($statusDate);
        $ts = $startTs + $bs_s / $dayH * 86400;
        $tf = $startTs + $bs_f / $dayH * 86400;
        $pvFrac = $tf <= $ts ? 1 : max(0, min(1, ($sd - $ts) / ($tf - $ts)));
        $pv = $bc * $pvFrac;
        $ev = $bc * ((float)$T[$uid]['percent_complete'] / 100);
        $ac = (float)$T[$uid]['total_cost'];   // client đã persist actual/total cost minh bạch
        $PV += $pv; $EV += $ev; $AC += $ac;
        $perTask[] = [
            'uid' => $uid, 'bac' => round($bc, 2), 'pv' => round($pv, 2),
            'ev' => round($ev, 2), 'ac' => round($ac, 2),
            'sv' => round($ev - $pv, 2), 'cv' => round($ev - $ac, 2),
            'spi' => $pv > 0 ? round($ev / $pv, 3) : null,
            'cpi' => $ac > 0 ? round($ev / $ac, 3) : null,
        ];
    }
    $SPI = $PV > 0 ? $EV / $PV : null;
    $CPI = $AC > 0 ? $EV / $AC : null;
    $EAC = $CPI && $CPI > 0 ? $BAC / $CPI : $BAC;
    out([
        'project'   => ['id' => $pid, 'name' => $P['name'], 'asOf' => $statusDate,
                        'baseline' => $B['label'], 'baselineNo' => (int)$B['bl_number']],
        'summary'   => [
            'BAC' => round($BAC, 2), 'PV' => round($PV, 2), 'EV' => round($EV, 2), 'AC' => round($AC, 2),
            'SV'  => round($EV - $PV, 2), 'CV' => round($EV - $AC, 2),
            'SPI' => $SPI !== null ? round($SPI, 3) : null,
            'CPI' => $CPI !== null ? round($CPI, 3) : null,
            'EAC' => round($EAC, 2), 'ETC' => round($EAC - $AC, 2), 'VAC' => round($BAC - $EAC, 2),
            'formulas' => [
                'SV=%s'  => 'EV − PV', 'CV' => 'EV − AC', 'SPI' => 'EV / PV', 'CPI' => 'EV / AC',
                'EAC' => 'BAC / CPI', 'ETC' => 'EAC − AC', 'VAC' => 'BAC − EAC',
            ],
        ],
        'tasks' => $perTask,
    ]);
}
