<?php
session_start();
require_once 'conexiones/conDB.php';

$nombre = $_SESSION['nombre'] ?? null; //Si existe el nombre y rol que lo asigne, sino q no ponga nada. Asi la gente sin iniciar sesion puede entrar.
$rol = $_SESSION['rol'] ?? null;
$foto = $_SESSION['foto'] ?? null; // Obtener la foto de la sesión
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
//--------------------------------------------------------------------------------------------------

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

//-----------------------------------------------------------------------

//------ BÚSQUEDA DE CANCHAS ------

//Creamos filtros para poder encontrar la cancha que queremos.
//De esta forma indicamos parámetros y con el LIKE y %% podemos encontrar alguna similitud.

$buscarNombre = $_GET['nombre'] ?? '';
$buscarLugar = $_GET['lugar'] ?? '';
$buscarBio = $_GET['bio'] ?? '';
$buscarPrecioMin = $_GET['precio_min'] ?? '';
$buscarPrecioMax = $_GET['precio_max'] ?? '';

try {
  $sql = "SELECT * FROM cancha WHERE 1=1"; //1=1 es una forma de generar un "true", de esta forma siempre podemos ir agregando condiciones sin preocuparnos de q alguna no sea correcta.
  $params = []; //Con este guardamos todos los datos.

  if (!empty($buscarNombre)) {
    $sql .= " AND nombre LIKE ?";
    $params[] = "%$buscarNombre%";
  }

  if (!empty($buscarLugar)) {
    $sql .= " AND lugar LIKE ?";
    $params[] = "%$buscarLugar%";
  }

  if (!empty($buscarBio)) {
    $sql .= " AND bio LIKE ?";
    $params[] = "%$buscarBio%";
  }

  if (!empty($buscarPrecioMin)) {
    $sql .= " AND precio >= ?";
    $params[] = $buscarPrecioMin;
  }

  if (!empty($buscarPrecioMax)) {
    $sql .= " AND precio <= ?";
    $params[] = $buscarPrecioMax;
  }



  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $canchas = $stmt->fetchAll();

  if ($rol === 'duenio' && $idduenio) {
    $stmt2 = $pdo->prepare("SELECT * FROM cancha WHERE id_duenio = ?");
    $stmt2->execute([$idduenio]);
    $misCanchas = $stmt2->fetchAll();
  }
} catch (PDOException $e) {
  echo "Error al encontrar las canchas: " . $e->getMessage();
  $canchas = [];
  $misCanchas = [];
}


//verifica si hay filtros activos (sirve para poder limpiar y realizar la búsqueeda).
$hayFiltros = !empty($buscarNombre) || !empty($buscarLugar) || !empty($buscarBio) || !empty($buscarPrecioMin) || !empty($buscarPrecioMax);

//---------------------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buscador CanchApp</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
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
  </style>
</head>


<body>
  <div class="container-fluid p-0 m-0" style="background-color: #f0f0f0; min-height: 100vh;">
    <section class="text-center text-white d-flex flex-column p-0 m-0 rounded-top-0" id="inicio">
<<<<<<< HEAD
      <!-- Navbar -->
      <div class="row p-3 rounded-bottom-300 px-3" id="navbar">
        <div class="col-12 rounded-bottom-300 px-3" >
          <nav class="navbar navbar-expand-lg" >
=======

      <!-- Navbar ----------------------------------------------------------------------------------------------------->
      <div class="row" id="navbar">
        <div class="col-12">
          <nav class="navbar navbar-expand-lg">
>>>>>>> a2988790846f653770bf2f044ed2e36559639718
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
                    <a class="nav-link mx-lg-2 " href="index.php">Inicio</a>
                  </li>
                  <?php if ($rol === 'duenio'): ?>
                    <li class="nav-item">
                      <a class="nav-link mx-lg-2" href="gestion.php">Gestión</a>
                    </li>
                  <?php endif; ?>
                  <li class="nav-item">
                    <a class="nav-link mx-lg-2 active" aria-current="page" href="buscador.php">Reservar</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link mx-lg-2" href="acerca-de.php">Acerca de</a>
                  </li>
                </ul>
              </div>
            </div>

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
                      <?= strtoupper(substr($nombre, 0, length: 1)) ?>
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
      <!-- Fin Navbar --------------------------------------------------------------------------------------------------------->

     <!-----------------------BUSCAR CANCHA------------------------------------->

<<<<<<< HEAD

     <!-----------------------BUSCAR CANCHA------------------>

      <!--nia ichi ni san nia kevin ariel gatoooooooo :) -->
      <div class="row mb-4 rounded-bottom-300 px-3">
=======
      <div class="row mb-4">
>>>>>>> a2988790846f653770bf2f044ed2e36559639718
        <div class="col-12">
          <div class="card text-dark rounded-top-300">
            <!--Brad, si estás viendo esto, antes de text-white, ponele esto para ver la caja ""card bg-dark"" (sin las comillas)-->
            <div class="card-header ">
              <h5 class="mb-0">
                <i class="fas fa-filter"></i> Buscar
              </h5>
            </div>
            <div class="card-body">
              <form method="GET" action="buscador.php">
                <div class="row g-3 align-items-end">

                  <!--NOMBRE DE CANCHA -->
                  <div class="col-md-2">
                    <label for="nombre" class="form-label">
                      <i class="fas fa-font"></i> Nombre
                    </label>
                    <input type="text" class="form-control" id="nombre" name="nombre"
                      value="<?= htmlspecialchars($buscarNombre) ?>">
                  </div>

                  <!--LUGAR -->
                  <div class="col-md-2">
                    <label for="lugar" class="form-label">
                      <i class="fas fa-map-marker-alt"></i> Ubicación
                    </label>
                    <input type="text" class="form-control" id="lugar" name="lugar"
                      value="<?= htmlspecialchars($buscarLugar) ?>">
                  </div>

                  <!--FILTRO BIOO -->
                  <div class="col-md-3">
                    <label for="bio" class="form-label">
                      <i class="fas fa-align-left"></i> Descripción
                    </label>
                    <input type="text" class="form-control" id="bio" name="bio"
                      value="<?= htmlspecialchars($buscarBio) ?>">
                  </div>

                  <!--FILTRO PRECIO -->
                  <div class="col-md-3">
                    <label class="form-label">
                      <i class="fas fa-dollar-sign"></i> Rango de Precio
                    </label>
                    <div class="input-group">
                      <input type="number" class="form-control" name="precio_min" placeholder="Mín"
                        value="<?= htmlspecialchars($buscarPrecioMin) ?>">
                      <span class="input-group-text">-</span>
                      <input type="number" class="form-control" name="precio_max" placeholder="Máx"
                        value="<?= htmlspecialchars($buscarPrecioMax) ?>">
                    </div>
                  </div>

                  <!--BOTONES PARA BUSCAR Y LIMPIAR-->
                  <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-success w-100">
                      <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if ($hayFiltros): ?>
                      <a href="buscador.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i> Limpiar
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <!-------------------------------TERMINA BUSCAR CANCHA------------------------------->
    </section>
    






    <!--------------------------------------FAVORITOS--------------------------------------------------->
    

    <?php if ($misFavoritos): ?>
    <h5 >Mis Favoritos</h5>
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
                    <button type="submit" name="accion" value="toggle_favorito" class="btn btn-favorito rounded-circle p-2 border-0 shadow-sm" style="width: 40px; height: 40px;">
                        <span class="fs-5"><?= in_array($cancha['id_cancha'], $favoritosIds) ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-suit-heart-fill" viewBox="0 0 16 16">
  <path d="M4 1c2.21 0 4 1.755 4 3.92C8 2.755 9.79 1 12 1s4 1.755 4 3.92c0 3.263-3.234 4.414-7.608 9.608a.513.513 0 0 1-.784 0C3.234 9.334 0 8.183 0 4.92 0 2.755 1.79 1 4 1"/>
</svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-suit-heart" viewBox="0 0 16 16">
  <path d="m8 6.236-.894-1.789c-.222-.443-.607-1.08-1.152-1.595C5.418 2.345 4.776 2 4 2 2.324 2 1 3.326 1 4.92c0 1.211.554 2.066 1.868 3.37.337.334.721.695 1.146 1.093C5.122 10.423 6.5 11.717 8 13.447c1.5-1.73 2.878-3.024 3.986-4.064.425-.398.81-.76 1.146-1.093C14.446 6.986 15 6.131 15 4.92 15 3.326 13.676 2 12 2c-.777 0-1.418.345-1.954.852-.545.515-.93 1.152-1.152 1.595zm.392 8.292a.513.513 0 0 1-.784 0c-1.601-1.902-3.05-3.262-4.243-4.381C1.3 8.208 0 6.989 0 4.92 0 2.755 1.79 1 4 1c1.6 0 2.719 1.05 3.404 2.008.26.365.458.716.596.992a7.6 7.6 0 0 1 .596-.992C9.281 2.049 10.4 1 12 1c2.21 0 4 1.755 4 3.92 0 2.069-1.3 3.288-3.365 5.227-1.193 1.12-2.642 2.48-4.243 4.38z"/>
</svg>' ?></span>
                    </button>
                </form>
                
                <!-- Imagen -->
                <?php if ($cancha['foto']): ?>
                    <img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" class="img-fluid" style="height: 300px; width: 100%; object-fit: cover;">
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
                <p class="text-secondary mb-3"><?php echo htmlspecialchars($cancha['lugar']); ?></p>
                
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


    <!---------------------------------------CANCHAS NORMALES---------------------------------------------------------------->


    <!------------------------------------------------------------------------------------------------------->

    <h5 style="color: #0a0505ff;">Canchas registradas</h5>
      <?php if ($canchas && count($canchas) > 0): ?>
        <div class="row g-4 p-1">
          <?php foreach ($canchas as $cancha): ?>
          <?php
          $rating = obtenerPromedioValoracion($pdo, $cancha['id_cancha']);
          $promedio = $rating['promedio'];
          $total = $rating['total'];
          ?>
          
          <!-- Card -->
          <div class="col-12 col-md-6 col-lg-4">
              <div class="card shadow-lg rounded-4 overflow-hidden border-0 h-100">
                  <!-- Imagen de la cancha -->
                  <div class="card-image position-relative">
                      <!-- Botón de favoritos -->
                      <form method="post" class="position-absolute top-0 end-0 m-3">
                          <input type="hidden" name="id_cancha" value="<?= $cancha['id_cancha'] ?>">
                          <button type="submit" name="accion" value="toggle_favorito" class="btn btn-favorito rounded-circle p-2 border-0 shadow-sm" style="width: 40px; height: 40px;">
                              <span class="fs-5"><?= in_array($cancha['id_cancha'], $favoritosIds) ? '⭐' : '☆' ?></span>
                          </button>
                      </form>
                      
                      <!-- Imagen -->
                      <?php if ($cancha['foto']): ?>
                          <img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" class="img-fluid" style="height: 300px; width: 100%; object-fit: cover;">
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
                      <p class="text-secondary mb-3"><?php echo htmlspecialchars($cancha['lugar']); ?></p>
                      
                      <!-- Descripción -->
                      <button type="button" class="btn btn-outline-secondary btn-sm mb-4" data-bs-container="body" data-bs-toggle="popover" data-bs-placement="bottom" data-bs-content="<?php echo htmlspecialchars($cancha['bio']); ?>">
                          Ver Descripción
                      </button>
                      
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
        <?php else: ?>
        <div class="alert alert-info text-center">No hay canchas registradas.</div>
        <?php endif; ?>

    <!--Bootstrap 5 JS Bundle (incluye Poppers que es una librería)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
      const collapses = document.querySelectorAll('.collapse'); //Busca todos los elementos con clase .collapse.

      collapses.forEach((item) => {
        item.addEventListener('show.bs.collapse', () => { //Un .collapse está abierto.
          collapses.forEach((el) => {
            if (el !== item) {
              const collapseInstance = bootstrap.Collapse.getInstance(el);
              if (collapseInstance) {
                collapseInstance.hide(); // Esto hace que a lo hora de abrir un .collapse, cierre los demás en caso de haber otro abierto.
              }
            }
          });
        });
      });
    </script>

    <script>
      const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]') //Todos los objetos que tenga esa clase crea un popover.
      const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
    </script>
  </div>
</body>

</html>