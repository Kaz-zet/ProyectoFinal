<?php
session_start();
require_once 'conexiones/conDB.php';

// Solo usuarios pueden ver su perfil
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'usuario') {
    die("Solo los usuarios pueden ver su perfil.");
}

$id_usuario = $_SESSION['id'];
$msg = '';
$error = '';

// Obtener datos del usuario
try {
    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        die("Usuario no encontrado.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// CANCELAR RESERVA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_reserva'])) {
    $id_reserva = $_POST['id_reserva'] ?? '';
    
    if (!empty($id_reserva)) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM reserva 
                WHERE id_reserva = ? AND id_usuario = ? AND estado = 'activa'
            ");
            $stmt->execute([$id_reserva, $id_usuario]);
            $reserva = $stmt->fetch();
            
            if ($reserva) {
                $fecha_hora_reserva = $reserva['fecha'] . ' ' . $reserva['hora_inicio'];
                $ts_reserva = strtotime($fecha_hora_reserva);
                $ts_actual = time();
                
                if ($ts_reserva > $ts_actual) {
                    $stmt = $pdo->prepare("UPDATE reserva SET estado = 'cancelada' WHERE id_reserva = ?");
                    $stmt->execute([$id_reserva]);
                    
                    $msg = "Reserva cancelada exitosamente. Código: " . $reserva['codigo_reserva'];
                } else {
                    $error = "No puedes cancelar una reserva que ya comenzó o pasó.";
                }
            } else {
                $error = "Reserva no encontrada o no tienes permisos para cancelarla.";
            }
        } catch (PDOException $e) {
            $error = "Error al cancelar la reserva: " . $e->getMessage();
        }
    }
}

// ACTUALIZAR DATOS PERSONALES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_datos'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $categoria = intval($_POST['categoria'] ?? 0);
    $posicion = trim($_POST['posicion'] ?? '');


    if ($nombre === '' || $email === '') {
        $error = 'El nombre y email son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = ? AND id_usuario != ?");
            $stmt->execute([$email, $id_usuario]);
            
            if ($stmt->fetch()) {
                $error = 'Este email ya está en uso por otro usuario.';
            } else {
                // Manejar subida de foto
                $foto_final = $usuario['foto'];
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                    $maxSize = 5 * 1024 * 1024;
                    
                    if (!in_array($_FILES['foto']['type'], $allowedTypes)) {
                        $error = 'Solo se permiten archivos JPG, JPEG y PNG.';
                    } elseif ($_FILES['foto']['size'] > $maxSize) {
                        $error = 'El archivo es muy grande. Máximo 5MB.';
                    } else {
                        if (!file_exists('uploads/usuarios')) {
                            mkdir('uploads/usuarios', 0777, true);
                        }
                        
                        if ($usuario['foto'] && file_exists('uploads/usuarios/' . $usuario['foto'])) {
                            unlink('uploads/usuarios/' . $usuario['foto']);
                        }
                        
                        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $filename = 'usuario_' . $id_usuario . '_' . time() . '.' . $extension;
                        $uploadPath = 'uploads/usuarios/' . $filename;
                        
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
                            $foto_final = $filename;
                        } else {
                            $error = 'Error al subir la imagen.';
                        }
                    }
                }

                if (empty($error)) {
                    $stmt = $pdo->prepare("
                        UPDATE usuario 
                        SET nombre = ?, email = ?, telefono = ?, foto = ?, categoria = ?, posicion = ?
                        WHERE id_usuario = ?
                    ");
                    $stmt->execute([$nombre, $email, $telefono, $foto_final, $categoria, $posicion, $id_usuario]);
                    
                    $_SESSION['nombre'] = $nombre;
                    
                    // Recargar datos del usuario
                    $usuario['nombre'] = $nombre;
                    $usuario['email'] = $email;
                    $usuario['telefono'] = $telefono;
                    $usuario['foto'] = $foto_final;
                    $usuario['categoria'] = $categoria;
                    $usuario['posicion'] = $posicion;
                    
                    $msg = 'Datos actualizados correctamente.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error al actualizar datos: ' . $e->getMessage();
        }
    }
}

// CAMBIAR CONTRASEÑA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'] ?? '';
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    if ($password_actual !== $usuario['contrasena']) {
        $error = 'La contraseña actual es incorrecta.';
    } elseif (strlen($nueva_password) < 3) {
        $error = 'La nueva contraseña debe tener al menos 3 caracteres.';
    } elseif ($nueva_password !== $confirmar_password) {
        $error = 'Las contraseñas nuevas no coinciden.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuario SET contrasena = ? WHERE id_usuario = ?");
            $stmt->execute([$nueva_password, $id_usuario]);
            
            $usuario['contrasena'] = $nueva_password;
            $msg = 'Contraseña cambiada correctamente.';
        } catch (PDOException $e) {
            $error = 'Error al cambiar contraseña: ' . $e->getMessage();
        }
    }
}

// OBTENER RESERVAS
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.id_reserva,
            r.codigo_reserva,
            r.fecha,
            r.hora_inicio,
            r.hora_final,
            r.espacios_reservados,
            r.jugadores_reservados,
            r.telefono,
            r.observaciones,
            r.estado,
            c.nombre as cancha_nombre,
            c.lugar as cancha_lugar,
            c.precio,
            CASE 
                WHEN r.estado = 'cancelada' THEN 'cancelada'
                WHEN r.fecha < CURDATE() THEN 'completado'
                WHEN r.fecha = CURDATE() AND r.hora_final <= CURTIME() THEN 'completado'
                WHEN r.fecha = CURDATE() THEN 'hoy'
                ELSE 'confirmada'
            END as estado_calculado
        FROM reserva r
        INNER JOIN cancha c ON r.id_cancha = c.id_cancha
        WHERE r.id_usuario = ?
        ORDER BY r.fecha DESC, r.hora_inicio DESC
    ");
    $stmt->execute([$id_usuario]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separar reservas próximas y historial
    $reservas_proximas = array_filter($reservas, function($r) {
        return $r['estado'] === 'activa' && ($r['estado_calculado'] === 'confirmada' || $r['estado_calculado'] === 'hoy');
    });
    
    $historial = array_filter($reservas, function($r) {
        return $r['estado_calculado'] === 'completado' || $r['estado'] === 'cancelada';
    });
    
} catch (PDOException $e) {
    $reservas_proximas = [];
    $historial = [];
    $error_reservas = 'Error al cargar reservas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - PadelReservas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --padel-primary: #2c5530;
            --padel-secondary: #4a7c59;
            --padel-accent: #8bc34a;
            --padel-light: #f8f9fa;
        }

        body {
            background-color: var(--padel-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .color-header-bg{
            background: linear-gradient(135deg, var(--padel-primary) 0%, var(--padel-secondary) 100%);
        }

        .profile-header {
            color: white;
            padding: 2rem 0;
            border-radius: 0 0 15px 15px;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card-header {
            background-color: var(--padel-primary);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.5rem;
            border: none;
        }

        .btn-primary {
            background-color: var(--padel-primary);
            border-color: var(--padel-primary);
        }

        .btn-primary:hover {
            background-color: var(--padel-secondary);
            border-color: var(--padel-secondary);
        }

        .btn-outline-success {
            color: var(--padel-primary);
            border-color: var(--padel-primary);
        }

        .btn-outline-success:hover {
            background-color: var(--padel-primary);
            border-color: var(--padel-primary);
        }

        .reservation-item {
            border-left: 4px solid var(--padel-accent);
            background-color: white;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 10px 10px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .profile-tabs .nav-link {
            color: var(--padel-primary);
            border: none;
            font-weight: 500;
        }

        .profile-tabs .nav-link.active {
            background-color: var(--padel-primary);
            color: white;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: var(--padel-accent);
            box-shadow: 0 0 0 0.2rem rgba(139, 195, 74, 0.25);
        }

        .alert {
            border-radius: 10px;
        }

        .codigo-reserva {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
        }

        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <div class="container-fluid p-2 d-flex-justify-content-center bg-dark" style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-repeat: no-repeat;">
        <div class="color-header-bg">
            <!-- Header del Perfil -->
            <div class="profile-header">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <button class="btn btn-dark" onclick="history.back()">
                            ← Volver 
                            </button>
                            <h2 class="mb-2"><?= htmlspecialchars($usuario['nombre']) ?></h2>
                            <p class="mb-1"><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($usuario['email']) ?></p>
                            <?php if ($usuario['telefono']): ?>
                            <p class="mb-1"><i class="fas fa-phone me-2"></i><?= htmlspecialchars($usuario['telefono']) ?></p>
                            <?php endif; ?>
                            <p class="mb-0"><i class="fas fa-calendar me-2"></i>Miembro desde el <?= date('d \d\e F Y', strtotime($usuario['fecha_registro'])) ?></p>
                        </div>
                        <div class="col-md-3 text-center">
                            <?php if (!empty($usuario['foto'])): ?>
                                <img src="uploads/usuarios/<?= htmlspecialchars($usuario['foto']) ?>" alt="Avatar" class="profile-avatar">
                            <?php else: ?>
                                <div
                                    alt="Avatar" class="profile-avatar border-white d-flex align-items-center justify-content-center bg-primary text-white"
                                    style="font-size: 16px; font-weight: bold;">
                                    <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Mostrar mensajes -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Contenido Principal con Tabs -->
            <div class="row">
                <div class="col-12">
                    <ul class="nav nav-tabs profile-tabs mb-4 bg-light rounded-4" id="profileTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="reservas-tab" data-bs-toggle="tab"
                                data-bs-target="#reservas" type="button" role="tab">
                                <i class="fas fa-calendar-check me-2"></i>Mis Reservas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos"
                                type="button" role="tab">
                                <i class="fas fa-user-edit me-2"></i>Datos Personales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial"
                                type="button" role="tab">
                                <i class="fas fa-history me-2"></i>Historial
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="profileTabContent">
                        <!-- Tab Reservas -->
                        <div class="tab-pane fade show active" id="reservas" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Próximas Reservas</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($reservas_proximas)): ?>
                                                <?php foreach ($reservas_proximas as $reserva): ?>
                                                    <div class="reservation-item">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-4">
                                                                <h6 class="mb-1"><?= htmlspecialchars($reserva['cancha_nombre']) ?></h6>
                                                                <p class="text-muted mb-1">
                                                                    <i class="fas fa-calendar me-1"></i><?= date('l, d M Y', strtotime($reserva['fecha'])) ?>
                                                                    <i class="fas fa-clock ms-3 me-1"></i><?= substr($reserva['hora_inicio'], 0, 5) ?> - <?= substr($reserva['hora_final'], 0, 5) ?>
                                                                </p>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($reserva['cancha_lugar']) ?>
                                                                </small>
                                                            </div>
                                                            <div class="col-md-4 text-center">
                                                                <h6 class="mb-1">Código de Reserva</h6>
                                                                <span class="codigo-reserva"><?= htmlspecialchars($reserva['codigo_reserva']) ?></span>
                                                                <p class="text-muted mt-2 mb-0">Espacios: <?= $reserva['espacios_reservados'] ?>/4</p>
                                                            </div>
                                                            <div class="col-md-4 text-end">
                                                                <?php
                                                                $estado_class = $reserva['estado_calculado'] === 'hoy' ? 'status-pending' : 'status-confirmed';
                                                                $estado_text = $reserva['estado_calculado'] === 'hoy' ? 'Hoy' : 'Confirmada';
                                                                ?>
                                                                <span class="status-badge <?= $estado_class ?> mb-2 d-block"><?= $estado_text ?></span>
                                                                <?php if ($reserva['estado_calculado'] === 'confirmada'): ?>
                                                                    <form method="post" style="display: inline;" 
                                                                          onsubmit="return confirm('¿Estás seguro de que quieres cancelar esta reserva?');">
                                                                        <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                                                        <button type="submit" name="cancelar_reserva" class="btn btn-sm btn-outline-danger">Cancelar</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center py-5">
                                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                                    <h5>No tienes reservas próximas</h5>
                                                    <p class="text-muted">¡Reserva una cancha y comienza a jugar!</p>
                                                    <a href="buscador.php" class="btn btn-primary">Reservar Ahora</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Datos Personales -->
                        <div class="tab-pane fade" id="datos" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Información Personal</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label">Foto de Perfil</label>
                                                        <input type="file" name="foto" class="form-control" accept="image/*">
                                                        <small class="text-muted">Formatos: JPG, JPEG, PNG. Máximo 5MB.</small>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Nombre</label>
                                                        <input type="text" name="nombre" class="form-control" 
                                                               value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" name="email" class="form-control"
                                                            value="<?= htmlspecialchars($usuario['email']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Teléfono</label>
                                                        <input type="tel" name="telefono" class="form-control" 
                                                               value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Categoría</label>
                                                        <select class="form-control neumorphic-input" id="categoria" name="categoria" required>
                                                        <option value="1" <?= ($usuario['categoria'] == 1) ? 'selected' : '' ?>>Categoría 1</option>
                                                        <option value="2" <?= ($usuario['categoria'] == 2) ? 'selected' : '' ?>>Categoría 2</option>
                                                        <option value="3" <?= ($usuario['categoria'] == 3) ? 'selected' : '' ?>>Categoría 3</option>
                                                        <option value="4" <?= ($usuario['categoria'] == 4) ? 'selected' : '' ?>>Categoría 4</option>
                                                        <option value="5" <?= ($usuario['categoria'] == 5) ? 'selected' : '' ?>>Categoría 5</option>
                                                        <option value="6" <?= ($usuario['categoria'] == 6) ? 'selected' : '' ?>>Categoría 6</option>
                                                        <option value="7" <?= ($usuario['categoria'] == 7) ? 'selected' : '' ?>>Categoría 7</option>
                                                        </select>     
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Posición</label>
                                                        <select class="form-control neumorphic-input" id="posicion" name="posicion" required>
                                                            <option value="Derecha" <?= ($usuario['posicion'] == 'Derecha') ? 'selected' : '' ?>>Derecha</option>
                                                            <option value="Izquierda" <?= ($usuario['posicion'] == 'Izquierda') ? 'selected' : '' ?>>Izquierda</option>
                                                            <option value="SinPreferencia" <?= ($usuario['posicion'] == 'SinPreferencia') ? 'selected' : '' ?>>Sin Preferencia</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <button type="submit" name="actualizar_datos" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-key me-2"></i>Cambiar Contraseña</h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <label class="form-label">Contraseña Actual</label>
                                                    <input type="password" name="password_actual" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Nueva Contraseña</label>
                                                    <input type="password" name="nueva_password" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Confirmar Contraseña</label>
                                                    <input type="password" name="confirmar_password" class="form-control" required>
                                                </div>
                                                <button type="submit" name="cambiar_password" class="btn btn-primary w-100">
                                                    Cambiar Contraseña
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Historial -->
                        <div class="tab-pane fade" id="historial" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historial de Partidos</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($historial)): ?>
                                        <?php foreach ($historial as $reserva): ?>
                                            <div class="reservation-item">
                                                <div class="row align-items-center">
                                                    <div class="col-md-8">
                                                        <h6 class="mb-1"><?= htmlspecialchars($reserva['cancha_nombre']) ?></h6>
                                                        <p class="text-muted mb-1">
                                                            <i class="fas fa-calendar me-1"></i><?= date('l, d M Y', strtotime($reserva['fecha'])) ?>
                                                            <i class="fas fa-clock ms-3 me-1"></i><?= substr($reserva['hora_inicio'], 0, 5) ?> - <?= substr($reserva['hora_final'], 0, 5) ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($reserva['cancha_lugar']) ?>
                                                            <span class="ms-3">Espacios: <?= $reserva['espacios_reservados'] ?>/4</span>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <?php if ($reserva['estado'] === 'cancelada'): ?>
                                                            <span class="badge bg-danger mb-1">Cancelado</span>
                                                            <div class="text-muted small">Código: <?= htmlspecialchars($reserva['codigo_reserva']) ?></div>
                                                        <?php else: ?>
                                                            <span class="badge bg-success mb-1">Completado</span>
                                                            <div class="text-muted small">$<?= number_format($reserva['precio']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                            <h5>No hay historial aún</h5>
                                            <p class="text-muted">Tu historial de partidos aparecerá aquí una vez que completes tus primeras reservas.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>