<?php
session_start();

/* =========================
   CONFIGURACIÓN
========================= */
define('ADMIN_USER', 'jocarsa');
define('ADMIN_PASS', 'jocarsa');
define('DB_FILE', __DIR__ . '/admin.sqlite');

/* =========================
   CONEXIÓN SQLITE
========================= */
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            apellidos TEXT NOT NULL,
            email TEXT NOT NULL,
            telefono TEXT NOT NULL,
            curso_matriculado TEXT NOT NULL,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS chatbot_qa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pregunta TEXT NOT NULL,
            respuesta TEXT NOT NULL,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS ifttt_acciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            resumen_if TEXT NOT NULL,
            destinatario_email TEXT NOT NULL,
            asunto TEXT NOT NULL,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    die("Error al conectar con SQLite: " . htmlspecialchars($e->getMessage()));
}

/* =========================
   FUNCIONES
========================= */
function h($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function redirigir($url) {
    header("Location: $url");
    exit;
}

function esta_logueado() {
    return !empty($_SESSION['admin_logueado']);
}

function get_seccion_actual() {
    $seccion = $_GET['seccion'] ?? 'usuarios';
    $permitidas = ['usuarios', 'chatbot', 'ifttt'];
    return in_array($seccion, $permitidas, true) ? $seccion : 'usuarios';
}

function url_admin($seccion, $extra = []) {
    $params = array_merge(['seccion' => $seccion], $extra);
    return 'admin.php?' . http_build_query($params);
}

/* =========================
   LOGIN / LOGOUT
========================= */
if (isset($_GET['logout'])) {
    session_destroy();
    redirigir('admin.php');
}

$error_login = '';

if (isset($_POST['accion']) && $_POST['accion'] === 'login') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logueado'] = true;
        redirigir(url_admin('usuarios'));
    } else {
        $error_login = 'Usuario o contraseña incorrectos';
    }
}

/* =========================
   ESTADO GENERAL
========================= */
$mensaje = '';
$error = '';
$seccion_actual = get_seccion_actual();

/* =========================
   CRUD USUARIOS
========================= */
if (esta_logueado()) {
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $curso_matriculado = trim($_POST['curso_matriculado'] ?? '');

        if ($nombre === '' || $apellidos === '' || $email === '' || $telefono === '' || $curso_matriculado === '') {
            $error = 'Todos los campos del usuario son obligatorios';
        } else {
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, apellidos, email, telefono, curso_matriculado)
                VALUES (:nombre, :apellidos, :email, :telefono, :curso_matriculado)
            ");
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':email' => $email,
                ':telefono' => $telefono,
                ':curso_matriculado' => $curso_matriculado
            ]);
            redirigir(url_admin('usuarios', ['msg' => 'Usuario creado correctamente']));
        }
    }

    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_usuario') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $curso_matriculado = trim($_POST['curso_matriculado'] ?? '');

        if ($id <= 0 || $nombre === '' || $apellidos === '' || $email === '' || $telefono === '' || $curso_matriculado === '') {
            $error = 'Todos los campos del usuario son obligatorios';
        } else {
            $stmt = $db->prepare("
                UPDATE usuarios
                SET nombre = :nombre,
                    apellidos = :apellidos,
                    email = :email,
                    telefono = :telefono,
                    curso_matriculado = :curso_matriculado
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':email' => $email,
                ':telefono' => $telefono,
                ':curso_matriculado' => $curso_matriculado
            ]);
            redirigir(url_admin('usuarios', ['msg' => 'Usuario actualizado correctamente']));
        }
    }

    if (isset($_GET['eliminar_usuario'])) {
        $id = (int)$_GET['eliminar_usuario'];
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            redirigir(url_admin('usuarios', ['msg' => 'Usuario eliminado correctamente']));
        }
    }
}

/* =========================
   CRUD CHATBOT Q&A
========================= */
if (esta_logueado()) {
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear_qa') {
        $pregunta = trim($_POST['pregunta'] ?? '');
        $respuesta = trim($_POST['respuesta'] ?? '');

        if ($pregunta === '' || $respuesta === '') {
            $error = 'Pregunta y respuesta son obligatorias';
        } else {
            $stmt = $db->prepare("
                INSERT INTO chatbot_qa (pregunta, respuesta)
                VALUES (:pregunta, :respuesta)
            ");
            $stmt->execute([
                ':pregunta' => $pregunta,
                ':respuesta' => $respuesta
            ]);
            redirigir(url_admin('chatbot', ['msg' => 'Pregunta y respuesta creadas correctamente']));
        }
    }

    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_qa') {
        $id = (int)($_POST['id'] ?? 0);
        $pregunta = trim($_POST['pregunta'] ?? '');
        $respuesta = trim($_POST['respuesta'] ?? '');

        if ($id <= 0 || $pregunta === '' || $respuesta === '') {
            $error = 'Pregunta y respuesta son obligatorias';
        } else {
            $stmt = $db->prepare("
                UPDATE chatbot_qa
                SET pregunta = :pregunta,
                    respuesta = :respuesta
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':pregunta' => $pregunta,
                ':respuesta' => $respuesta
            ]);
            redirigir(url_admin('chatbot', ['msg' => 'Pregunta y respuesta actualizadas correctamente']));
        }
    }

    if (isset($_GET['eliminar_qa'])) {
        $id = (int)$_GET['eliminar_qa'];
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM chatbot_qa WHERE id = :id");
            $stmt->execute([':id' => $id]);
            redirigir(url_admin('chatbot', ['msg' => 'Pregunta y respuesta eliminadas correctamente']));
        }
    }
}

/* =========================
   CRUD IFTTT ACCIONES
========================= */
if (esta_logueado()) {
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear_ifttt') {
        $resumen_if = trim($_POST['resumen_if'] ?? '');
        $destinatario_email = trim($_POST['destinatario_email'] ?? '');
        $asunto = trim($_POST['asunto'] ?? '');

        if ($resumen_if === '' || $destinatario_email === '' || $asunto === '') {
            $error = 'Todos los campos de la acción IFTTT son obligatorios';
        } else {
            $stmt = $db->prepare("
                INSERT INTO ifttt_acciones (
                    resumen_if,
                    destinatario_email,
                    asunto
                )
                VALUES (
                    :resumen_if,
                    :destinatario_email,
                    :asunto
                )
            ");
            $stmt->execute([
                ':resumen_if' => $resumen_if,
                ':destinatario_email' => $destinatario_email,
                ':asunto' => $asunto
            ]);
            redirigir(url_admin('ifttt', ['msg' => 'Acción IFTTT creada correctamente']));
        }
    }

    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_ifttt') {
        $id = (int)($_POST['id'] ?? 0);
        $resumen_if = trim($_POST['resumen_if'] ?? '');
        $destinatario_email = trim($_POST['destinatario_email'] ?? '');
        $asunto = trim($_POST['asunto'] ?? '');

        if ($id <= 0 || $resumen_if === '' || $destinatario_email === '' || $asunto === '') {
            $error = 'Todos los campos de la acción IFTTT son obligatorios';
        } else {
            $stmt = $db->prepare("
                UPDATE ifttt_acciones
                SET resumen_if = :resumen_if,
                    destinatario_email = :destinatario_email,
                    asunto = :asunto
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':resumen_if' => $resumen_if,
                ':destinatario_email' => $destinatario_email,
                ':asunto' => $asunto
            ]);
            redirigir(url_admin('ifttt', ['msg' => 'Acción IFTTT actualizada correctamente']));
        }
    }

    if (isset($_GET['eliminar_ifttt'])) {
        $id = (int)$_GET['eliminar_ifttt'];
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM ifttt_acciones WHERE id = :id");
            $stmt->execute([':id' => $id]);
            redirigir(url_admin('ifttt', ['msg' => 'Acción IFTTT eliminada correctamente']));
        }
    }
}

/* =========================
   MENSAJES
========================= */
if (isset($_GET['msg'])) {
    $mensaje = (string)$_GET['msg'];
}

/* =========================
   CARGA EDICIÓN USUARIOS
========================= */
$usuario_editar = null;
if (esta_logueado() && isset($_GET['editar_usuario'])) {
    $id = (int)$_GET['editar_usuario'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuario_editar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

/* =========================
   CARGA EDICIÓN QA
========================= */
$qa_editar = null;
if (esta_logueado() && isset($_GET['editar_qa'])) {
    $id = (int)$_GET['editar_qa'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM chatbot_qa WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $qa_editar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

/* =========================
   CARGA EDICIÓN IFTTT
========================= */
$ifttt_editar = null;
if (esta_logueado() && isset($_GET['editar_ifttt'])) {
    $id = (int)$_GET['editar_ifttt'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM ifttt_acciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $ifttt_editar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

/* =========================
   LISTADOS
========================= */
$usuarios = [];
$qa_items = [];
$ifttt_items = [];

if (esta_logueado()) {
    $stmt = $db->query("SELECT * FROM usuarios ORDER BY id DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT * FROM chatbot_qa ORDER BY id DESC");
    $qa_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT * FROM ifttt_acciones ORDER BY id DESC");
    $ifttt_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   CONTADORES
========================= */
$total_usuarios = 0;
$total_qa = 0;
$total_ifttt = 0;

if (esta_logueado()) {
    $total_usuarios = (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $total_qa = (int)$db->query("SELECT COUNT(*) FROM chatbot_qa")->fetchColumn();
    $total_ifttt = (int)$db->query("SELECT COUNT(*) FROM ifttt_acciones")->fetchColumn();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Admin Jocarsa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root{
            --wp-bg:#f0f0f1;
            --wp-panel:#ffffff;
            --wp-border:#dcdcde;
            --wp-text:#1d2327;
            --wp-muted:#646970;
            --wp-primary:#2271b1;
            --wp-primary-hover:#135e96;
            --wp-sidebar:#1d2327;
            --wp-sidebar-hover:#2c3338;
            --wp-sidebar-text:#f0f0f1;
            --wp-danger:#b32d2e;
            --wp-success-bg:#edfaef;
            --wp-success-text:#116329;
            --wp-error-bg:#fcf0f1;
            --wp-error-text:#8a2424;
            --radius:8px;
            --shadow:0 1px 1px rgba(0,0,0,0.04);
        }

        *{
            box-sizing:border-box;
        }

        html,body{
            margin:0;
            padding:0;
            background:var(--wp-bg);
            color:var(--wp-text);
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
        }

        a{
            color:var(--wp-primary);
            text-decoration:none;
        }

        .login-page{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:30px;
        }

        .login-box{
            width:100%;
            max-width:380px;
            background:#fff;
            border:1px solid var(--wp-border);
            border-radius:12px;
            padding:28px;
            box-shadow:0 10px 30px rgba(0,0,0,0.08);
        }

        .login-box h1{
            margin:0 0 22px 0;
            font-size:24px;
        }

        .app{
            min-height:100vh;
            display:grid;
            grid-template-columns:240px 1fr;
            grid-template-rows:50px 1fr;
            grid-template-areas:
                "sidebar header"
                "sidebar main";
        }

        .header{
            grid-area:header;
            background:#fff;
            border-bottom:1px solid var(--wp-border);
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 20px;
            gap:20px;
        }

        .header-title{
            font-size:16px;
            font-weight:600;
        }

        .header-right{
            display:flex;
            align-items:center;
            gap:14px;
            color:var(--wp-muted);
            font-size:14px;
        }

        .sidebar{
            grid-area:sidebar;
            background:var(--wp-sidebar);
            color:var(--wp-sidebar-text);
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }

        .brand{
            padding:16px 18px;
            font-size:18px;
            font-weight:700;
            border-bottom:1px solid rgba(255,255,255,0.08);
        }

        .menu{
            padding:10px 0;
        }

        .menu a{
            display:block;
            color:var(--wp-sidebar-text);
            padding:12px 18px;
            font-size:14px;
            border-left:4px solid transparent;
        }

        .menu a:hover{
            background:var(--wp-sidebar-hover);
        }

        .menu a.activo{
            background:var(--wp-sidebar-hover);
            border-left-color:var(--wp-primary);
            font-weight:600;
        }

        .main{
            grid-area:main;
            padding:24px;
        }

        .page-title{
            margin:0 0 18px 0;
            font-size:24px;
            font-weight:600;
        }

        .cards{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
            gap:16px;
            margin-bottom:20px;
        }

        .card-mini,
        .box{
            background:var(--wp-panel);
            border:1px solid var(--wp-border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
        }

        .card-mini{
            padding:18px;
        }

        .card-mini .k{
            color:var(--wp-muted);
            font-size:13px;
            margin-bottom:8px;
        }

        .card-mini .v{
            font-size:28px;
            font-weight:700;
        }

        .box{
            margin-bottom:20px;
            overflow:hidden;
        }

        .box-header{
            padding:16px 18px;
            border-bottom:1px solid var(--wp-border);
            background:#fff;
        }

        .box-header h2{
            margin:0;
            font-size:18px;
        }

        .box-body{
            padding:18px;
        }

        .notice{
            padding:12px 14px;
            border-radius:8px;
            margin-bottom:18px;
            border:1px solid transparent;
            font-size:14px;
        }

        .notice.success{
            background:var(--wp-success-bg);
            color:var(--wp-success-text);
            border-color:#bfe3c6;
        }

        .notice.error{
            background:var(--wp-error-bg);
            color:var(--wp-error-text);
            border-color:#e8b9bd;
        }

        form{
            margin:0;
        }

        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
            gap:16px;
        }

        .campo{
            display:flex;
            flex-direction:column;
            gap:6px;
        }

        .campo.full{
            grid-column:1 / -1;
        }

        .campo label{
            font-size:13px;
            color:var(--wp-text);
            font-weight:600;
        }

        .campo input,
        .campo textarea{
            width:100%;
            padding:10px 12px;
            border:1px solid var(--wp-border);
            border-radius:6px;
            font-size:14px;
            background:#fff;
            color:var(--wp-text);
            outline:none;
        }

        .campo textarea{
            min-height:140px;
            resize:vertical;
        }

        .campo input:focus,
        .campo textarea:focus{
            border-color:var(--wp-primary);
            box-shadow:0 0 0 1px var(--wp-primary);
        }

        .actions{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:18px;
        }

        .btn,
        .btn-secondary,
        .btn-danger{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:36px;
            padding:0 14px;
            border-radius:6px;
            border:1px solid transparent;
            font-size:13px;
            font-weight:500;
            cursor:pointer;
            text-decoration:none;
            transition:0.2s ease;
        }

        .btn{
            background:var(--wp-primary);
            color:#fff;
        }

        .btn:hover{
            background:var(--wp-primary-hover);
        }

        .btn-secondary{
            background:#f6f7f7;
            color:var(--wp-text);
            border-color:#c3c4c7;
        }

        .btn-secondary:hover{
            background:#f0f0f1;
        }

        .btn-danger{
            background:var(--wp-danger);
            color:#fff;
        }

        .btn-danger:hover{
            filter:brightness(0.95);
        }

        table{
            width:100%;
            border-collapse:collapse;
            background:#fff;
        }

        th, td{
            padding:12px 14px;
            border-bottom:1px solid var(--wp-border);
            text-align:left;
            vertical-align:top;
            font-size:14px;
        }

        th{
            background:#f6f7f7;
            font-size:13px;
            font-weight:600;
        }

        .acciones-tabla{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }

        .qa-text,
        .ifttt-text{
            max-width:520px;
            white-space:pre-wrap;
            word-break:break-word;
        }

        @media (max-width: 980px){
            .app{
                grid-template-columns:1fr;
                grid-template-rows:auto auto 1fr;
                grid-template-areas:
                    "header"
                    "sidebar"
                    "main";
            }

            .sidebar{
                min-height:auto;
            }

            .menu{
                display:flex;
                flex-wrap:wrap;
                padding:8px;
                gap:8px;
            }

            .menu a{
                border-left:none;
                border-radius:6px;
            }
        }

        @media (max-width: 760px){
            table, thead, tbody, tr, th, td{
                display:block;
            }

            thead{
                display:none;
            }

            tr{
                margin-bottom:12px;
                border:1px solid var(--wp-border);
                border-radius:8px;
                overflow:hidden;
            }

            td{
                position:relative;
                padding-top:28px;
            }

            td::before{
                content:attr(data-label);
                position:absolute;
                top:8px;
                left:14px;
                font-size:12px;
                font-weight:700;
                color:var(--wp-muted);
            }
        }
    </style>
</head>
<body>

<?php if (!esta_logueado()): ?>
    <div class="login-page">
        <div class="login-box">
            <h1>Acceso administración</h1>

            <?php if ($error_login !== ''): ?>
                <div class="notice error"><?php echo h($error_login); ?></div>
            <?php endif; ?>

            <form method="post" action="admin.php" autocomplete="off">
                <input type="hidden" name="accion" value="login">

                <div class="campo" style="margin-bottom:14px;">
                    <label>Usuario</label>
                    <input type="text" name="usuario" required>
                </div>

                <div class="campo" style="margin-bottom:18px;">
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Entrar</button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">Jocarsa Admin</div>

            <nav class="menu">
                <a href="<?php echo h(url_admin('usuarios')); ?>" class="<?php echo $seccion_actual === 'usuarios' ? 'activo' : ''; ?>">Usuarios</a>
                <a href="<?php echo h(url_admin('chatbot')); ?>" class="<?php echo $seccion_actual === 'chatbot' ? 'activo' : ''; ?>">Chatbot Q&amp;A</a>
                <a href="<?php echo h(url_admin('ifttt')); ?>" class="<?php echo $seccion_actual === 'ifttt' ? 'activo' : ''; ?>">Acciones IFTTT</a>
            </nav>
        </aside>

        <header class="header">
            <div class="header-title">Panel de administración</div>
            <div class="header-right">
                <span>Conectado como <strong>jocarsa</strong></span>
                <a class="btn-secondary" href="admin.php?logout=1">Cerrar sesión</a>
            </div>
        </header>

        <main class="main">
            <?php if ($seccion_actual === 'usuarios'): ?>
                <h1 class="page-title">Usuarios</h1>

                <div class="cards">
                    <div class="card-mini">
                        <div class="k">Total usuarios</div>
                        <div class="v"><?php echo (int)$total_usuarios; ?></div>
                    </div>
                    <div class="card-mini">
                        <div class="k">Total preguntas/respuestas</div>
                        <div class="v"><?php echo (int)$total_qa; ?></div>
                    </div>
                    <div class="card-mini">
                        <div class="k">Total acciones IFTTT</div>
                        <div class="v"><?php echo (int)$total_ifttt; ?></div>
                    </div>
                </div>

                <?php if ($mensaje !== ''): ?>
                    <div class="notice success"><?php echo h($mensaje); ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="notice error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="box">
                    <div class="box-header">
                        <h2><?php echo $usuario_editar ? 'Editar usuario' : 'Nuevo usuario'; ?></h2>
                    </div>
                    <div class="box-body">
                        <form method="post" action="<?php echo h($usuario_editar ? url_admin('usuarios', ['editar_usuario' => (int)$usuario_editar['id']]) : url_admin('usuarios')); ?>">
                            <input type="hidden" name="accion" value="<?php echo $usuario_editar ? 'actualizar_usuario' : 'crear_usuario'; ?>">
                            <?php if ($usuario_editar): ?>
                                <input type="hidden" name="id" value="<?php echo (int)$usuario_editar['id']; ?>">
                            <?php endif; ?>

                            <div class="grid">
                                <div class="campo">
                                    <label>Nombre</label>
                                    <input type="text" name="nombre" required value="<?php echo h($usuario_editar['nombre'] ?? ''); ?>">
                                </div>

                                <div class="campo">
                                    <label>Apellidos</label>
                                    <input type="text" name="apellidos" required value="<?php echo h($usuario_editar['apellidos'] ?? ''); ?>">
                                </div>

                                <div class="campo">
                                    <label>Email</label>
                                    <input type="email" name="email" required value="<?php echo h($usuario_editar['email'] ?? ''); ?>">
                                </div>

                                <div class="campo">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono" required value="<?php echo h($usuario_editar['telefono'] ?? ''); ?>">
                                </div>

                                <div class="campo full">
                                    <label>Curso matriculado</label>
                                    <input type="text" name="curso_matriculado" required value="<?php echo h($usuario_editar['curso_matriculado'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="actions">
                                <button class="btn" type="submit"><?php echo $usuario_editar ? 'Guardar cambios' : 'Crear usuario'; ?></button>
                                <?php if ($usuario_editar): ?>
                                    <a class="btn-secondary" href="<?php echo h(url_admin('usuarios')); ?>">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h2>Listado de usuarios</h2>
                    </div>
                    <div class="box-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Apellidos</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Curso matriculado</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$usuarios): ?>
                                <tr><td colspan="8">No hay usuarios todavía.</td></tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo (int)$usuario['id']; ?></td>
                                        <td data-label="Nombre"><?php echo h($usuario['nombre']); ?></td>
                                        <td data-label="Apellidos"><?php echo h($usuario['apellidos']); ?></td>
                                        <td data-label="Email"><?php echo h($usuario['email']); ?></td>
                                        <td data-label="Teléfono"><?php echo h($usuario['telefono']); ?></td>
                                        <td data-label="Curso matriculado"><?php echo h($usuario['curso_matriculado']); ?></td>
                                        <td data-label="Creado"><?php echo h($usuario['creado_en']); ?></td>
                                        <td data-label="Acciones">
                                            <div class="acciones-tabla">
                                                <a class="btn" href="<?php echo h(url_admin('usuarios', ['editar_usuario' => (int)$usuario['id']])); ?>">Editar</a>
                                                <a class="btn-danger" href="<?php echo h(url_admin('usuarios', ['eliminar_usuario' => (int)$usuario['id']])); ?>" onclick="return confirm('¿Seguro que quieres eliminar este usuario?');">Eliminar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($seccion_actual === 'chatbot'): ?>
                <h1 class="page-title">Chatbot · Preguntas y respuestas</h1>

                <div class="cards">
                    <div class="card-mini">
                        <div class="k">Total usuarios</div>
                        <div class="v"><?php echo (int)$total_usuarios; ?></div>
                    </div>
                    <div class="card-mini">
                        <div class="k">Total preguntas/respuestas</div>
                        <div class="v"><?php echo (int)$total_qa; ?></div>
                    </div>
                    <div class="card-mini">
                        <div class="k">Total acciones IFTTT</div>
                        <div class="v"><?php echo (int)$total_ifttt; ?></div>
                    </div>
                </div>

                <?php if ($mensaje !== ''): ?>
                    <div class="notice success"><?php echo h($mensaje); ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="notice error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="box">
                    <div class="box-header">
                        <h2><?php echo $qa_editar ? 'Editar pregunta y respuesta' : 'Nueva pregunta y respuesta'; ?></h2>
                    </div>
                    <div class="box-body">
                        <form method="post" action="<?php echo h($qa_editar ? url_admin('chatbot', ['editar_qa' => (int)$qa_editar['id']]) : url_admin('chatbot')); ?>">
                            <input type="hidden" name="accion" value="<?php echo $qa_editar ? 'actualizar_qa' : 'crear_qa'; ?>">
                            <?php if ($qa_editar): ?>
                                <input type="hidden" name="id" value="<?php echo (int)$qa_editar['id']; ?>">
                            <?php endif; ?>

                            <div class="grid">
                                <div class="campo full">
                                    <label>Pregunta</label>
                                    <textarea name="pregunta" required><?php echo h($qa_editar['pregunta'] ?? ''); ?></textarea>
                                </div>

                                <div class="campo full">
                                    <label>Respuesta</label>
                                    <textarea name="respuesta" required><?php echo h($qa_editar['respuesta'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="actions">
                                <button class="btn" type="submit"><?php echo $qa_editar ? 'Guardar cambios' : 'Crear entrada'; ?></button>
                                <?php if ($qa_editar): ?>
                                    <a class="btn-secondary" href="<?php echo h(url_admin('chatbot')); ?>">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h2>Listado de preguntas y respuestas</h2>
                    </div>
                    <div class="box-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pregunta</th>
                                    <th>Respuesta</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$qa_items): ?>
                                <tr><td colspan="5">No hay preguntas y respuestas todavía.</td></tr>
                            <?php else: ?>
                                <?php foreach ($qa_items as $item): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo (int)$item['id']; ?></td>
                                        <td data-label="Pregunta"><div class="qa-text"><?php echo nl2br(h($item['pregunta'])); ?></div></td>
                                        <td data-label="Respuesta"><div class="qa-text"><?php echo nl2br(h($item['respuesta'])); ?></div></td>
                                        <td data-label="Creado"><?php echo h($item['creado_en']); ?></td>
                                        <td data-label="Acciones">
                                            <div class="acciones-tabla">
                                                <a class="btn" href="<?php echo h(url_admin('chatbot', ['editar_qa' => (int)$item['id']])); ?>">Editar</a>
                                                <a class="btn-danger" href="<?php echo h(url_admin('chatbot', ['eliminar_qa' => (int)$item['id']])); ?>" onclick="return confirm('¿Seguro que quieres eliminar esta entrada?');">Eliminar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($seccion_actual === 'ifttt'): ?>
                <h1 class="page-title">Acciones IFTTT</h1>

                <div class="cards">
                    <div class="card-mini">
                        <div class="k">Total usuarios</div>
                        <div class="v"><?php echo (int)$total_usuarios; ?></div>
                    </div>
                    <div class="card-mini">
                        <div class="k">Total preguntas/respuestas</div>
                        <div class="v"><?php echo (int)$total_qa; ?></div>
                    </div>
                    <div class="card-mini">
                        <div class="k">Total acciones IFTTT</div>
                        <div class="v"><?php echo (int)$total_ifttt; ?></div>
                    </div>
                </div>

                <?php if ($mensaje !== ''): ?>
                    <div class="notice success"><?php echo h($mensaje); ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="notice error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="box">
                    <div class="box-header">
                        <h2><?php echo $ifttt_editar ? 'Editar acción IFTTT' : 'Nueva acción IFTTT'; ?></h2>
                    </div>
                    <div class="box-body">
                        <form method="post" action="<?php echo h($ifttt_editar ? url_admin('ifttt', ['editar_ifttt' => (int)$ifttt_editar['id']]) : url_admin('ifttt')); ?>">
                            <input type="hidden" name="accion" value="<?php echo $ifttt_editar ? 'actualizar_ifttt' : 'crear_ifttt'; ?>">
                            <?php if ($ifttt_editar): ?>
                                <input type="hidden" name="id" value="<?php echo (int)$ifttt_editar['id']; ?>">
                            <?php endif; ?>

                            <div class="grid">
                                <div class="campo full">
                                    <label>Resumen de la acción desencadenante (if)</label>
                                    <textarea name="resumen_if" required><?php echo h($ifttt_editar['resumen_if'] ?? ''); ?></textarea>
                                </div>

                                <div class="campo">
                                    <label>Destinatario de email</label>
                                    <input type="email" name="destinatario_email" required value="<?php echo h($ifttt_editar['destinatario_email'] ?? ''); ?>">
                                </div>

                                <div class="campo">
                                    <label>Asunto</label>
                                    <input type="text" name="asunto" required value="<?php echo h($ifttt_editar['asunto'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="actions">
                                <button class="btn" type="submit"><?php echo $ifttt_editar ? 'Guardar cambios' : 'Crear acción'; ?></button>
                                <?php if ($ifttt_editar): ?>
                                    <a class="btn-secondary" href="<?php echo h(url_admin('ifttt')); ?>">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header">
                        <h2>Listado de acciones IFTTT</h2>
                    </div>
                    <div class="box-body" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>If</th>
                                    <th>Destinatario</th>
                                    <th>Asunto</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$ifttt_items): ?>
                                <tr><td colspan="6">No hay acciones IFTTT todavía.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ifttt_items as $item): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo (int)$item['id']; ?></td>
                                        <td data-label="If"><div class="ifttt-text"><?php echo nl2br(h($item['resumen_if'])); ?></div></td>
                                        <td data-label="Destinatario"><?php echo h($item['destinatario_email']); ?></td>
                                        <td data-label="Asunto"><div class="ifttt-text"><?php echo nl2br(h($item['asunto'])); ?></div></td>
                                        <td data-label="Creado"><?php echo h($item['creado_en']); ?></td>
                                        <td data-label="Acciones">
                                            <div class="acciones-tabla">
                                                <a class="btn" href="<?php echo h(url_admin('ifttt', ['editar_ifttt' => (int)$item['id']])); ?>">Editar</a>
                                                <a class="btn-danger" href="<?php echo h(url_admin('ifttt', ['eliminar_ifttt' => (int)$item['id']])); ?>" onclick="return confirm('¿Seguro que quieres eliminar esta acción?');">Eliminar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>

</body>
</html>
