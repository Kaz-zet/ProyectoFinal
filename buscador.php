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

//PARA EDITAR CANCHA!!

$msgError = []; //Se usan cuando querés editar una cancha, si hay error, lo guarda en este array.
$msgOk = [];

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

//EDITAR CANCHAS!!

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'editar') { //Utilizo el mismo filtro que al crear cancha pero en este caso saco su Id, y remplazo los datos utilizando esa ID.
  $id = $_POST['id_cancha'];
  $nombre = trim($_POST['nombre']);
  $lugar = trim($_POST['lugar']);
  $precio = trim($_POST['precio']);
  $bio = trim($_POST['bio']);
  $foto = "";

  if ($nombre === '' || $lugar === '' || $bio === '' || $precio === '') {
    $msgError[$id] = "Completa todos los campos.";
  } else {
    try {
      $stmt = $pdo->prepare("SELECT 1 FROM cancha WHERE nombre = ? AND id_cancha <> ?"); //<> permite que ponele, si qerés editar la cancha y dejás el mismo nombre, que no te mande q ya existe, sino q entienda q no la cambiaste.
      $stmt->execute([$nombre, $id]);

      if ($stmt->fetch()) {
        $msgError[$id] = "Ya existe otra cancha con ese nombre.";
      } else {

        if (!empty($_FILES['foto']['name'])) {
          // Buscar la foto vieja
          $stmt = $pdo->prepare("SELECT foto FROM cancha WHERE id_cancha = ?");
          $stmt->execute([$id]);
          $cancha = $stmt->fetch();

          if ($cancha && !empty($cancha['foto'])) {
            $rutaVieja = __DIR__ . "/uploads/" . $cancha['foto'];
            if (file_exists($rutaVieja)) {
              unlink($rutaVieja);
            }
          }

          // Guardar nueva foto
          $nombreArchivo = time() . "_" . basename($_FILES['foto']['name']);
          $rutaDestino = __DIR__ . "/uploads/" . $nombreArchivo;
          move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);

          $foto = $nombreArchivo;
        }
        if ($foto) {
          $stmt = $pdo->prepare("UPDATE cancha SET nombre = ?, lugar = ?, bio = ?, foto = ?, precio = ? WHERE id_cancha = ?");
          $stmt->execute([$nombre, $lugar, $bio, $foto, $precio, $id]);
        } else {
          $stmt = $pdo->prepare("UPDATE cancha SET nombre = ?, lugar = ?, bio = ?, precio = ? WHERE id_cancha = ?");
          $stmt->execute([$nombre, $lugar, $bio, $precio, $id]);
        }

        $_SESSION['msgOk'] = "Cancha editada correctamente.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
      }
    } catch (Throwable $e) {
      $msgError[$id] = "Error: " . $e->getMessage();
    }
  }
}
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
                    class="rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-secondary text-white"
                    style="width: 40px; height: 40px; font-size: 20px;">
                    👤
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



    <!-----------------------BUSCAR CANCHA------------------>

          <!--Contiene obviamente boostrap de chatpgt pq no se como hacerlo yo :) -->
    <div class="row mt-4 mb-4">
  <div class="col-12">
    <div class="text-white"> <!--Brad, si estás viendo esto, antes de text-white, ponele esto para ver la caja ""card bg-dark"" (sin las comillas)--> 
      <div class="card-header">
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

            <!--FILTRO RPECIO -->
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

            <!--BTOTNES PARA BUSCAR Y LIMPIAR-->
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



    <!--------------------------------------FAVORITOS--------------------------------------------------->

    <?php if ($misFavoritos): ?>
    <h1>Mis Favoritos</h1>
    <ul>
      <?php foreach ($misFavoritos as $cancha): ?>
      <li>
        <?= htmlspecialchars($cancha['nombre']) ?> -
        <?= htmlspecialchars($cancha['lugar']) ?> -
        <?= htmlspecialchars($cancha['precio']) ?> -
        <?= htmlspecialchars($cancha['bio']) ?>
        <?php if ($cancha['foto']): ?>
        <br><img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" width="100" height="60">
        <?php endif; ?> <a href="reservacion.php?id=<?= $cancha['id_cancha'] ?>"
          style="background: #000000ff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">
          Ver Detalles
        </a>
        <form method="post" style="display:inline;">
          <input type="hidden" name="id_cancha" value="<?= $cancha['id_cancha'] ?>">
          <button type="submit" name="accion" value="toggle_favorito">
            <?= in_array($cancha['id_cancha'], $favoritosIds) ? '⭐' : '☆' ?>
          </button>
        </form>
      </li>


      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <!------------------------------------------------------------------------------------------------------->

    <h1 style="color: #ffffffff;">Canchas registradas</h1>
    <?php if ($canchas && count($canchas) > 0): ?>
    <!--permite comprobar que existan canchas y que tengan datos adentro-->
      <ul>
        <?php foreach ($canchas as $cancha): ?>
          <li>
            <strong style="color: #ffffffff;"><?php echo htmlspecialchars($cancha['nombre']); ?></strong>
            <!--Muestra las variables q queremos-->
            <strong style="color: #ffffffff;">- Ubicación: <?php echo htmlspecialchars($cancha['lugar']); ?></strong>
            <strong style="color: #ffffffff;">- Precio: $<?php echo htmlspecialchars($cancha['precio']); ?></strong>
            <strong style="color: #ffffffff;">- Descipcion: <?php echo htmlspecialchars($cancha['bio']); ?></strong>
            <?php if ($cancha['foto']): ?>
              <br><img src="uploads/<?= htmlspecialchars($cancha['foto']) ?>" width="100" height="60">
            <?php endif; ?>
            <a href="reservacion.php?id=<?= $cancha['id_cancha'] ?>"
              style="background: #000000ff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">
              Ver Detalles
            </a>



            <!--PARA AGREGAR FAV CANCHAS (EL CODE ESTÁ EN ESTE PHP, MIS FAVORTIOS.PHP NO ANDA)-->


            <form method="post" style="display:inline;">
              <input type="hidden" name="id_cancha" value="<?= $cancha['id_cancha'] ?>">
              <button type="submit" name="accion" value="toggle_favorito">
                <?= in_array($cancha['id_cancha'], $favoritosIds) ? '⭐' : '☆' ?>
              </button>
            </form>



          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>No hay canchas registradas.</p>
    <?php endif; ?>
    <a href="index.php">Volver</a>

    <!-- Bootstrap 5 JS Bundle (incluye Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>