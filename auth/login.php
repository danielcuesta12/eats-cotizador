<?php
// Sesion PRIMERO, antes de cualquier HTML
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Redirigir si ya esta logueado
if (isLoggedIn()) {
    redirect('/admin/dashboard');
}

// Verificar cookie recordarme
if (isset($_COOKIE['remember_token'])) {
    $rtoken = $_COOKIE['remember_token'];
    $ruser  = Database::fetch(
        "SELECT * FROM users WHERE remember_token = ? AND remember_expires > NOW() AND active = 1 LIMIT 1",
        array($rtoken)
    );
    if ($ruser) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $ruser['id'];
        $_SESSION['user_name']  = $ruser['name'];
        $_SESSION['user_email'] = $ruser['email'];
        $_SESSION['user_role']  = $ruser['role'];
        $newTok = generateToken();
        $newExp = date('Y-m-d H:i:s', strtotime('+30 days'));
        Database::execute(
            "UPDATE users SET remember_token=?, remember_expires=?, last_login=NOW() WHERE id=?",
            array($newTok, $newExp, $ruser['id'])
        );
        setcookie('remember_token', $newTok, time() + (30*24*3600), '/', '', isset($_SERVER['HTTPS']), true);
        redirect('/admin/dashboard');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF manualmente (mas compatible)
    $postToken    = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $sessionToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

    if (empty($sessionToken) || empty($postToken) || $postToken !== $sessionToken) {
        // Token invalido - regenerar y mostrar error
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $error = 'Error de seguridad. Por favor intenta de nuevo.';
    } else {
        $email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
        $password = isset($_POST['password']) ? $_POST['password']       : '';
        $remember = isset($_POST['remember']);

        $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

        if (empty($email) || empty($password)) {
            $error = 'Completa todos los campos.';
        } else {
            $user = Database::fetch(
                "SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1",
                array($email)
            );

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                unset($_SESSION['csrf_token']);

                Database::execute("UPDATE users SET last_login=NOW() WHERE id=?", array($user['id']));

                if ($remember) {
                    $tok = generateToken();
                    $exp = date('Y-m-d H:i:s', strtotime('+30 days'));
                    Database::execute(
                        "UPDATE users SET remember_token=?, remember_expires=? WHERE id=?",
                        array($tok, $exp, $user['id'])
                    );
                    setcookie('remember_token', $tok, time() + (30*24*3600), '/', '', isset($_SERVER['HTTPS']), true);
                }

                $dest = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : '/admin/dashboard';
                unset($_SESSION['redirect_after_login']);
                redirect($dest);

            } else {
                sleep(1);
                $error = 'Email o contrasena incorrectos.';
            }
        }
    }
}

// Generar CSRF token para el formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/img/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/img/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo APP_URL; ?>/assets/img/favicon-180.png">
<title>Ingresar — El Gringo Cotizador</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
  background: #0f0f0f;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.wrap { width: 100%; max-width: 400px; }
.brand { text-align: center; margin-bottom: 28px; }
.brand-icon {
  width: 68px; height: 68px;
  background: #C8102E; border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 14px; font-size: 30px;
}
.brand-name { font-size: 20px; font-weight: 700; color: #fff; }
.brand-sub  { font-size: 13px; color: #555; margin-top: 3px; }
.card {
  background: #1a1a1a;
  border: 1px solid #2a2a2a;
  border-radius: 18px;
  padding: 32px 28px;
}
.card-title { font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 4px; }
.card-sub   { font-size: 14px; color: #555; margin-bottom: 24px; }
.alert-error {
  background: rgba(200,16,46,.12);
  border: 1px solid rgba(200,16,46,.3);
  color: #ff6b7a;
  padding: 11px 14px;
  border-radius: 10px;
  font-size: 14px;
  margin-bottom: 18px;
}
.form-group { margin-bottom: 16px; }
.form-label {
  display: block;
  font-size: 12px; font-weight: 600;
  color: #888; text-transform: uppercase;
  letter-spacing: .5px; margin-bottom: 6px;
}
.input-wrap { position: relative; }
.input-icon {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%);
  color: #444; font-size: 16px; pointer-events: none;
}
input[type="email"],
input[type="password"],
input[type="text"] {
  width: 100%;
  padding: 12px 14px 12px 40px;
  background: #111;
  border: 1.5px solid #2a2a2a;
  border-radius: 10px;
  color: #fff; font-size: 14px;
  outline: none;
  transition: border-color .2s;
  -webkit-appearance: none;
}
input:focus { border-color: #C8102E; }
input::placeholder { color: #333; }
.toggle-pass {
  position: absolute; right: 12px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  color: #444; cursor: pointer; font-size: 16px;
  padding: 2px; transition: color .2s;
}
.toggle-pass:hover { color: #C8102E; }
.remember {
  display: flex; align-items: center;
  gap: 8px; margin-bottom: 20px;
}
.remember input[type="checkbox"] {
  width: 16px; height: 16px;
  accent-color: #C8102E; cursor: pointer;
}
.remember label { font-size: 13px; color: #666; cursor: pointer; }
.btn-submit {
  width: 100%; padding: 14px;
  background: #C8102E; color: #fff;
  border: none; border-radius: 10px;
  font-size: 15px; font-weight: 700;
  cursor: pointer; transition: background .2s;
}
.btn-submit:hover { background: #a80d25; }
.footer { text-align: center; margin-top: 20px; font-size: 12px; color: #333; }
</style>
</head>
<body>

<div class="wrap">
  <div class="brand">
    <div class="brand-icon">&#127828;</div>
    <div class="brand-name">El Gringo Burger Joint</div>
    <div class="brand-sub">Sistema de Cotizacion</div>
  </div>

  <div class="card">
    <div class="card-title">Bienvenido</div>
    <div class="card-sub">Ingresa con tu cuenta</div>

    <?php if ($error): ?>
    <div class="alert-error">&#9888; <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo APP_URL; ?>/auth/login.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <div class="form-group">
        <label class="form-label" for="email">Correo electronico</label>
        <div class="input-wrap">
          <span class="input-icon">&#9993;</span>
          <input type="email" id="email" name="email"
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                 placeholder="tu@correo.com"
                 autocomplete="email" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Contrasena</label>
        <div class="input-wrap">
          <span class="input-icon">&#128274;</span>
          <input type="password" id="password" name="password"
                 placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                 autocomplete="current-password" required>
          <button type="button" class="toggle-pass" id="togglePass">&#128065;</button>
        </div>
      </div>

      <div class="remember">
        <input type="checkbox" id="remember" name="remember" value="1">
        <label for="remember">Recordar por 30 dias</label>
      </div>

      <button type="submit" class="btn-submit">Ingresar</button>
    </form>
  </div>

  <div class="footer">El Gringo Cotizador v1.0 &middot; Lima, Peru</div>
</div>

<script>
document.getElementById('togglePass').addEventListener('click', function() {
  var inp = document.getElementById('password');
  if (inp.type === 'password') {
    inp.type = 'text';
    this.innerHTML = '&#128584;';
  } else {
    inp.type = 'password';
    this.innerHTML = '&#128065;';
  }
});
</script>

</body>
</html>
