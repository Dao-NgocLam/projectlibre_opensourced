<svelte:options accessors={false} />
<script>
  import { onMount } from 'svelte';
  import { user, me, logout, toastQ, saveState } from './lib/store.js';
  import Auth from './routes/Auth.svelte';
  import Projects from './routes/Projects.svelte';
  import Workspace from './routes/Workspace.svelte';

  let booting = true;
  let view = 'projects';            // projects | workspace
  let activeProjectId = null;

  onMount(async () => {
    const u = await me();
    booting = false;
    if (!u) view = 'auth';
  });

  function open(pid) { activeProjectId = pid; view = 'workspace'; }
  function back() { view = 'projects'; activeProjectId = null; }

  $: label = { saved: 'Đã lưu', dirty: 'Chưa lưu…', saving: 'Đang lưu…', error: 'Lỗi lưu', offline: 'Ngoại tuyến' }[$saveState];
</script>

{#if booting}
  <div class="auth-wrap"><div class="card" style="width:280px">
    <div class="row" style="gap:10px"><div class="skeleton" style="width:36px;height:36px;border-radius:10px"></div>
      <div class="grow"><div class="skeleton" style="height:12px;margin-bottom:8px"></div><div class="skeleton" style="height:10px;width:70%"></div></div>
    </div></div></div>
{:else if !$user || view === 'auth'}
  <Auth onlogin={() => (view = 'projects')} />
{:else if view === 'projects'}
  <Projects {open} />
{:else}
  <Workspace projectId={activeProjectId} {back} />
{/if}

<!-- Toasts -->
<div class="toast-stack" role="status" aria-live="polite">
  {#each $toastQ as t (t.id)}
    <div class="toast {t.kind}">
      <span>{t.kind === 'err' ? '⛔' : t.kind === 'warn' ? '⚠️' : '✓'}</span>
      <span class="grow">{t.msg}</span>
    </div>
  {/each}
</div>

<style>
  :global(.save-pill) { display:flex; align-items:center; gap:6px; font-size:12px; color:#94A3B8; }
</style>
