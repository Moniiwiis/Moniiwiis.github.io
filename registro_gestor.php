<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

// Si ya está logueado, redirigir
if (esta_autenticado()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    
    // Validar campos obligatorios
    if (empty($rut) || empty($nombre) || empty($fecha_nacimiento) || empty($correo) || empty($password) || empty($confirm_password) || empty($sexo) || empty($telefono)) {
        $error = 'Por favor, complete todos los campos obligatorios (*).';
    } elseif (!validarRut($rut)) {
        $error = 'El RUT ingresado no es válido. Verifique el formato y el dígito verificador.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico ingresado no tiene un formato válido.';
    } elseif (strlen($nombre) > 100) {
        $error = 'El nombre completo no puede superar los 100 caracteres.';
    } elseif (strlen($correo) > 100) {
        $error = 'El correo electrónico no puede superar los 100 caracteres.';
    } elseif (strlen($telefono) > 20) {
        $error = 'El teléfono no puede superar los 20 caracteres.';
    } elseif (!validarPasswordRobusta($password)) {
        $error = 'La contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas ingresadas no coinciden.';
    } elseif (!isset($_FILES['certificado_antecedentes']) || $_FILES['certificado_antecedentes']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Debe adjuntar su Certificado de Antecedentes obligatorio.';
    } else {
        // Validar y subir archivo
        $file = $_FILES['certificado_antecedentes'];
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        $file_name = basename($file['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_exts)) {
            $error = 'Formato de certificado no válido. Formatos aceptados: PDF, JPG, JPEG, PNG, WEBP.';
        } elseif ($file['size'] > 20 * 1024 * 1024) { // Max 20MB
            $error = 'El archivo es demasiado grande. El tamaño máximo permitido es 20MB.';
        } else {
            try {
                // Formatear RUT antes de verificar
                $rut = formatearRut($rut);

                // Verificar si el RUT o Correo ya existen
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = :rut OR correo = :correo");
                $stmt->execute(['rut' => $rut, 'correo' => $correo]);
                if ($stmt->fetch()) {
                    $error = 'El RUT o el Correo Electrónico ya se encuentran registrados en el sistema.';
                } else {
                    // Generar nombre único para el archivo
                    $new_file_name = uniqid('cert_', true) . '.' . $file_ext;
                    $upload_dir = __DIR__ . '/uploads/certificados/';
                    $dest_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                        // Insertar usuario
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, correo, password, sexo, telefono, tipo, estado, certificado_antecedentes) 
                                               VALUES (:rut, :nombre, :fecha_nacimiento, :correo, :password, :sexo, :telefono, 'Gestor Freelance', 'Pendiente', :certificado)");
                        $stmt->execute([
                            'rut' => $rut,
                            'nombre' => $nombre,
                            'fecha_nacimiento' => $fecha_nacimiento,
                            'correo' => $correo,
                            'password' => $hashed_password,
                            'sexo' => $sexo,
                            'telefono' => $telefono,
                            'certificado' => $new_file_name
                        ]);
                        
                        $_SESSION['alerta_success'] = 'Postulación enviada con éxito. Tu cuenta quedará en revisión por el Administrador. Si eres aceptado, recibirás tus credenciales.';
                        header("Location: login.php");
                        exit;
                    } else {
                        $error = 'Error al guardar el archivo adjunto. Inténtelo de nuevo.';
                    }
                }
            } catch (\PDOException $e) {
                $error = 'Ocurrió un error al procesar la postulación. Inténtelo de nuevo más tarde.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════
REGISTRO GESTOR
══════════════════════════════════════════ -->
<div id="gestor" class="py-5">
    <div class="container my-4">
        <div class="form-card">
            <div class="section-title">
                <span>&#128188;</span>
                <h2>Registrarme como GESTOR INMOBILIARIO FREELANCE</h2>
                <p class="text-muted">Postula y comienza a generar tus INGRESOS ADICIONALES.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success small mb-4"><?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>
                <form method="POST" action="registro_gestor.php" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">RUT <span class="text-danger">*</span></label>
                            <input type="text" name="rut" class="form-control" placeholder="12.345.678-9" required value="<?php echo isset($_POST['rut']) ? htmlspecialchars($_POST['rut']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ingrese su nombre completo" required value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" name="correo" class="form-control" placeholder="correo@email.com" required value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repita la contraseña" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo" class="form-select" required>
                                <option value="" disabled selected>Seleccione</option>
                                <option value="Masculino" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Femenino" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                                <option value="Prefiero no indicar" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'Prefiero no indicar') ? 'selected' : ''; ?>>Prefiero no indicar</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono Móvil <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono" class="form-control" placeholder="+56 9 1234 5678" required value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Certificado de Antecedentes <span class="text-danger">*</span></label>
                            <input type="file" name="certificado_antecedentes" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Formatos aceptados: PDF, JPG, PNG. Tamaño máximo: 20MB.</div>
                        </div>
                    </div>
                    <div class="alert alert-info mb-4">
                        <strong>Pasos para comenzar:</strong>
                        <ol class="mb-0 mt-2 small">
                            <li>Regístrate como Gestor Inmobiliario</li>
                            <li>Capta una propiedad para la COMUNIDAD</li>
                            <li>Si eres aceptado recibirás tu <strong>PENKA_ID</strong> para ofertar propiedades y comisionar</li>
                        </ol>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-success px-5 py-2">Postular como Gestor Inmobiliario Free</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rutInput = document.querySelector('input[name="rut"]');
    if (rutInput) {
        rutInput.addEventListener('input', () => {
            let valor = rutInput.value.replace(/[^0-9kK]/g, '');
            if (valor.length < 2) return;
            let cuerpo = valor.slice(0, -1);
            let dv = valor.slice(-1).toUpperCase();
            let cuerpoFormateado = '';
            while (cuerpo.length > 3) {
                cuerpoFormateado = '.' + cuerpo.slice(-3) + cuerpoFormateado;
                cuerpo = cuerpo.slice(0, -3);
            }
            cuerpoFormateado = cuerpo + cuerpoFormateado;
            rutInput.value = cuerpoFormateado + '-' + dv;
        });
    }

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const rut = rutInput ? rutInput.value : '';
            const password = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;
            const fileInput = document.querySelector('input[name="certificado_antecedentes"]');
            
            // Validar RUT
            if (!validarRutJs(rut)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'RUT Inválido',
                    text: 'Por favor, ingrese un RUT chileno válido con el dígito verificador correcto.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar Robustez
            const robustRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/;
            if (!robustRegex.test(password)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseña Débil',
                    text: 'La contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar coincidencia
            if (password !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseñas no coinciden',
                    text: 'Las contraseñas ingresadas deben ser idénticas.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar archivo (extensión y tamaño)
            if (fileInput && fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                const ext = file.name.split('.').pop().toLowerCase();
                
                if (!allowed.includes(ext)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo Inválido',
                        text: 'El formato del certificado no es válido. Formatos aceptados: PDF, JPG, JPEG, PNG, WEBP.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }
                
                if (file.size > 20 * 1024 * 1024) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo demasiado grande',
                        text: 'El certificado excede el límite máximo de 20MB.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }
            }
        });
    }

    function validarRutJs(rut) {
        const cleanRut = rut.replace(/\./g, '').replace('-', '');
        if (cleanRut.length < 2) return false;
        const cuerpo = cleanRut.slice(0, -1);
        let dv = cleanRut.slice(-1).toUpperCase();
        if (!/^[0-9]+$/.test(cuerpo)) return false;
        
        let suma = 0;
        let multiplicador = 2;
        for (let i = cuerpo.length - 1; i >= 0; i--) {
            suma += parseInt(cuerpo[i]) * multiplicador;
            multiplicador = multiplicador === 7 ? 2 : multiplicador + 1;
        }
        const valorEsperado = 11 - (suma % 11);
        const dvEsperado = valorEsperado === 11 ? '0' : valorEsperado === 10 ? 'K' : valorEsperado.toString();
        return dv === dvEsperado;
    }
});
</script>

<?php if (!empty($error)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'error',
            title: 'Error de Validación',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#0f766e'
        });
    });
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
