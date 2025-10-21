<?php
session_start();
require_once 'conexiones/conDB.php';

if (!isset($_SESSION['id'])) {
    header('Location: inicioses.php'); 
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: buscador.php'); //Se saca la ID del usuario y se valida que la peticón sea POST, para más seguridad.
    exit;
}

$id_usuario = $_SESSION['id'];
$id_cancha = filter_input(INPUT_POST, 'id_cancha', FILTER_VALIDATE_INT);
$modo = $_POST['modo'] ?? 'nuevo'; //Modo se crea como variable auxiliar, en este caso es si nosotros queremos editar o eliminar nuestra reseña

//----------------------------ELIMINAR VALORACION--------------------------------------------------------------------------------------

if ($modo === 'eliminar') {
    if (!$id_cancha) {
        $_SESSION['error'] = "Datos inválidos.";
        header("Location: buscador.php");
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM valoracion WHERE id_cancha = ? AND id_usuario = ?");
        $resultado = $stmt->execute([$id_cancha, $id_usuario]);
        
        if ($resultado && $stmt->rowCount() > 0) {
            $_SESSION['msg'] = "Tu valoración ha sido eliminada correctamente.";
        } else {
            $_SESSION['error'] = "No se pudo eliminar la valoración.";
        }
    } catch (PDOException $e) {
        error_log("Error al eliminar valoración: " . $e->getMessage());
        $_SESSION['error'] = "Error al eliminar la valoración. Por favor intentalo de nuevo.";
    }
    
    header("Location: reservacion.php?id={$id_cancha}");
    exit;
}

//-----------------------------------------------------------------------------------------------------------------------------------------



//--------------------------------------CREAR VALORACIÓN----------------------------------------------------

$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_INT);
$comentario = trim($_POST['comentario'] ?? '');

//Revisamos que las estrellitas esas sean igual o mayor a uno e igual o menor a 5
if (!$id_cancha || !$valor || $valor < 1 || $valor > 5) {
    $_SESSION['error'] = "Datos inválidos!. Por favor seleccioná una puntuación entre 1 y 5.";
    header("Location: reservacion.php?id={$id_cancha}");
    exit;
}

//Con esto además de ponerle un límite al comentario, evitamos q puedan ingresar scripts muy picantes viste xd
if (strlen($comentario) > 777) {
    $_SESSION['error'] = "El comentario es muy largo (máximo 777 caracteres).";
    header("Location: reservacion.php?id={$id_cancha}");
    exit;
}

//---------------------------------------------------------------------------------------------------------------




//-----------------------------------EDITAR-------------------------------------------------------------------
try {
    if ($modo === 'editar') { //Se actualiza la ya existentee.
        $stmt = $pdo->prepare("
            UPDATE valoracion 
            SET valor = ?, comentario = ?, fecha = NOW() 
            WHERE id_cancha = ? AND id_usuario = ?
        ");
        $resultado = $stmt->execute([$valor, $comentario, $id_cancha, $id_usuario]);
        
        if ($resultado && $stmt->rowCount() > 0) {
            $_SESSION['msg'] = "Tu valoración fue actualizada correctamente.";
        } else {
            $_SESSION['error'] = "No se pudo actualizar la valoración.";
        }
    } else { //Se ingresa o inserta o como se diga la nueva valoración.
        $stmt = $pdo->prepare("
            INSERT INTO valoracion (valor, comentario, id_usuario, id_cancha, fecha) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $resultado = $stmt->execute([$valor, $comentario, $id_usuario, $id_cancha]);
        
        if ($resultado) {
            $_SESSION['msg'] = "Tu valoración fue exitosa :)."; //Si está bien,
        } else {
            $_SESSION['error'] = "No se pudo guardar la valoración :(."; //Si está mal.
        }
    }
//----------------------------------------------------------------------------------------------------------
    
} catch (PDOException $e) {
    //Error 23000 es error de clave duplicada, ya existe, no es ningún codigo ni nada-
    //De esta forma el programa dice, hell nah no vas a crear una valoración nueva pq ya teneés una, te la edito jiji.
    if ($e->getCode() == 23000) {
        $_SESSION['error'] = "Ya la valoraste nene.";
    } else {
        error_log("Error en valoración: " . $e->getMessage());
        $_SESSION['error'] = "Por el amor a dios intentalo de nuevo que algo salió mal.";
    }
}

// Redirigeeee
header("Location: reservacion.php?id={$id_cancha}");
exit;
?>