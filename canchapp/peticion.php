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
                <li class="nav-item">
                  <a class="nav-link mx-lg-2" href="buscador.php">Reservar</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link mx-lg-2" href="acerca-de.php">Acerca de</a>
                </li>
              </ul>
            </div>
          </div>
          
          <!-- Sistema de login/logout integrado -->
          <?php if ($nombre): ?>
            <div class="dropdown">
              <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (!empty($foto)): ?>
                  <img src="uploads/usuarios/<?= htmlspecialchars($foto) ?>" 
                       alt="Foto de perfil de <?= htmlspecialchars($nombre) ?>" 
                       class="rounded-circle border border-2 border-white" 
                       width="40" 
                       height="40" 
                       style="object-fit: cover;">
                <?php else: ?>
                  <div class="rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-secondary text-white" 
                       style="width: 40px; height: 40px; font-size: 20px;">
                    👤
                  </div>
                <?php endif; ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">¡Hola, <?= htmlspecialchars($nombre) ?>!</h6></li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($rol === 'usuario'): ?>
                  <li><a class="dropdown-item" href="perfil_padel.php">
                    <i class="fas fa-user me-2"></i>Editar Perfil
                  </a></li>
                  <li><hr class="dropdown-divider"></li>
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
