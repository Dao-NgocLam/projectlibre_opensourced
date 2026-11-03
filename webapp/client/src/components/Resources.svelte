<script>
  /** Resources — pool + assignment matrix + over-allocation (đúng tinh thần Resource Sheet/Usage của ProjectLibre). */
  import { graph, addResource, assignRes, unassign, mutate, selectedUid } from '../lib/store.js';
  import { round2, recomputeWork } from '../lib/engine.js';

  $: g = $graph;
  $: leaves = g ? g.tasks.filter(t => !t.is_summary) : [];
  $: overalloc = g ? recomputeWork(structuredClone(g.tasks), g.resources, structuredClone(g.assignments), g._cal).overalloc : new Map();

  function updRes(uid, key, val) {
    mutate('res', gg => { const r = gg.resources.find(x => x.id_uid === uid); if (r) r[key] = val; });
  }
  function updUnits(taskUid, resUid, val) {
    mutate('units', gg => { const a = gg.assignments.find(x => x.task_uid === taskUid && x.res_uid === resUid); if (a) a.units_pct = Math.max(0, +val||0); });
  }
</script>

<div class="split two">
  <section>
    <div class="toolbar">
      <h3 style="margin:0" class="grow">Resource Pool</h3>
      <button class="btn btn-sm" on:click={() => addResource('work')}>+ Nguồn lực</button>
      <button class="btn btn-sm" on:click={() => addResource('material')}>+ Vật tư</button>
    </div>
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          <th>ID</th><th>Tên</th><th>Loại</th>
          <th title="Max units % khả dụng tuần suất">Max %</th>
          <th title="Đơn giá giờ chuẩn (₫/h hoặc $/h)">Đơn giá/h</th>
          <th title="Phí sử dụng một lần">Cost/use</th>
          <th></th>
        </tr></thead>
        <tbody>
          {#each g.resources as r (r.id_uid)}
            <tr class:selected={$selectedUid === null && false}>
              <td class="num">{r.id_uid}</td>
              <td><input class="cell" value={r.name} on:change={(e)=>updRes(r.id_uid,'name',e.target.value)} /></td>
              <td><span class="chip {r.resource_type==='work'?'chip-info':'chip-neutral'}">{r.resource_type==='work'?'Người/Công cụ':'Vật tư'}</span></td>
              <td><input class="cell num" type="number" min="0" max="1000" value={r.max_units_pct} style="width:64px"
                         on:change={(e)=>updRes(r.id_uid,'max_units_pct',+e.target.value)} /></td>
              <td><input class="cell num" type="number" min="0" value={r.std_rate_hr} style="width:90px"
                         on:change={(e)=>updRes(r.id_uid,'std_rate_hr',+e.target.value)} /></td>
              <td><input class="cell num" type="number" min="0" value={r.cost_per_use} style="width:80px"
                         on:change={(e)=>updRes(r.id_uid,'cost_per_use',+e.target.value)} /></td>
              <td class="right">
                {#if overalloc.has(r.id_uid)}
                  <span class="chip chip-warn" title={`Over-allocation ${overalloc.get(r.id_uid).length} ngày`}>⚠ {overalloc.get(r.id_uid).length}d</span>
                {/if}
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
    <p class="small muted mt2">Chỉ báo ⚠ đếm số <b>ngày làm việc</b> mà tổng units giao vượt Max %. Dùng nút <b>Leveling</b> trên topbar để tự động dồn delay vào assignment.</p>
  </section>

  <section>
    <h3>Assignment ma trận (task chọn ở Gantt, hoặc mọi task)</h3>
    <div class="table-wrap" style="max-height:420px">
      <table class="grid">
        <thead><tr><th>Công việc</th><th>Nguồn lực</th><th title="Units %">Units %</th><th title="Work = Duration × Units">Work (h)</th><th></th></tr></thead>
        <tbody>
          {#each g.assignments as a (`${a.task_uid}-${a.res_uid}`)}
            {@const t = g.tasks.find(x => x.id_uid === a.task_uid)}
            {@const r = g.resources.find(x => x.id_uid === a.res_uid)}
            {#if t && r}
              <tr>
                <td>{t.wbs} {t.name}</td>
                <td>{r.name}</td>
                <td><input class="cell num" type="number" min="0" max="500" value={a.units_pct} style="width:64px"
                           on:change={(e)=>updUnits(a.task_uid, a.res_uid, e.target.value)} /></td>
                <td class="num right">{round2(a.work_h||0)}</td>
                <td class="right"><button class="btn btn-icon btn-sm" aria-label="Bỏ gán" title="Bỏ gán nguồn lực"
                        on:click={() => unassign(a.task_uid, a.res_uid)}>✕</button></td>
              </tr>
            {/if}
          {/each}
        </tbody>
      </table>
    </div>
    {#if leaves.length && g.resources.length}
      <div class="card mt2 row wrap" style="gap:8px">
        <span class="small muted grow">Gán nhanh cho task đang chọn:</span>
        {#each g.resources as r (r.id_uid)}
          <button class="btn btn-sm" disabled={!$selectedUid}
                  on:click={$selectedUid && assignRes($selectedUid, r.id_uid, 100)}>+ {r.name}</button>
        {/each}
      </div>
    {/if}
  </section>
</div>
