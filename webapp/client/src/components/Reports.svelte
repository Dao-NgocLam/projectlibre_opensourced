<script>
  /** Reports — EVM dashboard (BAC/PV/EV/AC/SV/CV/SPI/CPI/EAC/ETC/VAC) + S-curve + export. */
  import { graph, toast } from '../lib/store.js';
  import { round2 } from '../lib/engine.js';

  let data = null, loading = false, err = '';
  async function refresh() {
    loading = true; err = '';
    try {
      const tk = localStorage.getItem('dp_token');
      const res = await fetch(`/api/index.php?route=reports/evm&id=${$graph.project.id}`,
        { headers: { Authorization: 'Bearer ' + tk } });
      const d = await res.json();
      if (!res.ok || d.error) throw new Error(d.error || d.hint || 'Lỗi tải báo cáo');
      data = d;
    } catch (e) { err = e.message; }
    finally { loading = false; }
  }
  function onEnter() { refresh(); }
  $: if ($graph && data === null && !loading && !err) { /* auto-load khi mở tab */ }

  const money = v => v == null ? '—' : Number(v).toLocaleString('vi-VN') + ' ₫';
  const num = v => v == null ? '—' : (+v).toFixed(3);
  const chipBy = (v, goodUp=true) => v == null ? 'chip-neutral'
    : goodUp ? (v >= 1 ? 'chip-done' : v >= .9 ? 'chip-warn' : 'chip-critical')
             : (v >= 0 ? 'chip-done' : v > -0.05*1e6 ? 'chip-warn' : 'chip-critical');

  // S-curve client-side từ baseline snapshot + %complete
  $: curve = (() => {
    if (!$graph) return null;
    const bl = $graph.baselines?.[$graph.baselines.length-1]; if (!bl) return null;
    const cal = $graph._cal, dayH = cal.dayHours;
    const weeks = []; const maxF = Math.max(...Object.values(bl.snapshotObj).map(b=>b.f), 1);
    for (let h = 0; h <= maxF; h += dayH*5) { // mỗi tuần làm việc 1 điểm
      const pv = Object.entries(bl.snapshotObj).reduce((s,[uid,b]) => {
        const frac = b.f<=b.s?1:Math.max(0,Math.min(1,(h-b.s)/(b.f-b.s))); return s + (b.c||0)*frac; }, 0);
      const t = $graph.tasks.find(x=>x.id_uid==uid0(h)); void t;
      weeks.push({ h, pv, label: cal.toISO(h) });
    }
    const ev = Object.entries(bl.snapshotObj).reduce((s,[uid,b]) => {
      const t = $graph.tasks.find(x=>x.id_uid==+uid); return s + (b.c||0)*((t?.percent_complete||0)/100); }, 0);
    const ac = $graph.tasks.reduce((s,t)=>s+(t.is_summary?0:(t.total_cost||0)*(t.percent_complete||0)/100), 0);
    return { weeks, ev, ac, maxPv: Math.max(...weeks.map(w=>w.pv), 1) };
    function uid0(){return 0;}
  })();

  function printReport() { window.print(); }
</script>

<div class="toolbar">
  <h3 style="margin:0" class="grow">Reports — Earned Value Management</h3>
  <button class="btn btn-sm" on:click={onEnter} disabled={loading}>{loading?'Đang tính…':'⟳ Tính lại từ server'}</button>
  <button class="btn btn-sm" on:click={printReport}>🖨 In / PDF</button>
</div>

{#if err}<div class="chip chip-critical mb2">⛔ {err}</div>{/if}

{#if !$graph?.baselines?.length}
  <div class="card">
    <h3>Chưa có Baseline</h3>
    <p class="muted">EVM so sánh <b>hiện tại vs kế hoạch đã chốt</b>. Hãy hoàn tất kế hoạch → bấm <b>📌 Baseline</b> trên topbar, sau đó cập nhật tiến độ trong Work Log rồi quay lại đây.</p>
  </div>
{:else}
  <!-- KPI cards -->
  <section class="metrics mb4">
    {#each [
      ['BAC', money(data?.summary.BAC ?? $graph.tasks.filter(t=>!t.is_summary).reduce((s,t)=>s+(t.total_cost||0),0)), 'Tổng dự toán (Budget at Completion)'],
      ['PV/BCWS', money(data?.summary.PV), 'Giá trị kế hoạch tới status date'],
      ['EV/BCWP', money(data?.summary.EV), 'Giá trị đạt được (%comp × baseline cost)'],
      ['AC/ACWP', money(data?.summary.AC), 'Chi phí thực tế'],
      ['SPI', num(data?.summary.SPI), 'EV ÷ PV — hiệu quả tiến độ'],
      ['CPI', num(data?.summary.CPI), 'EV ÷ AC — hiệu quả chi phí'],
      ['EAC', money(data?.summary.EAC), 'BAC ÷ CPI — dự báo cuối'],
      ['ETC', money(data?.summary.ETC), 'EAC − AC — còn phải chi'],
      ['VAC', money(data?.summary.VAC), 'BAC − EAC — lệch ngân sách'],
    ] as [k, v, tip] (k)}
      <div class="card metric tooltip-host" title={tip}>
        <div class="k">{k}{#if k==='SPI'||k==='CPI'}<span class="chip {chipBy(parseFloat(String(v)))}" style="padding:0 6px">{parseFloat(String(v))>=1?'✓ On track':parseFloat(String(v))>=0.9?'◐ Slipping':'⚠ Critical'}</span>{/if}</div>
        <div class="v num">{v}</div>
        <div class="sub">{tip.split('—')[1]?.trim() || tip}</div>
      </div>
    {/each}
  </section>

  <div class="split two">
    <section class="card">
      <h3>S-Curve (PV theo đường cơ sở)</h3>
      {#if curve}
        <svg viewBox="0 0 400 180" style="width:100%" role="img" aria-label="Đường cong kế hoạch tích luỹ">
          <path d={curve.weeks.map((w,i)=>`${i?'L':'M'}${(i/(curve.weeks.length-1||1))*380+10},${160-(w.pv/curve.maxPv)*140}`).join(' ')}
                fill="none" stroke="var(--c2)" stroke-width="2"/>
          <line x1="10" y1="{160-(curve.ev/curve.maxPv)*140}" x2="390" y2="{160-(curve.ev/curve.maxPv)*140}" stroke="var(--c1)" stroke-dasharray="5 4" stroke-width="2"/>
          <line x1="10" y1="{160-(curve.ac/curve.maxPv)*140}" x2="390" y2="{160-(curve.ac/curve.maxPv)*140}" stroke="var(--c4)" stroke-dasharray="2 4" stroke-width="2"/>
          <text x="12" y="{160-(curve.ev/curve.maxPv)*140-4}" font-size="10" fill="var(--c1)">EV hiện tại</text>
          <text x="12" y="{160-(curve.ac/curve.maxPv)*140+12}" font-size="10" fill="var(--c4)">AC hiện tại</text>
        </svg>
        <p class="small muted">Đậm xanh dương = PV tích luỹ theo baseline; nét đứt = EV/AC quy đổi. Khoảng cách trực quan chính là SV/CV.</p>
      {:else}<p class="small muted">Re-fetch để lấy số liệu chi tiết từ server.</p>{/if}
    </section>

    <section class="card">
      <h3>Công thức đang áp dụng</h3>
      <table class="grid"><tbody>
        {#each Object.entries(data?.summary?.formulas || {SV:'EV − PV',CV:'EV − AC',SPI:'EV / PV',CPI:'EV / AC',EAC:'BAC / CPI',ETC:'EAC − AC',VAC:'BAC − EAC'}) as [k,f] (k)}
          <tr><td><b>{k}</b></td><td class="muted">{f}</td></tr>
        {/each}
      </tbody></table>
      <p class="small muted mt2">Minh bạch: mọi con số đều suy ra từ dữ liệu hiển thị ở bảng dưới — không có giá trị "ẩn".</p>
    </section>
  </div>

  {#if data?.tasks?.length}
    <section class="mt4">
      <h3>Chi tiết theo công việc</h3>
      <div class="table-wrap" style="max-height:380px"><table class="grid">
        <thead><tr><th>ID</th><th>BAC</th><th>PV</th><th>EV</th><th>AC</th><th>SV</th><th>CV</th><th>SPI</th><th>CPI</th></tr></thead>
        <tbody>{#each data.tasks as t (t.uid)}
          <tr><td class="num">{t.uid}</td><td class="num right">{money(t.bac)}</td><td class="num right">{money(t.pv)}</td>
            <td class="num right">{money(t.ev)}</td><td class="num right">{money(t.ac)}</td>
            <td class="num right" style:color={t.sv>=0?'var(--done)':'var(--critical)'}>{money(t.sv)}</td>
            <td class="num right" style:color={t.cv>=0?'var(--done)':'var(--critical)'}>{money(t.cv)}</td>
            <td class="num right">{num(t.spi)}</td><td class="num right">{num(t.cpi)}</td></tr>
        {/each}</tbody>
      </table></div>
    </section>
  {/if}
{/if}

<style>:global(@media print) { .topbar,.sidebar,.statusbar,.toolbar button { display:none!important } .main{grid-template-columns:1fr} }</style>
