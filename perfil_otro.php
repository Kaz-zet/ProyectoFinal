<?php
session_start();
require_once 'conexiones/conDB.php';

// Solo usuarios pueden ver su perfil
if (!isset($_SESSION['rol'])) {
    die("Solo los usuarios pueden ver su perfil.");
}

$id_usuario = $_SESSION['id'];
$msg = '';
$error = '';

$id_perfil = $_GET['id'] ?? null;
// Obtener datos del usuario
if ($id_perfil) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id_perfil]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            die("Usuario no encontrado.");
        }
    } catch (PDOException $e) {
    die("Error: " . $e->getMessage());
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
            CONCAT(c.direccion, ', ', c.ciudad) as cancha_lugar,
            c.precio
        FROM reserva r
        INNER JOIN cancha c ON r.id_cancha = c.id_cancha
        WHERE r.id_usuario = ?
        ORDER BY r.fecha DESC, r.hora_inicio DESC
    ");
    $stmt->execute([$id_usuario]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    //Calcula estado de la reserva!
    $fecha_actual = date('Y-m-d');
    $hora_actual = date('H:i:s');
    
    foreach ($reservas as &$reserva) {
        if ($reserva['estado'] === 'cancelada') {
            $reserva['estado_calculado'] = 'cancelada';
        } elseif ($reserva['fecha'] < $fecha_actual) {
            $reserva['estado_calculado'] = 'completado';
        } elseif ($reserva['fecha'] == $fecha_actual && $reserva['hora_final'] <= $hora_actual) {
            $reserva['estado_calculado'] = 'completado';
        } elseif ($reserva['fecha'] == $fecha_actual) {
            $reserva['estado_calculado'] = 'hoy';
        } else {
            $reserva['estado_calculado'] = 'confirmada';
        }
    }
    unset($reserva);
    
    //Separa las reservas entre próximas y el historial.

    unset($reserva);
    
    $reservas_proximas = array_filter($reservas, function($r) {
        return $r['estado'] === 'activa' && 
               ($r['estado_calculado'] === 'confirmada' || $r['estado_calculado'] === 'hoy');
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
            --padel-light2: #D9D4D2;
            --padel-orange: #ff6b35;
        }

        body {
            background-color: #D9D4D2;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .color-header-bg{
            background: linear-gradient(135deg, var(--padel-primary) 0%, var(--padel-orange) 100%);
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
    <div class="container-fluid p-2 d-flex-justify-content-center" >
        <div class="color-header-bg rounded">
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