<?php
// Activar errores para ver qué falla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================
// SETUP.PHP — Instalador El Gringo Cotizador
// Compatible con PHP 7.4+ / cPanel
// ============================================================
$step    = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors  = array();
$success = array();

$configFile = dirname(__DIR__) . '/config/config.php';
$schemaFile = __DIR__ . '/schema.sql';

// ---- PASO 2: Procesar formulario ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {

    $host   = trim(isset($_POST['db_host'])    ? $_POST['db_host']   : 'localhost');
    $name   = trim(isset($_POST['db_name'])    ? $_POST['db_name']   : '');
    $user   = trim(isset($_POST['db_user'])    ? $_POST['db_user']   : '');
    $pass   = trim(isset($_POST['db_pass'])    ? $_POST['db_pass']   : '');
    $secret = trim(isset($_POST['app_secret']) ? $_POST['app_secret']: '');
    $url    = rtrim(trim(isset($_POST['app_url']) ? $_POST['app_url'] : ''), '/');
    $aname  = trim(isset($_POST['admin_name'])  ? $_POST['admin_name']  : '');
    $aemail = trim(isset($_POST['admin_email']) ? $_POST['admin_email'] : '');
    $apass  = trim(isset($_POST['admin_pass'])  ? $_POST['admin_pass']  : '');

    // Validaciones
    if (!$name || !$user)
        $errors[] = 'Nombre de BD y usuario son obligatorios.';
    if (!$url)
        $errors[] = 'La URL del sistema es obligatoria.';
    if (!$aname || !$aemail || !$apass)
        $errors[] = 'Datos del administrador incompletos.';
    if (strlen($apass) < 8)
        $errors[] = 'La contrasena admin debe tener al menos 8 caracteres.';
    if (!filter_var($aemail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Email del admin no valido.';
    if (!file_exists($schemaFile))
        $errors[] = 'No se encontro el archivo schema.sql en: ' . $schemaFile;

    // Probar conexión a BD
    if (empty($errors)) {
        try {
            $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $success[] = 'Conexion a la base de datos: OK';
        } catch (PDOException $e) {
            $errors[] = 'No se pudo conectar a la BD: ' . $e->getMessage();
        }
    }

    // Ejecutar schema.sql
    if (empty($errors)) {
        try {
            $sql = file_get_contents($schemaFile);
            if ($sql === false) {
                $errors[] = 'No se pudo leer schema.sql';
            } else {
                // Limpiar comentarios de bloque
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                // Separar por punto y coma
                $statements = explode(';', $sql);
                $executed = 0;
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    // Saltar lineas vacias y comentarios de linea
                    if (empty($stmt)) continue;
                    if (substr($stmt, 0, 2) === '--') continue;
                    // Saltar si solo tiene comentarios
                    $lines = explode("\n", $stmt);
                    $hasCode = false;
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (!empty($line) && substr($line, 0, 2) !== '--') {
                            $hasCode = true;
                            break;
                        }
                    }
                    if (!$hasCode) continue;
                    try {
                        $pdo->exec($stmt);
                        $executed++;
                    } catch (PDOException $e) {
                        // Ignorar errores de "ya existe" (IF NOT EXISTS)
                        if (strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), 'Duplicate entry') === false) {
                            // Solo registrar, no detener
                        }
                    }
                }
                $success[] = 'Base de datos creada: ' . $executed . ' sentencias ejecutadas.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error procesando schema.sql: ' . $e->getMessage();
        }
    }

    // Crear usuario admin
    if (empty($errors)) {
        try {
            $hash = password_hash($apass, PASSWORD_BCRYPT, array('cost' => 10));
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, active)
                 VALUES (?, ?, ?, 'admin', 1)
                 ON DUPLICATE KEY UPDATE password = VALUES(password), name = VALUES(name), active = 1"
            );
            $stmt->execute(array($aname, $aemail, $hash));
            $success[] = 'Usuario administrador creado: ' . $aemail;
        } catch (PDOException $e) {
            $errors[] = 'Error creando usuario admin: ' . $e->getMessage();
        }
    }

    // Generar config.php
    if (empty($errors)) {
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
        }
        $appPath = str_replace('\\', '/', dirname(__DIR__));

        // Construir config línea por línea (sin heredoc para evitar problemas)
        $lines = array();
        $lines[] = '<?php';
        $lines[] = '// Auto-generado por setup.php el ' . date('d/m/Y H:i') . ' — NO EDITAR';
        $lines[] = "define('DB_HOST',   '" . addslashes($host) . "');";
        $lines[] = "define('DB_NAME',   '" . addslashes($name) . "');";
        $lines[] = "define('DB_USER',   '" . addslashes($user) . "');";
        $lines[] = "define('DB_PASS',   '" . addslashes($pass) . "');";
        $lines[] = "define('DB_CHARSET','utf8mb4');";
        $lines[] = '';
        $lines[] = "define('APP_NAME',    'El Gringo Cotizador');";
        $lines[] = "define('APP_VERSION', '1.0.0');";
        $lines[] = "define('APP_URL',     '" . addslashes($url) . "');";
        $lines[] = "define('APP_PATH',    '" . addslashes($appPath) . "');";
        $lines[] = "define('APP_SECRET',  '" . addslashes($secret) . "');";
        $lines[] = "define('REMEMBER_DAYS', 30);";
        $lines[] = '';
        $lines[] = "define('UPLOAD_PATH', APP_PATH . '/assets/img/uploads/');";
        $lines[] = "define('UPLOAD_URL',  APP_URL  . '/assets/img/uploads/');";
        $lines[] = "define('MAX_FILE_SIZE', 2097152);";
        $lines[] = "define('ALLOWED_IMG_TYPES', array('image/jpeg', 'image/png', 'image/webp'));";
        $lines[] = '';
        $lines[] = "date_default_timezone_set('America/Lima');";
        $lines[] = "define('DEBUG_MODE', false);";
        $lines[] = "ini_set('display_errors', 0);";
        $lines[] = "error_reporting(0);";
        $lines[] = '';
        $lines[] = 'if (session_status() === PHP_SESSION_NONE) {';
        $lines[] = '    $secure = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";';
        $lines[] = '    session_set_cookie_params(array(';
        $lines[] = "        'lifetime' => 0,";
        $lines[] = "        'path'     => '/',";
        $lines[] = "        'secure'   => \$secure,";
        $lines[] = "        'httponly' => true,";
        $lines[] = "        'samesite' => 'Strict',";
        $lines[] = '    ));';
        $lines[] = '    session_start();';
        $lines[] = '}';

        $configContent = implode("\n", $lines) . "\n";

        // Asegurar que la carpeta config/ tenga permisos
        $configDir = dirname($configFile);
        if (!is_writable($configDir)) {
            @chmod($configDir, 0755);
        }

        if (file_put_contents($configFile, $configContent) !== false) {
            $success[] = 'Archivo config/config.php generado correctamente.';
        } else {
            $errors[] = 'No se pudo escribir config/config.php. Permisos de la carpeta config/: ' .
                        substr(sprintf('%o', fileperms($configDir)), -4);
        }
    }

    // Crear carpetas de uploads
    if (empty($errors)) {
        $uploadsBase = dirname(__DIR__) . '/assets/img/uploads';
        @mkdir($uploadsBase . '/products', 0755, true);
        @mkdir($uploadsBase . '/logos',    0755, true);
        $success[] = 'Carpetas de uploads creadas.';
        $step = 3;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacion — El Gringo Cotizador</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #1a1a1a; }
.container { max-width: 680px; margin: 40px auto; padding: 0 20px 60px; }
.card { background: #fff; border-radius: 12px; padding: 32px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
h1 { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
h2 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #C8102E; }
.subtitle { color: #666; margin-bottom: 16px; }
label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #444; margin-top: 12px; }
input { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 4px; }
input:focus { outline: none; border-color: #C8102E; }
.btn { display: block; width: 100%; padding: 14px; background: #C8102E; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 20px; }
.btn:hover { background: #a80d25; }
.btn-link { display: inline-block; padding: 14px 24px; background: #1a1a1a; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 16px; }
.alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; line-height: 1.6; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-info    { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.step-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
.step-row:last-child { border-bottom: none; }
.icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.ok  { background: #d4edda; color: #155724; }
.err { background: #f8d7da; color: #721c24; }
hr { border: none; border-top: 1px solid #f0f0f0; margin: 20px 0; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.section-title { font-size: 14px; font-weight: 700; color: #444; margin: 20px 0 4px; }
</style>
</head>
<body>
<div class="container">

<div class="card">
  <h1>El Gringo Cotizador</h1>
  <p class="subtitle">Instalador del sistema v1.0.0 — elgringo.pe</p>
</div>

<?php if ($step === 1): ?>

<?php
$checks = array();
$phpOk  = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks[] = array('PHP 7.4 o superior', $phpOk, PHP_VERSION);

$pdoOk = extension_loaded('pdo_mysql');
$checks[] = array('Extension PDO MySQL', $pdoOk, $pdoOk ? 'Disponible' : 'No encontrada');

$mbOk = extension_loaded('mbstring');
$checks[] = array('Extension mbstring', $mbOk, $mbOk ? 'Disponible' : 'No encontrada');

$gdOk = extension_loaded('gd');
$checks[] = array('Extension GD', $gdOk, $gdOk ? 'Disponible' : 'No encontrada');

$configDir = dirname(__DIR__) . '/config';
$cfgOk = is_writable($configDir);
$checks[] = array('Carpeta config/ escribible', $cfgOk, $cfgOk ? 'OK (' . substr(sprintf('%o', fileperms($configDir)), -4) . ')' : 'Sin permisos - ejecuta chmod 755 config/');

$assetsDir = dirname(__DIR__) . '/assets';
$assOk = is_writable($assetsDir);
$checks[] = array('Carpeta assets/ escribible', $assOk, $assOk ? 'OK' : 'Sin permisos - ejecuta chmod 755 assets/');

$schOk = file_exists($schemaFile);
$checks[] = array('schema.sql encontrado', $schOk, $schOk ? 'OK (' . number_format(filesize($schemaFile)) . ' bytes)' : 'No encontrado en: ' . $schemaFile);

$allOk = true;
foreach ($checks as $c) { if (!$c[1]) { $allOk = false; break; } }
?>

<div class="card">
  <h2>Paso 1 — Diagnostico del servidor</h2>
  <?php foreach ($checks as $c): ?>
  <div class="step-row">
    <span class="icon <?php echo $c[1] ? 'ok' : 'err'; ?>"><?php echo $c[1] ? '&#10003;' : '&#10007;'; ?></span>
    <div>
      <strong><?php echo htmlspecialchars($c[0]); ?></strong><br>
      <small style="color:#666"><?php echo htmlspecialchars($c[2]); ?></small>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (!$allOk): ?>
    <div class="alert alert-error" style="margin-top:20px">
      Hay requisitos no cumplidos. Revisa los puntos en rojo antes de continuar.
    </div>
  <?php else: ?>
    <div class="alert alert-success" style="margin-top:20px">
      &#10003; Tu servidor esta listo. Puedes continuar.
    </div>
    <a href="?step=2"><button class="btn">Continuar &rarr;</button></a>
  <?php endif; ?>
</div>

<?php elseif ($step === 2): ?>

<div class="card">
  <h2>Paso 2 — Configuracion</h2>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error">&#10007; <?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="alert alert-info">
    Los datos de la BD los encuentras en <strong>cPanel &rarr; MySQL Databases</strong>.
    El nombre real incluye tu usuario cPanel como prefijo (ej: <code>ebakxdhm_cotizador</code>).
  </div>

  <form method="post" action="?step=2">

    <div class="section-title">Base de datos</div>

    <label>Host de la BD</label>
    <input type="text" name="db_host" value="<?php echo isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : 'localhost'; ?>">

    <label>Nombre de la BD (con prefijo cPanel)</label>
    <input type="text" name="db_name" value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : ''; ?>" placeholder="ebakxdhm_cotizador" required>

    <label>Usuario de la BD (con prefijo cPanel)</label>
    <input type="text" name="db_user" value="<?php echo isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : ''; ?>" placeholder="ebakxdhm_cotizador" required>

    <label>Contrasena de la BD</label>
    <input type="password" name="db_pass" placeholder="Contrasena que pusiste al crear el usuario">

    <hr>
    <div class="section-title">Sistema</div>

    <label>URL del sistema (sin slash al final)</label>
    <input type="text" name="app_url" value="<?php echo isset($_POST['app_url']) ? htmlspecialchars($_POST['app_url']) : 'https://elgringo.pe/cotizador'; ?>" required>

    <label>Clave secreta (dejar vacio para generar automaticamente)</label>
    <input type="text" name="app_secret" placeholder="Se genera automaticamente si lo dejas vacio">

    <hr>
    <div class="section-title">Cuenta administrador</div>

    <label>Tu nombre</label>
    <input type="text" name="admin_name" value="<?php echo isset($_POST['admin_name']) ? htmlspecialchars($_POST['admin_name']) : 'Daniel'; ?>" required>

    <label>Email (para iniciar sesion)</label>
    <input type="email" name="admin_email" value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : ''; ?>" placeholder="tu@email.com" required>

    <label>Contrasena (minimo 8 caracteres)</label>
    <input type="password" name="admin_pass" placeholder="Minimo 8 caracteres" required>

    <button type="submit" class="btn">Instalar sistema</button>
  </form>
</div>

<?php elseif ($step === 3): ?>

<div class="card">
  <h2>&#10003; Instalacion completada</h2>

  <?php foreach ($success as $s): ?>
    <div class="alert alert-success">&#10003; <?php echo htmlspecialchars($s); ?></div>
  <?php endforeach; ?>

  <div class="alert alert-info" style="margin-top:16px">
    <strong>IMPORTANTE — Elimina el instalador:</strong><br><br>
    Por seguridad, ve al File Manager de cPanel y elimina o renombra el archivo
    <code>install/setup.php</code> a <code>install/setup.php.bak</code>
  </div>

  <?php
  $loginUrl = '';
  if (file_exists($configFile)) {
      include $configFile;
      $loginUrl = defined('APP_URL') ? APP_URL . '/auth/login.php' : '';
  }
  if ($loginUrl): ?>
    <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn-link">Ir al sistema &rarr;</a>
  <?php else: ?>
    <a href="../auth/login.php" class="btn-link">Ir al sistema &rarr;</a>
  <?php endif; ?>
</div>

<?php endif; ?>

</div>
</body>
</html>
