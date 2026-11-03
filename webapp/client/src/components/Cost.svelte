<script>
  /** Cost view — bảng chi phí task + phân bổ theo nguồn lực, total roll-up minh bạch. */
  import { graph, mutate } from '../lib/store.js';
  import { round2 } from '../lib/engine.js';

  $: g = $graph;
  $: leaves = g ? g.tasks.filter(t => !t.is_summary) : [];
  $: byRes = (() => {
    const m = new Map();
    g?.assignments.forEach(a => {
      const cost = (+a.work_h||0) * (g.resources.find(r=>r.id_uid===a.res_uid)?.std_rate_hr||0);
      m.set(a.res_uid, (m.get(a.res_uid)||0) + cost);
    });
    return [...m.entries()].sort((x,y)=>y[1]-x[1]);
  })();
  $: grand = leaves.reduce((s,t)=>s+(+t.total_cost||0),0);
  $: maxRes = Math.max(1, ...byRes.map(([,v])=>v));

  function setFixed(uid, val) { mutate('fixcost', gg => { const t = gg.tasks.find(x=>x.id_uid===uid); if (t) t.fixed_cost = Math.max(0,+val||0); }); }
  function setMat(uid, val) { mutate('matcost', gg => { const t = gg.tasks.find(x=>x.id_uid===uid); if (t) t.material_cost = Math.max(0,+val||0); }); }
  const money = v => (v||0).toLocaleString('vi-VN');

  /* donut angles tính trực tiếp trong instance script */
  $: fixedSum = leaves.reduce((s,t)=>s+(+t.fixed_cost||0),0);
  $: matSum = leaves.reduce((s,t)=>s+(+t.material_cost||0),0);
  $: resSum = Math.max(0, grand - fixedSum - matSum);
  $: slices = [['Nguồn lực', resSum, 'var(--c2)'], ['Cố định', fixedSum, 'var(--c1)'], ['Vật tư', matSum, 'var(--c5)']];
  $: angles = (() => { let a = -Math.PI/2; return slices.map(([,v]) => { const frac = grand ? v/grand : 0; const a0 = a; a += frac*2*Math.PI; return [a0, a]; }); })();
  function arc(a0, a1) {
    const R = 52, cx = 60, cy = 60;
    const p = (x) => [cx + R*Math.cos(x), cy + R*Math.sin(x)];
    if (a1 - a0 >= 2*Math.PI - 1e-6) a1 = a0 + 2*Math.PI - 1e-4;
    const large = (a1-a0) > Math.PI ? 1 : 0;
    const [x0,y0]=p(a0), [x1,y1]=p(a1);
    return `M${cx},${cy} L${x0},${y0} A${R},${R} 0 ${large} 1 ${x1},${y1} Z`;
  }
</script>

<div class="split two">
  <section>
    <div class="toolbar"><h3 style="margin:0" class="grow">Chi phí theo công việc</h3>
      <span class="chip chip-info">Tổng dự toán: <b>&nbsp;{money(grand)} ₫</b></span></div>
    <p class="small muted mb2">Total = Σ(work × đơn giá nguồn lực) + Fixed cost + Material cost. Sửa trực tiếp ô nhập — engine tính lại tức thì.</p>
    <div class="table-wrap" style="max-height:calc(100vh - 320px)">
      <table class="grid">
        <thead><tr><th>WBS</th><th>Công việc</th><th title="Σ assignment cost">Resource (₫)</th>
          <th title="Chi phí cố định nhập tay">Fixed (₫)</th><th title="Vật tư">Material (₫)</th><th>Total (₫)</th></tr></thead>
        <tbody>
          {#each leaves as t (t.id_uid)}
            {@const resCost = Math.max(0, round2((t.total_cost||0) - (t.fixed_cost||0) - (t.material_cost||0)))}
            <tr>
              <td class="num muted">{t.wbs}</td><td>{t.name}</td>
              <td class="num right">{money(resCost)}</td>
              <td><input class="cell num" type="number" min="0" value={t.fixed_cost||0} on:change={(e)=>setFixed(t.id_uid,e.target.value)} /></td>
              <td><input class="cell num" type="number" min="0" value={t.material_cost||0} on:change={(e)=>setMat(t.id_uid,e.target.value)} /></td>
              <td class="num right"><b>{money(t.total_cost)}</b></td>
            </tr>
          {/each}
          <tr style="background:var(--surface)"><td colspan="5"><b>Tổng cộng</b></td><td class="num right"><b>{money(grand)} ₫</b></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <aside>
    <div class="card">
      <h3>Phân bổ theo nguồn lực</h3>
      {#if byRes.length === 0}<p class="small muted">Chưa có assignment nào có chi phí.</p>{/if}
      {#each byRes as [rid, cost] (rid)}
        {@const r = g.resources.find(x=>x.id_uid===rid)}
        <div class="bar-row">
          <span class="lbl" title={r?.name}>{r?.name || rid}</span>
          <div class="track"><div class="fill" style="width:{Math.round(cost/maxRes*100)}%"></div></div>
          <span class="num val">{money(cost)} ₫</span>
        </div>
      {/each}
    </div>
    <div class="card mt4">
      <h3>Cơ cấu chi phí</h3>
      <svg viewBox="0 0 120 120" width="150" height="150" style="display:block;margin:auto" role="img" aria-label="Biểu đồ tròn cơ cấu chi phí">
        {#each slices as [lab, v, col], i}
          {@const [a0, a1] = angles[i]}
          {#if v > 0}<path d={arc(a0,a1)} fill={col}><title>{lab}: {money(v)} ₫ ({grand?Math.round(v/grand*100):0}%)</title></path>{/if}
        {/each}
        <circle cx="60" cy="60" r="30" fill="var(--paper)"/>
        <text x="60" y="57" text-anchor="middle" font-size="9" fill="var(--muted)">Tổng</text>
        <text x="60" y="69" text-anchor="middle" font-size="10" font-weight="700" fill="var(--ink)">{grand>=1e9?(grand/1e9).toFixed(1)+'B':grand>=1e6?(grand/1e6).toFixed(1)+'M':money(grand)}</text>
      </svg>
      <div class="row wrap small mt2" style="gap:10px">
        <span><i class="sq" style="background:var(--c2)"></i> Nguồn lực {grand?Math.round(resSum/grand*100):0}%</span>
        <span><i class="sq" style="background:var(--c1)"></i> Cố định {grand?Math.round(fixedSum/grand*100):0}%</span>
        <span><i class="sq" style="background:var(--c5)"></i> Vật tư {grand?Math.round(matSum/grand*100):0}%</span>
      </div>
    </div>
  </aside>
</div>

<style>
  .bar-row { display:grid; grid-template-columns: 110px 1fr 90px; gap:8px; align-items:center; margin-bottom:8px; font-size:var(--fs-12); }
  .lbl { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .track { height:10px; background:var(--soft); border-radius:999px; overflow:hidden; }
  .fill { height:100%; background:linear-gradient(90deg,var(--brand),#14B8A6); border-radius:999px; transition:width var(--t-panel); }
  .val { text-align:right; color:var(--muted); }
  .sq { display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:4px;vertical-align:-1px; }
</style>
