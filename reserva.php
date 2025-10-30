<?php
session_start();
require_once 'conexiones/conDB.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$id_usuario = $_SESSION['id'] ?? null;
$rol = $_SESSION['rol'] ?? null;
$msg = '';
$error = '';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'usuario') {
    header("Location: reservacion.php");
    exit;
}

if (!$id_usuario) {
    header("Location: inicioses.php");
    exit;
}

// Se obtienen los datos del usuario logueado
$usuario_data = null;
try {
    $stmt = $pdo->prepare("SELECT nombre, telefono, categoria FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar datos del usuario.";
}

// Obtener datos del formulario
$id_cancha = $_GET['id_cancha'] ?? $_POST['id_cancha'] ?? '';
$fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? '';
$hora_inicio = $_GET['hora_inicio'] ?? $_POST['hora_inicio'] ?? '';

// Procesar reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_reserva'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $espacios = (int)($_POST['espacios'] ?? 1);
    $categoria = (int)($_POST['categoria'] ?? 1);
    
    if (empty($id_cancha) || empty($fecha) || empty($hora_inicio) || empty($nombre) || empty($telefono) || $espacios < 1 || $espacios > 4) {
        $error = "Por favor completa todos los campos obligatorios. Los espacios deben ser entre 1 y 4.";
    } else {
        // Calcular hora final
        $hora_final = date('H:i', strtotime($hora_inicio . ' +1 hour'));
        
        // Validar fecha y hora
        $fecha_actual = date('Y-m-d');
        $ts_actual = strtotime(date('Y-m-d H:i'));
        $ts_solicitada = strtotime($fecha . ' ' . $hora_inicio);
        
        if ($fecha < $fecha_actual) {
            $error = "No puedes reservar en fechas ya pasadas.";
        } elseif ($fecha === $fecha_actual && $ts_solicitada <= $ts_actual) {
            $error = "No puedes reservar en horarios que ya pasaron hoy.";
        } else {
            try {
                // Calcular espacios ya ocupados en ese horario
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(espacios_reservados), 0) as espacios_ocupados
                    FROM reserva 
                    WHERE id_cancha = ? AND fecha = ? AND estado = 'activa'
                    AND (
                        (hora_inicio <= ? AND hora_final > ?) 
                        OR
                        (hora_inicio < ? AND hora_final >= ?)
                        OR
                        (hora_inicio >= ? AND hora_final <= ?)
                    )
                ");
                $stmt->execute([
                    $id_cancha, $fecha, 
                    $hora_inicio, $hora_inicio,
                    $hora_final, $hora_final,
                    $hora_inicio, $hora_final
                ]);
                
                $resultado_espacios = $stmt->fetch(PDO::FETCH_ASSOC);
                $espacios_ocupados = (int)$resultado_espacios['espacios_ocupados'];
                $espacios_disponibles = 4 - $espacios_ocupados;
                
                // NUEVO: Verificar si hay reservas existentes y obtener su categoría
                $categoria_horario = null;
                if ($espacios_ocupados > 0) {
                    $stmt = $pdo->prepare("
                        SELECT categoria 
                        FROM reserva 
                        WHERE id_cancha = ? AND fecha = ? AND estado = 'activa'
                        AND (
                            (hora_inicio <= ? AND hora_final > ?) 
                            OR
                            (hora_inicio < ? AND hora_final >= ?)
                            OR
                            (hora_inicio >= ? AND hora_final <= ?)
                        )
                        LIMIT 1
                    ");
                    $stmt->execute([
                        $id_cancha, $fecha, 
                        $hora_inicio, $hora_inicio,
                        $hora_final, $hora_final,
                        $hora_inicio, $hora_final
                    ]);
                    $categoria_horario = $stmt->fetchColumn();
                    
                    // Validar que la categoría del usuario coincida
                    if ($categoria_horario && $categoria != $categoria_horario) {
                        $error = "No puedes unirte a este horario. Las reservas existentes son de Categoría {$categoria_horario} y tu categoría es {$categoria}. Solo puedes reservar con jugadores de tu misma categoría.";
                    }
                }
                // Verificar si hay suficientes espacios disponibles
                if ($espacios > $espacios_disponibles) {
                    if ($espacios_disponibles === 0) {
                        $error = "Este horario ya está completamente ocupado (4/4 espacios). Por favor elige otro horario.";
                    } else {
                        $error = "Solo quedan {$espacios_disponibles} espacios disponibles en este horario. Reduce tu reserva a {$espacios_disponibles} espacios o menos.";
                    }
                } elseif (!empty($error)) {
                } else {
                    // Generar código único de 6 caracteres
                    do {
                        $codigo = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reserva WHERE codigo_reserva = ?");
                        $stmt->execute([$codigo]);
                    } while ($stmt->fetchColumn() > 0);
                    
                    // Se crea la reserva
                    $stmt = $pdo->prepare("
                        INSERT INTO reserva (codigo_reserva, fecha, hora_inicio, hora_final, id_usuario, id_cancha, espacios_reservados, telefono, observaciones, categoria, estado) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa')
                    ");
                    $stmt->execute([$codigo, $fecha, $hora_inicio, $hora_final, $id_usuario, $id_cancha, $espacios, $telefono, $observaciones, $categoria]);
                    
                    // Obtener nombre de la cancha
                    $stmt = $pdo->prepare("SELECT nombre FROM cancha WHERE id_cancha = ?");
                    $stmt->execute([$id_cancha]);
                    $nombre_cancha = $stmt->fetchColumn();
                    
                    // Calcular espacios restantes después de esta reserva
                    $espacios_restantes = $espacios_disponibles - $espacios;
                    
                    $msg = "¡Reserva realizada con éxito! <br><br>
                           <div style='background: var(--main-color); color: white; padding: 15px; border-radius: 10px; font-size: 20px; font-weight: bold; letter-spacing: 2px; text-align: center; font-family: monospace;'>{$codigo}</div><br>
                           <strong>{$nombre_cancha}</strong><br>
                           " . date('d/m/Y', strtotime($fecha)) . "<br>
                           {$hora_inicio} - {$hora_final}<br>
                           {$espacios} espacios reservados<br>";
                    
                    if ($espacios_restantes > 0) {
                        $msg .= "<br><div style='background: #ffc107; color: #000; padding: 10px; border-radius: 8px; font-size: 14px;'>
                                 Quedan {$espacios_restantes} espacios disponibles. Otros jugadores pueden unirse a este horario.
                                 </div>";
                    } else {
                        $msg .= "<br><div style='background: #dc3545; color: white; padding: 10px; border-radius: 8px; font-size: 14px;'>
                                 Has reservado la cancha completa (4/4 espacios).
                                 </div>";
                    }
                    
                    $msg .= "<br><strong>¡Presenta este código en la cancha!</strong>";
                }
            } catch (PDOException $e) {
                $error = "Error al procesar la reserva: " . $e->getMessage();
            }
        }
    }
}

// Obtener datos de la cancha
$cancha = null;
if ($id_cancha) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM cancha WHERE id_cancha = ?");
        $stmt->execute([$id_cancha]);
        $cancha = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error al cargar los datos de la cancha.";
    }
}

// Obtener espacios ocupados actuales para mostrar información actualizada
$espacios_ocupados_actual = 0;
$espacios_disponibles_actual = 4;
$reservas_existentes = '';

if ($id_cancha && $fecha && $hora_inicio) {
    try {
        $hora_final = date('H:i', strtotime($hora_inicio . ' +1 hour'));
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(espacios_reservados), 0) as espacios_ocupados,
                   GROUP_CONCAT(CONCAT(u.nombre, ' (', r.espacios_reservados, ' espacios)') SEPARATOR ', ') as reservas_info
            FROM reserva r 
            LEFT JOIN usuario u ON r.id_usuario = u.id_usuario
            WHERE r.id_cancha = ? AND r.fecha = ? AND r.estado = 'activa'
            AND (
                (r.hora_inicio <= ? AND r.hora_final > ?) 
                OR
                (r.hora_inicio < ? AND r.hora_final >= ?)
                OR
                (r.hora_inicio >= ? AND r.hora_final <= ?)
            )
        ");
        $stmt->execute([
            $id_cancha, $fecha, 
            $hora_inicio, $hora_inicio,
            $hora_final, $hora_final,
            $hora_inicio, $hora_final
        ]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $espacios_ocupados_actual = (int)$resultado['espacios_ocupados'];
        $espacios_disponibles_actual = 4 - $espacios_ocupados_actual;
        $reservas_existentes = $resultado['reservas_info'];
        
    } catch (PDOException $e) {
        // En caso de error, mantener valores por defecto
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cancha - CanchApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
          --bg-color: #e0e5ec;
          --main-color: #3f4e6d;
          --shadow-light: #ffffff;
          --shadow-dark: #a3b1c6;
        }
        body {
          background-color: var(--bg-color);
        }
        .neumorphic-card {
          background: var(--bg-color);
          border-radius: 20px;
          padding: 3rem;
          max-width: 500px;
          width: 100%;
          transition: all .8s ease-in-out;
        }

        .neumorphic-input {
          height: 50px;
          background-color: var(--bg-color);
          border: none;
          border-radius: 10px;
          box-shadow: inset 5px 5px 10px var(--shadow-dark), inset -5px -5px 10px var(--shadow-light);
          transition: all 0.3s ease;
        }
        .neumorphic-input:focus {
          background-color: var(--bg-color);
          box-shadow: inset 2px 2px 5px var(--shadow-dark), inset -2px -2px 5px var(--shadow-light), 0 0 0 3px var(--main-color);
          border: none;
          outline: none;
        }

        .neumorphic-btn {
          margin-top: 15px;
          background-color: var(--bg-color);
          color: var(--main-color);
          border-radius: 10px;
          font-weight: 600;
          box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light);
          transition: all 0.5s ease-in-out;
          border: none;
          padding: 1rem;
        }
        .neumorphic-btn:hover {
          transform: scale(0.98);
          background-color: var(--main-color);
          color: var(--shadow-light);
        }
        .form-label {
          color: var(--main-color);
          font-weight: 500;
        }
        .alert-custom {
          border-radius: 10px;
          border: none;
          box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
          margin-bottom: 1rem;
        }
        .cancha-info {
          background: var(--bg-color);
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 25px;
          box-shadow: inset 3px 3px 6px var(--shadow-dark), inset -3px -3px 6px var(--shadow-light);
        }
        .cancha-info h3 {
          color: var(--main-color);
          margin-bottom: 8px;
        }
        .reserva-details {
          background: var(--bg-color);
          padding: 15px;
          border-radius: 12px;
          margin-bottom: 25px;
          box-shadow: inset 2px 2px 4px var(--shadow-dark), inset -2px -2px 4px var(--shadow-light);
          display: flex;
          justify-content: space-between;
          flex-wrap: wrap;
          gap: 10px;
        }
        .reserva-details div {
          text-align: center;
          color: var(--main-color);
        }
        .reserva-details strong {
          display: block;
          margin-bottom: 5px;
        }
        .ocupacion-actual {
          background: var(--bg-color);
          border-radius: 12px;
          padding: 15px;
          margin-bottom: 20px;
          box-shadow: inset 2px 2px 4px var(--shadow-dark), inset -2px -2px 4px var(--shadow-light);
        }
        .ocupacion-actual h4 {
          color: var(--main-color);
          margin-bottom: 10px;
          display: flex;
          align-items: center;
          gap: 8px;
        }
        .espacios-visual {
          display: flex;
          gap: 8px;
          margin: 10px 0;
        }
        .espacio-visual {
          width: 35px;
          height: 35px;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 14px;
          font-weight: bold;
          box-shadow: 3px 3px 6px var(--shadow-dark), -3px -3px 6px var(--shadow-light);
        }
        .espacio-ocupado {
          background: linear-gradient(135deg, #dc3545, #c82333);
          color: white;
        }
        .espacio-disponible {
          background: linear-gradient(135deg, #28a745, #20c997);
          color: white;
        }
        .neumorphic-select {
          height: 50px;
          background-color: var(--bg-color);
          border: none;
          border-radius: 10px;
          box-shadow: inset 5px 5px 10px var(--shadow-dark), inset -5px -5px 10px var(--shadow-light);
          transition: all 0.3s ease;
          padding: 0 15px;
          color: var(--main-color);
        }
        .neumorphic-select:focus {
          background-color: var(--bg-color);
          box-shadow: inset 2px 2px 5px var(--shadow-dark), inset -2px -2px 5px var(--shadow-light), 0 0 0 3px var(--main-color);
          border: none;
          outline: none;
        }
        .neumorphic-textarea {
          background-color: var(--bg-color);
          border: none;
          border-radius: 10px;
          box-shadow: inset 5px 5px 10px var(--shadow-dark), inset -5px -5px 10px var(--shadow-light);
          transition: all 0.3s ease;
          padding: 15px;
          min-height: 80px;
          resize: vertical;
        }
        .neumorphic-textarea:focus {
          background-color: var(--bg-color);
          box-shadow: inset 2px 2px 5px var(--shadow-dark), inset -2px -2px 5px var(--shadow-light), 0 0 0 3px var(--main-color);
          border: none;
          outline: none;
        }
        .info-badge {
          background: var(--bg-color);
          padding: 8px 12px;
          border-radius: 8px;
          font-size: 14px;
          color: var(--main-color);
          box-shadow: inset 1px 1px 2px var(--shadow-dark), inset -1px -1px 2px var(--shadow-light);
          margin-top: 8px;
        }
        .warning-badge {
          background: linear-gradient(135deg, #ffc107, #ffb300);
          color: #000;
          padding: 12px;
          border-radius: 10px;
          margin-top: 8px;
          font-size: 14px;
          box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        }
        .btn-back {
          background-color: var(--shadow-dark);
          color: white;
        }
        .btn-back:hover {
          background-color: #95a3b8;
          color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-2" style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-repeat: no-repeat;">
          
    <!-- Formulario de Reserva -->
    <div id="main" class="d-flex justify-content-center align-items-center min-vh-100">
      <div class="neumorphic-card">
        <h1 class="text-center fw-bold mb-4" style="color: var(--main-color);"> Reservar Cancha</h1>
        
        <?php if ($msg): ?>
        <div class="alert alert-success alert-custom">
          <?php echo $msg; ?>
          <div style="margin-top: 15px;">
            <a href="reservacion.php?id=<?= $cancha['id_cancha'] ?? '' ?>&fecha=<?= $fecha ?>" class="btn neumorphic-btn">Ver Calendario</a>
          </div>
        </div>
        <?php elseif ($error): ?>
        <div class="alert alert-danger alert-custom">
          <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="col-12">
                <h3 class="text-center mb-4">Valoraciones!</h3>

                <?php
                try {
                  $stmt = $pdo->prepare("
                    SELECT r.*, u.nombre, u.foto, u.id_usuario
                    FROM reserva r
                    INNER JOIN usuario u ON r.id_usuario = u.id_usuario
                    WHERE r.id_cancha = ?
                      AND r.fecha = ?
                      AND r.estado = 'activa'
                      AND (
                          (r.hora_inicio <= ? AND r.hora_final > ?)
                          OR
                          (r.hora_inicio < ? AND r.hora_final >= ?)
                          OR
                          (r.hora_inicio >= ? AND r.hora_final <= ?)
                      )
                    ORDER BY r.fecha ASC
                  ");

                $stmt->execute([
                    $id_cancha,
                    $fecha,
                    $hora_inicio, $hora_inicio,
                    $hora_final, $hora_final,
                    $hora_inicio, $hora_final
                ]);

                $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  if (count($reservas) === 0) {
                    echo "<div class='alert alert-info alert-custom'>No hay reservas para esta cancha aún.</div>";
                  }
                } catch (PDOException $e) {
                  echo "<div class='alert alert-danger alert-custom'>Error al cargar los perfiles.</div>";
                }
?>
        </div>
        

        <?php if ($cancha && !$msg): ?>
            <!-- Información de la Cancha -->
            <div class="cancha-info">
                <h3><?= htmlspecialchars($cancha['nombre']) ?></h3>
                <p><strong></strong> <?= htmlspecialchars($cancha['lugar']) ?></p>
                <p><strong></strong> <?= htmlspecialchars($cancha['bio']) ?></p>
                <p><strong></strong> $<?= number_format($cancha['precio']) ?> por hora</p>
            </div>
            
            <?php if ($fecha && $hora_inicio): ?>
                <!-- Detalles de la Reserva -->
                <div class="reserva-details">
                    <div>
                        <strong>Fecha</strong>
                        <?= date('d/m/Y', strtotime($fecha)) ?>
                    </div>
                    <div>
                        <strong>Horario</strong>
                        <?= $hora_inicio ?> - <?= date('H:i', strtotime($hora_inicio . ' +1 hour')) ?>
                    </div>
                </div>
                
                <!-- Estado actual de ocupación -->
                <div class="ocupacion-actual">
                    <h4>Estado actual del horario</h4>
                    
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px; color: var(--main-color);">
                        <span><strong>Espacios ocupados:</strong> <?= $espacios_ocupados_actual ?>/4</span>
                        <span><strong>Disponibles:</strong> <?= $espacios_disponibles_actual ?>/4</span>
                    </div>
                    
                    <div class="espacios-visual">
                      <?php foreach ($reservas as $r): ?>
                        <?php if (!empty($r['foto'])): ?>
                          <a href="perfil_otro.php?id=<?= $r['id_usuario'] ?>">
                            <img src="uploads/usuarios/<?= htmlspecialchars($r['foto']) ?>"
                            alt="<?= htmlspecialchars($r['nombre']) ?>" class="rounded-circle me-3"
                            width="50" height="50" style="object-fit: cover;">
                          </a>
                        <?php else: ?>
                          <a href="perfil_otro.php?id=<?= $r['id_usuario'] ?>" style="text-decoration: none;">
                            <div class="rounded-circle  text-black me-3 d-flex align-items-center justify-content-center"
                                 style="width: 50px; height: 50px; font-size: 20px; font-weight: bold;">
                              <?= strtoupper(substr($r['nombre'], 0, 1)) ?>
                            </div>
                          </a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                    
                    <?php if ($espacios_ocupados_actual > 0 && $reservas_existentes): ?>
                        <div style="font-size: 13px; color: var(--main-color); margin-top: 8px;">
                            <strong>Ya reservado por:</strong> <?= htmlspecialchars($reservas_existentes) ?>
                        </div>
                        <?php 
                        // Mostrar la categoria del horario reservado
                        $stmt = $pdo->prepare("
                            SELECT categoria 
                            FROM reserva 
                            WHERE id_cancha = ? AND fecha = ? AND estado = 'activa'
                            AND (
                                (hora_inicio <= ? AND hora_final > ?) 
                                OR
                                (hora_inicio < ? AND hora_final >= ?)
                                OR
                                (hora_inicio >= ? AND hora_final <= ?)
                            )
                            LIMIT 1
                        ");
                        $stmt->execute([
                            $id_cancha, $fecha, 
                            $hora_inicio, $hora_inicio,
                            $hora_final, $hora_final,
                            $hora_inicio, $hora_final
                        ]);
                        $cat_horario = $stmt->fetchColumn();
                        
                        if ($cat_horario && $cat_horario != $usuario_data['categoria']): ?>
                            <div style="background: #dc3545; color: white; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 14px;">
                                <strong>Atención:</strong> Este horario es para Categoría <?= $cat_horario ?>.
                                Tu categoría es <?= $usuario_data['categoria'] ?? 1 ?>.
                                Solo puedes reservar con jugadores de tu misma categoría.
                            </div>
                        <?php elseif ($cat_horario): ?>
                            <div style="background: #28a745; color: white; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 14px;">
                                Este horario es Categoría <?= $cat_horario ?>. Puedes unirte a este grupo.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($espacios_disponibles_actual > 0): ?>
                <!-- Formulario de Reserva -->
                <form action="" method="POST" id="reservaForm">
                    <input type="hidden" name="id_cancha" value="<?= htmlspecialchars($id_cancha) ?>">
                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                    <input type="hidden" name="hora_inicio" value="<?= htmlspecialchars($hora_inicio) ?>">
                    
                    <div class="mb-4">
                        <label for="nombre" class="form-label">Tu Nombre <span style="color: #dc3545;">*</span></label>
                        <input type="text" name="nombre" class="form-control neumorphic-input" required id="nombre" 
                               placeholder="Nombre completo" 
                               value="<?= htmlspecialchars($usuario_data['nombre'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="telefono" class="form-label">Teléfono <span style="color: #dc3545;">*</span></label>
                        <input type="tel" name="telefono" class="form-control neumorphic-input" required id="telefono" 
                               placeholder="Ej: +54 9 11 1234-5678" 
                               value="<?= htmlspecialchars($usuario_data['telefono'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="categoria" class="form-label">Tu Categoría <span style="color: #dc3545;">*</span></label>
                        <select name="categoria" class="form-control neumorphic-select" required id="categoria">
                          <option value="<?= $usuario_data['categoria'] ?? 1 ?>" selected>
                              Categoría <?= $usuario_data['categoria'] ?? 1 ?>
                          </option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="espacios" class="form-label">Espacios a Reservar <span style="color: #dc3545;">*</span></label>
                        <select name="espacios" class="form-control neumorphic-select" required id="espacios">
                            <?php for ($i = 1; $i <= min(4, $espacios_disponibles_actual); $i++): ?>
                                <option value="<?= $i ?>" <?= (isset($_POST['espacios']) && $_POST['espacios'] == $i) ? 'selected' : '' ?>>
                                    <?= $i ?> espacio<?= $i > 1 ? 's' : '' ?> (<?= $i ?> jugador<?= $i > 1 ? 'es' : '' ?>)
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <div class="info-badge">
                            Puedes reservar hasta <?= $espacios_disponibles_actual ?> espacios disponibles.
                            <?php if ($espacios_ocupados_actual > 0): ?>
                                <br>Te unirás a otros jugadores en este horario.
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control neumorphic-textarea" id="observaciones" placeholder="Información adicional (opcional)..."><?= isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : '' ?></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="confirmar_reserva" class="btn neumorphic-btn"> Confirmar Reserva</button>
                    </div>
                    
                    <div class="d-grid">
                        <button type="button" onclick="history.back()" class="btn neumorphic-btn btn-back">← Volver</button>
                    </div>
                </form>
                
                <?php else: ?>
                    <div class="warning-badge">
                        <strong>Horario completo</strong><br>
                        Este horario ya tiene los 4 espacios ocupados. Por favor selecciona otro horario.
                    </div>
                    <div class="d-grid">
                        <button type="button" onclick="history.back()" class="btn neumorphic-btn">← Elegir otro horario</button>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-warning alert-custom">
                    Faltan datos. Vuelve al calendario para seleccionar fecha y hora.
                </div>
                <div class="d-grid">
                    <a href="reservacion.php?id=<?= $cancha['id_cancha'] ?>" class="btn neumorphic-btn">Ir al Calendario</a>
                </div>
            <?php endif; ?>
            
        <?php elseif (!$msg): ?>
            <div class="alert alert-danger alert-custom">
                No se pudo cargar la cancha.
            </div>
            <div class="d-grid">
                <a href="reservacion.php" class="btn neumorphic-btn">Ir al Calendario</a>
            </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- Footer -->
    <footer>
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
    <!-- Fin Footer -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>
</html>