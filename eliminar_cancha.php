<?php
session_start();
require_once 'conexiones/conDB.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrarcancha'])) {
    $id_borrar = (int)$_POST['borrarcancha'];

    //Busca la foto asociada a la cancha para borrarla
    $stmt = $pdo->prepare("SELECT foto FROM cancha WHERE id_cancha = ?");
    $stmt->execute([$id_borrar]);
    $cancha = $stmt->fetch();

    if ($cancha && !empty($cancha['foto'])) {
        $rutaFoto = __DIR__ . "/uploads/" . $cancha['foto'];
        if (file_exists($rutaFoto)) {
            unlink($rutaFoto); // Se borra la foto del servidor
        }
    }

    //Borra la cancha
    $stmt = $pdo->prepare("DELETE FROM cancha WHERE id_cancha = ?");
    $stmt->execute([$id_borrar]);

    header("Location: gestion_reservas.php");
    exit;
}
?>