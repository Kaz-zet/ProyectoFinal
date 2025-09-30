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
    <title>Administrar Solicitudes</title>
</head>
<body>
    <h1>Solicitudes para ser dueño</h1>

    <?php if ($msg): ?>
        <p><strong><?php echo htmlspecialchars($msg); ?></strong></p>
    <?php endif; ?>

    <?php if (count($solicitudes) > 0): ?>
        <table border="0" cellpadding="8" cellspacing="0"> <!--Cambiar border si querés que tenga un borde xd-->
            <tr>
                <th>ID Solicitud</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Fecha</th>
                <th>Solicitud</th>
            </tr>
            <?php foreach ($solicitudes as $sol): ?> <!--$sol de soicitud es una varibale auxiliar para poder crear la tabla-->
                <tr>
                    <td><?php echo $sol['id_verificacion']; ?></td>
                    <td><?php echo htmlspecialchars($sol['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($sol['email']); ?></td>
                    <td><?php echo $sol['fecha']; ?></td>
                    <td>
                        <form method="post" style="display:inline;"> <!--aprobar-->
                            <input type="hidden" name="id_usuario" value="<?php echo $sol['id_usuario']; ?>">
                            <button type="submit" name="dec" value="aprobar">Aprobar</button>
                        </form>

                        <form method="post" style="display:inline;"> <!--rechazar-->
                            <input type="hidden" name="id_usuario" value="<?php echo $sol['id_usuario']; ?>">
                            <button type="submit" name="dec" value="rechazar"> Rechazar</button> 
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No existen peticiones pendientes.</p>
    <?php endif; ?>

    <br>
    <a href="index.php">Volver</a>
</body>
</html>

