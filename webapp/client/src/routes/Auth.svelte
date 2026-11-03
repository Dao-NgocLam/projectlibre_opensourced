<script>
  import { login, register, toast } from '../lib/store.js';
  export let onlogin;
  let mode = 'login'; // login | register
  let email = '', username = '', password = '';
  let err = '', busy = false;

  async function submit(e) {
    e.preventDefault(); err = ''; busy = true;
    try {
      if (mode === 'login') await login(email, password);
      else await register(email, username, password);
      toast('Chào mừng đến DeedsPlan! 🎉');
      onlogin();
    } catch (ex) { err = ex.message; }
    finally { busy = false; }
  }
</script>

<div class="auth-wrap">
  <div>
    <div class="brand-hero">
      <span class="mark">DP</span>
      <h1 style="margin-top:12px">DeedsPlan</h1>
      <p class="muted" style="max-width:340px;margin:0 auto">
        ProjectLibre trực tuyến — lập kế hoạch, theo dõi & báo cáo dự án<br/>minh bạch, tường minh, rõ ràng.
      </p>
    </div>

    <form class="card auth-card" on:submit={submit}>
      <h2 style="font-size:18px">{mode === 'login' ? 'Đăng nhập' : 'Tạo tài khoản'}</h2>

      <div class="field">
        <label for="email">Email</label>
        <input id="email" class="input" type="email" required autocomplete="email" bind:value={email} placeholder="ban@deedsteam.click" />
      </div>

      {#if mode === 'register'}
        <div class="field">
          <label for="uname">Tên hiển thị</label>
          <input id="uname" class="input" required minlength="2" bind:value={username} placeholder="Nguyễn Văn A" />
          <span class="hint">Xuất hiện trong audit trail — mọi người thấy "ai đã sửa".</span>
        </div>
      {/if}

      <div class="field">
        <label for="pwd">Mật khẩu</label>
        <input id="pwd" class="input" type="password" required minlength="8"
               autocomplete={mode === 'login' ? 'current-password' : 'new-password'} bind:value={password} />
        {#if mode === 'register'}<span class="hint">Tối thiểu 8 ký tự.</span>{/if}
      </div>

      {#if err}<div class="chip chip-critical mb2" role="alert">⛔ {err}</div>{/if}

      <button class="btn btn-primary" style="width:100%;justify-content:center" disabled={busy}>
        {busy ? 'Đang xử lý…' : mode === 'login' ? 'Đăng nhập' : 'Đăng ký'}
      </button>

      <p class="small muted mt4" style="text-align:center">
        {mode === 'login' ? 'Chưa có tài khoản?' : 'Đã có tài khoản?'}
        <a href="#!" on:click|preventDefault={() => { mode = mode === 'login' ? 'register' : 'login'; err = ''; }}>
          {mode === 'login' ? 'Đăng ký' : 'Đăng nhập'}
        </a>
      </p>
    </form>
    <p class="small muted mt4" style="text-align:center">deedsteam.click · Dữ liệu lưu trên MySQL của bạn</p>
  </div>
</div>
