<?php
session_start();
require_once 'conexiones/conDB.php';
$nombre = $_SESSION['nombre'] ?? null; //Si existe el nombre y rol que lo asigne, sino q no ponga nada. Asi la gente sin iniciar sesion puede entrar.
$rol = $_SESSION['rol'] ?? null;
$foto = $_SESSION['foto'] ?? null; // Obtener la foto de la sesión
date_default_timezone_set('America/Argentina/Buenos_Aires');

$id_usuario = $_SESSION['id'] ?? null;
$msg = '';
$error = '';

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
//----------------------------------------------------------------

// Sacamos ID de cancha
$id_cancha = $_GET['id'] ?? null;

$fecha_mostrar = $_GET['fecha'] ?? date('Y-m-d');

// Sacamos datos medaunte esa ID
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

// Se generan reservaciones para horario y dia especifico
function obtenerreservas($pdo, $id_cancha, $fecha) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nombre as usuario_nombre 
        FROM reserva r 
        INNER JOIN usuario u ON r.id_usuario = u.id_usuario 
        WHERE r.id_cancha = ? AND r.fecha = ? AND r.estado = 'activa'
        ORDER BY r.hora_inicio
    ");
    $stmt->execute([$id_cancha, $fecha]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Se crean llos horarios disponisbles
function generarhorarios() {
    $horarios = [];
    for ($h = 8; $h <= 22; $h++) {
        $horarios[] = sprintf("%02d:00", $h);
    }
    return $horarios;
}

// Se fija si el luggar está ocupado y los espacios.
function verificarDisponibilidad($reservas, $hora, $fecha_mostrar, $espacios_total = 4) {
    $hora_fin = date('H:i', strtotime($hora . ' +1 hour'));
    $espacios_ocupados = 0;
    $reservas_en_horario = [];

    // Verificar si la fecha es hoy y la hora ya pasó
    if ($fecha_mostrar === date('Y-m-d')) {
        $hora_actual_ts = strtotime(date('Y-m-d H:i'));
        $hora_slot_ts   = strtotime($fecha_mostrar . ' ' . $hora);

        if ($hora_slot_ts <= $hora_actual_ts) {
            return ['tipo' => 'pasada', 'mensaje' => 'Hora pasada'];
        }
    }

    //Contar espacios ocupados en este horario
    foreach ($reservas as $reserva) {
        $r_inicio = substr($reserva['hora_inicio'], 0, 5);
        $r_final  = substr($reserva['hora_final'], 0, 5);

        // Verificar si hay conflicto de horarios
        if (
            ($hora >= $r_inicio && $hora < $r_final) ||
            ($hora_fin > $r_inicio && $hora_fin <= $r_final) ||
            ($hora <= $r_inicio && $hora_fin >= $r_final)
        ) {
            $espacios_reservados = $reserva['espacios_reservados'] ?? 1;
            $espacios_ocupados += $espacios_reservados;
            $reservas_en_horario[] = [
                'usuario' => $reserva['usuario_nombre'],
                'espacios' => $espacios_reservados,
                'jugadores' => $reserva['jugadores_reservados'] ?? 1
            ];
        }
    }

    $espacios_disponibles = $espacios_total - $espacios_ocupados;

    // Determinar el estado del horario
    if ($espacios_disponibles <= 0) {
        return [
            'tipo' => 'ocupado',
            'espacios_disponibles' => 0,
            'espacios_ocupados' => $espacios_ocupados,
            'reservas' => $reservas_en_horario
        ];
    } elseif ($espacios_disponibles < $espacios_total) {
        return [
            'tipo' => 'parcial',
            'espacios_disponibles' => $espacios_disponibles,
            'espacios_ocupados' => $espacios_ocupados,
            'reservas' => $reservas_en_horario
        ];
    } else {
        return [
            'tipo' => 'disponible',
            'espacios_disponibles' => $espacios_disponibles,
            'espacios_ocupados' => 0,
            'reservas' => []
        ];
    }
}

//Pasa de ingles a españolds
function diasespanol($dia_ingles) {
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
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
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
        
        .time-slot.parcial:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
            color: #000;
            text-decoration: none;
        }
        
        .time-slot.ocupado {
            background-color: #dc3545;
            cursor: not-allowed;
        }
        
        .time-slot.pasado {
            background-color: #6c757d;
            cursor: not-allowed;
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
    <div class="container-fluid text-light p-2">

    
<!-- Navbar -->
    <div class="row" id="navbar">
      <div class="col-12">
        <nav class="navbar navbar-expand-lg">
          <a class="navbar-brand me-auto" href="#">
            <img src="image/icon.png" alt="Logo" width="85" height="60" class="d-inline-block align-text-top">
          </a>
          <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar"
            aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
              <h5 class="offcanvas-title" id="offcanvasNavbarLabel">CanchApp</h5>
              <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
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
                  <a class="nav-link mx-lg-2 active" aria-current="page" href="buscador.php">Reservar</a>
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
                       class="rounded-circle border border-2 border-white" 
                       width="40" 
                       height="40" 
                       style="object-fit: cover;">
                <?php else: ?>
                  <div
                    class="rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-primary text-white"
                    style="width: 40px; height: 40px; font-size: 16px; font-weight: bold;">
                    <?= strtoupper(substr($nombre, 0, 1)) ?>
                  </div>
                <?php endif; ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">¡Hola, <?= htmlspecialchars($nombre) ?>!</h6></li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($rol === 'usuario'): ?>
                  <li><a class="dropdown-item" href="perfil_padel.php">
                    <i class="fas fa-user me-2"></i>Editar Perfil
                  </a></li>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><a class="dropdown-item text-danger" href="logout.php">
                  <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                </a></li>
              </ul>
            </div>
          <?php else: ?>
            <a href="inicioses.php" class="login-button btn btn-primary">Login</a>
          <?php endif; ?>
          
          <button class="navbar-toggler pe-0 ms-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
        </nav>
      </div>
    </div>
    <!-- Fin Navbar -->

        <!-- Mensage de error u success -->
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
                <button class="btn btn-outline-secondary" onclick="history.back()">
                    ← Volver al buscador
                </button>
            </div>
        </div>

        <?php if ($cancha): ?>
        <!-- Busca imagen -->
        <div class="row mt-4">
            <div class="col-12">
                <?php if (!empty($cancha['foto'])): ?>
                    <img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" 
                         class="img-fluid w-100 court-image" 
                         alt="<?= htmlspecialchars($cancha['nombre']) ?>">
                <?php else: ?>
                    <img src="image/cancha.jpg" 
                         class="img-fluid w-100 court-image" 
                         alt="<?= htmlspecialchars($cancha['nombre']) ?>">
                <?php endif; ?>
            </div>
        </div>

        <!--Informacoion de la cancha -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="text-center">
                    <h2 class="text-primary mb-3"><?= htmlspecialchars($cancha['nombre']) ?></h2>
                    <p class="text-info mb-2"><span><?= htmlspecialchars($cancha['lugar']) ?></span></p>
                    <p class="lead mb-4">
                        <?= htmlspecialchars($cancha['bio']) ?>
                    </p>
                    <?php if (!empty($cancha['duenio_nombre'])): ?>
                        <p class="text-info mb-2">Dueño: <?= htmlspecialchars($cancha['duenio_nombre']) ?></p>
                    <?php endif; ?>
                    <p class="text-success mb-4">Precio: $<?= number_format($cancha['precio']) ?> por hora</p>
                </div>
            </div>
        </div>

        <?php if (!$id_usuario): ?>
            <div class="alert-login">
                <a href="inicioses.php">Inicia sesión</a> para poder reservar esta cancha
            </div>
        <?php endif; ?>

        <!-- Sistema de reserva-->
        <div class="row mt-4">
            <div class="col-12">
                <h3 class="text-center mb-4"><?= $id_usuario ? 'Haz clic para reservar' : 'Horarios disponibles' ?></h3>
                
                <!-- PARA SELECCIONAR DIAS-->
                <div class="date-picker-container text-center" style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-position: center;">
                    <h5 class="mb-3">Selecciona el día</h5>
                    <p class="text-light mb-3">Mostrando: <span id="currentDate"><?= date('d/m/Y', strtotime($fecha_mostrar)) ?></span></p>
                    
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
                            <input type="date" name="fecha" class="form-control" style="max-width: 200px;" value="<?= $fecha_mostrar ?>" min="<?= date('Y-m-d') ?>">
                            <button type="submit" class="btn btn-primary">Ver fecha</button>
                        </form>
                    </div>
                </div>

                <!-- Legend -->
                <div class="legend justify-content-center">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #28a745;"></div>
                        <span>Disponible</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #ffc107;"></div>
                        <span>Parcialmente ocupado</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #dc3545;"></div>
                        <span>Ocupado</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #6c757d;"></div>
                        <span>Hora pasada</span>
                    </div>
                </div>

                <!--Horas disponivles -->
                <div class="row g-3" id="timeSlots">
                    <?php foreach ($horarios as $hora): ?>
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
                                        <?= count($disponibilidad['reservas']) ?> reserva(s) activa(s)
                                    </div>
                                </div>
                                
                            <?php elseif ($disponibilidad['tipo'] === 'parcial'): ?>
                                <?php if ($id_usuario): ?>
                                    <a href="reserva.php?id_cancha=<?= $id_cancha ?>&fecha=<?= $fecha_mostrar ?>&hora_inicio=<?= $hora ?>" 
                                       class="time-slot parcial">
                                        <div class="fs-5 fw-bold"><?= $hora ?></div>
                                        <div class="small">
                                            <?= $disponibilidad['espacios_disponibles'] ?> espacio(s) disponible(s)<br>
                                            <?= $disponibilidad['espacios_ocupados'] ?> espacio(s) ocupado(s)
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="time-slot parcial">
                                        <div class="fs-5 fw-bold"><?= $hora ?></div>
                                        <div class="small">
                                            <?= $disponibilidad['espacios_disponibles'] ?> espacio(s) disponible(s)<br>
                                            <?= $disponibilidad['espacios_ocupados'] ?> espacio(s) ocupado(s)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: // disponible ?>
                                <?php if ($id_usuario): ?>
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
        <div>

        <!--proximas valoraciones y comentarios------------------------>
            <h1 class="text-center mb-4">VALORACIONES (no hecho)</h1>


        </div>

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
                    <small>&copy; 2024 CanchApp. Todos los derechos reservados.</small>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>