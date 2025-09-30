<?php
session_start();
require_once 'conexiones/conDB.php';
$nombre = $_SESSION['nombre'] ?? null; //Si existe el nombre y rol que lo asigne, sino q no ponga nada. Asi la gente sin iniciar sesion puede entrar.
$rol = $_SESSION['rol'] ?? null;
$foto = $_SESSION['foto'] ?? null; // Obtener la foto de la sesión
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acerca de Nosotros</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        section {
            background-image: url('image/Tavola_da_disegno_1_2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            width: 100%;
            height: 100vh;
        }


        .feature-icon {
            font-size: 3rem;
            color: #007bff;
            margin-bottom: 1rem;
        }

        .feature-card {
            padding: 2rem;
            border-radius: 10px;
            transition: transform 0.3s ease;
            height: 100%;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }




        /* diseño de la sección de contacto */
        .contact-section {
            background-image: url('image/padel-fondo.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #f8f9fa;
        }

        .contact-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .contact-icon {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .contact-card h5 {
            font-weight: bold;
            margin-bottom: 1rem;
            color: #333;
        }

        .contact-card .main-text {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .contact-card .sub-text {
            color: #666;
            font-size: 0.9rem;
        }

        .contact-cta {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-top: 3rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-contact {
            margin: 0.5rem;
            padding: 10px 30px;
            border-radius: 25px;
            font-weight: 500;
        }

        .btn-primary-contact {
            background-color: #333;
            border-color: #333;
            color: white;
        }

        .btn-outline-contact {
            border: 2px solid #333;
            color: #333;
            background: transparent;
        }

        .btn-outline-contact:hover {
            background-color: #333;
            color: white;
        }


        /* diseño del acordeón */
        .faq-section {
            background-color: #f8f9fa;
            padding: 80px 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.2rem;
            margin-bottom: 3rem;
        }

        .accordion-button {
            background-color: white;
            border: none;
            font-weight: 500;
            padding: 1.5rem;
            color: #333;
            box-shadow: none;
        }

        .accordion-button:not(.collapsed) {
            background-color: #007bff;
            color: white;
            box-shadow: none;
        }

        .accordion-button:focus {
            box-shadow: none;
            border: none;
        }

        .accordion-item {
            border: none;
            margin-bottom: 1rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .accordion-body {
            padding: 1.5rem;
            background-color: white;
        }

        .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23333'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-button:not(.collapsed)::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }



        .blur-container {
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.3);
            /* semitransparente */
            padding: 20px;
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-2 bg-light">

        <!-- Header Section -->
        <section class="text-center text-white d-flex flex-column"> a
            <!-- Navbar -->
            <div class="row" id="navbar">
                <div class="col-12">
                    <nav class="navbar navbar-expand-lg">
                        <a class="navbar-brand me-auto" href="#">
                            <img src="image/icon.png" alt="Logo" width="85" height="60"
                                class="d-inline-block align-text-top">
                        </a>
                        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar"
                            aria-labelledby="offcanvasNavbarLabel">
                            <div class="offcanvas-header">
                                <h5 class="offcanvas-title" id="offcanvasNavbarLabel">CanchApp</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                                    aria-label="Close"></button>
                            </div>
                            <div class="offcanvas-body">
                                <ul class="navbar-nav justify-content-center flex-grow-1 pe-3">
                                    <li class="nav-item">
                                        <a class="nav-link mx-lg-2" aria-current="page" href="index.php">Inicio</a>
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
                                        <a class="nav-link mx-lg-2 active" href="acerca-de.php">Acerca de</a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Sistema de login/logout integrado -->
                        <?php if ($nombre): ?>
                            <div class="dropdown">
                                <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <?php if (!empty($foto)): ?>
                                        <img src="uploads/usuarios/<?= htmlspecialchars($foto) ?>"
                                            alt="Foto de perfil de <?= htmlspecialchars($nombre) ?>"
                                            class="rounded-circle border border-2 border-white" width="40" height="40"
                                            style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle border border-2 border-white d-flex align-items-center justify-content-center bg-secondary text-white"
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
                            data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar"
                            aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                    </nav>
                </div>
            </div>
            <!-- Fin Navbar -->



            <div class="d-flex align-items-center justify-content-center text-white">
                <div class="col-12 col-md-8 text-center my-5 blur-container">
                    <h1>Acerca de Nosotros</h1>
                    <p class="lead">Bienvenido a nuestra página de Acerca de. Aquí puedes encontrar información sobre
                        nuestra empresa, misión y valores.</p>
                    <p>Nuestra empresa se dedica a ofrecer los mejores servicios en el sector. Nos esforzamos por
                        brindar
                        calidad y satisfacción a nuestros clientes.</p>
                    <p>Si tienes alguna pregunta o deseas saber más sobre nosotros, no dudes en contactarnos.</p>
                </div>
            </div>

        </section>
        <!-- Fin Header Section -->

        <!-- Quiénes Somos -->
        <div class="row justify-content-center align-items-center p-4 g-4">

            <div class="col-lg-7 col-sm-12">
                <div class="row justify-content-center align-items-center g-2">
                    <div class="col align-text-top">
                        <div class="row align-items-center">
                            <div class="col">
                                <h2 class="section-title">¿Quiénes Somos?</h2>

                                <p class="mb-4">Somos una plataforma innovadora dedicada a revolucionar la forma en que
                                    los jugadores de pádel reservan y gestionan sus partidos. Desde 2020, hemos
                                    facilitado más de 100,000 reservas en canchas de toda España.</p>

                                <p class="mb-4">Nuestro objetivo es hacer que el pádel sea más accesible para todos,
                                    proporcionando una experiencia de reserva fluida y conectando a la comunidad de
                                    jugadores.</p>

                                <div class="row g-4 mt-4">
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <i class="fas fa-calendar-alt feature-icon">
                                            </i>
                                            <h5>Reservas Fáciles</h5>
                                            <p>Sistema intuitivo para reservar canchas en tiempo real</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <i class="fas fa-users feature-icon"></i>
                                            <h5>Comunidad Activa</h5>
                                            <p>Conecta con otros jugadores y forma grupos</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <i class="fas fa-map-marker-alt feature-icon"></i>
                                            <h5>Múltiples Ubicaciones</h5>
                                            <p>Canchas en toda la ciudad a tu disposición</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <i class="fas fa-clock feature-icon"></i>
                                            <h5>Disponibilidad 24/7</h5>
                                            <p>Reserva cuando quieras, las 24 horas del día</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5 col-sm-12">
                <div class="row justify-content-center align-items-center g-2">
                    <div class="col align-items-center">
                        <img src="image/Prêt à relever tous les défis sur le terrain de Padel _ 🎾.jpg"
                            alt="Descripción de la imagen" class="img-fluid rounded-2 w-100 "
                            style="height: 500px; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>
        <!-- Fin Quiénes Somos -->


        <!-- FAQ Section -->
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="section-title">Preguntas Frecuentes</h2>
                <p class="section-subtitle">Resuelve tus dudas más comunes sobre nuestro servicio de reservas</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse1">
                                ¿Cómo puedo reservar una cancha?
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Es muy sencillo. Solo tienes que registrarte en nuestra plataforma, seleccionar la
                                ubicación y horario que prefieras, y confirmar tu reserva. Recibirás una confirmación
                                inmediata por email.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse2">
                                ¿Puedo cancelar mi reserva?
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Sí, puedes cancelar tu reserva hasta 4 horas antes del horario programado sin ningún
                                costo. Para cancelaciones con menos tiempo, se aplicará una penalización del 50%.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse3">
                                ¿Qué métodos de pago aceptan?
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Aceptamos todas las tarjetas de crédito y débito principales (Visa, Mastercard, American
                                Express), PayPal, y transferencias bancarias.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading4">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse4">
                                ¿Las canchas incluyen material deportivo?
                            </button>
                        </h2>
                        <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Depende del centro deportivo. Algunos incluyen raquetas y pelotas en el precio, mientras
                                que otros las alquilan por separado. Esta información aparece claramente en cada
                                reserva.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading5">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse5">
                                ¿Hay descuentos por reservas frecuentes?
                            </button>
                        </h2>
                        <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Sí, ofrecemos descuentos progresivos para usuarios frecuentes. A partir de la quinta
                                reserva al mes, obtienes un 10% de descuento, y con más de 10 reservas mensuales, el
                                descuento es del 15%.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading6">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse6">
                                ¿Puedo reservar para un grupo?
                            </button>
                        </h2>
                        <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Por supuesto. Puedes reservar múltiples canchas para eventos grupales o torneos. Para
                                grupos grandes, contáctanos directamente para obtener precios especiales.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading7">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse7">
                                ¿Qué pasa si llueve el día de mi reserva?
                            </button>
                        </h2>
                        <div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Para canchas cubiertas no hay problema. En canchas al aire libre, si las condiciones
                                meteorológicas impiden el juego, puedes reprogramar sin costo o solicitar un reembolso
                                completo.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading8">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse8">
                                ¿Cómo puedo contactar con el centro deportivo?
                            </button>
                        </h2>
                        <div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                En cada reserva encontrarás los datos de contacto del centro específico. También puedes
                                usar nuestro chat en vivo o llamar a nuestro servicio de atención al cliente que hará de
                                intermediario.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Fin FAQ Section -->

        <!-- Contacto Section -->
        <div class="row justify-content-center contact-section">
            <div class="row text-center text-light mb-5">
                <div class="col-12">
                    <h2 class="section-title">Contacto</h2>
                    <p class="section-subtitle">¿Tienes alguna pregunta? Estamos aquí para ayudarte</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <i class="fas fa-envelope contact-icon"></i>
                        <h5>Email</h5>
                        <p class="main-text">info@padelreservas.com</p>
                        <p class="sub-text">Respuesta en menos de 24 horas</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <i class="fas fa-phone contact-icon"></i>
                        <h5>Teléfono</h5>
                        <p class="main-text">+34 900 123 456</p>
                        <p class="sub-text">Lunes a Viernes, 9:00 - 18:00</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <i class="fas fa-map-marker-alt contact-icon"></i>
                        <h5>Oficina Central</h5>
                        <p class="main-text">Madrid, España</p>
                        <p class="sub-text">Calle del Pádel, 123</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <i class="fas fa-clock contact-icon"></i>
                        <h5>Soporte</h5>
                        <p class="main-text">24/7 Online</p>
                        <p class="sub-text">Chat en vivo disponible</p>
                    </div>
                </div>
            </div>

            <div class="contact-cta p-5">
                <h4 class="mb-3">¿Necesitas más información?</h4>
                <p class="mb-4">Nuestro equipo de soporte está listo para resolver todas tus dudas sobre la
                    plataforma, reservas, pagos o cualquier aspecto técnico.</p>
                <button class="btn btn-primary-contact btn-contact">
                    <i class="fas fa-envelope me-2"></i>Enviar Email
                </button>
                <button class="btn btn-outline-contact btn-contact">
                    <i class="fas fa-phone me-2"></i>Llamar Ahora
                </button>
            </div>
        </div>
        <!-- Fin Contacto Section -->

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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
            crossorigin="anonymous"></script>

    </div>

</html>