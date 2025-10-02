<?php
session_start();
require_once 'conexiones/conDB.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';

if ($_POST) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $contraseña = $_POST['contraseña'];
    $repetir_contraseña = $_POST['repetir_contraseña'];
    $telefono = trim($_POST['telefono'] ?? '');
    $categoria = intval($_POST['categoria'] ?? 0);
    
    // Validaciones
    if (empty($nombre) || empty($email) || empty($contraseña) || empty($repetir_contraseña) || empty($categoria)) {
        $error_message = 'Por favor, complete todos los campos obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Por favor, ingrese un email válido.';
    } elseif ($categoria < 1 || $categoria > 7) {
        $error_message = 'Categoría inválida.';
    } elseif (strlen($contraseña) < 6) {
        $error_message = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($contraseña !== $repetir_contraseña) {
        $error_message = 'Las contraseñas no coinciden.';
    } else {
        try {
            // Verifica si el email ya existe
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = 'Este email ya está registrado.';
            } else {
                // Inserta nuevo usuario
                $stmt = $pdo->prepare("INSERT INTO usuario (nombre, email, contrasena, telefono, categoria, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())");
                
                if ($stmt->execute([$nombre, $email, $contraseña, $telefono, $categoria])) {
                    $success_message = 'Usuario registrado exitosamente. <a href="inicioses.php">Iniciar sesión</a>';
                    
                    // Limpia campos del formulario
                    $_POST = array();
                } else {
                    $error_message = 'Error al registrar usuario. Intente nuevamente.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Error en el sistema. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - CanchApp</title>
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
      max-width: 400px;
      width: 100%;
      transition: all .8s ease-in-out;
    }
    .neumorphic-card:hover {
      box-shadow: 8px 8px 16px var(--shadow-light), -8px -8px 16px var(--shadow-dark);
    }
    .neumorphic-input {
      height: 50px ;
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
      margin-top: 15px ;
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
    .password-match {
      font-size: 0.875em;
      margin-top: 0.25rem;
    }
    .password-match.text-success {
      color: #28a745 !important;
    }
    .password-match.text-danger {
      color: #dc3545 !important;
    }
    </style>
<body>
        <div class="container-fluid p-2"  style="background-image: url('image/padel-fondo.jpg'); background-size: cover; background-repeat: no-repeat;">
    <!--formulario-->
    <div id="main" class="d-flex justify-content-center align-items-center min-vh-100"  >
      <div class="neumorphic-card">
        <h1 class="text-center fw-bold mb-4">Registro</h1>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-custom">
          <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-custom">
          <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <form action="" method="POST" id="registroForm">
          <div class="mb-4">
            <label for="nombre" class="form-label">Nombre *</label>
            <input type="text" name="nombre" class="form-control neumorphic-input" required id="nombre" placeholder="Ingrese su Nombre" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
          </div>
          <div class="mb-4">
            <label for="email" class="form-label">Email *</label>
            <input type="email" name="email" class="form-control neumorphic-input" required id="email" placeholder="Ingrese su email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          <div class="mb-4">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control neumorphic-input" id="telefono" placeholder="Ingrese su teléfono" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
          </div>


        <!--BRAD, acá tenés un pop over, funciona de forma que cuando te vas a registrar
        y querés elegir categoría, toques en un lugar donde te explique que es, pq capaz q la persona no sabe
        Está muy feo, si podés hacerlo lindo :) -->
          <div class="mb-4">
            <label for="categoria" class="form-label">Categoría *

            <button type="button" class="btn-sm btn-outline-info rounded-circle"  
              data-bs-toggle="popover" 
              title="¿Qué son las categorías?" 
              data-bs-content="Las categorías van del 1 al 7 y representan tu nivel de juego. 1 es principiante y 7 es avanzado. (No te preocupes, mas tarde se puede cambiar)">
              i
            </button>

            </label>
            <select  class="form-control neumorphic-input" id="categoria" name="categoria" required>
              <option value="">Selecciona tu categoría</option>
              <option value="1">Categoría 1</option>
              <option value="2">Categoría 2</option>
              <option value="3">Categoría 3</option>
              <option value="4">Categoría 4</option>
              <option value="5">Categoría 5</option>
              <option value="6">Categoría 6</option>
              <option value="7">Categoría 7</option>
            </select>
          </div>

          <div class="mb-4">
            <label for="contraseña" class="form-label">Contraseña *</label>
            <input type="password" name="contraseña" class="form-control neumorphic-input" required id="contraseña" placeholder="Ingrese su contraseña" minlength="6">
            <div class="password-match mt-1" style="font-size: 0.8em; color: #666;">Mínimo 6 caracteres</div>
          </div>      
          <div class="mb-4">
            <label for="repetir_contraseña" class="form-label">Repetir Contraseña *</label>
            <input type="password" name="repetir_contraseña" class="form-control neumorphic-input" required id="repetir_contraseña" placeholder="Repita su contraseña">
            <div id="password-feedback" class="password-match"></div>
          </div>
          <div class="mb-3">
            <a href="inicioses.php">¿Ya tienes una cuenta? Inicia sesión aquí</a>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn neumorphic-btn" id="submitBtn">Registrarse</button>
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
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>

        <!--SCRIPT PARA EL MATCHEO DE CONTRASEÑASS!!-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('contraseña');
    const repeatPassword = document.getElementById('repetir_contraseña');
    const feedback = document.getElementById('password-feedback');
    const submitBtn = document.getElementById('submitBtn');
    
    function validatePasswords() {
        const pass1 = password.value;
        const pass2 = repeatPassword.value;
        
        if (pass2.length === 0) {
            feedback.textContent = '';
            feedback.className = 'password-match';
            return;
        }
        
        if (pass1 === pass2) {
            feedback.textContent = '✓ Las contraseñas coinciden';
            feedback.className = 'password-match text-success';
        } else {
            feedback.textContent = '✗ Las contraseñas no coinciden';
            feedback.className = 'password-match text-danger';
        }
    }
    
    password.addEventListener('input', validatePasswords);
    repeatPassword.addEventListener('input', validatePasswords);
});


</script>

<!--Script para los detalles de la categoría!!---------------------------->
<script>
  document.addEventListener("DOMContentLoaded", function(){
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl)
    })
  });
</script>

</body>
</html>