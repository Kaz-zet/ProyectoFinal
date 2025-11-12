<?php
session_start();
require_once 'conexiones/conDB.php';
$nombre = $_SESSION['nombre'] ?? null;
$rol = $_SESSION['rol'] ?? null;
$foto = $_SESSION['foto'] ?? null;
date_default_timezone_set('America/Argentina/Buenos_Aires');

$id_usuario = $_SESSION['id'] ?? null;
$msg = '';
$error = '';
$puede_reservar = ($id_usuario && $rol !== 'duenio');

//---------------FOTO DE PERFIL----------------------------------------------
if ($nombre) {
    require_once 'conexiones/conDB.php';
    try {
        $stmt = $pdo->prepare("SELECT foto FROM usuario WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['foto'])) {
            $foto = $row['foto'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching foto: " . $e->getMessage());
    }
}

// Obtener categoría del usuario logueado
$categoria_usuario = null;
if ($id_usuario) {
    try {
        $stmt = $pdo->prepare("SELECT categoria FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $categoria_usuario = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching categoria: " . $e->getMessage());
    }
}
//----------------------------------------------------------------

// Sacamos ID de cancha
$id_cancha = $_GET['id'] ?? null;

$fecha_mostrar = $_GET['fecha'] ?? date('Y-m-d');

// Sacamos datos mediante esa ID
if ($id_cancha) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, d.nombre as duenio_nombre 
            FROM cancha c 
            LEFT JOIN duenio d ON c.id_duenio = d.id_duenio 
            WHERE c.id_cancha = ? AND (c.verificado = 1 OR c.verificado = 0)
        ");
        $stmt->execute([$id_cancha]);
        $cancha = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cancha) {
            header("Location: buscador.php");
            exit;
        }

    } catch (PDOException $e) {
        $error = "Error al cargar la cancha: " . $e->getMessage();
        $cancha = null;
    }
} else {
    header("Location: buscador.php");
    exit;
    
}

//FUNCIÓN PARA OBTENER RESERVAS
function obtenerreservas($pdo, $id_cancha, $fecha)
{
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre as usuario_nombre, r.categoria as categoria_reserva
        FROM reserva r 
        INNER JOIN usuario u ON r.id_usuario = u.id_usuario 
        WHERE r.id_cancha = ? AND r.fecha = ? AND r.estado = 'activa'
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$id_cancha, $fecha]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Se crean los horarios disponibles
function generarhorarios()
{
    $horarios = [];
    for ($h = 8; $h <= 22; $h++) {
        $horarios[] = sprintf("%02d:00", $h);
    }
    return $horarios;
}

//Verifica disponibilidad del horario
function verificarDisponibilidad($reservas, $hora, $fecha_mostrar, $espacios_total = 4)
{
    $hora_fin = date('H:i', strtotime($hora . ' +1 hour'));
    $espacios_ocupados = 0;
    $reservas_en_horario = [];
    $categoria_horario = null;

    // Verificar si la fecha es hoy y la hora ya pasó
    if ($fecha_mostrar === date('Y-m-d')) {
        $hora_actual_ts = strtotime(date('Y-m-d H:i'));
        $hora_slot_ts = strtotime($fecha_mostrar . ' ' . $hora);

        if ($hora_slot_ts <= $hora_actual_ts) {
            return ['tipo' => 'pasada', 'mensaje' => 'Hora pasada'];
        }
    }

    //Cuenta los espacios ocupados de la reserva.
    foreach ($reservas as $reserva) {
        $r_inicio = substr($reserva['hora_inicio'], 0, 5);
        $r_final = substr($reserva['hora_final'], 0, 5);

        //Verifica si hay  algún conflicto de horarios.
        if (
            ($hora >= $r_inicio && $hora < $r_final) ||
            ($hora_fin > $r_inicio && $hora_fin <= $r_final) ||
            ($hora <= $r_inicio && $hora_fin >= $r_final)
        ) {
            $espacios_reservados = $reserva['espacios_reservados'] ?? 1;
            $espacios_ocupados += $espacios_reservados;

            //La primera reserva define la categoría del horario. x ej, si la primera reserva es categoria 2, solo los usuarios de categoria 2 podran reservar.
            if ($categoria_horario === null) {
                $categoria_horario = $reserva['categoria_reserva'];
            }

            $reservas_en_horario[] = [
                'usuario' => $reserva['usuario_nombre'],
                'espacios' => $espacios_reservados,
                'jugadores' => $reserva['jugadores_reservados'] ?? 1,
                'categoria' => $reserva['categoria_reserva']
            ];
        }
    }

    $espacios_disponibles = $espacios_total - $espacios_ocupados;

    //Determina el estado de las reservas!!
    if ($espacios_disponibles <= 0) {
        return [
            'tipo' => 'ocupado',
            'espacios_disponibles' => 0,
            'espacios_ocupados' => $espacios_ocupados,
            'reservas' => $reservas_en_horario,
            'categoria_requerida' => $categoria_horario
        ];
    } elseif ($espacios_disponibles < $espacios_total) {
        return [
            'tipo' => 'parcial',
            'espacios_disponibles' => $espacios_disponibles,
            'espacios_ocupados' => $espacios_ocupados,
            'reservas' => $reservas_en_horario,
            'categoria_requerida' => $categoria_horario
        ];
    } else {
        return [
            'tipo' => 'disponible',
            'espacios_disponibles' => $espacios_disponibles,
            'espacios_ocupados' => 0,
            'reservas' => [],
            'categoria_requerida' => null
        ];
    }
}

//Pasa de ingles a español.
function diasespanol($dia_ingles)
{
    $dias = [
        'Mon' => 'Lun',
        'Tue' => 'Mar',
        'Wed' => 'Mié',
        'Thu' => 'Jue',
        'Fri' => 'Vie',
        'Sat' => 'Sáb',
        'Sun' => 'Dom'
    ];
    return $dias[$dia_ingles] ?? $dia_ingles;
}

$horarios = generarhorarios();
$reservas = obtenerreservas($pdo, $id_cancha, $fecha_mostrar);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cancha - CanchApp</title>
    <!--Bootstrap 5 CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>

        /* estilos de hero section */
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        }

        .court-image {
            transition: transform 0.5s ease;
        }

        .court-image:hover {
            transform: scale(1.05);
        }
        /* fin estilos hero secion */



        .court-image {
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }

        .time-slot {
            height: 80px;
            border-radius: 8px;
            border: none;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-decoration: none;
        }

        .time-slot.disponible {
            background-color: #28a745;
        }

        .time-slot.disponible:hover {
            background-color: #218838;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .time-slot.parcial {
            background-color: #ffc107;
            color: #000;
        }

        .time-slot.parcial:hover:not(.bloqueado) {
            background-color: #e0a800;
            transform: translateY(-2px);
            color: #000;
            text-decoration: none;
        }

        .time-slot.bloqueado {
            opacity: 0.6;
            cursor: not-allowed;
            position: relative;
        }

        .time-slot.ocupado {
            background-color: #dc3545;
            cursor: not-allowed;
        }

        .time-slot.pasado {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .time-slot.pasado:hover {
            background-color: #6c757d;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .time-slot.selected {
            background-color: #007bff !important;
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }

        .day-selector {
            border-radius: 8px;
            padding: 8px 12px;
            margin: 0 2px;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 60px;
            text-decoration: none;
            color: #495057;
        }

        .day-selector.active {
            background-color: #007bff;
            color: white;
        }

        .day-selector:not(.active) {
            background-color: #f8f9fa;
            color: #495057;
        }

        .day-selector:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: #007bff;
        }

        .day-selector.active:hover {
            color: white;
        }

        .date-picker-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .reservation-summary {
            background-color: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .confirm-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .confirm-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .alert-login {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .alert-login a {
            color: #007bff;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container-fluid text-light p-2" style= "background-color: #f0f0f0; min-height: 100vh;">


        <!-- Navbar -->
        <div class="row" id="navbar">
            <div class="col-12">
                <nav class="navbar navbar-expand-lg">
                    <a class="navbar-brand me-auto" href="#">
                        <img src="image/icon.png" alt="Logo" width="85" height="60"
                            class="d-inline-block align-text-top">
                    </a>
                    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar"
                        aria-labelledby="offcanvasNavbarLabel">
                        <div class="offcanvas-header">
                            <h5 class="offcanvas-title" id="offcanvasNavbarLabel">CanchApp</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                                aria-label="Close"></button>
                        </div>
                        <div class="offcanvas-body">
                            <ul class="navbar-nav justify-content-center flex-grow-1 pe-3">
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2 " href="index.php">Inicio</a>
                                </li>
                                <?php if ($rol === 'duenio'): ?>
                                    <li class="nav-item">
                                        <a class="nav-link mx-lg-2" href="gestion.php">Gestión</a>
                                    </li>
                                <?php endif; ?>
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2 active" aria-current="page"
                                        href="buscador.php">Reservar</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2" href="acerca-de.php">Acerca de</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Sistema de login/logout integrado -->
                    <?php if ($nombre): ?>
                        <div class="dropdown">
                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($foto)): ?>
                                    <img src="uploads/usuarios/<?= htmlspecialchars($foto) ?>"
                                        alt="Foto de perfil de <?= htmlspecialchars($nombre) ?>"
                                        class="rounded-circle border border-2 border-white" width="40" height="40"
                                        style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-primary text-white"
                                        style="width: 40px; height: 40px; font-size: 16px; font-weight: bold;">
                                        <?= strtoupper(substr($nombre, 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <h6 class="dropdown-header">¡Hola, <?= htmlspecialchars($nombre) ?>!</h6>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <?php if ($rol === 'usuario'): ?>
                                    <li><a class="dropdown-item" href="perfil_padel.php?from=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                                            Editar Perfil
                                        </a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endif; ?>
                                <li><a class="dropdown-item text-danger" href="logout.php">
                                        Cerrar Sesión
                                    </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="inicioses.php" class="login-button btn btn-primary">Login</a>
                    <?php endif; ?>

                    <button class="navbar-toggler pe-0 ms-2" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar"
                        aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </nav>
            </div>
        </div>
        <!-- Fin Navbar -->

        <!-- Mensaje de error u success -->
        <?php if (!empty($msg)): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-success"><?= $msg ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Volver -->
        <div class="row mt-3">
            <div class="col-12">
                <button class="btn btn-outline-secondary" onclick="window.location.href='buscador.php'">
                    ← Volver al buscador
                </button>
            </div>
        </div>

        <?php if ($cancha): ?>
            <!-- Busca imagen -->
            <div class="row justify-content-center align-items-center p-4 g-4">
                <!-- Imagen de la cancha -->
                <div class="col-md-5 col-sm-12">
                    <div class="position-relative overflow-hidden rounded-4 shadow-lg">
                        <?php if (!empty($cancha['foto'])): ?>
                            <img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" 
                                class="img-fluid w-100 court-image" 
                                alt="<?= htmlspecialchars($cancha['nombre']) ?>"
                                style="object-fit: cover; height: 400px;">
                        <?php else: ?>
                            <img src="image/cancha.jpg" 
                                class="img-fluid w-100 court-image" 
                                alt="<?= htmlspecialchars($cancha['nombre']) ?>"
                                style="object-fit: cover; height: 400px;">
                        <?php endif; ?>
                        <div class="position-absolute top-0 start-0 w-100 h-100" 
                            style="background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.2) 100%);"></div>
                    </div>
                </div>

                <!-- Información de la cancha -->
                <div class="col-lg-7 col-sm-12">
                    <div class="px-lg-4">
                        <!-- Título y descripción -->
                        <h2 class="fw-bold mb-3" style="color: #1a1a2e; font-size: 2.5rem;">
                            <?= htmlspecialchars($cancha['nombre']) ?>
                        </h2>
                        <p class="mb-4 text-muted" style="font-size: 1.1rem; line-height: 1.7;">
                            <?= htmlspecialchars($cancha['bio']) ?>
                        </p>

                        <!-- Cards de información -->
                        <div class="row g-3 mt-4">
                            <!-- Ciudad -->
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 hover-lift">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle mx-auto mb-3" 
                                            style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-compass" viewBox="0 0 16 16">
  <path d="M8 16.016a7.5 7.5 0 0 0 1.962-14.74A1 1 0 0 0 9 0H7a1 1 0 0 0-.962 1.276A7.5 7.5 0 0 0 8 16.016m6.5-7.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
  <path d="m6.94 7.44 4.95-2.83-2.83 4.95-4.949 2.83 2.828-4.95z"/>
</svg>
                                        </div>
                                        <h5 class="fw-semibold mb-2 " style="color: #1a1a2e;">Ciudad</h5>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($cancha['ciudad']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- Dirección -->
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 hover-lift">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle mx-auto mb-3" 
                                            style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-compass" viewBox="0 0 16 16">
  <path d="M8 16.016a7.5 7.5 0 0 0 1.962-14.74A1 1 0 0 0 9 0H7a1 1 0 0 0-.962 1.276A7.5 7.5 0 0 0 8 16.016m6.5-7.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
  <path d="m6.94 7.44 4.95-2.83-2.83 4.95-4.949 2.83 2.828-4.95z"/>
</svg>
                                        </div>
                                        <h5 class="fw-semibold mb-2 " style="color: #1a1a2e;">Direccion</h5>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($cancha['direccion']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Propietario -->
                            <?php if (!empty($cancha['duenio_nombre'])): ?>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 hover-lift">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle mx-auto mb-3" 
                                            style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffffffff 0%rgba(255, 255, 255, 1)6c 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
  <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/>
</svg>
                                        </div>
                                        <h5 class="fw-semibold mb-2" style="color: #1a1a2e;">Propietario</h5>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($cancha['duenio_nombre']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Precio -->
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 hover-lift">
                                    <div class="card-body text-center p-4">
                                        <div class="icon-circle mx-auto mb-3" 
                                            style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-coin" viewBox="0 0 16 16">
  <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518z"/>
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11m0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12"/>
</svg>
                                        </div>
                                        <h5 class="fw-semibold mb-2" style="color: #1a1a2e;">Precio</h5>
                                        <p class="mb-0">
                                            <span class="fs-4 fw-bold" style="color: #000000ff;">$<?= number_format($cancha['precio']) ?></span>
                                            <span class="text-muted"> / hora</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$id_usuario): ?>
    <div class="alert-login">
        <a href="inicioses.php">Inicia sesión</a> para poder reservar esta cancha
    </div>
<?php elseif ($rol === 'duenio'): ?>
    <div class="alert alert-warning text-center" style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0;">
        Los dueños no pueden realizar reservas. Esta función es solo para jugadores.
    </div>
<?php endif; ?>

            <!-- Sistema de reserva-->
            <div class="row mt-4">
                <div class="col-12">
                    <h3 class="text-center mb-4" style= "color: #0B0519" ><?= $id_usuario ? 'Haz clic para reservar' : 'Horarios disponibles' ?></h3>

                    <!-- PARA SELECCIONAR DIAS-->
                    <div class="date-picker-container text-center"
                        style="background-color: #41644A;">
                        <h5 class="mb-3">Selecciona el día</h5>
                        <p class="text-light mb-3">Mostrando: <span
                                id="currentDate"><?= date('d/m/Y', strtotime($fecha_mostrar)) ?></span></p>

                        <div class="d-flex justify-content-center flex-wrap mb-3" id="daySelector">
                            <?php
                            for ($i = 0; $i < 7; $i++) {
                                $fecha_btn = date('Y-m-d', strtotime("+$i days"));
                                $fecha_texto = date('d/m', strtotime("+$i days"));
                                $dia_semana_ingles = date('D', strtotime("+$i days"));
                                $dia_semana = diasespanol($dia_semana_ingles);
                                $clase_activo = ($fecha_btn == $fecha_mostrar) ? 'active' : '';

                                $url_params = "id={$id_cancha}&fecha={$fecha_btn}";

                                echo "<a href='?{$url_params}' class='day-selector {$clase_activo}'>";
                                echo "{$dia_semana}<br>{$fecha_texto}";
                                echo "</a>";
                            }
                            ?>
                        </div>

                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                                <input type="hidden" name="id" value="<?= $id_cancha ?>">
                                <input type="date" name="fecha" class="form-control" style="max-width: 200px;"
                                    value="<?= $fecha_mostrar ?>" min="<?= date('Y-m-d') ?>">
                                <button type="submit" class="btn btn-primary">Ver fecha</button>
                            </form>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="legend justify-content-center">
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #28a745;"></div>
                            <span style= "color: #0B0519">Disponible</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #ffc107;"></div>
                            <span style= "color: #0B0519">Parcialmente ocupado</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #dc3545;"></div>
                            <span style= "color: #0B0519">Ocupado</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #6c757d;"></div>
                            <span style= "color: #0B0519">Hora pasada</span>
                        </div>
                    </div>

                    <!-- Horas disponibles -->
                    <div class="row g-3" id="timeSlots">
                        <?php foreach ($horarios as $hora): //Horarios es una variable que contiene la función generar horarios.?> 
                            <?php
                            $disponibilidad = verificarDisponibilidad($reservas, $hora, $fecha_mostrar, 4);
                            $hora_fin = date('H:i', strtotime($hora . ' +1 hour'));
                            ?>

                            <div class="col-md-6 col-lg-4">
                                <?php if ($disponibilidad['tipo'] === 'pasada'): ?>
                                    <div class="time-slot pasado">
                                        <div class="fs-5 fw-bold"><?= $hora ?></div>
                                        <div class="small">Hora pasada</div>
                                    </div>

                                <?php elseif ($disponibilidad['tipo'] === 'ocupado'): ?>
                                    <div class="time-slot ocupado">
                                        <div class="fs-5 fw-bold"><?= $hora ?></div>
                                        <div class="small">
                                            Completamente ocupado<br>
                                            <?php if ($disponibilidad['categoria_requerida']): ?>
                                                <span class="categoria-badge">Cat. <?= $disponibilidad['categoria_requerida'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                <?php elseif ($disponibilidad['tipo'] === 'parcial'): ?>
                                    <?php
                                    $puede_reservar = ($categoria_usuario == $disponibilidad['categoria_requerida']);
                                    ?>

                                    <?php if ($id_usuario && $puede_reservar): ?>
                                            <a href="reserva.php?id_cancha=<?= $id_cancha ?>&fecha=<?= $fecha_mostrar ?>&hora_inicio=<?= $hora ?>"
                                            class="time-slot parcial">
                                            <div class="fs-5 fw-bold"><?= $hora ?></div>
                                            <div class="small">
                                                <?= $disponibilidad['espacios_disponibles'] ?> espacio(s) disponible(s)
                                                <br>
                                                Categoria <?= $disponibilidad['categoria_requerida'] ?>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div class="time-slot parcial bloqueado">
                                            <div class="fs-5 fw-bold"><?= $hora ?></div>
                                            <div class="small">
                                                <?php if ($id_usuario): ?>
                                                    Solo Categoría <?= $disponibilidad['categoria_requerida'] ?><br>
                                                    <span style="font-size: 10px;">(Tu cat: <?= $categoria_usuario ?>)</span>
                                                <?php else: ?>
                                                    <?= $disponibilidad['espacios_disponibles'] ?> espacio(s) disponible(s)
                                                    <span class="categoria-badge">Cat. <?= $disponibilidad['categoria_requerida'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                <?php else: // disponible ?>
                                    <?php if ($puede_reservar): ?>
                                        <a href="reserva.php?id_cancha=<?= $id_cancha ?>&fecha=<?= $fecha_mostrar ?>&hora_inicio=<?= $hora ?>"
                                            class="time-slot disponible">
                                            <div class="fs-5 fw-bold"><?= $hora ?></div>
                                            <div class="small">Disponible<br>4 espacios libres</div>
                                        </a>
                                    <?php else: ?>
                                        <div class="time-slot disponible">
                                            <div class="fs-5 fw-bold"><?= $hora ?></div>
                                            <div class="small">Disponible<br>4 espacios libres</div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <h3>Cancha no encontrada</h3>
                        <p>La cancha solicitada no existe o no está disponible.</p>
                        <a href="buscador.php" class="btn btn-primary">Ver todas las canchas</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>


        <!----------------------------VALORACIONES DATOS GENERALES  ----------------------------------->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="text-center mb-4"  style= "color:#0B0519">Valoraciones!</h3>

                <?php
                //Datos de las canchas!
                try {
                    $stmt = $pdo->prepare("
                SELECT v.*, u.nombre, u.foto
                FROM valoracion v
                INNER JOIN usuario u ON v.id_usuario = u.id_usuario
                WHERE v.id_cancha = ?
                ORDER BY v.fecha DESC
            ");
                    $stmt->execute([$id_cancha]);
                    $valoraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    //Para calcular el promedio de las valoraciones, tiene jss guarda!!
                    if (!empty($valoraciones)) {
                        $suma_valores = array_sum(array_column($valoraciones, 'valor'));
                        $promedio = $suma_valores / count($valoraciones);
                    }
                } catch (PDOException $e) {
                    error_log("Error al cargar valoraciones: " . $e->getMessage());
                    $valoraciones = [];
                }
                ?>

                <!--ESTRELLAS GENERALES! (LA PARTE DE ARRIBA)-->
                <?php if (!empty($valoraciones)): ?>
                    <div class="text-center mb-4">
                        <div class="d-inline-block p-3  rounded"> <!--Poner bg-light para q sea blanco-->
                            <div class="fs-1 text-warning fw-bold"> 
                                <?= number_format($promedio, 1) ?>
                            </div>
                            <div class="text-warning fs-3">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= round($promedio) ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </div>
                            <div class="small text-dark">
                                Basado en <?= count($valoraciones) ?>
                                <?= count($valoraciones) === 1 ? 'valoración' : 'valoraciones' ?> 
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!--VALORACIONES LO MAYOR FUNCIONAL ESTÁ EN (PROCESAR_VALORACIÓN.PHP)-------------------------------->

        <?php if ($id_usuario): ?>
            <?php
            //Acá se chusmea si ya se valoro la cancha. De que sirve? 
            //1-Para poder editar la cancha en caso que lo desee.
            //2-Para poder cambiar datos que cambien dependiendo si es la primera vez o se edita.
            try {
                $stmt = $pdo->prepare("SELECT * FROM valoracion WHERE id_cancha = ? AND id_usuario = ?");
                $stmt->execute([$id_cancha, $id_usuario]);
                $miValoracion = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Error al verificar valoración: " . $e->getMessage());
                $miValoracion = null; //Variable para ver si está valorado ya o no.
            }
            ?>


            <div class="row mt-5 mb-5 ">
                <div class="col-md-8 offset-md-2" style="border-radius: 10px;" >

                    <h4 class="text-center mb-4" style="color:black">
                        <?= $miValoracion ? 'Editar tu valoración' : 'Deja tu valoración' ?>
                    </h4>

                    <form method="POST" action="procesar_valoracion.php" id="formValoracion" style="background-color: #f0f0f0f0; border-radius: 16Spx">
                        <input type="hidden" name="id_cancha" value="<?= htmlspecialchars($id_cancha) ?>">
                        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($id_usuario) ?>">
                        <input type="hidden" name="modo" value="<?= $miValoracion ? 'editar' : 'nuevo' ?>" id="modoInput">

                        <div class="mb-4 text-center">
                            <label class="form-label fw-bold fs-5" style= "color: #0B0519">Tu puntuación:</label>



                            <!--Estrellitas-->

                            <div id="rating" class="fs-1 text-warning" style="cursor: pointer;">

                                <?php
                                $valorActual = $miValoracion['valor'] ?? 0;
                                for ($i = 1; $i <= 5; $i++):
                                    ?>

                                    <span class="star <?= $i <= $valorActual ? 'selected' : '' ?>" data-value="<?= $i ?>"
                                        style="transition: all 0.2s;">
                                        <?= $i <= $valorActual ? '★' : '☆' ?>
                                    </span>

                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="valor" id="valor" value="<?= $valorActual ?>" required>
                            <div id="error-rating" class="text-danger small mt-2" style="display: none;">
                                Por favor seleccioná alguna estrella!
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="comentario" class="form-label fw-bold" style= "color: #0B0519">Opiniones!</label>
                            <textarea name="comentario" id="comentario" class="form-control" rows="4" maxlength="777"
                                placeholder="Escribí tu experiencia con esta cancha...tu opinion realmente nos importa!"><?= htmlspecialchars($miValoracion['comentario'] ?? '') ?></textarea>
                            <div class="form-text">Máximo 777 caracteres</div>
                        </div>


                        <!--Boton Actualizar o mandar valoración, (la cagada esta se mueve cuando pones el clicke ncima y ns pq)-->

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary  px-5" id="btnEnviar">
                                <?= $miValoracion ? 'Actualizar valoración' : 'Enviar valoración' ?>
                            </button>

                            <!--Boton eliminar valoración!-->
                            <?php if ($miValoracion): ?>
                                <button type="button" class="btn btn-outline-danger px-4" id="btnEliminar">
                                    Eliminar
                                </button>
                            <?php endif; ?>

                        </div>

                    </form>

                </div>

            </div>

            <!-----JavaScript para las estrellitass----->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const stars = document.querySelectorAll('.star');
                const valorInput = document.getElementById('valor');
                const errorRating = document.getElementById('error-rating');
                const form = document.getElementById('formValoracion');
                const btnEliminar = document.getElementById('btnEliminar');

                //--------------------------Manejar clic en estrellas
                stars.forEach(star => {
                    //Pal jover
                    star.addEventListener('mouseenter', function () {
                        const value = parseInt(this.dataset.value);
                        updateStars(value, false);
                    });

                    // Click para seleccionar
                    star.addEventListener('click', function () {
                        const value = parseInt(this.dataset.value);
                        valorInput.value = value;
                        updateStars(value, true);
                        errorRating.style.display = 'none';
                    });
                });

                //--------------------Restaura estrellitas al salir del hover
                document.getElementById('rating').addEventListener('mouseleave', function () {
                    const currentValue = parseInt(valorInput.value) || 0;
                    updateStars(currentValue, true);
                });

                //----------------------Función para actualizar las estrellitas visualmente
                function updateStars(value, permanent) {
                    stars.forEach(star => {
                        const starValue = parseInt(star.dataset.value);
                        if (starValue <= value) {
                            star.textContent = '★';
                            star.style.color = '#ffc107';
                            if (permanent) star.classList.add('selected');
                        } else {
                            star.textContent = '☆';
                            star.style.color = '#ccc';
                            if (permanent) star.classList.remove('selected');
                        }
                    });
                }

                //----------------------Validar formulario antes de enviar
                if (form) {
                    form.addEventListener('submit', function (e) {
                        if (!valorInput.value || valorInput.value < 1 || valorInput.value > 5) {
                            e.preventDefault();
                            errorRating.style.display = 'block';
                            errorRating.textContent = 'Por favor selecciona una puntuación entre 1 y 5 estrellas';
                            document.getElementById('rating').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                }

                //-------------Botón de eliminar
                if (btnEliminar) {
                    btnEliminar.addEventListener('click', function () {
                        const modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
                        modal.show();
                    });
                }
            });
        </script>

        <style>
            .star {
                cursor: pointer;
                font-size: 3rem;
                color: #ccc;
                transition: all 0.2s ease;
                display: inline-block;
                user-select: none;
            }

            .star:hover {
                transform: scale(1.2);
            }

            .star.selected {
                color: #ffc107;
            }

            #modalEliminar {
                z-index: 9999 !important;
            }

            #modalEliminar .modal-backdrop {
                z-index: 9998 !important;
            }

            .modal-backdrop.show {
                z-index: 9998 !important;
            }
        </style>

        <?php else: ?>
        <div class="row mt-5 mb-5">
            <div class="col-md-8 offset-md-2">
                <div class="alert alert-info text-center shadow">
                    <h5>¿Quieres dejar tu valoración?</h5>
                    <p class="mb-3">Inicia sesión para compartir tu experiencia con esta cancha</p>
                    <a href="inicioses.php" class="btn btn-primary btn-lg">Iniciar Sesión</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

                <!--COMENTARIOS Y VALORACIONES DE USUARIOS-->
<div class="row" style="background-color: #f0f0f0f0; border-radius: 16px;">
    <div class="col-md-10 offset-md-1">
        <?php if (!empty($valoraciones)): ?>
            <div id="contenedorValoraciones">
                <?php foreach ($valoraciones as $index => $v): ?>
                    <div class="valoracion-item border-0 <?= $index >= 1 ? 'valoracion-oculta' : '' ?>" 
                         style="<?= $index >= 1 ? 'display: none;' : '' ?>">
                        <div class="card-body" style="background-color: #f0f0f0;">
                            <div class="d-flex align-items-center mb-2" style="background-color: #f0f0f0;">

                                <!--FOTO DEL USUARIO PARA EL COMENTARIO-->
                                <?php if (!empty($v['foto'])): ?>
                                    <a href="perfil_otro.php?id=<?= $v['id_usuario'] ?>">
                                        <img src="uploads/usuarios/<?= htmlspecialchars($v['foto']) ?>"
                                            alt="<?= htmlspecialchars($v['nombre']) ?>" class="rounded-circle me-3 text-dark"
                                            width="50" height="50" style="object-fit: cover;">
                                    </a>
                                <?php else: ?>
                                    <a href="perfil_otro.php?id=<?= $v['id_usuario'] ?>" style="text-decoration: none;">
                                        <div class="rounded-circle text-dark me-3 d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px; font-size: 20px; font-weight: bold; background-color: 0D4715;">
                                            <?= strtoupper(substr($v['nombre'], 0, 1)) ?>
                                        </div>
                                    </a>
                                <?php endif; ?>

                                <div class="flex-grow-1">
                                    <a href="perfil_otro.php?id=<?= $v['id_usuario'] ?>" style="color: inherit; text-decoration: none;">
                                        <h5 class="mb-0 text-dark"><?= htmlspecialchars($v['nombre']) ?></h5>
                                    </a>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= $v['valor'] ? '★' : '☆' ?>
                                        <?php endfor; ?>
                                        <span class="text-dark ms-2 small">
                                            <?= date('d/m/Y', strtotime($v['fecha'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($v['comentario'])): ?>
                                <p class="card-text mb-0" style="color: #0B0519"><?= nl2br(htmlspecialchars($v['comentario'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            

            <!--BOTON PARA VER MAS Y SU JAVASCRIPT!---------------------------------------------------------------------------------------------------->
            <?php if (count($valoraciones) > 1): ?>
                <div class="text-center mt-4 mb-3">
                    <button id="btnVerMas" class="btn btn-outline-primary px-5" onclick="toggleValoraciones()">
                        Ver más valoraciones (<?= count($valoraciones) - 1 ?> más)
                    </button>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <p class="fs-5" style="color: #0B0519">Todavía no hay valoraciones para esta cancha.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// JavaScript para Ver más/menos valoraciones
function toggleValoraciones() {
    const valoracionesOcultas = document.querySelectorAll('.valoracion-oculta');
    const btnVerMas = document.getElementById('btnVerMas');
    const totalOcultas = valoracionesOcultas.length;
    
    // Verificar si están ocultas o visibles
    const primerElemento = valoracionesOcultas[0];
    const estaOculto = primerElemento.style.display === 'none';
    
    if (estaOculto) {
        // Mostrar todas
        valoracionesOcultas.forEach(val => {
            val.style.display = 'block';
        });
        btnVerMas.textContent = 'Ver menos';
        btnVerMas.classList.remove('btn');
        btnVerMas.classList.add('btn');
    } else {
        // Ocultar
        valoracionesOcultas.forEach(val => {
            val.style.display = 'none';
        });
        btnVerMas.textContent = `Ver más valoraciones (${totalOcultas} más)`;
        btnVerMas.classList.remove('btn');
        btnVerMas.classList.add('btn');
        
        // Scroll suave hacia las valoraciones
        document.querySelector('.row.mt-5 h3').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
}
</script>



        

        <!-- Footer -->
        <footer class="mt-5">
            <div class="row p-5 bg-secondary text-white">
                <div class="col-xs-12 col-md-6 col-lg-3 mb-3">
                    <h3 class="mb-2">CanchApp</h3>
                    <p>Tu sitio de confianza para reservar y gestionar canchas de pádel.</p>
                </div>
                <div class="col-xs-12 col-md-6 col-lg-3 mb-3">
                    <h5 class="mb-2">Enlaces</h5>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Inicio</a>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Sobre Nosotros</a>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Servicios</a>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Contacto</a>
                </div>
                <div class="col-xs-12 col-md-6 col-lg-3 mb-3">
                    <h5 class="mb-2">Contacto</h5>
                    <p class="mb-1">Email: info@canchapp.com</p>
                    <p class="mb-1">Tel: +54 11 1234-5678</p>
                    <p class="mb-1">Dirección: Av. Pádel 123, Buenos Aires</p>
                </div>
                <div class="col-xs-12 col-md-6 col-lg-3 mb-3">
                    <h5 class="mb-2">Síguenos</h5>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Instagram</a>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Facebook</a>
                    <a href="#" class="d-block text-white text-decoration-none mb-1">Twitter</a>
                </div>
            </div>
            <div class="row bg-dark text-white text-center py-2">
                <div class="col-12">
                    <small>&copy; 2025 CanchApp. Todos los derechos reservados.</small>
                </div>
            </div>
        </footer>
    </div>




    <!--MODAL DE ELIMINACIÓN------------------------------------------------------------------------------------>
    <!--Está abajo del todo pq sino me tira eeror :( -->
    <?php if ($id_usuario && $miValoracion): ?>
        <div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center">
                        <h5 class="mb-3">¿Estás seguro de que deseas eliminar tu valoración?</h5>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                            No!
                        </button>
                        <form method="POST" action="procesar_valoracion.php" class="d-inline">
                            <input type="hidden" name="id_cancha" value="<?= htmlspecialchars($id_cancha) ?>">
                            <input type="hidden" name="modo" value="eliminar">
                            <button type="submit" class="btn btn-danger px-4">
                                Si!
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>