<script>
  import { onMount, onDestroy } from 'svelte';
  import {
    graph, openProject, saveState, flushSave, undo, redo, canUndo, canRedo,
    currentView, selectedUid, addTask, deleteTask, autoLevel, saveBaseline, toast, calOf, makeInvite
  } from '../lib/store.js';
  import Gantt from '../components/Gantt.svelte';
  import WorkLog from '../components/WorkLog.svelte';
  import Resources from '../components/Resources.svelte';
  import Network from '../components/Network.svelte';
  import CalendarView from '../components/CalendarView.svelte';
  import Cost from '../components/Cost.svelte';
  import Reports from '../components/Reports.svelte';

  export let projectId;
  export let back;
  let loading = true, error = '';
  let showShare = false, inviteCode = '', shareBusy = false;

  const VIEWS = [
    ['gantt', 'Gantt', 'Sơ đồ thanh tiến độ + bảng WBS'],
    ['worklog', 'Work Log', 'Nhập % hoàn thành theo task/resource'],
    ['resources', 'Resources', 'Resource pool, assignment, over-allocation'],
    ['network', 'Network', 'Sơ đồ PDM (mạng công việc)'],
    ['calendar', 'Calendar', 'Lịch làm việc & ngày nghỉ'],
    ['cost', 'Cost', 'Chi phí theo task & nguồn lực'],
    ['reports', 'Reports', 'EVM: SPI/CPI/EAC/ETC'],
  ];

  async function load() {
    loading = true; error = '';
    try { await openProject(projectId); }
    catch (e) { error = e.message; }
    finally { loading = false; }
  }
  onMount(load);
  $: if (projectId) load();

  async function share() {
    shareBusy = true;
    try { const d = await makeInvite(projectId); inviteCode = d.inviteCode; showShare = true; }
    catch (e) { toast(e.message, 'err'); }
    finally { shareBusy = false; }
  }

  function keydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) { e.preventDefault(); redo(); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); flushSave(); toast('Đã lưu thủ công.'); }
  }
</script>

<svelte:window on:keydown={keydown} />

<div class="app">
  <!-- TOPBAR -->
  <header class="topbar">
    <button class="tab" on:click={back} title="Về danh sách dự án">← Dự án</button>
    <div class="sep"></div>
    <span class="logo"><span class="mark">DP</span></span>
    {#if $graph}<strong style="color:#fff;font-size:14px">{$graph.project.name}</strong>{/if}
    <div class="sep"></div>
    <nav class="tabs" role="tablist" aria-label="Chế độ xem">
      {#each VIEWS as [id, name, tip] (id)}
        <button class="tab" role="tab" aria-current={$currentView === id} title={tip}
                on:click={() => currentView.set(id)}>{name}</button>
      {/each}
    </nav>
    <div class="spacer"></div>
    <div class="row" style="gap:6px">
      <button class="btn btn-sm" style="background:#1E293B;border-color:#334155;color:#CBD5E1" disabled={!$canUndo} on:click={undo} title="Hoàn tác (Ctrl+Z)">↶</button>
      <button class="btn btn-sm" style="background:#1E293B;border-color:#334155;color:#CBD5E1" disabled={!$canRedo} on:click={redo} title="Làm lại (Ctrl+Y)">↷</button>
      <button class="btn btn-sm" style="background:#1E293B;border-color:#334155;color:#CBD5E1" on:click={autoLevel} title="Tự động cân bằng nguồn lực">⚖ Leveling</button>
      <button class="btn btn-sm btn-primary" on:click={() => saveBaseline()} title="Lưu Baseline hiện tại">📌 Baseline</button>
      <button class="btn btn-sm" style="background:#1E293B;border-color:#334155;color:#CBD5E1" disabled={shareBusy} on:click={share}>🔗 Chia sẻ</button>
    </div>
    <div class="save-state tooltip-host">
      <span class="dot {$saveState === 'saved' ? '' : $saveState === 'error' || $saveState === 'offline' ? 'error' : 'dirty'}"></span>
      {{ saved:'Đã lưu', dirty:'Chưa lưu…', saving:'Đang lưu…', error:'Lỗi lưu — bấm ⌘S', offline:'Ngoại tuyến — sẽ tự lưu khi có mạng' }[$saveState]}
    </div>
  </header>

  <!-- MAIN -->
  <div class="main">
    <aside class="sidebar">
      <div class="side-group">
        <div class="side-title">Thêm mới</div>
        <button class="side-item" on:click={() => addTask($selectedUid)}>➕ Task<br/><span class="small muted">phía dưới task đang chọn</span></button>
      </div>
      {#if $graph}
        <div class="side-group">
          <div class="side-title">Thu phóng Gantt</div>
          <p class="small muted">Dùng thanh Zoom trong view Gantt. Kéo mép thanh để đổi duration, kéo thân để shift.</p>
        </div>
        <div class="side-group">
          <div class="side-title">Thông tin dự án</div>
          <div class="small">Bắt đầu: <b>{$graph.project.start_date}</b></div>
          <div class="small">Hoàn thành (tính): <b>{$graph.project.finishDate || '—'}</b></div>
          <div class="small">Ngày hiện tại: <b>{$graph.project.current_date || '(hôm nay)'}</b></div>
          <div class="small">Giờ/ngày làm việc: <b>{$graph.project.work_day_hours}h</b></div>
        </div>
        {#if $graph._warnings?.length}
          <div class="side-group">
            <div class="side-title">Cảnh báo lập lịch</div>
            {#each $graph._warnings as w}<div class="chip chip-warn mb2" style="white-space:normal">⚠ {w}</div>{/each}
          </div>
        {/if}
      {/if}
    </aside>

    <main class="content" aria-live="polite">
      {#if loading}<div class="card"><div class="skeleton" style="height:20px;width:40%;margin-bottom:12px"></div><div class="skeleton" style="height:380px"></div></div>
      {:else if error}<div class="card"><span class="chip chip-critical">⛔ {error}</span> <button class="btn btn-sm mt2" on:click={load}>Thử lại</button></div>
      {:else if $graph}
        {#if $currentView === 'gantt'}<Gantt />{/if}
        {#if $currentView === 'worklog'}<WorkLog />{/if}
        {#if $currentView === 'resources'}<Resources />{/if}
        {#if $currentView === 'network'}<Network />{/if}
        {#if $currentView === 'calendar'}<CalendarView />{/if}
        {#if $currentView === 'cost'}<Cost />{/if}
        {#if $currentView === 'reports'}<Reports />{/if}
      {/if}
    </main>
  </div>

  <!-- STATUS BAR: tường minh chỉ số toàn dự án -->
  <footer class="statusbar">
    {#if $graph}
      {@const leaves = $graph.tasks.filter(t => !t.is_summary)}
      <span>Tasks: <b>{leaves.length}</b></span>
      <span>Đường găng: <b style="color:var(--critical)">{leaves.filter(t => t.critical).length}</b> task</span>
      <span>% hoàn thành: <b>{Math.round(leaves.reduce((s,t)=>s+(+t.percent_complete||0),0)/Math.max(1,leaves.length))}%</b></span>
      <span>Tổng chi phí: <b>{(leaves.reduce((s,t)=>s+(+t.total_cost||0),0)).toLocaleString('vi-VN')} ₫</b></span>
      <span class="grow right small">Engine CPM chạy client-side · kết quả persist lên MySQL để mọi thành viên thấy giống nhau</span>
    {/if}
  </footer>
</div>

<!-- MODAL SHARE -->
{#if showShare}
  <div class="modal-backdrop" on:click={() => showShare = false} role="dialog" aria-modal="true" aria-label="Chia sẻ dự án">
    <div class="card modal" on:click|stopPropagation>
      <h2>Chia sẻ dự án</h2>
      <p class="muted">Gửi mã mời bên dưới cho đồng nghiệp. Họ đăng nhập DeedsPlan → “Tham gia dự án chia sẻ” → nhập mã. Quyền: <b>Editor</b>.</p>
      <div class="row"><input class="input num" style="font-size:24px;letter-spacing:.3em;text-align:center" value={inviteCode} readonly id="ic" />
        <button class="btn btn-primary" on:click={() => { navigator.clipboard?.writeText(inviteCode); toast('Đã copy mã mời.'); }}>Copy</button></div>
      <p class="small muted mt2">Mỗi lần bấm “Chia sẻ” sẽ sinh mã mới (mã cũ hết hiệu lực).</p>
      <button class="btn mt4" on:click={() => showShare = false}>Đóng</button>
    </div>
  </div>
{/if}

<style>
  .side-item { flex-direction: column; align-items: flex-start; gap: 2px; }
</style>
