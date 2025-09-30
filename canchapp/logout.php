<?php
session_start();        //Se inicia sesion
session_unset();        //Borramos TODOS los datos de la sesion
session_destroy();      //Y finalmente destruimos la sesión

//Te manda al index al desloguearte
header("Location: index.php");
exit;