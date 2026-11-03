<?php
/**
 * Projects API
 *  GET  projects               → danh sách dự án của tôi + được chia sẻ
 *  POST projects              → tạo project rỗng (kèm summary task mặc định)
 *  GET  project?id=N          → full graph {project,tasks,links,resources,assignments,baselines}
 *  DELETE project?id=N        → owner only
 *  PUT  graph                 → persist toàn bộ trạng thái sau khi client chạy engine (idempotent replace)
 *  POST baseline              → lưu snapshot; GET baseline → list
 *  POST invite                → sinh mã mời 6 ký tự (owner/editor)
 *  POST join                  → tham gia bằng mã mời
 *  GET  members | DELETE member?id=&uid=
 */
function handle(string $route): void {
    $method = $_SERVER['REQUEST_METHOD'];
    $pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    switch ($route) {

    case 'projects':
        if ($method === 'GET') {
            $u = require_user();
            $st = db()->prepare(
                'SELECT p.*, m.role AS shared_role, o.username AS owner_name
                 FROM projects p
                 LEFT JOIN project_members m ON m.project_id=p.id AND m.user_id=?
                 JOIN users o ON o.id=p.owner_id
                 WHERE p.owner_id=? OR m.user_id=?
                 ORDER BY p.updated_at DESC'
            );
            $st->execute([$u['id'], $u['id'], $u['id']]);
            $rows = $st->fetchAll();
            foreach ($rows as &$r) {
                $r['role'] = ((int)$r['owner_id'] === (int)$u['id']) ? 'owner' : ($r['shared_role'] ?? 'viewer');
                unset($r['shared_role']);
            }
            out(['projects' => $rows]);
        }
        if ($method === 'POST') {
            $u = require_user();
            $b = body_json();
            $name = trim((string)($b['name'] ?? ''));
            if ($name === '') fail('Tên dự án không được trống.');
            $start = (string)($b['start_date'] ?? date('Y-m-d'));
            $week = json_encode($b['work_week_days'] ?? [1,2,3,4,5]);
            $st = db()->prepare(
                'INSERT INTO projects (owner_id,name,description,start_date,work_week_days,autoschedule)
                 VALUES (?,?,?,?,?,?)'
            );
            $st->execute([$u['id'], $name, $b['description'] ?? null, $start, $week, !empty($b['autoschedule']) ? 1 : 0]);
            $pid = (int)db()->lastInsertId();
            // Task tổng quan đầu tiên giống ProjectLibre ("Project name" summary)
            db()->prepare('INSERT INTO tasks (project_id,id_uid,name,is_summary,level,duration_h,sort_order,wbs)
                           VALUES (?,1,?,1,0,0,0,\'0\')')->execute([$pid, $name]);
            audit($pid, (int)$u['id'], 'project.create', 'project', $pid);
            out(['project' => get_project_row($pid)], 201);
        }
        fail('Method không hỗ trợ.', 405);

    case 'project':
        if ($method === 'DELETE') {
            require_role($pid, 'owner');
            db()->prepare('DELETE FROM projects WHERE id=?')->execute([$pid]);
            out(['ok' => true]);
        }
        require_role($pid, 'viewer');
        out(get_graph($pid));

    case 'graph':
        if ($method !== 'PUT') fail('Method không hỗ trợ.', 405);
        $u = require_user();
        $b = body_json();
        $pid = (int)($b['projectId'] ?? 0);
        require_role($pid, 'editor');
        $rawLen = strlen(file_get_contents('php://input') ?: '');
        global $CFG;
        if ($rawLen > $CFG['max_graph_bytes']) fail('Payload quá lớn (>2MB). Tách nhỏ hơn.', 413);

        $pdo = db(); $pdo->beginTransaction();
        try {
            // --- project info ---
            if (!empty($b['project'])) {
                $p = $b['project'];
                $pdo->prepare(
                    'UPDATE projects SET name=?, description=?, start_date=?, finish_date=?, current_date=?,
                     work_day_hours=?, work_week_days=?, calendar_json=?, autoschedule=?, critical_path_visible=?
                     WHERE id=?'
                )->execute([
                    $p['name'] ?? '', $p['description'] ?? null, $p['startDate'] ?? date('Y-m-d'),
                    $p['finishDate'] ?? null, $p['currentDate'] ?? null,
                    (float)($p['dayHours'] ?? 8), json_encode($p['workDays'] ?? [1,2,3,4,5]),
                    json_encode($p['holidays'] ?? []), !empty($p['autoschedule']) ? 1 : 0,
                    !empty($p['showCritical']) ? 1 : 0, $pid,
                ]);
            }
            // --- replace collections (client là nguồn tính toán; server persist kết quả) ---
            replace_rows($pdo, $pid, 'tasks', $b['tasks'] ?? [], [
                'id_uid','name','notes','parent_uid','level','is_summary','is_milestone','is_manual',
                'type_task','duration_h','work_h','start_h','finish_h','constraint_type','constraint_date',
                'percent_complete','actual_start_h','actual_finish_h','actual_work_h','remaining_work_h',
                'priority','fixed_cost','material_cost','total_cost','splits_json','wbs','sort_order',
            ], (int)$u['id']);
            replace_rows($pdo, $pid, 'task_links', $b['links'] ?? [],
                ['pred_uid','succ_uid','link_type','lag_h','enabled']);
            replace_rows($pdo, $pid, 'resources', $b['resources'] ?? [],
                ['id_uid','name','resource_type','initials','max_units_pct','std_rate_hr',
                 'ovt_rate_hr','cost_per_use','material_label','calendar_json','is_active','notes']);
            replace_rows($pdo, $pid, 'assignments', $b['assignments'] ?? [],
                ['task_uid','res_uid','units_pct','work_h','actual_work_h','cost_type',
                 'overtime_h','delay_h','remaining_work_h','notes']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            fail('Lưu thất bại: ' . $e->getMessage(), 500);
        }
        audit($pid, (int)$u['id'], 'graph.put', 'project', $pid,
              ['tasks' => count($b['tasks'] ?? []), 'links' => count($b['links'] ?? [])]);
        out(['ok' => true, 'savedAt' => gmdate('c')]);

    case 'baseline':
        if ($method === 'POST') {
            $u = require_user();
            $b = body_json();
            $pid = (int)($b['projectId'] ?? 0);
            require_role($pid, 'editor');
            $pdo = db();
            $n = (int)($pdo->query("SELECT COUNT(*) c FROM baselines WHERE project_id=$pid")->fetch()['c'] ?? 0) + 1;
            $label = (string)($b['label'] ?? "Baseline $n");
            $snap = json_encode($b['snapshot'] ?? new stdClass(), JSON_UNESCAPED_UNICODE);
            if (strlen((string)$snap) > 5_000_000) fail('Snapshot quá lớn.', 413);
            $pdo->prepare('INSERT INTO baselines (project_id,bl_number,label,saved_by,snapshot) VALUES (?,?,?,?,?)')
                ->execute([$pid, $n, $label, $u['id'], $snap]);
            $pdo->prepare('UPDATE projects SET baseline_count=? WHERE id=?')->execute([$n, $pid]);
            audit($pid, (int)$u['id'], 'baseline.save', 'baseline', $n);
            out(['ok' => true, 'number' => $n, 'label' => $label], 201);
        }
        require_role($pid, 'viewer');
        $st = db()->prepare('SELECT id,bl_number,label,saved_at,saved_by FROM baselines WHERE project_id=? ORDER BY bl_number');
        $st->execute([$pid]);
        out(['baselines' => $st->fetchAll()]);

    case 'invite':
        if ($method === 'POST') {
            $b = body_json();
            $pid = (int)($b['projectId'] ?? 0);
            require_role($pid, 'editor');
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', base64_encode(random_bytes(6))), 0, 6));
            db()->prepare('UPDATE projects SET invite_code=? WHERE id=?')->execute([$code, $pid]);
            out(['inviteCode' => $code]);
        }
        fail('Method không hỗ trợ.', 405);

    case 'join':
        if ($method === 'POST') {
            $u = require_user();
            $code = strtoupper(trim((string)(body_json()['code'] ?? '')));
            if (strlen($code) !== 6) fail('Mã mời phải 6 ký tự.');
            $st = db()->prepare('SELECT id,owner_id FROM projects WHERE invite_code=?');
            $st->execute([$code]);
            $p = $st->fetch();
            if (!$p) fail('Mã mời không tồn tại.', 404);
            db()->prepare('INSERT IGNORE INTO project_members (project_id,user_id,role) VALUES (?,?,\'editor\')')
                ->execute([$p['id'], $u['id']]);
            audit((int)$p['id'], (int)$u['id'], 'project.join', 'member', (int)$u['id']);
            out(['ok' => true, 'projectId' => (int)$p['id']]);
        }
        fail('Method không hỗ trợ.', 405);

    case 'members':
        require_role($pid, 'viewer');
        $st = db()->prepare(
            'SELECT u.id,u.username,u.email,m.role,m.joined_at FROM project_members m
             JOIN users u ON u.id=m.user_id WHERE m.project_id=?'
        );
        $st->execute([$pid]);
        out(['members' => $st->fetchAll()]);

    default:
        fail('Route project chưa xử lý: ' . $route, 404);
    }
}

/* ---------------- helpers nội bộ ---------------- */
function get_project_row(int $pid): array {
    $st = db()->prepare('SELECT * FROM projects WHERE id=?'); $st->execute([$pid]);
    $r = $st->fetch(); if (!$r) fail('Không tìm thấy dự án.', 404); return $r;
}
function get_graph(int $pid): array {
    $q = fn($sql, $args = [$pid]) => (function () use ($sql, $args) {
        $st = db()->prepare($sql); $st->execute($args); return $st->fetchAll();
    })();
    return [
        'project'     => get_project_row($pid),
        'tasks'       => $q('SELECT * FROM tasks WHERE project_id=? ORDER BY sort_order,id'),
        'links'       => $q('SELECT * FROM task_links WHERE project_id=?'),
        'resources'   => $q('SELECT * FROM resources WHERE project_id=? ORDER BY id_uid'),
        'assignments' => $q('SELECT * FROM assignments WHERE project_id=?'),
        'baselines'   => $q('SELECT id,bl_number,label,saved_at,saved_by,snapshot FROM baselines WHERE project_id=? ORDER BY bl_number'),
        'audit'       => $q('SELECT a.*,u.username FROM audit_log a LEFT JOIN users u ON u.id=a.user_id
                             WHERE a.project_id=? ORDER BY a.created_at DESC LIMIT 200'),
    ];
}
/**
 * Thay thế toàn bộ rows của một collection theo "diff merge":
 * xoá uid không còn, upsert phần gửi lên. Columns whitelist bắt buộc.
 */
function replace_rows(PDO $pdo, int $pid, string $table, array $rows, array $cols, ?int $byUid = null): void {
    $key = in_array('id_uid', $cols, true) ? 'id_uid' : null;
    $seen = [];
    foreach ($rows as $r) {
        $vals = []; $names = [];
        foreach ($cols as $c) {
            if ($c === 'updated_by') continue;
            $v = $r[$c] ?? ($r[camel($c)] ?? null);
            if (is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            if ($v === null && in_array($c, ['name','id_uid','pred_uid','succ_uid','task_uid','res_uid'], true)) continue 2; // row thiếu khoá → bỏ
            $vals[] = $v; $names[] = $c;
        }
        if (!$names) continue;
        $sql = 'INSERT INTO ' . $table . ' (project_id,' . implode(',', $names) . ',updated_by) VALUES ('
             . rtrim(str_repeat('?,', count($names) + 2), ',') . ')'
             . ' ON DUPLICATE KEY UPDATE ' . implode(',', array_map(fn($n) => "$n=VALUES($n)", $names))
             . ($byUid !== null ? ',updated_by=?' : '');
        $args = array_merge([$pid], $vals);
        if ($byUid !== null) $args[] = $byUid;
        $pdo->prepare($sql)->execute($args);
        if ($key && isset($r[$key])) $seen[] = (int)$r[$key];
    }
    // Xoá các bản ghi không còn xuất hiện trong payload (chỉ khi payload có ít nhất 1 row — tránh xoá nhầm rỗng)
    if ($rows && $key) {
        $in = $seen ? implode(',', array_map('intval', $seen)) : '0';
        $pdo->exec("DELETE FROM $table WHERE project_id=$pid AND $key NOT IN ($in)");
    } elseif ($rows && $table === 'task_links') {
        $keep = array_map(fn($l) => [(int)$l['pred_uid'] ?? ($l['predUid'] ?? 0), (int)$l['succ_uid'] ?? ($l['succUid'] ?? 0)], $rows);
        $pdo->prepare('DELETE FROM task_links WHERE project_id=?')->execute([$pid]);
        foreach ($keep as [$p, $s]) if ($p && $s)
            $pdo->prepare('INSERT IGNORE INTO task_links (project_id,pred_uid,succ_uid,link_type,lag_h) VALUES (?,?,?,?,?)')
                ->execute([$pid, $p, $s, 'FS', 0]);
    }
}
function camel(string $snake): string {
    return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snake))));
}
