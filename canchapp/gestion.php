<?php
session_start();
require_once 'conexiones/conDB.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'duenio') {
    die("Solo los dueños pueden acceder a esta página.");
}

$id_duenio = $_SESSION['id'];
$msg = '';
$error = '';

// Obtener canchas del dueño
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            COUNT(v.id_valoracion) as total_valoraciones,
            AVG(v.valor) as promedio_valoraciones,
            ROUND(AVG(v.valor), 1) as promedio_redondeado
        FROM cancha c
        LEFT JOIN valoracion v ON c.id_cancha = v.id_cancha
        WHERE c.id_duenio = ? 
        GROUP BY c.id_cancha
        ORDER BY c.nombre
    ");
    $stmt->execute([$id_duenio]);
    $miscanchas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar canchas: " . $e->getMessage();
    $miscanchas = [];
}

//-----------------------------------------------------------------------

//EDITAR CANCHAS!!

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'editar') { //Utilizo el mismo filtro que al crear cancha pero en este caso saco su Id, y remplazo los datos utilizando esa ID.
    $id = $_POST['id_cancha'];
    $nombre = trim($_POST['nombre']);
    $lugar  = trim($_POST['lugar']);
    $bio  = trim($_POST['bio']);
    $precio = trim($_POST['precio']);

    if ($nombre === '' || $lugar === '' || $bio === '' || $precio === '' ) {
        $msgError[$id] = "Completa todos los campos.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM cancha WHERE nombre = ? AND id_cancha <> ?"); //<> permite que ponele, si qerés editar la cancha y dejás el mismo nombre, que no te mande q ya existe, sino q entienda q no la cambiaste.
            $stmt->execute([$nombre, $id]);

            if ($stmt->fetch()) {
                $msgError[$id] = "Ya existe otra cancha con ese nombre.";
            } else {
                $stmt = $pdo->prepare("UPDATE cancha SET nombre = ?, lugar = ?, bio = ?, precio = ? WHERE id_cancha = ?");
                $stmt->execute([$nombre, $lugar, $bio, $precio, $id]);
                $msgOk[$id] = "Cancha editada correctamente.";

                $_SESSION['msgOk'] = "Cancha editada correctamente.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit;
            }
        } catch (Throwable $e) {
            $msgError[$id] = "Error: " . $e->getMessage();
        }
    }
}
//-------------------------------------------------------------------------------------------

// Cancelar reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_reserva'])) {
    $id_reserva = $_POST['id_reserva'] ?? '';
    
    if (!empty($id_reserva)) {
        try {
            $stmt = $pdo->prepare("
                SELECT r.* FROM reserva r
                INNER JOIN cancha c ON r.id_cancha = c.id_cancha
                WHERE r.id_reserva = ? AND c.id_duenio = ? AND r.estado = 'activa'
            ");
            $stmt->execute([$id_reserva, $id_duenio]);
            $reserva = $stmt->fetch();
            
            if ($reserva) {
                $stmt = $pdo->prepare("UPDATE reserva SET estado = 'cancelada' WHERE id_reserva = ?");
                $stmt->execute([$id_reserva]);
                $msg = "Reserva cancelada correctamente. Código: " . $reserva['codigo_reserva'];
            } else {
                $error = "No tienes permisos para cancelar esta reserva o ya está cancelada.";
            }
        } catch (PDOException $e) {
            $error = "Error al cancelar la reserva: " . $e->getMessage();
        }
    }
}

// Obtener reservas del dueño
function obtenerreservasduenio($pdo, $id_duenio, $fecha_desde = null, $filtro_estado = 'todas') {
    $fecha_desde = $fecha_desde ?: date('Y-m-d');
    
    $sql = "
        SELECT 
            r.id_reserva,
            r.codigo_reserva,
            r.fecha,
            r.hora_inicio,
            r.hora_final,
            r.espacios_reservados,
            r.telefono,
            r.observaciones,
            r.estado,
            c.nombre as cancha_nombre,
            c.lugar as cancha_lugar,
            u.nombre as usuario_nombre,
            u.email as usuario_email
        FROM reserva r
        INNER JOIN cancha c ON r.id_cancha = c.id_cancha
        INNER JOIN usuario u ON r.id_usuario = u.id_usuario
        WHERE c.id_duenio = ? AND r.fecha >= ?
    ";
    
    $params = [$id_duenio, $fecha_desde];
    
    if ($filtro_estado !== 'todas') {
        $sql .= " AND r.estado = ?";
        $params[] = $filtro_estado;
    }
    
    $sql .= " ORDER BY r.fecha ASC, r.hora_inicio ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$filtro_estado = $_GET['estado'] ?? 'activa';
$reservas = obtenerreservasduenio($pdo, $id_duenio, $_GET['desde'] ?? null, $filtro_estado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Dueño</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .main-container {
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #3f9c43ff 0%, #52a24bff 100%);
        }
        
        .navbar-brand img {
            filter: brightness(0) invert(1);
        }
        
        .nav-link {
            color: black !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: #ffd43b !important;
            transform: translateY(-2px);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #3f9c43ff 0%, #52a24bff 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 2rem;
        }
        
        .section-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3f9c43ff, #52a24bff);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #35803a, #468a42);
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            border: none;
            color: #212529;
            font-weight: 500;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            color: #212529;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background: #495057;
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 500;
        }
        
        .table tbody td {
            padding: 1rem;
            border-color: #f8f9fa;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .codigo-reserva {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: bold;
            letter-spacing: 1px;
            font-family: 'Courier New', monospace;
        }
        
        .cancha-card {
            transition: transform 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .filtros-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #3f9c43ff, #52a24bff);
            color: white;
            border: none;
        }
        
        .nav-tabs .nav-link:hover {
            background: #e9ecef;
            border: none;
        }
        
        .nav-tabs .nav-link.active:hover {
            background: linear-gradient(135deg, #3f9c43ff, #52a24bff);
        }
    </style>
</head>

<body>
    <div class="container-fluid main-container">
        <!-- Navbar -->
        <div class="row" id="navbar">
            <div class="col-12">
                <nav class="navbar navbar-expand-lg">
                    <a class="navbar-brand me-auto" href="#">
                        <img src="image/icon.png" alt="Logo" width="85" height="60" class="d-inline-block align-text-top">
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                        <div class="offcanvas-header">
                            <h5 class="offcanvas-title" id="offcanvasNavbarLabel">CanchApp</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                        </div>
                        <div class="offcanvas-body">
                            <ul class="navbar-nav justify-content-center flex-grow-1 pe-3">
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2" href="index.php">Index</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2 active" aria-current="page" href="gestion_reservas.php">Gestión</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2" href="cancha.php">Reservar</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link mx-lg-2" href="acerca-de.html">Acerca de</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <div class="container">
                <div class="text-center">
                    <h1 class="display-4 mb-3">Panel de Control</h1>
                    <p class="lead">Gestiona tus canchas y reservas</p>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Mensajes -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabs Para ver reservas y canchas --------------------------------->
            <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="courts-tab" data-bs-toggle="tab" data-bs-target="#courts" type="button" role="tab">
                        <i class="fas fa-tennis-ball me-2"></i>Canchas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">
                        <i class="fas fa-calendar-alt me-2"></i>Reservas
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="managementTabsContent">





                <!-- Tab de Canchas -------------------------------------------------------------------------------------->
                <div class="tab-pane fade show active" id="courts" role="tabpanel">
                    <div class="section-card">
                        <div class="section-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="mb-0">
                                    <i class="fas fa-tennis-ball me-2"></i>
                                    Mis Canchas
                                </h3>
                                <div>
                                    <a href="dueño.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Nueva Cancha
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($miscanchas)): ?>
                                <div class="row">
                                    <?php foreach ($miscanchas as $cancha): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card cancha-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="card-title"><?= htmlspecialchars($cancha['nombre']) ?></h5>
                                                        <span class="badge bg-success status-badge">Activa</span>
                                                    </div>
                                                    <p class="card-text text-muted mb-2">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars($cancha['lugar']) ?>
                                                    </p>
                                                    <p class="card-text mb-2">
                                                        <strong>Precio:</strong> $<?= number_format($cancha['precio'], 0, ',', '.') ?> por hora
                                                    </p>
                                                    <p class="card-text mb-3">
                                                        <strong>Capacidad:</strong> 4 jugadores
                                                    </p>
                                                    
                                                    <div class="btn-group w-100">
                                                        <form method="post" action="eliminar_cancha.php" style="display:inline;" onsubmit="return confirm('¿Seguro que querés eliminar esta cancha?');">
                                                            <input type="hidden" name="borrarcancha" value="<?= (int)$cancha['id_cancha'] ?>">
                                                            <button type="submit">Borrar</button>
                                                        </form>
                                                        <!--BOTON PARA EDITAR-->
                        <button onclick="abrirModal(
                        '<?= $cancha['id_cancha'] ?>',
                        '<?= htmlspecialchars($cancha['nombre']) ?>',
                        '<?= htmlspecialchars($cancha['lugar']) ?>',
                        '<?= htmlspecialchars($cancha['bio']) ?>',
                        '<?= htmlspecialchars($cancha['precio']) ?>',
                        '<?= htmlspecialchars($cancha['foto']) ?>'
                        )">Editar</button>

                        <!--POPUP PARA EDITAR CANCHA-->
                        <div id="modalEditar" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
                        <div style="background:#fff; padding:20px; margin:10% auto; width:300px; border-radius:10px;">
                            <h2>Editar Cancha</h2>
                            <form method="post">
                            <input type="hidden" name="id_cancha" id="edit_id">
                            <input type="text" name="nombre" id="edit_nombre" required><br><br>
                            <input type="text" name="lugar" id="edit_lugar" required><br><br>
                            <input type="text" name="bio" id="edit_bio" required><br><br>
                            <input type="integer" name="precio" id="edit_precio" required><br><br>
                            <input type="file" name="foto" id="edit_foto"><br><br>
                            <button type="submit" name="accion" value="editar">Guardar</button>
                            <button type="button" onclick="cerrarModal()">Cancelar</button>
                            </form>
                        </div>
                        </div>


                        <!--SCRIPT PARA EL POPUP-->
                        <script>
                        function abrirModal(id, nombre, lugar, bio, precio, foto) {
                        document.getElementById('modalEditar').style.display = 'block';
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_nombre').value = nombre;
                        document.getElementById('edit_lugar').value = lugar;
                        document.getElementById('edit_bio').value = bio;
                        document.getElementById('edit_precio').value = precio;
                        document.getElementById('edit_foto').value = foto;
                        }
                        function cerrarModal() {
                        document.getElementById('modalEditar').style.display = 'none';
                        }
                        </script>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-tennis-ball fa-3x text-muted mb-3"></i>
                                    <h4>No tienes canchas registradas</h4>
                                    <p class="text-muted">Crea tu primera cancha para comenzar a recibir reservas.</p>
                                    <a href="dueño.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-plus me-2"></i>Crear Primera Cancha
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab de Reservas ---------------------------------------->
                <div class="tab-pane fade" id="bookings" role="tabpanel">
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Gestión de Reservas
                            </h3>
                        </div>
                        <div class="card-body">
                            <!-- Filtros ------------------>
                            <div class="filtros-section">
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <label for="fechaDesde" class="form-label">Desde:</label>
                                        <input type="date" class="form-control" id="fechaDesde" name="desde" value="<?= $_GET['desde'] ?? date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="estadoFiltro" class="form-label">Estado:</label>
                                        <select class="form-select" id="estadoFiltro" name="estado">
                                            <option value="todas" <?= $filtro_estado === 'todas' ? 'selected' : '' ?>>Todas</option>
                                            <option value="activa" <?= $filtro_estado === 'activa' ? 'selected' : '' ?>>Activas</option>
                                            <option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-primary" onclick="filtrarReservas()">
                                            <i class="fas fa-search me-2"></i>Filtrar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($reservas)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Cancha</th>
                                                <th>Cliente</th>
                                                <th>Fecha</th>
                                                <th>Horario</th>
                                                <th>Espacios</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reservas as $reserva): ?>
                                                <tr>
                                                    <td>
                                                        <span class="codigo-reserva"><?= htmlspecialchars($reserva['codigo_reserva']) ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($reserva['cancha_nombre']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($reserva['cancha_lugar']) ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($reserva['usuario_nombre']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($reserva['usuario_email']) ?></small>
                                                    </td>
                                                    <td><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></td>
                                                    <td>
                                                        <?= date('H:i', strtotime($reserva['hora_inicio'])) ?> - 
                                                        <?= date('H:i', strtotime($reserva['hora_final'])) ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $reserva['espacios_reservados'] ?>/4</span>
                                                    </td>
                                                    <td>
                                                        <?php if ($reserva['estado'] === 'activa'): ?>
                                                            <span class="badge bg-success">Activa</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Cancelada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($reserva['estado'] === 'activa'): ?>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de cancelar esta reserva?')">
                                                                <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                                                <button type="submit" name="cancelar_reserva" class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-times me-1"></i>Cancelar
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h4>No hay reservas</h4>
                                    <p class="text-muted">No se encontraron reservas con los filtros seleccionados.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function filtrarReservas() {
            const fechaDesde = document.getElementById('fechaDesde').value;
            const estado = document.getElementById('estadoFiltro').value;
            
            let url = window.location.pathname + '?';
            const params = [];
            
            if (fechaDesde) {
                params.push('desde=' + encodeURIComponent(fechaDesde));
            }
            if (estado) {
                params.push('estado=' + encodeURIComponent(estado));
            }
            
            window.location.href = url + params.join('&');
        }
    </script>
</body>
</html>