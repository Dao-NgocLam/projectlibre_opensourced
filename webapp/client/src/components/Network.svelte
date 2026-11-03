<script>
  /** Network diagram — PDM boxes (Precedence Diagram Method) kiểu ProjectLibre. */
  import { graph, linkTasks, unlink, selectedUid } from '../lib/store.js';

  $: g = $graph;
  $: leaves = g ? g.tasks.filter(t => !t.is_summary) : [];
  const BW = 170, BH = 92, GX = 60, GY = 40;

  // layout: xếp lớp theo độ sâu phụ thuộc (longest-path layering)
  $: layers = (() => {
    const dep = new Map(); leaves.forEach(t => dep.set(t.id_uid, []));
    g.links.forEach(l => { if (dep.has(l.succ_uid)) dep.get(l.succ_uid).push(l.pred_uid); });
    const depth = new Map(); let guard = 0;
    const calc = (id) => { if (depth.has(id)) return depth.get(id);
      if (guard++ > 5000) return 0;
      const d = Math.max(0, ...((dep.get(id)||[]).map(calc)).concat([(dep.get(id)||[]).length? -1:0]));
      depth.set(id, ((dep.get(id)||[]).length ? d+1 : 0)); return depth.get(id); };
    leaves.forEach(t => calc(t.id_uid));
    const byL = new Map();
    leaves.forEach(t => { const d = depth.get(t.id_uid)||0; if (!byL.has(d)) byL.set(d, []); byL.get(d).push(t); });
    return [...byL.entries()].sort((a,b)=>a[0]-b[0]).map(([d,list]) => ({ d, list }));
  })();
  $: pos = new Map();
  $: layers.forEach(({list}, li) => list.forEach((t, i) => pos.set(t.id_uid, { x: li*(BW+GX)+20, y: i*(BH+GY)+20 })));
  $: W = Math.max(800, layers.length*(BW+GX)+40);
  $: H = Math.max(400, Math.max(...layers.map(l=>l.list.length),1)*(BH+GY)+40);

  function anchor(p, side) { const b = pos.get(p); return b && { l:[b.x,b.y+BH/2], r:[b.x+BW,b.y+BH/2], t:[b.x+BW/2,b.y], bo:[b.x+BW/2,b.y+BH] }[side]; }
  let picking = null; // uid đang chờ chọn successor để link
  function boxClick(uid) {
    if (picking == null) { selectedUid.set(uid); return; }
    if (picking !== uid) linkTasks(picking, uid, 'FS', 0);
    picking = null;
  }
</script>

<div class="toolbar">
  <h3 style="margin:0" class="grow">Sơ đồ mạng PDM</h3>
  {#if picking != null}
    <span class="chip chip-info">Đang nối từ task #{picking} — click task đích để tạo link FS · <a on:click|preventDefault={() => picking=null}>huỷ</a></span>
  {:else}
    <button class="btn btn-sm" on:click={() => $selectedUid && (picking = $selectedUid)} disabled={!$selectedUid}>🔗 Nối từ task đang chọn</button>
  {/if}
</div>

<div class="table-wrap net" style="max-height:calc(100vh - 260px)">
  <svg width={W} height={H}>
    <defs><marker id="narr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 z" fill="context-stroke"/></marker></defs>
    {#each g.links as l (l.pred_uid+'-'+l.succ_uid)}
      {#if pos.has(l.pred_uid) && pos.has(l.succ_uid)}
        {@const [x1,y1] = anchor(l.pred_uid,'r')}
        {@const [x2,y2] = anchor(l.succ_uid,'l')}
        <path d={`M${x1},${y1} C${x1+40},${y1} ${x2-40},${y2} ${x2},${y2}`} fill="none"
              stroke={(g.tasks.find(t=>t.id_uid===l.pred_uid)?.critical && g.tasks.find(t=>t.id_uid===l.succ_uid)?.critical) ? 'var(--bar-critical)' : 'var(--faint)'}
              stroke-width="1.6" marker-end="url(#narr)" style="cursor:pointer"
              on:dblclick={() => unlink(l.pred_uid, l.succ_uid)}>
          <title>Link {l.link_type}{l.lag_h?` lag ${l.lag_h}h`:''} — double-click để xoá</title></path>
      {/if}
    {/each}
    {#each leaves as t (t.id_uid)}
      {@const p = pos.get(t.id_uid)}
      {#if p}
        <g transform={`translate(${p.x},${p.y})`} style="cursor:pointer" on:click={() => boxClick(t.id_uid)}
           opacity={picking === t.id_uid ? .5 : 1}>
          <rect width={BW} height={BH} rx="10" fill="var(--paper)"
                stroke={$selectedUid===t.id_uid ? 'var(--brand)' : t.critical ? 'var(--bar-critical)' : 'var(--line-strong)'}
                stroke-width={$selectedUid===t.id_uid || t.critical ? 2.5 : 1.4}/>
          <rect width={BW} height="26" rx="10" fill={t.critical ? 'var(--critical-bg)' : 'var(--surface)'} />
          <text x="10" y="17" font-size="12" font-weight="600" fill="var(--ink)">{t.wbs} · {t.name.length>18?t.name.slice(0,17)+'…':t.name}</text>
          <line x1="0" y1="26" x2={BW} y2="26" stroke="var(--line)"/>
          <text x="10" y="44" font-size="11" fill="var(--muted)">Start: {g._cal.toISO(t.start_h)}</text>
          <text x="10" y="60" font-size="11" fill="var(--muted)">Finish: {g._cal.toISO(t.finish_h)}</text>
          <text x="10" y="78" font-size="11" fill="var(--ink-2)" font-weight="600">{Math.round((t.duration_h||0)/(g._cal.dayHours))}d · {Math.round(t.percent_complete||0)}%</text>
          {#if t.critical}<text x={BW-14} y="78" font-size="11" fill="var(--critical)" font-weight="700">CP</text>{/if}
        </g>
      {/if}
    {/each}
  </svg>
</div>
<style>.net svg text { user-select:none; }</style>
