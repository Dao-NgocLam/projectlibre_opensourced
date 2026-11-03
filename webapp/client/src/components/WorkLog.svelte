<script>
  /** Work Log — kiểu ProjectLibre: bảng task × resource, nhập %Complete / actual work theo kỳ. */
  import { graph, updateProgress, mutate } from '../lib/store.js';
  import { round2 } from '../lib/engine.js';

  let period = 'week'; // week | month
  $: g = $graph;
  $: leaves = g ? g.tasks.filter(t => !t.is_summary) : [];
  $: resOf = (uid) => g.assignments.filter(a => a.task_uid === uid).map(a => g.resources.find(r => r.id_uid === a.res_uid)?.name).filter(Boolean).join(', ');

  function setActualWork(uid, val) {
    mutate('actual', gg => { const t = gg.tasks.find(x => x.id_uid === uid); if (!t) return;
      t.actual_work_h = Math.max(0, +val || 0);
      if (+t.work_h > 0) t.percent_complete = round2(Math.min(100, t.actual_work_h / t.work_h * 100));
    });
  }
  function exportCsv() {
    const head = ['WBS','Task','Bắt đầu','Hoàn thành','%Complete','ActualWork(h)','Work(h)','Cost','Resources'];
    const lines = [head.join(',')].concat(leaves.map(t => [
      t.wbs, `"${(t.name||'').replace(/"/g,'""')}"`, g._cal.toISO(t.start_h), g._cal.toISO(t.finish_h),
      Math.round(t.percent_complete||0), t.actual_work_h||0, round2(t.work_h||0), t.total_cost||0, `"${resOf(t.id_uid)}"`,
    ].join(',')));
    const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'worklog.csv'; a.click();
  }
</script>

<div class="toolbar">
  <h3 style="margin:0" class="grow">Work Log — cập nhật tiến độ</h3>
  <button class="btn btn-sm" on:click={exportCsv}>⬇ Export CSV</button>
</div>
<p class="small muted mb2">Nhập <b>% hoàn thành</b> hoặc <b>giờ thực tế</b> — hệ tự suy ra giá trị còn lại (W = U×D). Summary tự roll-up theo trọng số work.</p>

<div class="table-wrap" style="max-height:calc(100vh - 300px)">
  <table class="grid">
    <thead><tr>
      <th>WBS</th><th style="min-width:220px">Công việc</th><th>Nguồn lực</th>
      <th title="Work kế hoạch (giờ)">Work (h)</th>
      <th title="% Complete — nhập trực tiếp">% Comp.</th>
      <th title="Giờ đã làm thực tế">Actual (h)</th>
      <th title="Còn lại = Work − Actual">Remaining (h)</th>
      <th>Trạng thái</th>
    </tr></thead>
    <tbody>
      {#each leaves as t (t.id_uid)}
        {@const rem = Math.max(0, round2((+t.work_h||0) - (+t.actual_work_h||0)))}
        <tr>
          <td class="num muted">{t.wbs}</td>
          <td>{t.name}{#if t.is_milestone} ◆{/if}</td>
          <td class="small">{resOf(t.id_uid) || '—'}</td>
          <td class="num right">{round2(t.work_h||0)}</td>
          <td><input class="cell num" type="number" min="0" max="100" value={Math.round(t.percent_complete||0)}
                     on:change={(e) => updateProgress(t.id_uid, e.target.value)} style="width:70px" /></td>
          <td><input class="cell num" type="number" min="0" step="0.5" value={t.actual_work_h||0}
                     on:change={(e) => setActualWork(t.id_uid, e.target.value)} style="width:80px" /></td>
          <td class="num right">{rem}</td>
          <td>{@const p = +t.percent_complete||0}
            {#if p >= 100}<span class="chip chip-done">✓ Hoàn thành</span>
            {:else if p > 0}<span class="chip chip-info">◐ Đang làm</span>
            {:else}<span class="chip chip-neutral">○ Chưa bắt đầu</span>{/if}
          </td>
        </tr>
      {/each}
    </tbody>
  </table>
</div>
