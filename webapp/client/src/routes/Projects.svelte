<script>
  import { onMount } from 'svelte';
  import { projects, loadProjects, createProject, deleteProject, joinByCode, user, logout, toast } from '../lib/store.js';
  export let open;

  let newName = '', startDate = new Date().toISOString().slice(0, 10), creating = false;
  let joinCode = '';
  let confirmDel = null;

  onMount(() => loadProjects().catch(e => toast(e.message, 'err')));

  async function create() {
    if (!newName.trim()) return;
    creating = true;
    try { const p = await createProject(newName.trim(), startDate); toast('Đã tạo dự án.'); newName=''; open(p.id); }
    catch (e) { toast(e.message, 'err'); }
    finally { creating = false; }
  }
  async function join() {
    try { const d = await joinByCode(joinCode.trim().toUpperCase()); toast('Đã tham gia dự án chia sẻ.'); joinCode=''; open(d.projectId); }
    catch (e) { toast(e.message, 'err'); }
  }
  const pct = (p) => Math.round(+p.baseline_count > 0 ? 100 : 0);
</script>

<div class="page">
  <header class="row mb4" style="justify-content:space-between">
    <div class="row" style="gap:10px">
      <span class="mark" style="width:34px;height:34px;border-radius:9px;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:700">DP</span>
      <div><h1 style="margin:0">DeedsPlan</h1><span class="small muted">Quản lý dự án trực tuyến — {$user?.username}</span></div>
    </div>
    <div class="row">
      <button class="btn btn-sm" on:click={() => { logout(); location.reload(); }}>Đăng xuất</button>
    </div>
  </header>

  <section class="split two">
    <div>
      <h3>Dự án của tôi</h3>
      {#if $projects.length === 0}
        <div class="card muted">Chưa có dự án nào. Tạo dự án đầu tiên ở khung bên cạnh 👉</div>
      {:else}
        <div class="list">
          {#each $projects as p (p.id)}
            <div class="card row item" role="button" tabindex="0"
                 on:click={() => open(p.id)}
                 on:keydown={(e) => e.key === 'Enter' && open(p.id)}>
              <div class="grow">
                <div class="row" style="gap:8px">
                  <b>{p.name}</b>
                  <span class="chip {p.role==='owner'?'chip-info':'chip-neutral'}">{p.role === 'owner' ? 'Chủ sở hữu' : p.role}</span>
                </div>
                <div class="small muted">Bắt đầu {p.start_date} · {p.baseline_count > 0 ? `Baseline #${p.baseline_count}` : 'chưa baseline'} · sửa lúc {p.updated_at}</div>
              </div>
              {#if confirmDel === p.id}
                <div class="row" on:click|stopPropagation>
                  <button class="btn btn-danger btn-sm" on:click={async () => { await deleteProject(p.id); confirmDel=null; toast('Đã xoá dự án.','warn'); }}>Xác nhận xoá</button>
                  <button class="btn btn-sm" on:click={() => confirmDel=null}>Huỷ</button>
                </div>
              {:else if p.role === 'owner'}
                <button class="btn btn-icon btn-sm" title="Xoá dự án" aria-label="Xoá dự án {p.name}"
                        on:click|stopPropagation={() => confirmDel = p.id}>🗑</button>
              {/if}
              <span class="chev">→</span>
            </div>
          {/each}
        </div>
      {/if}
    </div>

    <aside>
      <div class="card mb4">
        <h3>Tạo dự án mới</h3>
        <div class="field"><label for="pn">Tên dự án</label>
          <input id="pn" class="input" bind:value={newName} placeholder="VD: Nhà phố Anh Minh — Phần thô" /></div>
        <div class="field"><label for="ps">Ngày bắt đầu (PSAP)</label>
          <input id="ps" class="input" type="date" bind:value={startDate} />
          <span class="hint">Toàn bộ lịch được tính từ ngày này theo giờ làm việc.</span></div>
        <button class="btn btn-primary" disabled={creating || !newName.trim()} on:click={create}>
          {creating ? 'Đang tạo…' : '+ Tạo dự án'}
        </button>
      </div>

      <div class="card">
        <h3>Tham gia dự án chia sẻ</h3>
        <div class="field"><label for="jc">Mã mời 6 ký tự</label>
          <input id="jc" class="input num" maxlength="6" bind:value={joinCode} placeholder="ABC123"
                 on:input={e => joinCode = e.target.value.toUpperCase()} /></div>
        <button class="btn" disabled={joinCode.length !== 6} on:click={join}>Tham gia →</button>
        <p class="small muted mt2">Nhận mã từ chủ dự án (nút “Chia sẻ” trong workspace). Bạn sẽ có quyền Editor.</p>
      </div>
    </aside>
  </section>
</div>

<style>
  .page { max-width: 1080px; margin: 0 auto; padding: var(--s6) var(--s4); }
  .list { display: flex; flex-direction: column; gap: var(--s2); }
  .item { cursor: pointer; transition: box-shadow var(--t-fast), transform var(--t-fast); align-items:center; }
  .item:hover { box-shadow: var(--sh-2); transform: translateY(-1px); }
  .chev { color: var(--faint); margin-left: 8px; }
</style>
