<?php
session_start();
require_once 'conexiones/conDB.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'duenio') { //Si el rol no es dueño no puede pasar!
    header("Location: login.php");
    exit;
}

$id_duenio = $_SESSION['id'];
$msg = ''; //mensaje de error y succes cuando se crea cancha.
$error = '';
$easterEggTrigger = null; //JIJIJI

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $precio = trim($_POST['precio'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $foto = null;
    $uploadError = '';

    //Valida lo q se le entregue a la cancha.
    $errores = [];

    if (empty($nombre)) {
        $errores[] = "El nombre de la cancha es obligatorio.";
    } elseif (strlen($nombre) > 100) {
        $errores[] = "El nombre no puede exceder 100 caracteres.";
    }

    if (empty($ciudad)) {
        $errores[] = "La ciudad es obligatoria.";
    } elseif (strlen($ciudad) > 100) {
        $errores[] = "La ciudad no puede exceder 100 caracteres.";
    }

    if (empty($direccion)) {
        $errores[] = "La dirección es obligatoria.";
    } elseif (strlen($direccion) > 150) {
        $errores[] = "La dirección no puede exceder 150 caracteres.";
    }

    if (empty($precio) || !is_numeric($precio) || $precio < 0) {
        $errores[] = "El precio debe ser un número válido mayor o igual a 0.";
    } elseif ($precio > 999999.99) {
        $errores[] = "El precio no puede exceder 999,999.99.";
    }

    if (strlen($bio) > 500) {
        $errores[] = "La biografía no puede exceder 500 caracteres.";
    }



    //---------------Para subir fotos---------------------------------------------------------------------------------------------------
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) { //Comprueba que exista un archivo, sino se saltea.
        //UPLOAD_ERR_NO_FILE permite que si no se sube nada que se pueda seguir.
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) { //Si hay errores se pasa a abajo, que son los tipos de errores que puede haber.
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'El archivo es demasiado grande.',
                UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
                UPLOAD_ERR_NO_TMP_DIR => 'No se encontró la carpeta temporal.',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo.',
                UPLOAD_ERR_EXTENSION => 'Extensión de archivo no permitida.'
            ];
            $errores[] = $uploadErrors[$_FILES['foto']['error']] ?? 'Error desconocido al subir archivo.';
            //Se crea un array de errores, en caso de cumplirlos se indica el error, sino se sube correctamente.
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; //Se valida tamaño 5MB y tipo.

            if (!in_array($_FILES['foto']['type'], $allowedTypes)) {
                //Si se cumplen los demás pero no es del tipo indicado o pesa mucho salta error.
                $uploadError = 'Solo se permiten archivos JPG, JPEG y PNG.';
            } elseif ($_FILES['foto']['size'] > $maxSize) {
                $uploadError = 'El archivo es muy grande. Máximo 5MB.';
            } else {
                //Crea carpeta uploads si no existe.
                if (!file_exists('uploads')) {
                    mkdir('uploads', 0777, true); //0777 da permisos de lectura escritura y ejecución.
                    //Y el true permite crear supcarpetas.
                }

                //Se crea un nombre único random de la foto para que no haya 2 con el mismo nombre.
                $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $filename = 'cancha_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $uploadPath = 'uploads/' . $filename;


                if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
                    //Donde se estaba ingresando la foto es una carpeta temporal digamos.
                    //Una vez que se crea esa foto se mueve a la carpeta de fotos. Y se guarda la foto en la base de datos.
                    $foto = $filename;
                } else {
                    $uploadError = 'Error al subir la imagen.';
                }
            }
        }
    }
    //--------------------------------------------------------------------------------------------------------------------------------------



    //Si no hay errores en los datos ingresados:
    if (empty($errores)) {
        try {
            // Verifica si ya existe una cancha con el mismo nombre para este dueño.
            $stmt = $pdo->prepare('SELECT id_cancha FROM cancha WHERE nombre = ? AND id_duenio = ?');
            $stmt->execute([$nombre, $id_duenio]);

            if ($stmt->fetch()) {
                $error = 'Ya tenes una cancha registrada con ese nombre.';
            } else {
                //Sino se crea.
                $stmt = $pdo->prepare('
                    INSERT INTO cancha (nombre, ciudad, direccion, bio, foto, id_duenio, precio) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ');

                $resultado = $stmt->execute([
                    $nombre,
                    $ciudad,
                    $direccion,
                    $bio,
                    $foto,
                    $id_duenio,
                    $precio
                ]);

                if ($resultado) {
                    $msg = 'Cancha creada exitosamente!';
                } else {
                    $error = 'Error al crear la cancha en la base de datos.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = 'Error inesperado: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errores);
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Cancha - CanchApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<style>
    

    .form-label {
        color: var(--main-color);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .required {
        color: var(--error-color);
    }

    .alert-custom {
        border-radius: 15px;
        border: none;
        box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        margin-bottom: 1.5rem;
        padding: 1rem 1.5rem;
    }

    .alert-success-custom {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
    }

    .alert-danger-custom {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
    }

    .form-help {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .char-counter {
        text-align: right;
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .file-preview {
        margin-top: 1rem;
        max-width: 200px;
        border-radius: 10px;
        box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light);
        display: none;
    }

    .section-title {
        color: var(--main-color);
        font-weight: 700;
        margin-bottom: 1.5rem;
        text-align: center;
        font-size: 1.5rem;
    }

    .nav-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .neumorphic-card {
            margin: 1rem;
            padding: 1.5rem;
        }

        .nav-buttons {
            flex-direction: column;
            align-items: center;
        }

        .neumorphic-btn {
            width: 100%;
            max-width: 300px;
        }
    }
</style>

<body>
    <div class="container-fluid pt-2 pb-0 m-0" id="fondo-crear-cancha" style="min-height: 100vh;">
        <!-- Contenido Principal -->
        <div id="main" class="d-flex justify-content-center align-items-center py-4">
            <div class="container">
                <div class="row justify-content-center w-100">
                    <div class="col-lg-8 col-md-10">
                        <div class="neumorphic-card">
                            <h1 class="section-title">Crear Nueva Cancha</h1>

                            <!-- Mensajes-->
                            <?php if (!empty($msg)): ?> <!--Cancha creada correctamente-->
                                <div class="alert alert-success-custom alert-custom">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($msg) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($error)): ?> <!--Cancha no creada!-->
                                <div class="alert alert-danger-custom alert-custom">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= $error ?>
                                </div>
                            <?php endif; ?>

                            <!-- Botones de navegación -->
                            <div class="nav-buttons">
                                <a href="gestion.php" class="btn neumorphic-btn">
                                    <i class="fas fa-arrow-left me-2"></i>Volver
                                </a>
                            </div>

                            <!-- Formulario -->
                            <form method="post" enctype="multipart/form-data" id="formCrearCancha">
                                <div class="row">
                                    <!-- Fila 1: Nombre y Ciudad -->
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre" class="form-label">
                                            Nombre de la Cancha <span class="required">*</span>
                                        </label>
                                        <input type="text" id="nombre" name="nombre"
                                            class="form-control neumorphic-input"
                                            value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" maxlength="100"
                                            required>
                                        <div class="form-help">Máximo 100 caracteres</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="ciudad" class="form-label">
                                            Ciudad <span class="required">*</span>
                                        </label>
                                        <input type="text" id="ciudad" name="ciudad"
                                            class="form-control neumorphic-input"
                                            value="<?= htmlspecialchars($_POST['ciudad'] ?? '') ?>" maxlength="150"
                                            required>
                                        <div class="form-help">Ciudad o zona (máximo 150 caracteres)</div>
                                    </div>

                                    <!-- Fila 2: Dirección y Precio -->
                                    <div class="col-md-6 mb-3">
                                        <label for="direccion" class="form-label">
                                            Dirección <span class="required">*</span>
                                        </label>
                                        <input type="text" id="direccion" name="direccion"
                                            class="form-control neumorphic-input"
                                            value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>" maxlength="150"
                                            required>
                                        <div class="form-help">Dirección completa (máximo 150 caracteres)</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="precio" class="form-label">
                                            Precio por Espacio <span class="required">*</span>
                                        </label>
                                        <input type="number" id="precio" name="precio"
                                            class="form-control neumorphic-input"
                                            value="<?= htmlspecialchars($_POST['precio'] ?? '') ?>" step="0.01" min="0"
                                            max="999999.99" required>
                                        <div class="form-help">Precio por cada espacio de la cancha</div>
                                    </div>
                                </div>

                                <!-- Fuera del row: campos de ancho completo -->
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Biografía</label>
                                    <textarea id="bio" name="bio" class="form-control neumorphic-textarea"
                                        maxlength="500"
                                        onkeyup="updateCharCounter()"><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                                    <div class="char-counter">
                                        <span id="charCount">0</span>/500 caracteres
                                    </div>
                                    <div class="form-help">Información adicional sobre la cancha</div>
                                </div>

                                <div class="mb-4">
                                    <label for="foto" class="form-label">Foto de la Cancha</label>
                                    <input type="file" id="foto" name="foto" class="form-control neumorphic-input"
                                        accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this)">
                                    <div class="form-help">Formatos: JPG, JPEG, PNG. Tamaño máximo: 5MB</div>
                                    <img id="preview" class="file-preview" alt="Vista previa">
                                </div>

                                <!-- Botones de acción -->
                                <div class="d-flex justify-content-center gap-3">
                                    <button type="submit" class="btn neumorphic-btn-success">
                                        <i class="fas fa-plus me-2"></i>Crear Cancha
                                    </button>
                                    <button type="reset" class="btn neumorphic-btn" onclick="resetForm()">
                                        <i class="fas fa-eraser me-2"></i>Limpiar
                                        <!--Resetform borra todos los datos del formulario.-->
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    <p class="mb-1">Email: canchappcorp@gmail.com</p>
                    <p class="mb-1">Tel: +54 2494-648225</p>
                    <p class="mb-1">Dirección: Av. Pádel 123 ,Tandil ,Buenos Aires</p>
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
        <!-- Fin Footer -->
    </div>

    <?php if ($easterEggTrigger): ?>
        <script> //JavaScript de easteregg. Cambia el color de texto y de fondo.
            document.body.style.backgroundColor = '<?= $easterEggTrigger['color'] ?>';
            document.body.style.color = '<?= $easterEggTrigger['textColor'] ?>';
            setTimeout(() => {
                alert('<?= $easterEggTrigger['mensaje'] ?>');
                setTimeout(() => {
                    document.body.style.backgroundColor = '';
                    document.body.style.color = '';
                }, 3000);
            }, 500); //Luego de 0.5 segundos se muestra por 3 segundos el mensaje y el cambio de color. Y desp vuelve a la normalidad.
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        //Contador de caracteres para biografía que vimos arriba.
        function updateCharCounter() {
            const textarea = document.getElementById('bio');
            const counter = document.getElementById('charCount'); //Cuenta las letras de la bio y calcula su longitud.
            const currentLength = textarea.value.length;
            counter.textContent = currentLength;

            if (currentLength > 450) { //Avisa si está muy cerca del limite va tomando distintos colores.
                counter.style.color = '#dc3545';
            } else if (currentLength > 400) {
                counter.style.color = '#ffc107';
            } else {
                counter.style.color = '#6c757d';
            }
        }

        //Vista previa de imagen al elegirla!
        function previewImage(input) {
            const preview = document.getElementById('preview');
            if (input.files && input.files[0]) { //toma la foto que se elige en input.files
                const reader = new FileReader(); //El file reader permite convertir esa imagen elegida en una url temporal para verse.
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        //Limpiar formulario. Borra todo y vuelve a 0 el contador de chars.
        function resetForm() {
            document.getElementById('preview').style.display = 'none'; //Oculta la imagen de vista previa.
            updateCharCounter();
        }

        // Validación del formulario
        document.getElementById('formCrearCancha').addEventListener('submit', function (e) {
            const nombre = document.getElementById('nombre').value.trim();
            const ciudad = document.getElementById('ciudad').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            const precio = document.getElementById('precio').value;

            let errores = [];

            if (!nombre) errores.push('El nombre es obligatorio');
            if (!ciudad) errores.push('La ciudad es obligatoria');
            if (!direccion) errores.push('La dirección es obligatoria');
            if (!precio || precio < 0) errores.push('El precio debe ser mayor o igual a 0');

            if (errores.length > 0) {
                e.preventDefault(); //Si el formulario no está correcto, se previene de que se cree y se indica cuales son los errores.
                alert('Por favor corrige los siguientes errores:\n\n• ' + errores.join('\n• ')); //Por cada error una viñeta distinta.
                return false;
            }

            return true;
        });

        //Oculta con la clase .alert-custom a todos los elementos desp que pasen 5 segundos de estar activos.
        setTimeout(function () {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        //Empieza de una la función de charcounter asi al crear la cancha no hay que esperar.
        updateCharCounter();
    </script>
</body>

</html>