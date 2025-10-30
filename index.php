<?php
session_start();
$nombre = $_SESSION['nombre'] ?? null;
$rol = $_SESSION['rol'] ?? null;
$foto = null;
$idduenio = $_SESSION['id'] ?? null; //Creo variable para sacar la ID

$id_usuario = $_SESSION['id'] ?? null;

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

//----------------PARA VER EL PROMEDIO DE ESTRELLAS PARA LA CANCHA!--------------------------------------------------
function obtenerPromedioValoracion($pdo, $id_cancha)
{
  try {
    $stmt = $pdo->prepare("
            SELECT 
                COALESCE(AVG(valor), 0) as promedio,
                COUNT(*) as total
            FROM valoracion 
            WHERE id_cancha = ?
        ");
    $stmt->execute([$id_cancha]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log("Error getting rating: " . $e->getMessage());
    return ['promedio' => 0, 'total' => 0];
  }
}

//AGREGAR A FAVORITOSS LA CANCHA--------------------------------------------------------------------

$misFavoritos = [];
$favoritosIds = []; //Le ponemos ID asi se storea mas fácil.

if ($id_usuario) {
  $stmt = $pdo->prepare("
        SELECT c.*, f.id_favorito
        FROM cancha c
        INNER JOIN favoritos f ON c.id_cancha = f.id_cancha 
        WHERE f.id_usuario = ?
    ");
  $stmt->execute([$id_usuario]);
  $misFavoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  //Crea un array de las canchas favoritas para que sea mas lindo a la vista
  $favoritosIds = array_column($misFavoritos, 'id_cancha');
}

//Saca o pone en favoritos.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'toggle_favorito') {
  if (!$id_usuario) {
    header("Location: login.php");
    exit;
  }

  $id_cancha = $_POST['id_cancha'];

  try {
    //SI YA ESTÁ EN FAVORITOS
    $stmt = $pdo->prepare("SELECT id_favorito FROM favoritos WHERE id_usuario = ? AND id_cancha = ?");
    $stmt->execute([$id_usuario, $id_cancha]);
    $existe = $stmt->fetch(); //creamos la variable "existe" en la busqueda de canchas, por ende revisa si esa cancha existe en favoritos, si si, te deja eliminarla, si no, se añadae.

    if ($existe) {
      //SACAR DE FAVORITOS
      $stmt = $pdo->prepare("DELETE FROM favoritos WHERE id_usuario = ? AND id_cancha = ?");
      $stmt->execute([$id_usuario, $id_cancha]);
      $msg = "Cancha removida de favoritos";
    } else {
      //AÑADIR A FAVORITOS
      $stmt = $pdo->prepare("INSERT INTO favoritos (id_usuario, id_cancha) VALUES (?, ?)");
      $stmt->execute([$id_usuario, $id_cancha]);
      $msg = "Cancha agregada a favoritos";
    }

    //se restartea la pagina asi se ven los nuevos cambios.
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;

  } catch (Exception $e) {
    $msg = "Error: " . $e->getMessage();
  }
}

$reservarmsj = '';
$valoracionmsj = '';
$ver = '';
$pedir = "";
$calendario = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') { //En caso de que quiera accionar algún post y no tenga rol se lo redirige al index.
  if (!$rol) {
    header("Location: inicioses.php?redirect=" . urlencode($_SERVER['PHP_SELF']));
    exit;
  }

  if (isset($_POST['reservar'])) {
    $reservarmsj = "¡Reserva realizada con éxito!";
  } elseif (isset($_POST['valorar'])) {
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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CanchApp - Reserva tu cancha</title>
  <link rel="stylesheet" href="style.css">

  <!-- Estilos para el tutorial -->
  <style>
    .tutorial-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.7);
      /*Esto cambia el fondo que se ilumina*/
      display: none;
      z-index: 9998;
    }

    .tutorial-highlight {
      position: relative;
      z-index: 9999 !important;
      box-shadow: 0 0 0 4px rgba(255, 255, 0, 0.8), 0 0 20px rgba(255, 255, 0, 0.5);
      /*Esto cambia el recuadro que se ilumina*/
      border-radius: 6px;
      transition: all 0.3s ease;
    }

    .tutorial-tooltip {
      position: absolute;
      background: white;
      color: #333;
      padding: 15px 20px;
      border-radius: 10px;
      font-size: 14px;
      max-width: 280px;
      z-index: 10000;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      /*Esto cambia la letra de color*/
      text-align: center;
    }

    .tutorial-tooltip p {
      margin: 0 0 10px 0;
      /*Para la letra tamb*/
      line-height: 1.4;
    }

    .tutorial-btn-container {
      display: flex;
      gap: 10px;
      justify-content: center;
      /*Contenedor del tutorial!*/
      margin-top: 10px;
    }

    .tutorial-btn {
      background: #088d03ff;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 8px 16px;
      cursor: pointer;
      font-size: 13px;
      transition: background 0.2s;
    }

    .tutorial-btn:hover {
      background: #088d03ff;
    }


    .tutorial-progress {
      font-size: 11px;
      color: #666;
      /*Esto es lo que dice 1/7 y asi*/
      margin-top: 8px;
    }

    /* cards de favoritos */
    .card-image {
      background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
      height: 300px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-reservar {
      background: linear-gradient(135deg, #ff8a50 0%, #ff6b35 100%);
    }

    .btn-favorito {
      background: white;
      transition: transform 0.2s;
    }

    .btn-favorito:hover {
      transform: scale(1.1);
    }

    .star-filled {
      color: #ffc107;
    }

    .star-empty {
      color: #ddd;
    }

    section {
      height: 90vh;
      /* Ocupa toda la altura de la pantalla */
      background-image: url('image/inicio-2.jpg');
      /* Reemplazá con tu imagen */
      background-size: cover;
      /* Asegura que la imagen cubra todo el área */
      background-position: center;
      /* Centra la imagen */
      background-repeat: no-repeat;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: white;
      padding: 0 20px;

    }

    #carousel-section {
      padding-left: 0;
      padding-right: 0;
    }
    .blur-container {
        backdrop-filter: blur(20px);
        background-color: rgba(255, 255, 255, 0.3);
        /* semitransparente */
        padding: 20px;
        border-radius: 10px;
    }
  </style>

</head>

<body>

  <div class="container-fluid p-0 m-0" style="background-color: #F1F0E9; min-height: 100vh;">


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
                  <a class="nav-link mx-lg-2 active" aria-current="page" href="index.php" id="btnInicio">Inicio</a>
                </li>
                <?php if ($rol === 'duenio'): ?>
                  <li class="nav-item">
                    <a class="nav-link mx-lg-2" href="gestion.php" id="btnGestion">Gestión</a>
                  </li>
                <?php endif; ?>
                <?php if ($rol === 'duenio' || $rol === 'admin' || $rol === 'usuario'): ?>
                  <li class="nav-item">
                    <a class="nav-link mx-lg-2" href="buscador.php" id="btnReservar">Reservar</a>
                  </li>
                <?php endif; ?>
                <li class="nav-item">
                  <a class="nav-link mx-lg-2" href="acerca-de.php" id="btnAcerca">Acerca de</a>
                </li>
              </ul>
            </div>
          </div>

          <!--FOTO DE PERFIL------------------------------------------------------------------------------------->
          <?php if ($nombre): ?>
          <div class="dropdown">
            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false"
              id="btnPerfil">
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
            <!-------------------------------------------------------------------------------------------------->

              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <h6 class="dropdown-header">¡Hola, <?= htmlspecialchars($nombre) ?>!</h6>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <!--VER PERFIL DE PADEL DEL USUARIO-->
                <?php if ($rol === 'usuario'): ?>
                  <li><a class="dropdown-item"
                      href="perfil_padel.php?from=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                      <i class="fas fa-user me-2"></i>Editar Perfil
                    </a></li>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                <?php endif; ?>

                <!--DESLOGUEAR------------------------------->
              <li><a class="dropdown-item text-danger" href="logout.php">
                  <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                </a></li>

            </ul>
          </div>
          <!--LOGUEARSE SI NO TIENE CUENTA-->
          <?php else: ?>
            <a href="inicioses.php" class="login-button btn btn-primary" id="btnGuess">Login</a>
          <?php endif; ?>

          <button class="navbar-toggler pe-0 ms-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
        </nav>
      </div>
    </div>
    <!-- Fin Navbar -->
    <section class="text-center text-white d-flex flex-column">
      <!-- Overlay del tutorial -->
      <div id="tutorialOverlay" class="tutorial-overlay"></div>

      <!--INICIO-->
      <div class="row py-5 mb-5 mt-3">
        <div
          class="col-12 d-flex flex-column justify-content-center align-items-center text-center rounded blur-container">
          <h1 class="text-center-left text-white">
            ¡Hola, <?= htmlspecialchars($nombre ?? 'a CanchApp') ?>!
          </h1>
          <?php if ($rol === 'usuario'): ?>
            <p class="text-center-left text-white">Tu sitio de confianza para reservar canchas.</p>
          <?php endif; ?>
          <?php if ($rol === 'duenio'): ?>
            <p class="text-center-left text-white">Tu sitio de confianza para gestionar canchas.</p>
          <?php endif; ?>
          <?php if ($rol === null): ?>
            <p class="text-center-left text-white">Tu sitio de confianza para reservar o gestionar canchas.</p>
          <?php endif; ?>

          <!--Botón para iniciar tutorial-->
          <button id="btnIniciarTutorial" class="btn btn-info mt-2 mb-3">
            <i class="fas fa-question-circle"></i> Ver Tutorial
          </button>

          <!-- Botones de acción principales -->
          <div class="mt-3" id="botonesAccion">
            <form method="post" class="d-inline">
              <button type="submit" name="ver" class="btn btn-success me-2" id="btnVerCanchas">Ver Canchas</button>
            </form>

            <?php if ($rol === 'usuario'): ?>
              <form method="post" class="d-inline">
                <button type="submit" name="pedir" class="btn btn-warning" id="btnDueño">Ser Dueño</button>
              </form>
            <?php elseif (!$nombre): ?>
              <a href="inicioses.php" class="btn btn-primary">Iniciar Sesión</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </section>

    
<!-- 
    <div class="row mt-5 d-flex justify-content-center align-items-center ">
      <div class="col-6">
        <div class="card shadow-sm" style="width: 18rem;">
          <img src="https://via.placeholder.com/288x160?text=CanchApp" class="card-img-top" alt="CanchApp cancha">
          <div class="card-body text-center">
            <h5 class="card-title">Reservá tu cancha</h5>
            <p class="card-text">Con CanchApp, encontrá y reservá tu cancha favorita en segundos. ¡Jugá sin
              complicaciones!</p>
            <a href="#reservar" class="btn btn-primary">Reservar ahora</a>
          </div>
        </div>
      </div>
    </div> -->





    <!-- Carrusel -->
    <div id="carousel-section">
      <div class="row py-5 mt-5 rounded shadow-lg" style="background-color: #F1F0E9;">
        <div class="col-12 d-flex justify-content-center align-items-center mx-auto shadow-lg" style="height:400px;">
          <div id="carouselExampleCaptions" class="carousel slide w-100 h-100">
            <div class="carousel-indicators">
              <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="active"
                aria-current="true" aria-label="Slide 1"></button>
              <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1"
                aria-label="Slide 2"></button>
              <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2"
                aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner h-100">
              <div class="carousel-item active h-100">
                <img src="image/raqueta.avif" class="d-block w-100 h-100" alt="Padel racket resting on a court"
                  style="object-fit:cover;">
                <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-3">
                  <h5 class="fw-bold text-success">Reserva Fácil</h5>
                  <p class="text-white">Elige tu cancha y horario en segundos. ¡Jugar nunca fue tan simple!</p>
                </div>
              </div>
              <div class="carousel-item h-100">
                <img src="image/gestion.jpg" class="d-block w-100 h-100" alt="Gestiona tus canchas"
                  style="object-fit:cover;">
                <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-3">
                  <h5 class="fw-bold text-primary">Gestión Rápida</h5>
                  <p class="text-white">Administra disponibilidad y horarios con un solo click.</p>
                </div>
              </div>
              <div class="carousel-item h-100">
                <img src="image/grupopadel.avif" class="d-block w-100 h-100" alt="Disfruta el pádel"
                  style="object-fit:cover;">
                <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-3">
                  <h5 class="fw-bold text-warning">Disfruta el Pádel</h5>
                  <p class="text-white">Vive la mejor experiencia consiguiendo grupos para jugar al padel.</p>
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
    </div>


    <!-- Panel de control para dueños -->
    <?php if ($rol === 'duenio'): ?>
      <div class="row panel-control py-5 px-5 mt-5 rounded shadow text-black">
        <div class="col-12 text-center mb-4">
          <h2 class="fw-bold mb-3">Panel de Dueño</h2>
          <p class="fs-5">Administra tus canchas y reservas desde aquí</p>
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


    <!--------------------------------------FAVORITOS--------------------------------------------------->


    <?php if ($misFavoritos): ?>
    <h1>Mis Favoritos</h1>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 px-1">
      <?php foreach ($misFavoritos as $cancha): ?>
      <?php
      $rating = obtenerPromedioValoracion($pdo, $cancha['id_cancha']);
      $promedio = $rating['promedio'];
      $total = $rating['total'];
      ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-lg rounded-4 overflow-hidden border-0 h-100">
          <!-- Imagen de la cancha -->
              <div class="card-image position-relative">
                <!-- Botón de favoritos -->
                <form method="post" class="position-absolute top-0 end-0 m-3">
                  <input type="hidden" name="id_cancha" value="<?= $cancha['id_cancha'] ?>">
                  <button type="submit" name="accion" value="toggle_favorito"
                    class="btn btn-favorito rounded-circle p-2 border-0 shadow-sm" style="width: 40px; height: 40px;">
                    <span class="fs-5"><?= in_array($cancha['id_cancha'], $favoritosIds) ? '⭐' : '☆' ?></span>
                  </button>
                </form>

                <!-- Imagen -->
                <?php if ($cancha['foto']): ?>
                  <img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" class="img-fluid"
                    style="height: 300px; width: 100%; object-fit: cover;">
                <?php endif; ?>

                <!-- Precio -->
                <div class="position-absolute bottom-0 end-0 bg-white rounded-pill px-3 py-1 m-3 fw-semibold small">
                  Desde $<?php echo htmlspecialchars($cancha['precio']); ?>
                </div>
              </div>

              <!-- Contenido de la tarjeta -->
              <div class="card-body p-4 d-flex flex-column">
                  <div class="text-secondary text-uppercase small mb-2 fw-medium" style="letter-spacing: 0.5px;">
                      Cancha Deportiva
                  </div>
                  <h2 class="fs-3 fw-bold mb-3 text-dark"><?php echo htmlspecialchars($cancha['nombre']); ?></h2>
                  <p class="text-secondary mb-3">Ciudad: <?php echo htmlspecialchars($cancha['ciudad']); ?></p>
                  <p class="text-secondary mb-3">Direccion: <?php echo htmlspecialchars($cancha['direccion']); ?></p>
                  
                  <!-- Botón y Rating -->
                  <div class="row align-items-center mt-auto">
                      <div class="col-auto">
                          
                          <a href="reservacion.php?id=<?= $cancha['id_cancha'] ?>" class="btn btn-reservar text-white border-0 rounded-pill px-4 py-2 fw-semibold shadow-sm text-decoration-none">
                              Reservar
                          </a>
                      </div>
                      <div class="col">
                          <div class="d-flex flex-column align-items-end">
                              <small class="text-muted mb-1">Rating</small>
                              <?php if ($total > 0): ?>
                              <div class="d-flex gap-1 fs-5">
                                  <?php
                                  $stars = round($promedio);
                                  for ($i = 1; $i <= 5; $i++) {
                                      echo '<span class="' . ($i <= $stars ? 'star-filled' : 'star-empty') . '">' . ($i <= $stars ? '★' : '☆') . '</span>';
                                  }
                                  ?>
                              </div>
                              <small class="text-muted mt-1">
                                  <?= number_format($promedio, 1) ?>/5 (<?= $total ?> valoraciones)
                              </small>
                              <?php else: ?>
                              <div class="d-flex gap-1 fs-5">
                                  <span class="star-empty">☆</span>
                                  <span class="star-empty">☆</span>
                                  <span class="star-empty">☆</span>
                                  <span class="star-empty">☆</span>
                                  <span class="star-empty">☆</span>
                              </div>
                              <small class="text-muted mt-1">Sin valoraciones</small>
                              <?php endif; ?>
                          </div>
                      </div>
                  </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    <?php endif; ?>
    <hr class="h-200 mx-auto my-3 border-dark" style="height: 4px; background-color: #000; border: none;">

    <!-- Footer -->
    <footer class="mt-5" style="width: auto;">
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
  </div>

  <!------------------------------------------------SCRIPT SACADO DE BOOSTRAP PARA PODER HACER ESTO!!------------------------------------------------------------------------>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>

  <!--PARA AGREGAR BOTONES-->
  <script>
    document.addEventListener('DOMContentLoaded', function () { //Una vez que carga la página carga esto:
      const pasos = [
        {
          elemento: '#btnInicio',
          texto: ' Este es el botón de Inicio. Te lleva a la página principal donde estás ahora.'
        }<?php if ($rol === 'duenio'): ?>,

          {
            elemento: '#btnGestion',
            texto: ' Desde Gestión podés administrar tus canchas y ver las reservas.'
          }<?php endif; ?>,

        {
          elemento: '#btnReservar',
          texto: ' En Reservar podés buscar y reservar canchas disponibles.'
        },
        {
          elemento: '#btnAcerca',
          texto: ' Acá encontrarás información sobre CanchApp y cómo contactarnos.'
        }<?php if ($rol === null): ?>,

          {
            elemento: '#btnGuess',
            texto: ' Acá podrás registrarte y/o iniciar Sesión.'
          }<?php endif; ?> <?php if ($rol === 'usuario'): ?>,

          {
            elemento: '#btnPerfil',
            texto: ' Acá podrás ver tu perfil!.'
          }<?php endif; ?>,
        {
          elemento: '#btnDueño',
          texto: ' Acá podras pedir ser dueño!.'
        },
        {
          elemento: '#btnVerCanchas',
          texto: ' ¡Hacé click aquí para empezar a buscar canchas disponibles!'
        }
      ];

      let pasoActual = 0;
      const overlay = document.getElementById('tutorialOverlay');
      let tooltip = null;
      let elementoActual = null;

      function limpiarPaso() { //Vuelve a 0 los pasos una vez q cerrás.
        if (tooltip && tooltip.parentNode) {
          tooltip.remove(); //Y borra el cuadrito blanco anterior
        }
        if (elementoActual) {
          elementoActual.classList.remove('tutorial-highlight'); //Saca el resaltado
        }
        tooltip = null;
        elementoActual = null;
      }

      //Para que se vean los pasos---------------------------------------------------------------------------
      function mostrarPaso(index) {
        limpiarPaso(); //limpia paso anterior

        if (index >= pasos.length) { //Si no hay mas pasos termina tutorial
          finalizarTutorial();
          return;
        }

        const paso = pasos[index]; //La variable paso toma el valor del paso actual.
        const elem = document.querySelector(paso.elemento); //Con este busca el boton que tiene la ID que pusimos.

        if (!elem) { //Ponele q no sos dueño y no ves todos los pasos, se salta hasta encontrar el q te corresponda.
          pasoActual++;
          mostrarPaso(pasoActual);
          return;
        }
        //.................................................................

        //
        overlay.style.display = 'block'; //muestra la sombra esa.

        elem.scrollIntoView({ behavior: 'smooth', block: 'center' });

        setTimeout(() => {
          //Espera un cachito y resalta el elemento que estamos buscando.
          elem.classList.add('tutorial-highlight');
          elementoActual = elem;

          //ACA CREAS EL TOOLTIP Y PODÉS CAMBIAR LO QUE DICE EL CUADRITO EN LOS PASOS!! (CONTINUAR)
          tooltip = document.createElement('div');
          tooltip.className = 'tutorial-tooltip';
          tooltip.innerHTML = `
              <p><strong>${paso.texto}</strong></p>
              <div class="tutorial-btn-container">
                <button class="tutorial-btn" onclick="tutorialSiguiente()">
                  ${index === pasos.length - 1 ? 'Finalizar' : 'Siguiente'} 
                </button>
              </div>
              <div class="tutorial-progress">Paso ${index + 1} de ${pasos.length}</div>
            `;
          document.body.appendChild(tooltip);

          //Posiciona pooltip
          const rect = elem.getBoundingClientRect();
          const tooltipRect = tooltip.getBoundingClientRect();

          let top = rect.bottom + window.scrollY + 15;
          let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);

          //Ajusta el tamaño del tutorial
          if (left < 10) left = 10;
          if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
          }

          tooltip.style.top = top + 'px';
          tooltip.style.left = left + 'px';
        }, 300);
      }

      function finalizarTutorial() {
        limpiarPaso();
        overlay.style.display = 'none';
        pasoActual = 0;
      }

      //Funciones globales para los botones
      window.tutorialSiguiente = function () {
        pasoActual++;
        mostrarPaso(pasoActual);
      };
      //Inicia tutorial con el botón
      document.getElementById('btnIniciarTutorial').addEventListener('click', function () {
        pasoActual = 0;
        mostrarPaso(pasoActual);
      });

      //Cierra el tutorial al hacer click en el overlay (Afuera)
      overlay.addEventListener('click', function () {
        finalizarTutorial();
      });
    });
  </script>
</body>

</html>