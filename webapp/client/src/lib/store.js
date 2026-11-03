/**
 * store.js — Svelte stores: auth, projects, graph state, undo/redo, autosave (debounce), offline mode.
 */
import { writable, derived, get } from 'svelte/store';
import { schedule, recomputeWork, level, rollupPercent, baselineSnapshot, nextUid, assignWbs, Cal, round2 } from './engine.js';

const API = (window.DEEDSPLAN_API) || '/api/index.php?route=';
const token = writable(localStorage.getItem('dp_token') || '');
export const user = writable(null);
export const toastQ = writable([]);
export function toast(msg, kind = 'ok', ms = 3500) {
  const id = Math.random().toString(36).slice(2);
  toastQ.update(q => [...q, { id, msg, kind }]);
  setTimeout(() => toastQ.update(q => q.filter(t => t.id !== id)), ms);
}

async function api(route, opts = {}) {
  const headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
  const tk = get(token); if (tk) headers.Authorization = 'Bearer ' + tk;
  const res = await fetch(API + route, { ...opts, headers, body: opts.body ? JSON.stringify(opts.body) : undefined });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw Object.assign(new Error(data.error || ('Lỗi HTTP ' + res.status)), { status: res.status, data });
  return data;
}

/* ------------------------------- AUTH ------------------------------------- */
export async function login(email, password) {
  const d = await api('auth/login', { method: 'POST', body: { email, password } });
  token.set(d.token); localStorage.setItem('dp_token', d.token); user.set(d.user);
  return d.user;
}
export async function register(email, username, password) {
  const d = await api('auth/register', { method: 'POST', body: { email, username, password } });
  token.set(d.token); localStorage.setItem('dp_token', d.token); user.set(d.user);
  return d.user;
}
export async function me() {
  if (!get(token)) return null;
  try { const d = await api('auth/me'); user.set(d.user); return d.user; }
  catch { logout(); return null; }
}
export function logout() { token.set(''); localStorage.removeItem('dp_token'); user.set(null); }

/* ------------------------------ PROJECTS ---------------------------------- */
export const projects = writable([]);
export async function loadProjects() { projects.set((await api('projects')).projects); }
export async function createProject(name, startDate) {
  const d = await api('projects', { method: 'POST', body: { name, start_date: startDate } });
  await loadProjects(); return d.project;
}
export async function deleteProject(id) { await api('project&id=' + id, { method: 'DELETE' }); await loadProjects(); }
export async function joinByCode(code) { return api('join', { method: 'POST', body: { code } }); }
export async function makeInvite(projectId) { return api('invite', { method: 'POST', body: { projectId } }); }

/* ------------------------------- GRAPH ------------------------------------ */
/** graph = { project:{...}, tasks:[], links:[], resources:[], assignments:[] } */
export const graph = writable(null);
export const saveState = writable('saved'); // saved | dirty | saving | error | offline
export const canUndo = writable(false), canRedo = writable(false);
let undoStack = [], redoStack = [];
let saveTimer = null, dirty = false;

export function calOf(g) {
  const p = g.project;
  return new Cal({
    dayHours: +p.work_day_hours || 8,
    workDays: typeof p.work_week_days === 'string' ? JSON.parse(p.work_week_days) : (p.work_week_days || [1,2,3,4,5]),
    holidays: typeof p.calendar_json === 'string' ? JSON.parse(p.calendar_json || '[]') : (p.holidays || []),
    startDate: p.start_date,
  });
}

export async function openProject(id) {
  const g = await api('project&id=' + id);
  // parse JSON columns
  g.tasks.forEach(t => { t.splits_json = safeParse(t.splits_json, []); });
  g.baselines?.forEach(b => { b.snapshotObj = safeParse(b.snapshot, {}); });
  graph.set({ ...g, baselines: g.baselines || [] });
  saveState.set('saved'); undoStack = []; redoStack = []; canUndo.set(false); canRedo.set(false);
  runEngine(false);
  return g;
}
function safeParse(v, fb) { if (v == null) return fb; if (typeof v === 'object') return v; try { return JSON.parse(v); } catch { return fb; } }

/** Chạy engine (schedule+work+rollup) rồi đánh dấu dirty & hẹn giờ lưu. */
export function mutate(label, fn) {
  const g = get(graph); if (!g) return;
  pushUndo(g);
  fn(g);
  runEngine(true);
  markDirty();
}
function runEngine(mark) {
  const g = get(graph); if (!g) return;
  const cal = calOf(g);
  assignWbs(g.tasks);
  const res = schedule(g.tasks, g.links, cal);
  recomputeWork(g.tasks, g.resources, g.assignments, cal);
  rollupPercent(g.tasks);
  g._cal = cal; g._warnings = res.warnings;
  g.project.finishDate = cal.toISO(res.targetFinish);
  graph.set({ ...g });
  if (mark) markDirty();
}
function pushUndo(g) {
  undoStack.push(JSON.stringify(snapshotable(g)));
  if (undoStack.length > 50) undoStack.shift();
  redoStack = []; canUndo.set(true); canRedo.set(false);
}
function snapshotable(g) {
  return { project: g.project, tasks: g.tasks, links: g.links, resources: g.resources, assignments: g.assignments };
}
export function undo() {
  const g = get(graph); if (!g || !undoStack.length) return;
  redoStack.push(JSON.stringify(snapshotable(g)));
  applySnapshot(g, JSON.parse(undoStack.pop()));
  runEngine(true); canUndo.set(!!undoStack.length); canRedo.set(true);
}
export function redo() {
  const g = get(graph); if (!g || !redoStack.length) return;
  undoStack.push(JSON.stringify(snapshotable(g)));
  applySnapshot(g, JSON.parse(redoStack.pop()));
  runEngine(true); canRedo.set(!!redoStack.length); canUndo.set(true);
}
function applySnapshot(g, s) {
  g.project = s.project; g.tasks = s.tasks; g.links = s.links; g.resources = s.resources; g.assignments = s.assignments;
}

function markDirty() {
  dirty = true; saveState.set('dirty');
  clearTimeout(saveTimer);
  saveTimer = setTimeout(flushSave, 1500);
}
export async function flushSave() {
  const g = get(graph); if (!g || !dirty) return;
  saveState.set('saving');
  try {
    await api('graph', { method: 'PUT', body: payloadOf(g) });
    dirty = false; saveState.set('saved');
  } catch (e) {
    if (e.status === 401) { toast('Phiên hết hạn — vui lòng đăng nhập lại.', 'err'); logout(); }
    else if (!navigator.onLine) saveState.set('offline');
    else { saveState.set('error'); toast('Không lưu được: ' + e.message, 'err'); }
  }
}
function payloadOf(g) {
  return {
    projectId: g.project.id,
    project: {
      name: g.project.name, description: g.project.description, startDate: g.project.start_date,
      finishDate: g.project.finishDate, currentDate: g.project.current_date,
      dayHours: +g.project.work_day_hours, workDays: safeParse(g.project.work_week_days, [1,2,3,4,5]),
      holidays: safeParse(g.project.calendar_json, []), autoschedule: !!+g.project.autoschedule,
      showCritical: !!+g.project.critical_path_visible,
    },
    tasks: g.tasks.map(t => ({
      id_uid: t.id_uid, name: t.name, notes: t.notes, parent_uid: t.parent_uid, level: t.level,
      is_summary: !!t.is_summary, is_milestone: !!t.is_milestone, is_manual: !!t.is_manual,
      type_task: t.type_task, duration_h: +t.duration_h || 0, work_h: +t.work_h || 0,
      start_h: +t.start_h || 0, finish_h: +t.finish_h || 0, constraint_type: t.constraint_type || 'none',
      constraint_date: t.constraint_date || null, percent_complete: +t.percent_complete || 0,
      actual_start_h: t.actual_start_h ?? null, actual_finish_h: t.actual_finish_h ?? null,
      actual_work_h: +t.actual_work_h || 0, remaining_work_h: t.remaining_work_h ?? null,
      priority: t.priority ?? 128, fixed_cost: +t.fixed_cost || 0, material_cost: +t.material_cost || 0,
      total_cost: +t.total_cost || 0, splits_json: t.splits_json || [], wbs: t.wbs, sort_order: t.sort_order ?? 0,
    })),
    links: g.links.map(l => ({ pred_uid: l.pred_uid, succ_uid: l.succ_uid, link_type: l.link_type || 'FS', lag_h: +l.lag_h || 0, enabled: l.enabled !== false })),
    resources: g.resources.map(r => ({ id_uid: r.id_uid, name: r.name, resource_type: r.resource_type, initials: r.initials, max_units_pct: +r.max_units_pct || 100, std_rate_hr: +r.std_rate_hr || 0, ovt_rate_hr: +r.ovt_rate_hr || 0, cost_per_use: +r.cost_per_use || 0, material_label: r.material_label, calendar_json: r.calendar_json || null, is_active: r.is_active !== false, notes: r.notes })),
    assignments: g.assignments.map(a => ({ task_uid: a.task_uid, res_uid: a.res_uid, units_pct: +a.units_pct || 100, work_h: +a.work_h || 0, actual_work_h: +a.actual_work_h || 0, cost_type: a.cost_type || 'proportional', overtime_h: +a.overtime_h || 0, delay_h: +a.delay_h || 0, remaining_work_h: a.remaining_work_h ?? null, notes: a.notes })),
  };
}
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => { if (get(saveState) === 'offline') flushSave(); });
  window.addEventListener('beforeunload', () => { if (dirty) navigator.sendBeacon && navigator.sendBeacon(API + 'graph-beacon', Blob ? new Blob([JSON.stringify(payloadOf(get(graph)))], { type: 'application/json' }) : null); });
}

/* --------------------------- TASK/RESOURCE OPS ----------------------------- */
export function addTask(afterUid = null) {
  mutate('addTask', g => {
    const uid = nextUid(g.tasks);
    const ref = afterUid != null ? g.tasks.find(t => t.id_uid === afterUid) : g.tasks[g.tasks.length - 1];
    const parent = ref && !ref.is_summary ? ref.parent_uid : (ref ? ref.id_uid : null);
    const level = parent != null ? ((g.tasks.find(t => t.id_uid === parent)?.level || 0) + 1) : 0;
    let sort = ref ? (+ref.sort_order || 0) + 1 : g.tasks.length + 1;
    g.tasks.splice(ref ? g.tasks.indexOf(ref) + 1 : g.tasks.length, 0, {
      id_uid: uid, name: 'Công việc mới ' + uid, parent_uid: parent, level,
      duration_h: g._cal ? g._cal.dayHours : 8, work_h: 0, start_h: 0, finish_h: 0,
      is_summary: false, is_milestone: false, is_manual: false, type_task: 'fixed_units',
      constraint_type: 'none', percent_complete: 0, priority: 128, fixed_cost: 0, material_cost: 0,
      total_cost: 0, sort_order: sort, splits_json: [],
    });
  });
}
export function insertMilestone(afterUid) {
  mutate('milestone', g => {
    const uid = nextUid(g.tasks);
    const idx = g.tasks.findIndex(t => t.id_uid === afterUid);
    g.tasks.splice(idx + 1, 0, {
      id_uid: uid, name: 'Milestone', parent_uid: g.tasks[idx]?.parent_uid ?? null,
      level: g.tasks[idx]?.level ?? 0, duration_h: 0, work_h: 0, start_h: 0, finish_h: 0,
      is_summary: false, is_milestone: true, is_manual: false, type_task: 'fixed_duration',
      constraint_type: 'snet', constraint_date: g._cal?.toISO(g.tasks[idx]?.finish_h ?? 0) || null,
      percent_complete: 0, priority: 128, fixed_cost: 0, material_cost: 0, total_cost: 0,
      sort_order: (+g.tasks[idx]?.sort_order || 0) + 0.5, splits_json: [],
    });
  });
}
export function deleteTask(uid) {
  mutate('delTask', g => {
    const doomed = new Set();
    const collect = (id) => { doomed.add(id); g.tasks.filter(t => t.parent_uid === id).forEach(c => collect(c.id_uid)); };
    collect(uid);
    g.tasks = g.tasks.filter(t => !doomed.has(t.id_uid));
    g.links = g.links.filter(l => !doomed.has(l.pred_uid) && !doomed.has(l.succ_uid));
    g.assignments = g.assignments.filter(a => !doomed.has(a.task_uid));
  });
}
export function indentTask(uid) {
  mutate('indent', g => {
    const i = g.tasks.findIndex(t => t.id_uid === uid);
    const prev = g.tasks[i - 1];
    if (!prev || prev.id_uid === uid) return;
    const t = g.tasks[i];
    t.parent_uid = prev.is_summary ? prev.id_uid : (prev.is_milestone ? prev.parent_uid : mkSummary(prev, t, g));
    if (!prev.is_summary && t.parent_uid === prev.id_uid) { /* promoted above */ }
    t.level = (g.tasks.find(x => x.id_uid === t.parent_uid)?.level ?? -1) + 1;
  });
}
function mkSummary(prev, child, g) {
  // biến prev thành summary "ôm" prev cũ? ProjectLibre indent tạo cấu trúc cha là task liền trước.
  // Cách đơn giản & đúng thị giác: cha = prev nếu prev chưa có con; còn không tạo wrapper.
  if (!g.tasks.some(t => t.parent_uid === prev.id_uid)) { prev.is_summary = true; return prev.id_uid; }
  return prev.parent_uid;
}
export function outdentTask(uid) {
  mutate('outdent', g => {
    const t = g.tasks.find(x => x.id_uid === uid); if (!t || t.parent_uid == null) return;
    const parent = g.tasks.find(x => x.id_uid === t.parent_uid);
    const gp = parent?.parent_uid ?? null;
    t.parent_uid = gp; t.level = Math.max(0, (t.level || 1) - 1);
    if (gp == null) { /* lên top-level */ }
    // rebalance level subtree
    const fix = (id, lv) => { g.tasks.filter(c => c.parent_uid === id).forEach(c => { c.level = lv + 1; fix(c.id_uid, c.level); }); };
    fix(uid, t.level);
    // nếu parent không còn con → bỏ summary flag
    if (parent && !g.tasks.some(x => x.parent_uid === parent.id_uid)) parent.is_summary = false;
  });
}
export function moveTask(uid, dir /* -1 up, +1 down */) {
  mutate('move', g => {
    const i = g.tasks.findIndex(t => t.id_uid === uid);
    const j = i + dir;
    if (j < 0 || j >= g.tasks.length) return;
    // chỉ đổi trong cùng parent
    if (g.tasks[j].parent_uid !== g.tasks[i].parent_uid) return;
    [g.tasks[i], g.tasks[j]] = [g.tasks[j], g.tasks[i]];
    g.tasks.forEach((t, k) => t.sort_order = k + 1);
  });
}
export function linkTasks(pred, succ, type = 'FS', lagH = 0) {
  mutate('link', g => {
    if (pred === succ) return;
    if (g.links.some(l => l.pred_uid === pred && l.succ_uid === succ)) return;
    g.links.push({ pred_uid: pred, succ_uid: succ, link_type: type, lag_h: lagH, enabled: true });
  });
}
export function unlink(pred, succ) {
  mutate('unlink', g => { g.links = g.links.filter(l => !(l.pred_uid === pred && l.succ_uid === succ)); });
}
export function setField(uid, key, val) {
  mutate('set:' + key, g => {
    const t = g.tasks.find(x => x.id_uid === uid); if (!t) return;
    t[key] = val;
    if (key === 'duration_h' && t.is_milestone) t.duration_h = 0;
  });
}
export function addResource(type = 'work') {
  mutate('addRes', g => {
    const uid = nextUid(g.resources);
    g.resources.push({ id_uid: uid, name: type === 'work' ? 'Nguồn lực ' + uid : 'Vật tư ' + uid,
      resource_type: type, max_units_pct: 100, std_rate_hr: type === 'work' ? 50000 : 10000,
      ovt_rate_hr: 0, cost_per_use: 0, material_label: type === 'material' ? 'kg' : null,
      is_active: true, calendar_json: null });
  });
}
export function assignRes(taskUid, resUid, unitsPct = 100) {
  mutate('assign', g => {
    if (g.assignments.some(a => a.task_uid === taskUid && a.res_uid === resUid)) return;
    g.assignments.push({ task_uid: taskUid, res_uid: resUid, units_pct: unitsPct, work_h: 0, actual_work_h: 0, cost_type: 'proportional', overtime_h: 0, delay_h: 0 });
  });
}
export function unassign(taskUid, resUid) {
  mutate('unassign', g => { g.assignments = g.assignments.filter(a => !(a.task_uid === taskUid && a.res_uid === resUid)); });
}
export function autoLevel() {
  mutate('level', g => {
    const cal = g._cal || calOf(g);
    const iters = level(g.tasks, g.resources, g.assignments, cal);
    toast(`Đã leveling sau ${iters} vòng. Kiểm tra tab Resources để xem thay đổi.`, 'warn', 5000);
  });
}
export async function saveBaseline(label) {
  const g = get(graph); if (!g) return;
  await flushSave();
  const snap = baselineSnapshot(g.tasks);
  const d = await api('baseline', { method: 'POST', body: { projectId: g.project.id, label, snapshot: snap } });
  g.baselines.push({ id: Date.now(), bl_number: d.number, label: d.label, saved_at: new Date().toISOString(), snapshotObj: snap });
  graph.set({ ...g });
  toast(`Đã lưu "${d.label}" (#${d.number}) — dòng Baseline xuất hiện trên Gantt.`, 'ok');
}
export function updateProgress(uid, pct) {
  mutate('pct', g => {
    const t = g.tasks.find(x => x.id_uid === uid); if (!t || t.is_summary) return;
    t.percent_complete = Math.max(0, Math.min(100, +pct || 0));
    const cal = g._cal;
    if (t.percent_complete > 0 && t.actual_start_h == null) t.actual_start_h = t.start_h;
    if (t.percent_complete >= 100) { t.actual_finish_h = t.finish_h; t.actual_work_h = t.work_h; }
    else if (t.percent_complete > 0) {
      t.actual_start_h = t.actual_start_h ?? t.start_h;
      t.actual_work_h = round2(t.work_h * t.percent_complete / 100);
    }
    void cal;
  });
}
export const selectedUid = writable(null);
export const currentView = writable('gantt');
