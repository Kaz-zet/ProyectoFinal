<?php
session_start();
require_once 'conexiones/conDB.php';

//Si ya tiene rol lo manda al index
if (isset($_SESSION['rol'])) {
  header('Location: index.php');
  exit;
}

$error_message = '';
$success_message = '';

// Logout
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
  $success_message = 'Sesión cerrada exitosamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL); //Saca &%() u todas esas cosas raras-
  $contrasena = $_POST['contrasena'] ?? '';

  if (empty($nombre) || empty($email) || empty($contrasena)) {
    $error_message = 'Por favor, complete todos los campos.';
  } else {
    try {
      $rol = null;
      $user = null;

      // Busca en Admin
      $stmt = $pdo->prepare("SELECT id_admin AS id, nombre, contrasena 
                                   FROM admin WHERE email = ?");
      $stmt->execute([$email]);
      $user = $stmt->fetch();

      if ($user && $user['contrasena'] === $contrasena && mb_strtolower($user['nombre']) === mb_strtolower($nombre)) {
        $rol = "admin";
      } else {
        // Busca en Dueño
        $stmt = $pdo->prepare("SELECT id_duenio AS id, nombre, contrasena
                                       FROM duenio WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['contrasena'] === $contrasena && mb_strtolower($user['nombre']) === mb_strtolower($nombre)) {
          $rol = "duenio";
        } else {
          // Y x ultimo Busca en Usuario
          $stmt = $pdo->prepare("SELECT id_usuario AS id, nombre, contrasena 
                                           FROM usuario WHERE email = ?");
          $stmt->execute([$email]);
          $user = $stmt->fetch();

          if ($user && $user['contrasena'] === $contrasena && mb_strtolower($user['nombre']) === mb_strtolower($nombre)) {
            $rol = "usuario";
          }
        }
      }

      if ($rol && $user) {
        //GUARDA ROL!!
        $_SESSION['id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $rol;

        if ($rol === "usuario") {
          $_SESSION['usuario'] = [
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'email' => $email
          ];
        }

        // Redirigir según rol
        if ($rol === "admin") {
          header("Location: admin.php");
        } elseif ($rol === "duenio") {
          header("Location: index.php");
        } else {
          header("Location: index.php");
        }
        exit;
      } else {
        $error_message = 'Credenciales incorrectas. Verifique nombre, email y contraseña.';
      }

    } catch (PDOException $e) {
      $error_message = 'Error en el sistema. Intente nuevamente.';
      error_log("Login error: " . $e->getMessage());
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - CanchApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="style.css">
</head>
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
    box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light);
    padding: 3rem;
    max-width: 450px;
    width: 100%;
    transition: all .8s ease-in-out;
  }

  .neumorphic-card:hover {
    box-shadow: 8px 8px 16px var(--shadow-light), -8px -8px 16px var(--shadow-dark);
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
    column-rule: var(--main-color);
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

  .form.label {
    color: var(--main-color);
    font-weight: 500;
  }

  .alert-custom {
    border-radius: 10px;
    border: none;
    box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
    margin-bottom: 1rem;
  }

  .role-info {
    background: var(--bg-color);
    border-radius: 15px;
    box-shadow: inset 3px 3px 6px var(--shadow-dark), inset -3px -3px 6px var(--shadow-light);
    padding: 1rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    color: var(--main-color);
  }

  .role-info h6 {
    color: var(--main-color);
    margin-bottom: 0.5rem;
  }

  .role-info small {
    display: block;
    margin-bottom: 0.3rem;
  }
</style>

<body>
  <div class="container-fluid p-2"
    style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-repeat: no-repeat;">

    <!--formulario-->
    <div id="main" class="d-flex justify-content-center align-items-center min-vh-100">
      <div class="neumorphic-card">
        <h1 class="text-center fw-bold mb-4">Iniciar Sesión</h1>

        <!-- Información de roles de prueba -->
        <div class="role-info">
          <h6>Cuentas de prueba:</h6>
          <small><strong>Usuario:</strong> Beti - beti@gmail.com - 123456</small>
          <small><strong>Dueño:</strong> A - A@gmail.com - 123456</small>
          <small><strong>Admin:</strong> Ad - Ad@gmail.com - 123</small>
        </div>

        <?php if ($error_message): ?>
          <div class="alert alert-danger alert-custom">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
          <div class="alert alert-success alert-custom">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST">
          <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control neumorphic-input" required id="nombre"
              placeholder="Ingrese su nombre"
              value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" class="form-control neumorphic-input" required id="email"
              placeholder="Ingrese su email"
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          <div class="mb-4">
            <label for="contrasena" class="form-label">Contraseña</label>
            <input type="password" name="contrasena" class="form-control neumorphic-input" required id="contrasena"
              placeholder="Ingrese su contraseña">
          </div>
          <div class="mb-3 text-center">
            <a href="registro.php" class="text-decoration-none" style="color: var(--main-color);">
              ¿No tienes una cuenta? Regístrate aquí
            </a>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn neumorphic-btn">Iniciar Sesión</button>
          </div>
        </form>
      </div>
    </div>
    <!--fin formulario-->

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
          <small>&copy; 2024 CanchApp. Todos los derechos reservados.</small>
        </div>
      </div>
    </footer>
    <!-- Fin Footer -->
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>

  <script>
    // Auto-ocultar mensajes después de 5 segundos
    setTimeout(function () {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(function (alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);

    // Mejorar UX del formulario
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.querySelector('form');
      const submitBtn = document.querySelector('button[type="submit"]');

      form.addEventListener('submit', function () {
        submitBtn.innerHTML = '<span class="-border spinner-border-sm me-2"></span>Iniciando...';
        submitBtn.disabled = true;
      });
    });
  </script>
</body>

</html>