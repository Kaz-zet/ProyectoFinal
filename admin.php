<?php
session_start();
require_once 'conexiones/conDB.php';

// Solo admins pueden entrar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

$msg = '';
$solicitudes = [];

try {
    //Ingresamos parametros para el admin-
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $usuarioId = $_POST['id_usuario']; //usuarioId lo usamos para "capturar" de cierta forma el ID del usuario que desea ser dueño, pero no tiene ningun valor x si solo.   
        $dec = $_POST['dec']; //Utilizamos "dec" de decision de forma que esta pueda ser o aprobado o rechazado.


        //SI SE APRUEBA (ABAJO DEL TODO)

        if ($dec === 'aprobar') {
            //
            $stmt = $pdo->prepare("UPDATE verificacion SET estado = 'aprobado' WHERE id_usuario = ?");
            $stmt->execute([$usuarioId]); //Este "usuarioId" se pone acá en execute para evitar inyecciones y le corresponde el lugar del "?" de arriba.

            // Se pasam datos del usuario a la tabla duenio.
            $stmt2 = $pdo->prepare("
            INSERT INTO duenio (id_usuario, nombre, email, contrasena)
            SELECT id_usuario, nombre, email, contrasena 
            FROM usuario WHERE id_usuario = ?
            ");
            $stmt2->execute([$usuarioId]); //Lo mismo q arriba, este id es del usuario pero se realiza asi para evitra inyecciones.

            $msg = "Solicitud aprobada y usuario convertido en dueño!!.";

            //SI SE RECHAZA (ABAJO DEL TODO)

        } elseif ($dec === 'rechazar') {
            // Rechazar
            $stmt = $pdo->prepare("UPDATE verificacion SET estado = 'rechazado' WHERE id_usuario = ?");
            $stmt->execute([$usuarioId]);

            $msg = "Solicitud rechazada.";
        }
    }

    // Traer solicitudes pendientes
    $stmt = $pdo->query("
        SELECT v.id_verificacion, v.id_usuario, u.nombre, u.email, v.fecha 
        FROM verificacion v
        INNER JOIN usuario u ON v.id_usuario = u.id_usuario
        WHERE v.estado = 'pendiente'
        ORDER BY v.fecha ASC
    ");
    // En vez de poner verificacion y usuario, se pueden usar alias como "v" o "u".
    //Inner Join permite que se unan las tablas verificación y usuario, asi a la hora de mostrarlas muestre datos del usuario.
    //Order by, ordena en fecha, el primero arriba, el ultimo abajo.
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC); 

} catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage(); //Mensaje de error como en todos los phps.
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Solicitudes - CanchApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<style>
    :root {
        --bg-color: #e0e5ec;
        --main-color: #3f4e6d;
        --shadow-light: #ffffff;
        --shadow-dark: #a3b1c6;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --info-color: black;
    }

    body {
        background-color: var(--bg-color);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .neumorphic-card {
        background: var(--bg-color);
        border-radius: 20px;
        box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light);
        padding: 2.5rem;
        max-width: 1200px;
        width: 100%;
        transition: all 0.3s ease-in-out;
    }

    .neumorphic-card:hover {
        box-shadow: 12px 12px 20px var(--shadow-dark), -12px -12px 20px var(--shadow-light);
    }

    .neumorphic-btn-approve {
        background-color: var(--success-color);
        color: white;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        transition: all 0.3s ease-in-out;
        border: none;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .neumorphic-btn-approve:hover {
        transform: scale(0.98);
        background-color: #218838;
        color: white;
    }

    .neumorphic-btn-reject {
        background-color: var(--danger-color);
        color: white;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        transition: all 0.3s ease-in-out;
        border: none;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .neumorphic-btn-reject:hover {
        transform: scale(0.98);
        background-color: #c82333;
        color: white;
    }

    .neumorphic-btn-back {
        background-color: var(--bg-color);
        color: var(--main-color);
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light);
        transition: all 0.5s ease-in-out;
        border: none;
        padding: 0.75rem 1.5rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .neumorphic-btn-back:hover {
        transform: scale(0.98);
        background-color: var(--main-color);
        color: var(--shadow-light);
        text-decoration: none;
    }

    .alert-custom {
        border-radius: 15px;
        border: none;
        box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        margin-bottom: 1.5rem;
        padding: 1rem 1.5rem;
        text-align: center;
        font-weight: 500;
    }

    .alert-success-custom {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
    }

    .alert-danger-custom {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
    }

    .alert-info-custom {
        background: linear-gradient(135deg, #d1ecf1, #bee5eb);
        color: #0c5460;
    }

    .title {
        text-align: center;
        color: var(--main-color);
        font-weight: 700;
        margin-bottom: 2rem;
        font-size: 2.2rem;
    }

    .subtitle {
        text-align: center;
        color: #6c757d;
        margin-bottom: 2rem;
        font-size: 1rem;
    }

    .table-container {
        background: var(--bg-color);
        border-radius: 15px;
        box-shadow: inset 3px 3px 6px var(--shadow-dark), inset -3px -3px 6px var(--shadow-light);
        padding: 1.5rem;
        overflow-x: auto;
        margin-bottom: 2rem;
    }

    .custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }

    .custom-table thead th {
        background: var(--bg-color);
        color: var(--main-color);
        font-weight: 700;
        padding: 1rem;
        text-align: left;
        border: none;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .custom-table tbody tr {
        background: var(--bg-color);
        border-radius: 10px;
        box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        transition: all 0.3s ease;
    }

    .custom-table tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 6px 6px 12px var(--shadow-dark), -6px -6px 12px var(--shadow-light);
    }

    .custom-table tbody td {
        padding: 1rem;
        border: none;
        color: var(--main-color);
        vertical-align: middle;
    }

    .custom-table tbody tr td:first-child {
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
    }

    .custom-table tbody tr td:last-child {
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
    }

    .badge-id {
        background: var(--info-color);
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .no-solicitudes {
        text-align: center;
        padding: 3rem;
        color: #6c757d;
    }

    .no-solicitudes i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .actions-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .stats-badge {
        background: var(--bg-color);
        border-radius: 10px;
        padding: 0.5rem 1rem;
        display: inline-block;
        box-shadow: inset 2px 2px 4px var(--shadow-dark), inset -2px -2px 4px var(--shadow-light);
        font-weight: 600;
        color: var(--main-color);
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .neumorphic-card {
            margin: 1rem;
            padding: 1.5rem;
        }
        
        .title {
            font-size: 1.75rem;
        }

        .table-container {
            padding: 1rem;
        }

        .custom-table {
            font-size: 0.875rem;
        }

        .custom-table thead th,
        .custom-table tbody td {
            padding: 0.75rem 0.5rem;
        }

        .actions-group {
            flex-direction: column;
        }

        .neumorphic-btn-approve,
        .neumorphic-btn-reject {
            width: 100%;
        }
    }
</style>

<body>
    <div class="container-fluid p-2" style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-repeat: no-repeat;">
        <div id="main" class="d-flex justify-content-center align-items-center min-vh-100 py-4">
            <div class="neumorphic-card">
                <h1 class="title">
                    <i class="fas fa-user-shield me-2"></i>Panel de Administración
                </h1>
                
                <p class="subtitle">
                    Gestiona las solicitudes de usuarios que desean convertirse en dueños
                </p>

                <!-- Contador de solicitudes -->
                 <!--Se creó un array arriba para las solicitudes donde si hay una o mas se muestran. Si hay 2 o mas se le agrega s y es para que se lea bien. -->
                <?php if (count($solicitudes) > 0): ?>
                    <div class="text-center mb-4">
                        <span class="stats-badge">
                            <i class="fas fa-clipboard-list me-2"></i>
                            <?= count($solicitudes) ?> solicitud<?= count($solicitudes) != 1 ? 'es' : '' ?> pendiente<?= count($solicitudes) != 1 ? 's' : '' ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Mostrar mensajes -->
                <?php if ($msg): ?>
                    <?php if (strpos($msg, 'aprobada') !== false): ?>
                        <div class="alert-success-custom alert-custom">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php elseif (strpos($msg, 'rechazada') !== false): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-times-circle me-2"></i>
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php else: ?>
                        <div class="alert-danger-custom alert-custom">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!--Se crea un campo por cada solicitud-->
                <?php if (count($solicitudes) > 0): ?>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                    <th><i class="fas fa-user me-2"></i>Usuario</th>
                                    <th><i class="fas fa-envelope me-2"></i>Email</th>
                                    <th><i class="fas fa-calendar me-2"></i>Fecha</th>
                                    <th><i class="fas fa-cog me-2"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!--En cada campo se rellena con los datos del usuario-->
                                <?php foreach ($solicitudes as $sol): ?>
                                    <tr>
                                        <td>
                                            <span class="badge-id">#<?= $sol['id_verificacion'] ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($sol['nombre']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($sol['email']) ?></td>
                                        <td>
                                            <small><?= date('d/m/Y H:i', strtotime($sol['fecha'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="actions-group">
                                                <form method="post" style="display:inline;" onsubmit="return confirmarAprobacion('<?= htmlspecialchars($sol['nombre']) ?>')"><!--Onsubmit hace referencia que al clickear activará la función de js.-->
                                                    <input type="hidden" name="id_usuario" value="<?= $sol['id_usuario'] ?>"> <!--Se le manda al servidor la ID exacta del usuario con el que se está trabajando.-->
                                                    <button type="submit" name="dec" value="aprobar" class="neumorphic-btn-approve">
                                                        <i class="fas fa-check me-1"></i>Aprobar
                                                    </button>
                                                </form>

                                                <form method="post" style="display:inline;" onsubmit="return confirmarRechazo('<?= htmlspecialchars($sol['nombre']) ?>')">
                                                    <input type="hidden" name="id_usuario" value="<?= $sol['id_usuario'] ?>">
                                                    <button type="submit" name="dec" value="rechazar" class="neumorphic-btn-reject">
                                                        <i class="fas fa-times me-1"></i>Rechazar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-solicitudes">
                        <i class="fas fa-inbox"></i>
                        <h4>No hay solicitudes pendientes</h4>
                        <p>Todas las solicitudes han sido procesadas</p>
                    </div>
                <?php endif; ?>

                <!-- Botón para volver -->
                <div class="text-center mt-4">
                    <a class="dropdown-item text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                  </a></li>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Confirmación al aprobar
        function confirmarAprobacion(nombre) {
            return confirm(`¿Estás seguro de aprobar la solicitud de ${nombre}?\n\nEste usuario se convertirá en dueño y podrá gestionar canchas.`);
        }

        // Confirmación al rechazar
        function confirmarRechazo(nombre) {
            return confirm(`¿Estás seguro de rechazar la solicitud de ${nombre}?\n\nEsta acción no se puede deshacer.`);
        }

        // Auto-ocultar mensajes después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Animación de entrada para las filas
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.custom-table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.4s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>