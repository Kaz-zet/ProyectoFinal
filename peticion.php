   <?php
session_start();
require_once 'conexiones/conDB.php';
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'usuario') {
    die("Solo los usuarios pueden solicitar ser dueños.");
}
$id_usuario = $_SESSION['id'];
$msg = ''; //Creamos variable de msg, que esta va a tomar distintos valores a futuro dependiendo de lo q pase.
try {
    //Se fija si ya existe una peticion.
    $stmt = $pdo->prepare("SELECT * FROM verificacion WHERE id_usuario = ? AND estado = 'pendiente'");
    $stmt->execute([$id_usuario]);
    $solicitud = $stmt->fetch();
    if ($solicitud) {
        $msg = "Ya tenés una solicitud pendiente.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        //Sino existe, crea una peticion. El id obviamente depende del id del usuario, va a estar en estado pendiente y "NOW" permite que la fecha sea la misma que se envia en la vida real.
        $stmt2 = $pdo->prepare("INSERT INTO verificacion (id_usuario, estado, fecha) VALUES (?, 'pendiente', NOW())");
        $stmt2->execute([$id_usuario]);
        $msg = "Solicitud enviada. Esperá a que un admin la apruebe.";
    }
} catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar ser dueño - CanchApp</title>
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
        --warning-color: #ffc107;
        --error-color: #dc3545;
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
        padding: 3rem;
        max-width: 500px;
        width: 100%;
        transition: all 0.8s ease-in-out;
    }

    .neumorphic-card:hover {
        box-shadow: 8px 8px 16px var(--shadow-light), -8px -8px 16px var(--shadow-dark);
    }

    .neumorphic-btn {
        background-color: var(--bg-color);
        color: var(--main-color);
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light);
        transition: all 0.5s ease-in-out;
        border: none;
        padding: 1rem 2rem;
        width: 100%;
        margin: 1rem 0;
    }

    .neumorphic-btn:hover {
        transform: scale(0.98);
        background-color: var(--main-color);
        color: var(--shadow-light);
    }

    .neumorphic-btn-success {
        background-color: var(--success-color);
        color: white;
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light);
        transition: all 0.5s ease-in-out;
        border: none;
        padding: 1rem 2rem;
        width: 100%;
        margin: 1rem 0;
    }

    .neumorphic-btn-success:hover {
        transform: scale(0.98);
        background-color: #218838;
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

    .alert-warning-custom {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        color: #856404;
    }

    .alert-danger-custom {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
    }

    .title {
        text-align: center;
        color: var(--main-color);
        font-weight: 700;
        margin-bottom: 2rem;
        font-size: 2rem;
    }

    .description {
        text-align: center;
        color: #6c757d;
        margin-bottom: 2rem;
        font-size: 1.1rem;
        line-height: 1.5;
    }

    .info-section {
        background: var(--bg-color);
        border-radius: 15px;
        box-shadow: inset 3px 3px 6px var(--shadow-dark), inset -3px -3px 6px var(--shadow-light);
        padding: 1.5rem;
        margin-bottom: 2rem;
        color: var(--main-color);
    }

    .info-section h6 {
        color: var(--main-color);
        margin-bottom: 0.75rem;
        font-weight: 600;
    }

    .info-section p {
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .status-pending {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        color: #856404;
        padding: 1rem;
        border-radius: 15px;
        text-align: center;
        font-weight: 600;
        box-shadow: inset 3px 3px 6px var(--shadow-dark), inset -3px -3px 6px var(--shadow-light);
        margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
        .neumorphic-card {
            margin: 1rem;
            padding: 2rem;
        }
        
        .title {
            font-size: 1.75rem;
        }
        
        .description {
            font-size: 1rem;
        }
    }
</style>

<body>
    <div class="container-fluid p-2" style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-repeat: no-repeat;">
        <div id="main" class="d-flex justify-content-center align-items-center min-vh-100">
            <div class="neumorphic-card">
                <h1 class="title">Solicitar ser Dueño</h1>
                
                <div class="description">
                    ¿Querés gestionar tus propias canchas? Solicitá convertirte en dueño y empezá a ofrecer tus espacios deportivos.
                </div>

                <div class="info-section">
                    <h6><i class="fas fa-info-circle me-2"></i>¿Qué obtienes como dueño?</h6>
                    <p><i class="fas fa-check me-2 text-success"></i>Crear y gestionar tus canchas</p>
                    <p><i class="fas fa-check me-2 text-success"></i>Ver todas las reservas</p>
                    <p><i class="fas fa-check me-2 text-success"></i>Panel de control personalizado</p>
                    <p><i class="fas fa-check me-2 text-success"></i>Gestionar precios</p>
                </div>

                <!-- Formulario de solicitud -->
                <?php if (!$solicitud): ?>
                    <form method="post" id="solicitudForm">
                        <div class="info-section mb-3">
                            <h6><i class="fas fa-exclamation-circle me-2"></i>Importante</h6>
                            <p>Al enviar esta solicitud, un administrador revisará tu petición. El proceso puede demorar indefinidamente.</p>
                        </div>
                        
                        <button type="submit" class="btn neumorphic-btn-success">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud
                        </button>
                    </form>
                <?php else: ?>
                    <div class="status-pending">
                        <i class="fas fa-hourglass-half me-2"></i>
                        Tu solicitud está siendo revisada por un administrador
                    </div>
                <?php endif; ?>

                <!-- Botón para volver -->
                <div class="text-center mt-4">
                    <a href="index.php" class="neumorphic-btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>