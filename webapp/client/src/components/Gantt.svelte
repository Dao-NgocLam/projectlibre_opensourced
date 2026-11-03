<script>
  /**
   * Gantt.svelte — view chủ lực: bảng WBS (trái) + SVG timeline (phải), scroll đồng bộ.
   * Tương tác: click chọn, double-click sửa tên inline, drag thân thanh = shift, kéo mép = đổi duration,
   * kéo từ mép phải task này thả vào task khác = tạo link FS, baseline diamond/line, today line.
   */
  import { graph, selectedUid, setField, linkTasks, unlink, updateProgress, mutate } from '../lib/store.js';
  import { fmtDur, round2, iso } from '../lib/engine.js';

  let zoom = 1;                       // px/giờ-làm-việc hệ số
  const ROW_H = 30, BAR_H = 14, HEAD_H = 56;
  let wrapL, wrapR;                   // hai pane scroll sync
  let tip = null;                     // {x,y,text}
  let drag = null;                    // {uid, mode:'move'|'left'|'right'|'link', startX, orig:{s,f}}
  let editingUid = null, editVal = '';

  $: g = $graph;
  $: cal = g?._cal;
  $: rows = g ? flatten(g.tasks) : [];
  $: pxh = 0.9 * zoom;               // px mỗi giờ làm việc
  $: maxH = rows.length ? Math.max(...rows.map(r => r.t.finish_h || 0), (+g.project.work_day_hours||8)) + (cal?.dayHours||8)*5 : 100;
  $: chartW = Math.max(600, maxH * pxh);
  $: blSnap = g?.baselines?.length ? g.baselines[g.baselines.length - 1].snapshotObj : null;
  $: todayH = cal && g.project.current_date ? cal.fromISO(new Date().toISOString().slice(0,10)) : 0;

  function flatten(tasks) {
    const kids = new Map(); tasks.forEach(t => { const p = t.parent_uid ?? 0; if (!kids.has(p)) kids.set(p, []); kids.get(p).push(t); });
    const out = [];
    (function walk(pid, depth) {
      (kids.get(pid) || []).sort((a,b)=>(+a.sort_order||0)-(+b.sort_order||0)).forEach(t => { out.push({ t, depth }); walk(t.id_uid, depth+1); });
    })(0, 0);
    return out;
  }
  const X = h => h * pxh;

  function showTip(ev, text) { tip = { x: ev.clientX, y: ev.clientY, text }; }
  const hideTip = () => (tip = null);

  /* -------- interactions trên thanh Gantt -------- */
  function barDown(ev, t, mode) {
    ev.preventDefault(); stop(ev);
    selectedUid.set(t.id_uid);
    drag = { uid: t.id_uid, mode, startX: ev.clientX, orig: { s: t.start_h, f: t.finish_h }, moved: false };
    window.addEventListener('pointermove', barMove);
    window.addEventListener('pointerup', barUp);
  }
  function barMove(ev) {
    if (!drag) return;
    const dh = (ev.clientX - drag.startX) / pxh;
    if (Math.abs(ev.clientX - drag.startX) > 3) drag.moved = true;
    const t = g.tasks.find(x => x.id_uid === drag.uid);
    if (!t) return;
    if (drag.mode === 'move') { t.start_h = Math.max(0, round2(drag.orig.s + dh)); t.finish_h = Math.max(t.start_h, round2(drag.orig.f + dh)); }
    if (drag.mode === 'right') { t.finish_h = Math.max(t.start_h, round2(drag.orig.f + dh)); }
    if (drag.mode === 'left') { t.start_h = Math.min(round2(drag.orig.s + dh), t.finish_h); }
    graph.set({ ...g });
  }
  function barUp() {
    if (drag?.moved) {
      const t = g.tasks.find(x => x.id_uid === drag.uid);
      if (t && drag.mode !== 'link') {
        t.duration_h = Math.max(0, round2(t.finish_h - t.start_h));
        if (drag.mode === 'move') t.is_manual = true; // shift thủ công → manual (tường minh: hiện nhãn "Manual")
        mutate('bar-edit', gg => {});                // push undo marker & re-run engine
      }
    }
    drag = null;
    window.removeEventListener('pointermove', barMove);
    window.removeEventListener('pointerup', barUp);
  }
  function linkUp(ev) {
    if (!drag?.moved || drag.mode !== 'link') { drag = null; return; }
    const target = document.elementFromPoint(ev.clientX, ev.clientY)?.closest('[data-uid]');
    if (target) {
      const succ = +target.getAttribute('data-uid');
      if (succ !== drag.uid) linkTasks(drag.uid, succ, 'FS', 0);
    }
    drag = null;
    window.removeEventListener('pointermove', barMove);
    window.removeEventListener('pointerup', barUp);
  }
  function startLink(ev, t) {
    ev.stopPropagation(); ev.preventDefault();
    drag = { uid: t.id_uid, mode: 'link', startX: ev.clientX, orig: { s: t.start_h, f: t.finish_h }, moved: false };
    window.addEventListener('pointermove', barMove);
    window.addEventListener('pointerup', linkUp);
  }
  const stop = (e) => e.stopPropagation();

  function commitName(t) {
    if (editVal.trim()) setField(t.id_uid, 'name', editVal.trim());
    editingUid = null;
  }
  function onWheel(ev) { if (ev.ctrlKey) { ev.preventDefault(); zoom = Math.min(4, Math.max(.25, zoom * (ev.deltaY < 0 ? 1.15 : .87))); } }
  function syncScroll(e) { (e.target === wrapL ? wrapR : wrapL).scrollTop = e.target.scrollTop; }

  const durOf = (t) => fmtDur(t.duration_h, cal);
  const dateOf = (h) => cal?.toISO(h) || '';

  /* ---- time-axis ticks: duyệt tuần làm việc từ 0 → maxH ---- */
  function dayList() {
    const out = []; if (!cal) return out;
    let h = 0, d = new Date(cal.start), guard = 0;
    while (h <= maxH + cal.dayHours && guard++ < 3000) {
      const k = iso(d);
      if (cal.isWorkingDay(k)) { out.push({ h, iso: k, d: new Date(d) }); h += cal.dayHours; }
      else out.push({ h: null, iso: k, d: new Date(d), off: true });
      d.setDate(d.getDate() + 1);
    }
    return out;
  }
  $: days = dayList();
  function monthTicks() {
    const seen = new Set(), out = [];
    for (const x of days) {
      const key = x.iso.slice(0, 7);
      if (!seen.has(key)) { seen.add(key); out.push({ h: x.h ?? nearestH(x), label: vnMonth(x.iso) }); }
    }
    return out;
  }
  function weekTicks() {
    const out = [];
    for (let i = 0; i < days.length; i++) {
      const x = days[i]; if (x.h == null) continue;
      if (x.d.getDay() === 1) out.push({ h: x.h, label: 'Tuần ' + weekNo(x.iso) }); // T2
    }
    return out;
  }
  function offDays() {
    const out = []; let lastH = 0;
    days.forEach(x => { if (x.h != null) lastH = x.h + (cal?.dayHours || 8); });
    let runStart = null;
    for (let i = 0; i < days.length; i++) {
      const x = days[i];
      if (x.off) {
        if (runStart == null) runStart = { h: lastH, hours: 0 };
        runStart.hours += 24 / 8; // shading theo ngày nghỉ — quy ước 1 ô = 1 đơn vị giờ-axis để phủ màu
      } else if (runStart) { out.push(runStart); runStart = null; }
    }
    if (runStart) out.push(runStart);
    return out;
  }
  const nearestH = (x) => { let best = 0; for (const y of days) if (y.h != null && y.iso <= x.iso) best = y.h; return best; };
  const weekNo = (isok) => { const [Y,M,D] = isok.split('-').map(Number); const t = new Date(Y, M-1, D); const s = new Date(Y,0,4); return Math.floor(((t - s)/86400e3 + s.getDay() + 1) / 7) + 1; };
  const vnMonth = (isok) => ['Th1','Th2','Th3','Th4','Th5','Th6','Th7','Th8','Th9','Th10','Th11','Th12'][+isok.slice(5,7)-1] + ' ’' + isok.slice(2,4);
  function linkPath(x1,y1,x2,y2) {
    if (Math.abs(y2-y1) < 2) return `M${x1},${y1} L${x2-4},${y2}`;
    const mid = x2 > x1 ? Math.min(x1 + 14, x2 - 14) : x1 + 14;
    return `M${x1},${y1} H${mid} V${y2} H${x2-4}`;
  }
</script>

<div class="toolbar">
  <div class="group">
    <button class="btn btn-sm" on:click={() => zoom = Math.min(4, zoom*1.25)} title="Phóng to dòng thời gian">🔍+</button>
    <button class="btn btn-sm" on:click={() => zoom = Math.max(.25, zoom/1.25)} title="Thu nhỏ">🔍−</button>
    <span class="small muted" style="padding:0 8px">zoom {Math.round(zoom*100)}%</span>
  </div>
  <div class="group">
    <span class="chip chip-neutral"><i style="width:14px;height:8px;background:var(--bar);border-radius:2px;display:inline-block"></i>&nbsp;Task</span>
    <span class="chip chip-critical"><i style="width:14px;height:8px;background:var(--bar-critical);border-radius:2px;display:inline-block"></i>&nbsp;Đường găng</span>
    <span class="chip chip-done"><i style="width:14px;height:8px;background:var(--bar-progress);border-radius:2px;display:inline-block"></i>&nbsp;% hoàn thành</span>
    <span class="chip chip-neutral">◆ Baseline</span>
    <span class="chip chip-late">│ Hôm nay</span>
  </div>
  <span class="small muted grow right">Mẹo: Ctrl+cuộn = zoom · kéo thân thanh = shift (chuyển Manual) · kéo ◀▶ mép = duration · kéo vòng tròn cuối thanh sang task khác = link FS · double-click tên để sửa</span>
</div>

<div class="gantt row" style="align-items:stretch;gap:0" on:wheel={onWheel}>
  <!-- LEFT: WBS table -->
  <div class="left" bind:this={wrapL} on:scroll={syncScroll}>
    <table class="grid">
      <thead><tr>
        <th style="min-width:210px">WBS / Tên công việc</th>
        <th style="width:70px" title="Thời lượng theo ngày làm việc của lịch dự án">Thời lượng</th>
        <th style="width:86px" title="Ngày bắt đầu (start)">Bắt đầu</th>
        <th style="width:86px" title="Ngày hoàn thành (finish)">Hoàn thành</th>
        <th style="width:56px" title="% Complete">%</th>
        <th style="width:96px" title="Tổng chi phí = Σ assignment + fixed + material">Chi phí</th>
      </tr></thead>
      <tbody>
        {#each rows as { t, depth } (t.id_uid)}
          <tr data-uid={t.id_uid} class:selected={$selectedUid === t.id_uid} class:critical={t.critical}
              on:click={() => selectedUid.set(t.id_uid)}
              class:manual-row={t.is_manual}>
            <td>
              <span class="wbs num">{t.wbs}</span>
              {#if editingUid === t.id_uid}
                <input class="cell" bind:value={editVal} on:blur={() => commitName(t)}
                       on:keydown={(e) => e.key === 'Enter' && commitName(t)} autofocus />
              {:else}
                <span class:name on:dblclick={() => { editingUid = t.id_uid; editVal = t.name; }}
                      title="{t.name}{t.is_manual ? ' · Manual scheduling' : ''}{t.is_milestone ? ' · Milestone' : ''}"
                      style:font-weight={t.is_summary ? 700 : 400}
                      style:padding-left="{depth * 16}px">{t.name}</span>
                {#if t.is_manual}<span class="chip chip-warn mini">Manual</span>{/if}
                {#if t.is_milestone}<span aria-label="milestone">◆</span>{/if}
              {/if}
            </td>
            <td class="num right">{durOf(t)}</td>
            <td class="num">{dateOf(t.start_h)}</td>
            <td class="num">{dateOf(t.finish_h)}</td>
            <td class="num right">{Math.round(t.percent_complete || 0)}</td>
            <td class="num right">{(t.total_cost||0).toLocaleString('vi-VN')}</td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  <!-- RIGHT: SVG timeline -->
  <div class="right-pane" bind:this={wrapR} on:scroll={syncScroll}>
    <svg width={chartW} height={HEAD_H + rows.length * ROW_H + 20} role="img" aria-label="Sơ đồ Gantt">
      <!-- header: tháng + tuần -->
      <g font-size="11" fill="var(--muted)">
        {#each monthTicks() as m}
          <text x={X(m.h)+4} y="16" font-weight="600" fill="var(--ink-2)">{m.label}</text>
          <line x1={X(m.h)} y1="0" x2={X(m.h)} y2={HEAD_H + rows.length*ROW_H} stroke="var(--line)" />
        {/each}
        {#each weekTicks() as w}
          <text x={X(w.h)+3} y="34">{w.label}</text>
          <line x1={X(w.h)} y1="24" x2={X(w.h)} y2={HEAD_H + rows.length*ROW_H} stroke="var(--soft)" />
        {/each}
        <!-- weekend/holiday shading -->
        {#each offDays() as o}
          <rect x={X(o.h)} y={HEAD_H} width={Math.max(1, pxh*(o.hours||cal?.dayHours||8))} height={rows.length*ROW_H} fill="#EEF2F7" opacity=".7"/>
        {/each}
      </g>

      <!-- today line -->
      <line x1={X(todayH)} y1={HEAD_H-6} x2={X(todayH)} y2={HEAD_H + rows.length*ROW_H} stroke="var(--today-line)" stroke-width="2" stroke-dasharray="4 3"/>
      <text x={X(todayH)+4} y={HEAD_H-8} font-size="11" fill="var(--today-line)" font-weight="600">Hôm nay</text>

      <!-- links -->
      {#each g.links as l (l.pred_uid + '-' + l.succ_uid)}
        {@const p = g.tasks.find(t => t.id_uid === l.pred_uid)}
        {@const s = g.tasks.find(t => t.id_uid === l.succ_uid)}
        {#if p && s}
          {@const ri = rows.findIndex(r => r.t.id_uid === l.pred_uid)}
          {@const rj = rows.findIndex(r => r.t.id_uid === l.succ_uid)}
          {#if ri >= 0 && rj >= 0}
            {@const x1 = X(p.finish_h)}
            {@const y1 = HEAD_H + ri*ROW_H + ROW_H/2}
            {@const x2 = X(s.start_h)}
            {@const y2 = HEAD_H + rj*ROW_H + ROW_H/2}
            <path d={linkPath(x1,y1,x2,y2)} fill="none"
                  stroke={p.critical && s.critical ? 'var(--bar-critical)' : 'var(--faint)'} stroke-width="1.4"
                  marker-end="url(#arr)" class="linkhit"
                  on:dblclick={() => unlink(l.pred_uid, l.succ_uid)}
                  on:mouseenter={(e) => showTip(e, `Link ${l.link_type}${l.lag_h ? ` lag ${round2(l.lag_h/(cal?.dayHours||8))}d` : ''}\nDouble-click để xoá`)}
                  on:mouseleave={hideTip} />
          {/if}
        {/if}
      {/each}

      <!-- bars -->
      {#each rows as { t, depth }, i (t.id_uid)}
        {@const y = HEAD_H + i*ROW_H}
        {#if blSnap && blSnap[t.id_uid]}
          {@const b = blSnap[t.id_uid]}
          <line x1={X(b.s)} y1={y+ROW_H-6} x2={X(b.f)} y2={y+ROW_H-6} stroke="var(--baseline-bar)" stroke-width="3" opacity=".85"/>
          <polygon points="{X(b.s)}, {y+ROW_H-6} {X(b.s)-4},{y+ROW_H-10} {X(b.s)-4},{y+ROW_H-2}" fill="var(--baseline-bar)"/>
        {/if}
        {#if t.is_milestone}
          <polygon points="{X(t.start_h)},{y+ROW_H/2-7} {X(t.start_h)+7},{y+ROW_H/2} {X(t.start_h)},{y+ROW_H/2+7} {X(t.start_h)-7},{y+ROW_H/2}"
            fill={t.critical ? 'var(--bar-critical)' : 'var(--bar-summary)'} class="bar"
            on:pointerdown={(e) => barDown(e, t, 'move')}
            on:mouseenter={(e) => showTip(e, `${t.name}\nMilestone · ${dateOf(t.start_h)}${t.critical ? '\n⚠ Trên đường găng' : ''}`)} on:mouseleave={hideTip}/>
        {:else if t.is_summary}
          <g class="bar" on:pointerdown={(e) => barDown(e, t, 'move')}
             on:mouseenter={(e) => showTip(e, `${t.name} (Summary)\n${dateOf(t.start_h)} → ${dateOf(t.finish_h)}\nΣ work ${durOf({duration_h: g.tasks.filter(x=>x.parent_uid===t.id_uid).reduce((s,x)=>s+(+x.duration_h||0),0))}`)}
             on:mouseleave={hideTip}>
            <rect x={X(t.start_h)} y={y+ROW_H/2-4} width={Math.max(2, X(t.finish_h)-X(t.start_h))} height="8" fill="var(--bar-summary)" rx="1"/>
            <polygon points="{X(t.start_h)},{y+ROW_H/2+4} {X(t.start_h)+5},{y+ROW_H/2+10} {X(t.start_h)},{y+ROW_H/2+10}" fill="var(--bar-summary)"/>
            <polygon points="{X(t.finish_h)},{y+ROW_H/2+4} {X(t.finish_h)-5},{y+ROW_H/2+10} {X(t.finish_h)},{y+ROW_H/2+10}" fill="var(--bar-summary)"/>
          </g>
        {:else}
          {@const w = Math.max(2, X(t.finish_h) - X(t.start_h))}
          <g class="bar">
            <rect x={X(t.start_h)} y={y+(ROW_H-BAR_H)/2} width={w} height={BAR_H} rx="3"
                  fill={t.critical ? 'var(--bar-critical)' : 'var(--bar)'}
                  on:pointerdown={(e) => barDown(e, t, 'move')}
                  on:mouseenter={(e) => showTip(e, [
                    t.name,
                    `${dateOf(t.start_h)} → ${dateOf(t.finish_h)} (${durOf(t)})`,
                    `Work ${round2((t.work_h||0)/(cal?.dayHours||8))}d · Float ${round2((t.total_float_h||0)/(cal?.dayHours||8))}d`,
                    `Cost ${(t.total_cost||0).toLocaleString('vi-VN')} ₫`,
                    t.critical ? '⚠ CRITICAL PATH — float = 0' : '',
                    t.is_manual ? '✎ Manual — không tự động dịch khi update' : '',
                  ].filter(Boolean).join('\n'))} on:mouseleave={hideTip} />
            {#if t.percent_complete > 0}
              <rect x={X(t.start_h)} y={y+(ROW_H-BAR_H)/2} width={w * Math.min(1,(t.percent_complete||0)/100)} height={BAR_H} rx="3" fill="var(--bar-progress)" pointer-events="none"/>
            {/if}
            <!-- handles -->
            <rect x={X(t.start_h)-3} y={y+(ROW_H-BAR_H)/2} width="6" height={BAR_H} rx="2" fill="#fff" stroke="var(--line-strong)" class="handle"
                  on:pointerdown={(e) => barDown(e, t, 'left')} title="Kéo để đổi ngày bắt đầu"/>
            <rect x={X(t.finish_h)-3} y={y+(ROW_H-BAR_H)/2} width="6" height={BAR_H} rx="2" fill="#fff" stroke="var(--line-strong)" class="handle"
                  on:pointerdown={(e) => barDown(e, t, 'right')} title="Kéo để đổi thời lượng"/>
            <circle cx={X(t.finish_h)+6} cy={y+ROW_H/2} r="4.5" fill="var(--brand)" class="linkdot"
                    on:pointerdown={(e) => startLink(e, t)} title="Kéo sang task khác để tạo link FS"/>
          </g>
        {/if}
      {/each}

      <defs>
        <marker id="arr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
          <path d="M0,0 L10,5 L0,10 z" fill="context-stroke"/>
        </marker>
      </defs>
    </svg>
  </div>
</div>

{#if tip}
  <div class="tooltip" style="position:fixed;left:{tip.x}px;top:{tip.y - 12}px;transform:translate(-50%,-100%)">{tip.text}</div>
{/if}

<style>
  .gantt { border: 1px solid var(--line); border-radius: var(--r-md); overflow: hidden; background: var(--paper); height: calc(100vh - 230px); min-height: 420px; }
  .left { width: 620px; min-width: 460px; overflow-y: auto; border-right: 1px solid var(--line); background: var(--paper); }
  .right-pane { flex: 1; overflow: auto; background: #FCFDFE; position: relative; }
  .left thead th { top: 0; }
  .wbs { color: var(--faint); font-size: 11px; margin-right: 6px; }
  tr.manual-row .name::after { content: ' ✎'; color: var(--warn); }
  .bar { cursor: grab; } .bar:active { cursor: grabbing; }
  .handle { cursor: ew-resize; opacity: 0; transition: opacity var(--t-fast); }
  .bar:hover .handle { opacity: 1; }
  .linkdot { cursor: crosshair; opacity: 0; } .bar:hover .linkdot { opacity: 1; }
  .linkhit { cursor: pointer; }
  .mini { padding: 0 6px; font-size: 10px; margin-left: 6px; }
  td .name { display: inline-block; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; cursor: text; }
</style>
