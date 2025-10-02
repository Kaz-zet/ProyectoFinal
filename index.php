<?php
session_start();
$nombre = $_SESSION['nombre'] ?? null; //Si existe el nombre y rol que lo asigne, sino q no ponga nada. Asi la gente sin iniciar sesion puede entrar.
$rol = $_SESSION['rol'] ?? null;
$foto = null; // Obtener la foto de la sesión

//---------------FOTO DE PERFIL----------------------------------------------
if ($nombre) {
    require_once 'conexiones/conDB.php';
    try {
        $stmt = $pdo->prepare("SELECT foto FROM usuario WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['foto'])) {
            $foto = $row['foto'];
        }
    } catch (PDOException $e) { 
        error_log("Error fetching foto: " . $e->getMessage());
    }
}
//----------------------------------------------------------------

$reservarmsj = ''; //Se inicia la variable.
$valoracionmsj = '';
$ver = '';
$pedir = "";
$calendario = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') { //Esto hace q el login sea necesario unicamente cuando se activa algun boton o le pedis algo al servidor, si chusmeas no pasa nada.
  if (!$rol) {
    //Acá chusmea si está logueado, osea si tiene algún rol, sino lo manda al login.
    header("Location: inicioses.php?redirect=" . urlencode($_SERVER['PHP_SELF']));
    exit;
  }

  if (isset($_POST['reservar'])) {
    $reservarmsj = "¡Reserva realizada con éxito!";
  } elseif (isset($_POST['valorar'])) { //Adentro va el nombre del boton, entonces sería, si vos apretas el boton de reservar, te manda un mensaje y en este caso cada uno tiene color.
    $valoracionmsj = "¡Valoración enviada!";
  } elseif (isset($_POST['ver'])) {
    $ver = "¡!";
  } elseif (isset($_POST['pedir'])) {
    $pedir = "¡!";
  } elseif (isset($_POST['calendario'])) {
    $calendario = "¡!";
  }
}

// Redirects después del procesamiento
if ($ver)
  header("Location: buscador.php");
if ($pedir)
  header("Location: peticion.php");
if ($calendario)
  header("Location: calendario.php");
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <!-- Font Awesome para iconos -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CanchApp - Reserva tu cancha</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="container-fluid p-2">
    <!-- Navbar -->
    <div class="row" id="navbar">
      <div class="col-12">
        <nav class="navbar navbar-expand-lg">
          <a class="navbar-brand me-auto" href="#">
            <img src="image/icon.png" alt="Logo" width="85" height="60" class="d-inline-block align-text-top">
          </a>
          <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar"
            aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
              <h5 class="offcanvas-title" id="offcanvasNavbarLabel">CanchApp</h5>
              <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
              <ul class="navbar-nav justify-content-center flex-grow-1 pe-3">
                <li class="nav-item">
                  <a class="nav-link mx-lg-2 active" aria-current="page" href="index.php">Inicio</a>
                </li>
                <?php if ($rol === 'duenio'): ?>
                  <li class="nav-item">
                    <a class="nav-link mx-lg-2" href="gestion.php">Gestión</a>
                  </li>
                <?php endif; ?>
                <?php if ($rol === 'duenio' || $rol === 'admin' || $rol === 'usuario'): ?>
                  <li class="nav-item">
                    <a class="nav-link mx-lg-2" href="buscador.php">Reservar</a>
                  </li>
                <?php endif; ?>
                <li class="nav-item">
                  <a class="nav-link mx-lg-2" href="acerca-de.php">Acerca de</a>
                </li>
              </ul>
            </div>
          </div>
                <!-- Skibidi Toilet-->
          <!-- Sistema de login/logout integrado -->
          <?php if ($nombre): ?>
            <div class="dropdown">
              <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (!empty($foto)): ?>
                  <img src="uploads/usuarios/<?= htmlspecialchars($foto) ?>"
                    alt="Foto de perfil de <?= htmlspecialchars($nombre) ?>"
                    class="rounded-circle border border-2 border-white" width="40" height="40" style="object-fit: cover;">
                <?php else: ?>
                  <div
                    class="rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-primary text-white"
                    style="width: 40px; height: 40px; font-size: 16px; font-weight: bold;">
                    <?= strtoupper(substr($nombre, 0, 1)) ?>
                  </div>
                <?php endif; ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <h6 class="dropdown-header">¡Hola, <?= htmlspecialchars($nombre) ?>!</h6>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <?php if ($rol === 'usuario'): ?>
                  <li><a class="dropdown-item" href="perfil_padel.php">
                      <i class="fas fa-user me-2"></i>Editar Perfil
                    </a></li>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                <?php endif; ?>
                <li><a class="dropdown-item text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                  </a></li>
              </ul>
            </div>
          <?php else: ?>
            <a href="inicioses.php" class="login-button btn btn-primary">Login</a>
          <?php endif; ?>

          <button class="navbar-toggler pe-0 ms-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
        </nav>
      </div>
    </div>
    <!-- Fin Navbar -->

    <!-- Bienvenida personalizada -->
    <div class="row py-5 mb-5 mt-3" id="inicio">
      <div class="col-12 d-flex flex-column justify-content-center align-items-center text-center">
        <h1 class="text-center-left text-white">
          Bienvenido/a <?= htmlspecialchars($nombre ?? 'a CanchApp') ?>!
        </h1>
        <p class="text-center-left text-white">Tu sitio de confianza para reservar o gestionar canchas.</p>

        <!-- Botones de acción principales -->
        <div class="mt-3">
          <!-- Visible para todos -->
          <form method="post" class="d-inline">
            <button type="submit" name="ver" class="btn btn-success me-2">Ver Canchas</button>
          </form>

          <!-- Solo para usuarios logueados -->
          <?php if ($rol === 'usuario'): ?>
            <form method="post" class="d-inline">
              <button type="submit" name="pedir" class="btn btn-warning">Ser Dueño</button>
            </form>
          <?php elseif (!$nombre): ?>
            <a href="inicioses.php" class="btn btn-primary">Iniciar Sesión</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Carrusel -->
    <div class="row py-5 mt-5 bg-white rounded shadow-lg">
      <div class="col-12 d-flex justify-content-center align-items-center mx-auto" style="height:400px;">
        <div id="carouselExampleCaptions" class="carousel slide w-100 h-100">
          <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="active"
              aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1"
              aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2"
              aria-label="Slide 3"></button>
          </div>
          <div class="carousel-inner h-100" style="height:100%;">
            <div class="carousel-item active h-100" style="height:100%;">
              <img src="image/raqueta.avif" class="d-block w-100 h-100"
                alt="Padel racket resting on a court, surrounded by green turf and white lines, evoking a sense of anticipation and excitement for a game"
                style="object-fit:cover; height:100%;">
              <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-3">
                <h5 class="fw-bold text-success">Reserva Fácil</h5>
                <p class="text-white">Elige tu cancha y horario en segundos. ¡Jugar nunca fue tan simple!</p>
              </div>
            </div>
            <div class="carousel-item h-100" style="height:100%;">
              <img src="image/raqueta.avif" class="d-block w-100 h-100" alt="Gestiona tus canchas"
                style="object-fit:cover; height:100%;">
              <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-3">
                <h5 class="fw-bold text-primary">Gestión Rápida</h5>
                <p class="text-white">Administra disponibilidad y horarios con un solo click.</p>
              </div>
            </div>
            <div class="carousel-item h-100" style="height:100%;">
              <img src="image/raqueta.avif" class="d-block w-100 h-100" alt="Disfruta el pádel"
                style="object-fit:cover; height:100%;">
              <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-3">
                <h5 class="fw-bold text-warning">Disfruta el Pádel</h5>
                <p class="text-white">Vive la mejor experiencia en nuestras canchas modernas y cómodas.</p>
              </div>
            </div>
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleCaptions"
            data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleCaptions"
            data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Panel de control para dueños -->
    <?php if ($rol === 'duenio'): ?>
      <div class="row py-5 px-5 mt-5 bg-primary rounded shadow">
        <div class="col-12 text-center mb-4">
          <h2 class="text-white fw-bold mb-3">Panel de Dueño</h2>
          <p class="text-light fs-5">Administra tus canchas y reservas desde aquí</p>
        </div>
        <div class="col-12 d-flex justify-content-center gap-4 flex-wrap">
          <div class="card shadow-sm" style="width: 20rem;">
            <div class="card-body text-center">
              <h5 class="card-title text-primary fw-semibold">Agregar Cancha</h5>
              <p class="card-text text-secondary">Añade nuevas canchas a tu inventario</p>
              <a href="dueño.php" class="btn btn-primary w-100">Agregar</a>
            </div>
          </div>
          <div class="card shadow-sm" style="width: 20rem;">
            <div class="card-body text-center">
              <h5 class="card-title text-success fw-semibold">Gestionar Reservas</h5>
              <p class="card-text text-secondary">Administra las reservas de tus canchas</p>
              <a href="gestion.php" class="btn btn-success w-100">Gestionar</a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Mensajes de confirmación -->
    <?php if ($reservarmsj): ?>
      <div class="row mt-3">
        <div class="col-12">
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $reservarmsj ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($valoracionmsj): ?>
      <div class="row mt-3">
        <div class="col-12">
          <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= $valoracionmsj ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        </div>
      </div>
    <?php endif; ?>

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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>