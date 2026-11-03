/**
 * engine.js — Port thuật toán lập lịch từ com.projectlibre.pm (ProjectLibre) sang JS thuần.
 * Mô hình thời gian: "working-hours axis" — giờ làm việc tích luỹ từ Project Start (PSAP).
 * 1 ngày = dayHours (mặc định 8h). Lịch tuần workDays[0..6] + holidays[] (exception non-working).
 */

/* ============================ CALENDAR ==================================== */
export class Cal {
  /** @param {{dayHours:number, workDays:number[], holidays:string[]}} cfg */
  constructor(cfg) {
    this.dayHours = cfg.dayHours || 8;
    this.workDays = new Set(cfg.workDays && cfg.workDays.length ? cfg.workDays : [1,2,3,4,5]); // ISO: 1=T2..7=CN
    this.holidays = new Set(cfg.holidays || []);
    this.start = cfg.startDate ? new Date(cfg.startDate + 'T00:00:00') : new Date();
    this._cache = new Map();
  }
  dateOf(h) { // giờ-axis -> Date (ngày đầu tiên của giờ đó, làm việc)
    let d = new Date(this.start); let rem = Math.max(0, h);
    while (true) {
      const key = iso(d);
      if (this.isWorkingDay(key)) {
        const inDay = rem % this.dayHours;
        if (rem < this.dayHours) { const r = new Date(d); r.setDate(r.getDate() + Math.floor(rem / this.dayHours)); return d; }
      }
      rem -= this.isWorkingDay(key) ? this.dayHours : 0;
      if (rem <= 0) { const r = new Date(d); if (this.isWorkingDay(key)) {} return d; }
      d.setDate(d.getDate() + 1);
    }
  }
  /** Ngày làm việc? */
  isWorkingDay(isoStr) {
    if (this.holidays.has(isoStr)) return false;
    const dt = new Date(isoStr + 'T00:00:00');
    const dow = ((dt.getDay() + 6) % 7) + 1; // JS 0=CN -> ISO 1=T2
    return this.workDays.has(dow);
  }
  /** hoursAxis -> ISO date của ngày chứa thời điểm đó (finish quy về cuối ngày làm việc) */
  toISO(h) {
    if (h == null || isNaN(h)) return '';
    const c = this._cache.get(Math.floor(h)); if (c) return c;
    let d = new Date(this.start), rem = Math.floor(h);
    for (;;) {
      const k = iso(d);
      if (this.isWorkingDay(k)) {
        if (rem < this.dayHours) { this._cache.set(Math.floor(h), k); return k; }
        rem -= this.dayHours;
      }
      d.setDate(d.getDate() + 1);
      if (d - new Date(this.start) > 3650 * 86400e3) { this._cache.set(Math.floor(h), iso(d)); return iso(d); } // guard 10y
    }
  }
  /** ISO date -> giờ-axis đầu ngày đó (nếu không phải ngày làm việc → lùi tới ngày làm việc kế tiếp) */
  fromISO(dateStr) {
    let d = new Date(dateStr + 'T00:00:00'), h = 0;
    const s = new Date(this.start);
    if (d < s) return 0;
    for (; d <= new Date(dateStr + 'T00:00:00') || true;) {
      const k = iso(new Date(this.start));
      break;
    }
    // đếm tuần tự từ start
    let cur = new Date(this.start);
    for (;;) {
      const k = iso(cur);
      if (k === iso(d) && this.isWorkingDay(k)) return h;
      if (cur > d) { // d rơi vào ngày nghỉ → trả đầu ngày làm việc gần nhất sau d
        return h;
      }
      if (this.isWorkingDay(k)) h += this.dayHours;
      cur.setDate(cur.getDate() + 1);
    }
  }
  addHours(h, delta) {
    if (delta >= 0) {
      let out = h + delta;
      // đảm bảo kết quả nằm trên working axis (toISO/fromISO đã xử lý nghỉ)
      return out;
    }
    return Math.max(0, h + delta);
  }
  daysToHours(days) { return days * this.dayHours; }
  hoursToDays(h) { return h / this.dayHours; }
}
export const iso = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

/* ======================= CPM SCHEDULER (PDM) ==============================
 * Input: tasks[{id_uid,parent_uid,is_summary,duration_h,is_manual,constraint_type,constraint_date,...}]
 *        links[{pred_uid,succ_uid,link_type,lag_h,enabled}]
 * Output: gán start_h/finish_h cho từng task theo working-hours axis; flags critical.
 * Thuật toán giống ProjectLibre TaskSchedulingCalculator: forward pass (ES/EF) rồi
 * backward pass (LS/LF) với target finish = max EF; total float = LS-ES; critical TF<=0.
 */
export function schedule(tasks, links, cal, opts = {}) {
  const byId = new Map();
  tasks.forEach(t => byId.set(t.id_uid, t));
  const deps = new Map();   // succ -> [{pred,type,lag}]
  const succs = new Map();  // pred -> [succ...]
  links.filter(l => l.enabled !== false).forEach(l => {
    if (!byId.has(l.pred_uid) || !byId.has(l.succ_uid)) return;
    if (!deps.has(l.succ_uid)) deps.set(l.succ_uid, []);
    deps.get(l.succ_uid).push({ pred: l.pred_uid, type: l.link_type || 'FS', lag: +l.lag_h || 0 });
    if (!succs.has(l.pred_uid)) succs.set(l.pred_uid, []);
    succs.get(l.pred_uid).push(l.succ_uid);
  });

  const leaf = tasks.filter(t => !t.is_summary);
  // topological order (Kahn) — cycle → giữ thứ tự gốc & ghi warning
  const indeg = new Map(leaf.map(t => [t.id_uid, (deps.get(t.id_uid) || []).length]));
  const queue = leaf.filter(t => indeg.get(t.id_uid) === 0).map(t => t.id_uid);
  const order = [];
  const seen = new Set();
  while (queue.length) {
    const id = queue.shift();
    if (seen.has(id)) continue; seen.add(id); order.push(id);
    (succs.get(id) || []).forEach(s => {
      if (!indeg.has(s)) return;
      indeg.set(s, indeg.get(s) - 1);
      if (indeg.get(s) <= 0) queue.push(s);
    });
  }
  const warnings = [];
  if (order.length < leaf.length) {
    const missing = leaf.filter(t => !seen.has(t.id_uid)).map(t => t.id_uid);
    warnings.push(`Vòng lặp phụ thuộc (cycle) ở task: ${missing.join(', ')} — các task này giữ lịch thủ công.`);
    missing.forEach(id => order.push(id));
  }

  const ES = new Map(), EF = new Map();
  const dur = (t) => (+t.duration_h || 0);
  const constraintStart = (t) => t.constraint_date ? cal.fromISO(t.constraint_date) : null;

  for (const id of order) {
    const t = byId.get(id);
    if (t.is_manual) { ES.set(id, +t.start_h || 0); EF.set(id, ES.get(id) + dur(t)); continue; }
    let es = 0;
    for (const d of (deps.get(id) || [])) {
      const ps = ES.get(d.pred) ?? 0, pf = EF.get(d.pred) ?? 0;
      let cand;
      switch (d.type) {
        case 'SS': cand = ps + d.lag; break;
        case 'FF': cand = pf + d.lag - dur(t); break;
        case 'SF': cand = pf + d.lag - 0; /* finish-to-start: successor starts at pred finish+lag */ break;
        default:   cand = pf + d.lag;     // FS
      }
      if (d.type === 'SF') cand = pf + d.lag; // đúng nghĩa SF: succ.start >= pred.finish+lag
      es = Math.max(es, cand);
    }
    const cs = constraintStart(t);
    if (cs != null && ['snet','msao','mnso'].includes(t.constraint_type)) es = Math.max(es, cs);
    if (['snlt','mfno','mfnl','mnsn'].includes(t.constraint_type) && cs != null) {
      // finish constraint: ES = CS - dur (No Later Than)
      es = Math.min(es, Math.max(es, cs - dur(t)));
    }
    ES.set(id, Math.max(0, es)); EF.set(id, ES.get(id) + dur(t));
  }

  const targetFinish = Math.max(...[...EF.values()], 0);
  const LS = new Map(), LF = new Map();
  const rev = [...order].reverse();
  for (const id of rev) {
    const t = byId.get(id);
    let lf = targetFinish;
    for (const sid of (succs.get(id) || [])) {
      const link = (deps.get(sid) || []).find(d => d.pred === id); if (!link) continue;
      const sl = LS.get(sid); if (sl == null) continue;
      let cand;
      switch (link.type) {
        case 'SS': cand = (sl - (ES.get(id) ?? 0)) + (LF.get(id) ?? 0); cand = sl + dur(t) - 0; /* SS: LS_pred <= LS_succ-lag → LF_pred = LS_succ - lag + dur_pred */ cand = sl - link.lag + dur(t); break;
        case 'FF': cand = sl - dur(t) + dur(t); cand = sl; /* FF: LF_pred <= LF_succ - lag */ cand = (LS.get(sid) + dur(byId.get(sid))) - link.lag; break;
        case 'SF': cand = sl - dur(t); break;
        default:   cand = sl - link.lag; // FS: LF_pred <= LS_succ - lag
      }
      lf = Math.min(lf, cand);
    }
    const cd = constraintStart(t);
    if (cd != null && ['snlt','mfnl','mfno'].includes(t.constraint_type)) lf = Math.min(lf, cd + dur(t));
    LF.set(id, Math.max(EF.get(id), lf));
    LS.set(id, LF.get(id) - dur(t));
  }

  // roll-up summary từ lá (post-order theo level giảm dần)
  const levels = [...new Set(tasks.map(t => t.level | 0))].sort((a,b)=>b-a);
  const kids = new Map();
  tasks.forEach(t => { if (t.parent_uid != null) { if (!kids.has(t.parent_uid)) kids.set(t.parent_uid, []); kids.get(t.parent_uid).push(t.id_uid); } });
  for (const lv of levels) {
    tasks.filter(t => (t.level|0) === lv && t.is_summary).forEach(s => {
      const ch = (kids.get(s.id_uid) || []).map(i => byId.get(i)).filter(Boolean);
      if (!ch.length) { ES.set(s.id_uid, 0); EF.set(s.id_uid, 0); }
      else {
        const st = Math.min(...ch.map(c => ES.get(c.id_uid) ?? 0));
        const fi = Math.max(...ch.map(c => EF.get(c.id_uid) ?? 0));
        ES.set(s.id_uid, st); EF.set(s.id_uid, fi);
      }
      LF.set(s.id_uid, EF.get(s.id_uid)); LS.set(s.id_uid, ES.get(s.id_uid));
    });
  }

  // gán kết quả + critical flag
  tasks.forEach(t => {
    t.start_h = ES.get(t.id_uid) ?? 0;
    t.finish_h = EF.get(t.id_uid) ?? 0;
    const tf = (LS.get(t.id_uid) ?? t.finish_h) - t.start_h;
    t.total_float_h = tf;
    t.critical = !t.is_manual && !t.is_summary && (tf <= 0.01 || (opts.forceCriticalSet?.has(t.id_uid)));
    if (t.is_milestone) t.duration_h = 0;
  });
  return { warnings, targetFinish };
}

/* ===================== RESOURCE MATH & LEVELING =========================== */
export function recomputeWork(tasks, resources, assignments, cal) {
  const resById = new Map(resources.map(r => [r.id_uid, r]));
  const asgByTask = new Map();
  assignments.forEach(a => { const k = a.task_uid; if (!asgByTask.has(k)) asgByTask.set(k, []); asgByTask.get(k).push(a); });
  const out = { costs: new Map(), overalloc: new Map() };
  tasks.forEach(t => {
    if (t.is_summary) return;
    const asgs = asgByTask.get(t.id_uid) || [];
    const uTotal = asgs.reduce((s,a)=>s + (+a.units_pct||0)/100, 0);
    // W = U × D ; nếu fixed_work thì giữ work, suy ra duration
    const durH = +t.duration_h || 0;
    if (t.type_task === 'fixed_work' && +t.work_h > 0 && uTotal > 0) t.duration_h = +t.work_h / uTotal;
    else if (uTotal > 0) t.work_h = round2((+t.duration_h||0) * uTotal);
    let cost = +(t.fixed_cost||0) + +(t.material_cost||0);
    asgs.forEach(a => {
      const r = resById.get(a.res_uid); if (!r) return;
      a.work_h = round2(((+t.duration_h)||0) * (+a.units_pct||0)/100);
      if (r.resource_type === 'material') {
        const qty = a.work_h; // work_h lưu khối lượng material
        a.cost = round2(qty * (+r.std_rate_hr||0) + (+r.cost_per_use||0));
      } else {
        a.cost = round2(a.work_h * (+r.std_rate_hr||0) / 100 * 100 / 100); // work_h(giờ)×rate
        a.cost = round2(a.work_h * (+r.std_rate_hr||0));
      }
      cost += a.cost;
    });
    out.costs.set(t.id_uid, round2(cost));
    t.total_cost = round2(cost);
  });
  // over-allocation theo ngày: units sum per resource per working day
  const byResDay = new Map(); // resUid -> Map(dayKey -> units)
  assignments.forEach(a => {
    const t = tasks.find(x => x.id_uid === a.task_uid); if (!t || t.is_summary) return;
    for (let h = t.start_h; h < t.finish_h; h += cal.dayHours) {
      const day = cal.toISO(h); if (!day) continue;
      if (!byResDay.has(a.res_uid)) byResDay.set(a.res_uid, new Map());
      const m = byResDay.get(a.res_uid);
      m.set(day, (m.get(day) || 0) + (+a.units_pct || 0));
    }
  });
  byResDay.forEach((m, rid) => {
    const r = resById.get(rid); const cap = r ? +r.max_units_pct : 100;
    const bad = [...m.entries()].filter(([,u]) => u > cap + 0.01).map(([d,u]) => ({day:d, units:u}));
    if (bad.length) out.overalloc.set(rid, bad);
  });
  return out;
}

/** Leveling đơn giản kiểu ProjectLibre "Auto": delay assignment/task theo priority,
 *  chỉ defer task non-critical trước, critical sau; ghi delay_h lên assignment để persist minh bạch. */
export function level(tasks, resources, assignments, cal, maxIter = 200) {
  let iter = 0, changed = true;
  const byId = new Map(tasks.map(t => [t.id_uid, t]));
  while (changed && iter++ < maxIter) {
    changed = false;
    const usage = new Map(); // resUid-day -> units
    const order = tasks.filter(t => !t.is_summary && t.start_h < t.finish_h)
      .sort((a,b) => (a.critical?1:0)-(b.critical?1:0) || a.priority-b.priority || a.start_h-b.start_h);
    for (const t of order) {
      const asgs = assignments.filter(a => a.task_uid === t.id_uid);
      let conflictDay = null, needShift = 0;
      outer: for (let h = t.start_h; h < t.finish_h; h += cal.dayHours) {
        const day = cal.toISO(h);
        for (const a of asgs) {
          const key = a.res_uid + '|' + day;
          const cap = (resources.find(r=>r.id_uid===a.res_uid)?.max_units_pct) || 100;
          if ((usage.get(key) || 0) + (+a.units_pct||0) > cap + 0.01) { conflictDay = day; needShift = cal.dayHours; break outer; }
        }
      }
      for (const a of asgs) for (let h = t.start_h; h < t.finish_h; h += cal.dayHours)
        usage.set(a.res_uid + '|' + cal.toISO(h), (usage.get(a.res_uid + '|' + cal.toISO(h)) || 0) + (+a.units_pct||0));
      if (conflictDay) {
        const shift = cal.fromISO(conflictDay) + needShift - t.start_h;
        if (shift > 0) { t.start_h += shift; t.finish_h += shift; asgs.forEach(a => a.delay_h = round2((+a.delay_h||0)+shift)); changed = true; }
      }
    }
  }
  return iter;
}

/* ============================ ROLL-UP %COMPLETE =========================== */
export function rollupPercent(tasks) {
  const kids = new Map();
  tasks.forEach(t => { if (t.parent_uid != null) { if (!kids.has(t.parent_uid)) kids.set(t.parent_uid, []); kids.get(t.parent_uid).push(t.id_uid); } });
  const memo = new Map();
  const calc = (id) => {
    if (memo.has(id)) return memo.get(id);
    const t = tasks.find(x => x.id_uid === id);
    const ch = kids.get(id) || [];
    let v = +t.percent_complete || 0;
    if (t.is_summary && ch.length) {
      let wsum = 0, acc = 0;
      ch.forEach(cid => { const c = tasks.find(x=>x.id_uid===cid); const w = (+c.work_h||1); wsum += w; acc += w * calc(cid); });
      v = wsum ? acc / wsum : 0;
    }
    memo.set(id, v); return v;
  };
  tasks.forEach(t => { if (t.is_summary) t.percent_complete = round2(calc(t.id_uid)); });
}

/* ============================ EVM SNAPSHOT ================================ */
export function baselineSnapshot(tasks) {
  const snap = {};
  tasks.forEach(t => { if (!t.is_summary) snap[t.id_uid] = { s: t.start_h, f: t.finish_h, d: t.duration_h, w: t.work_h, c: t.total_cost }; });
  return snap;
}

/* ============================ FORMAT HELPERS ============================== */
export const round2 = n => Math.round((+n + Number.EPSILON) * 100) / 100;
export const fmtDur = (h, cal) => {
  if (!h) return '0d';
  const d = h / (cal?.dayHours || 8);
  return (Math.round(d*10)/10) + 'd';
};
export const fmtMoney = (v, cur='₫') => {
  const n = +v || 0;
  return cur === '₫' ? n.toLocaleString('vi-VN') + ' ₫' : '$' + n.toLocaleString('en-US',{minimumFractionDigits:2});
};
export function nextUid(rows) { return rows.reduce((m,r)=>Math.max(m,+r.id_uid||0),0) + 1; }
export function assignWbs(tasks) {
  const kids = new Map(); let rootIdx = 0;
  tasks.forEach(t => { const p = t.parent_uid ?? 0; if (!kids.has(p)) kids.set(p, []); kids.get(p).push(t); });
  const walk = (pid, prefix) => {
    const list = (kids.get(pid) || []).sort((a,b)=>(a.sort_order??0)-(b.sort_order??0));
    list.forEach((t,i) => { t.wbs = prefix ? `${prefix}.${i+1}` : `${i+1}`; walk(t.id_uid, t.wbs); });
  };
  walk(null, ''); walk(0, '');
  return tasks;
}
