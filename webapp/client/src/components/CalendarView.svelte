<script>
  /** Calendar — lịch tháng dự án + quản lý ngày nghỉ (exception calendar như ProjectLibre). */
  import { graph, mutate, toast } from '../lib/store.js';
  import { iso } from '../lib/engine.js';

  $: g = $graph;
  $: cal = g?._cal;
  let month = (g?.project?.start_date || new Date().toISOString()).slice(0, 7); // YYYY-MM
  let newHol = '';

  $: cells = (() => {
    if (!cal) return [];
    const [Y, M] = month.split('-').map(Number);
    const first = new Date(Y, M-1, 1), startDow = (first.getDay()+6)%7;
    const dim = new Date(Y, M, 0).getDate();
    const out = []; for (let i=0;i<startDow;i++) out.push(null);
    for (let d=1; d<=dim; d++) out.push(iso(new Date(Y, M-1, d)));
    return out;
  })();
  $: holidays = (() => { try { return typeof g?.project?.calendar_json==='string'?JSON.parse(g.project.calendar_json||'[]'):(g?.project?.holidays||[]); } catch { return []; } })();
  $: workDays = (() => { try { return typeof g?.project?.work_week_days==='string'?JSON.parse(g.project.work_week_days):[1,2,3,4,5]; } catch { return [1,2,3,4,5]; } })();

  function toggleHoliday(dayIso) {
    mutate('holiday', gg => {
      const arr = new Set(holidays);
      arr.has(dayIso) ? arr.delete(dayIso) : arr.add(dayIso);
      gg.project.calendar_json = JSON.stringify([...arr].sort());
    });
  }
  function addHoliday() { if (/^\d{4}-\d{2}-\d{2}$/.test(newHol)) { if(!holidays.includes(newHol)) toggleHoliday(newHol); newHol=''; } else toast('Ngày phải định dạng YYYY-MM-DD','err'); }
  function setWorkDay(dow) {
    mutate('wd', gg => {
      const s = new Set(workDays); s.has(dow) ? s.delete(dow) : s.add(dow);
      gg.project.work_week_days = JSON.stringify([...s].sort());
    });
  }
  const DOW = ['T2','T3','T4','T5','T6','T7','CN'];
  const isOff = (d) => d && (holidays.includes(d) || !workDays.includes(((new Date(d+'T00:00:00').getDay()+6)%7)+1));
  function shift(m, delta) { const [Y,M]=m.split('-').map(Number); const dt=new Date(Y,M-1+delta,1); month=`${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`; }
</script>

<div class="split two">
  <section>
    <div class="toolbar">
      <button class="btn btn-sm" on:click={() => shift(month,-1)}>←</button>
      <h3 style="margin:0">Tháng {month.split('-').reverse().join('/')}</h3>
      <button class="btn btn-sm" on:click={() => shift(month,1)}>→</button>
      <span class="grow"></span>
      <div class="row"><input class="input" style="width:150px" type="date" bind:value={newHol} /><button class="btn btn-sm btn-primary" on:click={addHoliday}>+ Ngày nghỉ</button></div>
    </div>
    <div class="cal-grid card" role="grid" aria-label="Lịch làm việc dự án">
      {#each DOW as d}<div class="cal-dow">{d}</div>{/each}
      {#each cells as c, i (i)}
        {#if c}
          <button class="cal-cell" class:off={isOff(c)} on:click={() => toggleHoliday(c)}
                  title={isOff(c)?'Ngày NGHỈ — click để bật lại làm việc':'Ngày LÀM VIỆC — click để đặt nghỉ (exception)'}>
            <span class="num">{c.slice(8)}</span>
            {#if isOff(c)}<span class="offtag">NGHỈ</span>{/if}
          </button>
        {:else}<div class="cal-cell blank"></div>{/if}
      {/each}
    </div>
    <p class="small muted mt2">Click một ngày để bật/tắt <b>exception calendar</b> (như Change Working Time trong ProjectLibre). Ngày nghỉ được loại khỏi trục tính toán của mọi task.</p>
  </section>

  <aside>
    <div class="card">
      <h3>Tuần làm việc chuẩn</h3>
      <div class="row wrap" style="gap:6px">
        {#each DOW as d, i}<button class="btn btn-sm" aria-pressed={workDays.includes(i+1)}
          style={workDays.includes(i+1) ? 'background:var(--brand);color:#fff;border-color:var(--brand)' : ''}
          on:click={() => setWorkDay(i+1)}>{d}</button>{/each}
      </div>
      <div class="field mt4"><label>Giờ làm việc / ngày</label>
        <input class="input num" type="number" min="1" max="24" value={g?.project?.work_day_hours || 8}
               on:change={(e) => mutate('dh', gg => gg.project.work_day_hours = Math.min(24, Math.max(1, +e.target.value)))} /></div>
    </div>
    <div class="card mt4">
      <h3>Danh sách ngày nghỉ</h3>
      {#if holidays.length === 0}<p class="small muted">Chưa có exception nào.</p>{/if}
      <div class="row wrap" style="gap:6px">
        {#each holidays as h (h)}
          <span class="chip chip-late">{h} <button class="x" on:click={() => toggleHoliday(h)} aria-label="Bỏ ngày nghỉ">×</button></span>
        {/each}
      </div>
      <p class="small muted mt2">Gợi ý lễ VN 2026: Giỗ Tổ 01/03(al≈26/04), 30/4–1/5, Quốc khánh 2/9 (1–5/9 diện hoán vị), Tết dương lịch 1/1.</p>
    </div>
  </aside>
</div>

<style>
  .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; padding: var(--s3); }
  .cal-dow { text-align:center; font-size: var(--fs-12); font-weight: 700; color: var(--muted); padding: 4px 0; }
  .cal-cell { position: relative; border: 1px solid var(--line); background: var(--paper); border-radius: var(--r-sm);
    min-height: 64px; display:flex; justify-content:flex-end; align-items:flex-start; padding:6px; transition: all var(--t-fast); }
  .cal-cell:hover { border-color: var(--brand); box-shadow: var(--sh-1); }
  .cal-cell.off { background: var(--late-bg); border-color: #FED7AA; }
  .cal-cell.blank { border: none; background: transparent; }
  .offtag { position:absolute; left:6px; bottom:6px; font-size:10px; font-weight:700; color:#9A3412; letter-spacing:.04em; }
  .x { border:none;background:none;color:inherit;font-weight:700;margin-left:4px;padding:0 2px; }
</style>
