<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Filomena | Monitoreo</title>
  <style>
    :root{
      --bg:#0b1220; --card:#111828; --muted:#95a3b9; --text:#eaf0ff;
      --pri:#3b82f6; --pri2:#1d4ed8; --err:#ef4444; --stroke:#233257; --field:#0a1323;
    }
    * , *::before , *::after { box-sizing: border-box; }
    html,body{height:100%}
    body{
      margin:0; background:radial-gradient(1200px 600px at 80% -10%,#1b2440 0,#0b1220 40%,#0b1220 100%);
      color:var(--text); font:14px/1.4 system-ui,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;
      display:grid; place-items:center; padding:16px;
    }
    .card{
      width:100%; max-width:420px; background:linear-gradient(180deg,#111a2a,#0e1626);
      border:1px solid var(--stroke); border-radius:16px; padding:24px 22px;
      box-shadow:0 20px 60px rgba(0,0,0,.35);
    }
    .brand{display:flex;align-items:center;gap:10px;margin-bottom:12px}
    .dot{width:10px;height:10px;border-radius:50%;background:var(--pri)}
    h1{margin:0 0 6px;font-size:20px;font-weight:700}
    p.sub{margin:0 0 18px;color:var(--muted);font-size:13px}

    .field{margin-bottom:14px}
    label{display:block;margin-bottom:6px;color:#c8d4ea;font-size:12px}

    .input{
      width:100%;
      padding:12px 12px;
      border-radius:10px;
      border:1px solid #2a3b60;
      background:var(--field);
      color:var(--text);
      outline:none;
    }
    .input:focus{border-color:#4b74ff; box-shadow:0 0 0 3px rgba(59,130,246,.25)}

    .control{position:relative}
    .input--has-eye{padding-right:42px}

    .eye{
      position:absolute; top:50%; right:10px; transform:translateY(-50%);
      width:32px; height:32px; display:grid; place-items:center;
      border:0; background:transparent; color:#c9d4eb; cursor:pointer; border-radius:8px;
    }
    .eye:hover{background:rgba(255,255,255,.05)}
    .eye svg{width:18px;height:18px}

    button.primary{
      width:100%; padding:12px; border:0; border-radius:10px;
      background:linear-gradient(180deg,var(--pri),var(--pri2));
      color:#fff; font-weight:700; cursor:pointer;
    }
    .err{
      color:#ffd2d2; background:#3a0f16; border:1px solid #5f1b24;
      border-radius:10px; padding:10px 12px; margin:10px 0 0; font-size:12px;
    }
  </style>
</head>
<body>
  <form class="card" method="POST" action="{{ route('login') }}">
    @csrf
    <div class="brand"><span class="dot"></span><strong>Filomena | Monitoreo</strong></div>
    <p class="sub">Ingrese con su DNI y contraseña.</p>

    <div class="field">
      <label for="dni">DNI</label>
      <input id="dni" class="input" name="dni" inputmode="numeric" pattern="[0-9]*" required autofocus autocomplete="username">
      @error('dni')<div class="err">{{ $message }}</div>@enderror
    </div>

    <div class="field">
      <label for="password">Contraseña</label>
      <div class="control">
        <input id="password" class="input input--has-eye" type="password" name="password" required autocomplete="current-password">
        <button type="button" class="eye" aria-label="Mostrar u ocultar contraseña" onclick="togglePwd()">
          <!-- ícono ojo -->
          <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7Zm0 12a5 5 0 1 1 .001-10.001A5 5 0 0 1 12 17Zm0-2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg>
          <svg id="eye-closed" style="display:none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3.3 2.3 2 3.6 5.2 6.8C3.1 8.2 1.7 10 1.7 12c0 0 3 7 10.3 7 2.1 0 3.9-.5 5.3-1.3l3.6 3.6 1.3-1.3L3.3 2.3ZM7.7 9.3l1.8 1.8a2.5 2.5 0 0 0 3.3 3.3l1.8 1.8a5 5 0 0 1-6.9-6.9Zm4.3-4.3c7.3 0 10.3 7 10.3 7a11.6 11.6 0 0 1-4.1 4.7l-1.5-1.5a9 9 0 0 0 3-3.2c0 0-2.4-5.5-8-5.5-.9 0-1.7.1-2.5.4l-1.4-1.4a10 10 0 0 1 3.6-.5Z"/></svg>
        </button>
      </div>
      @error('password')<div class="err">{{ $message }}</div>@enderror
    </div>

    @if ($errors->any() && !$errors->has('dni') && !$errors->has('password'))
      <div class="err">Credenciales inválidas.</div>
    @endif

    <button class="primary" type="submit">Ingresar</button>
  </form>

  <script>
    function togglePwd(){
      const input = document.getElementById('password');
      const open  = document.getElementById('eye-open');
      const close = document.getElementById('eye-closed');
      if (input.type === 'password') {
        input.type = 'text'; open.style.display='none'; close.style.display='block';
      } else {
        input.type = 'password'; open.style.display='block'; close.style.display='none';
      }
    }
  </script>
</body>
</html>
