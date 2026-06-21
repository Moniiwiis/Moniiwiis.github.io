<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

// Asegurar que el usuario sea administrador
verificar_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['alerta_error'] = 'El usuario especificado no existe.';
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $sexo = $_POST['sexo'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $nro_registro_bbr = trim($_POST['nro_registro_bbr'] ?? '');
    $password_nueva = $_POST['password_nueva'] ?? '';

    // Validar campos obligatorios generales
    if (empty($rut) || empty($nombre) || empty($fecha_nacimiento) || empty($correo) || empty($sexo) || empty($telefono) || empty($tipo) || empty($estado)) {
        $error = 'Por favor, complete todos los campos obligatorios (*)';
    } elseif (!validarRut($rut)) {
        $error = 'El RUT ingresado no es válido. Verifique el formato y dígito verificador.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico ingresado no tiene un formato válido.';
    } elseif (strlen($nombre) > 100) {
        $error = 'El nombre completo no puede superar los 100 caracteres.';
    } elseif (strlen($correo) > 100) {
        $error = 'El correo electrónico no puede superar los 100 caracteres.';
    } elseif (strlen($telefono) > 20) {
        $error = 'El teléfono no puede superar los 20 caracteres.';
    } elseif ($tipo === 'Propietario' && empty($nro_registro_bbr)) {
        $error = 'Para un usuario Propietario, debe ingresar el N° de Registro de Bienes Raíces.';
    } elseif ($tipo === 'Propietario' && strlen($nro_registro_bbr) > 50) {
        $error = 'El número de registro de bienes raíces no puede superar los 50 caracteres.';
    } elseif (!empty($password_nueva) && !validarPasswordRobusta($password_nueva)) {
        $error = 'La nueva contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.';
    } else {
        try {
            $rut_formateado = formatearRut($rut);

            // Verificar si el RUT o Correo ya existen en otro usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE (rut = :rut OR correo = :correo) AND id != :id");
            $stmt->execute(['rut' => $rut_formateado, 'correo' => $correo, 'id' => $id]);
            if ($stmt->fetch()) {
                $error = 'El RUT o el Correo Electrónico ya están asignados a otro usuario.';
            } else {
                // Preparar actualización
                $sql = "UPDATE usuarios SET 
                        rut = :rut, 
                        nombre = :nombre, 
                        fecha_nacimiento = :fecha_nacimiento, 
                        correo = :correo, 
                        sexo = :sexo, 
                        telefono = :telefono, 
                        tipo = :tipo, 
                        estado = :estado,
                        nro_registro_bbr = :nro_registro_bbr";
                
                $params = [
                    'rut' => $rut_formateado,
                    'nombre' => $nombre,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'correo' => $correo,
                    'sexo' => $sexo,
                    'telefono' => $telefono,
                    'tipo' => $tipo,
                    'estado' => $estado,
                    'nro_registro_bbr' => ($tipo === 'Propietario') ? $nro_registro_bbr : null,
                    'id' => $id
                ];

                // Si se proporcionó una nueva contraseña, actualizarla también
                if (!empty($password_nueva)) {
                    $sql .= ", password = :password";
                    $params['password'] = password_hash($password_nueva, PASSWORD_BCRYPT);
                }

                $sql .= " WHERE id = :id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $_SESSION['alerta_success'] = 'Usuario actualizado correctamente.';
                header("Location: dashboard.php");
                exit;
            }
        } catch (\PDOException $e) {
            $error = 'Ocurrió un error al guardar los datos del usuario: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div id="editar-usuario" class="py-5">
    <div class="container my-4">
        <div class="form-card">
            <div class="section-title">
                <span>&#128100;</span>
                <h2>Editar Usuario: <?php echo htmlspecialchars($user['nombre']); ?></h2>
                <p class="text-muted">Modifique los antecedentes del usuario y sus permisos.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="editar_usuario.php?id=<?php echo $id; ?>">
                <h6 class="text-muted text-uppercase small mb-3 border-bottom pb-2">Información Personal</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">RUT <span class="text-danger">*</span></label>
                        <input type="text" name="rut" class="form-control" placeholder="12.345.678-9" required value="<?php echo htmlspecialchars($user['rut']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required value="<?php echo htmlspecialchars($user['nombre']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_nacimiento" class="form-control" required value="<?php echo htmlspecialchars($user['fecha_nacimiento']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sexo <span class="text-danger">*</span></label>
                        <select name="sexo" class="form-select" required>
                            <option value="Masculino" <?php echo ($user['sexo'] === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo ($user['sexo'] === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Prefiero no indicar" <?php echo ($user['sexo'] === 'Prefiero no indicar') ? 'selected' : ''; ?>>Prefiero no indicar</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="correo" class="form-control" required value="<?php echo htmlspecialchars($user['correo']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono Móvil <span class="text-danger">*</span></label>
                        <input type="tel" name="telefono" class="form-control" required value="<?php echo htmlspecialchars($user['telefono']); ?>">
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Sistema y Permisos</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo de Usuario <span class="text-danger">*</span></label>
                        <select id="tipoUsuario" name="tipo" class="form-select" required>
                            <option value="Administrador" <?php echo ($user['tipo'] === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="Propietario" <?php echo ($user['tipo'] === 'Propietario') ? 'selected' : ''; ?>>Propietario</option>
                            <option value="Gestor Freelance" <?php echo ($user['tipo'] === 'Gestor Freelance') ? 'selected' : ''; ?>>Gestor Freelance</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estado de la Cuenta <span class="text-danger">*</span></label>
                        <select name="estado" class="form-select" required>
                            <option value="Pendiente" <?php echo ($user['estado'] === 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="Activo" <?php echo ($user['estado'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Rechazado" <?php echo ($user['estado'] === 'Rechazado') ? 'selected' : ''; ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3" id="grupo-bbr" style="display:none">
                        <label class="form-label">N° de Propiedad según Registro de Bienes Raíces <span class="text-danger">*</span></label>
                        <input type="text" name="nro_registro_bbr" class="form-control" placeholder="Ej: 458796-1" value="<?php echo htmlspecialchars($user['nro_registro_bbr'] ?? ''); ?>">
                    </div>
                    
                    <?php if ($user['tipo'] === 'Gestor Freelance' && !empty($user['certificado_antecedentes'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label">Certificado de Antecedentes Adjunto</label>
                            <div class="p-2 border rounded bg-light">
                                <a href="uploads/certificados/<?php echo htmlspecialchars($user['certificado_antecedentes']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>Ver Certificado de Antecedentes
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Nueva Contraseña <span class="text-muted">(dejar en blanco para no cambiar)</span></label>
                        <input type="password" name="password_nueva" class="form-control" placeholder="Mínimo 8 caracteres, mayúscula, minúscula, número y especial">
                    </div>
                </div>

                <div class="text-center mt-4 d-flex gap-3 justify-content-center">
                    <a class="btn btn-outline-secondary px-4" href="dashboard.php">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-5 py-2">Guardar Cambios</button>
                </div>
            </form>
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

    const tipoSelect = document.getElementById('tipoUsuario');
    const grupoBbr = document.getElementById('grupo-bbr');
    const inputBbr = grupoBbr ? grupoBbr.querySelector('input') : null;

    function toggleBbr() {
        if (!tipoSelect) return;
        if (tipoSelect.value === 'Propietario') {
            grupoBbr.style.display = 'block';
            if (inputBbr) {
                inputBbr.required = true;
                inputBbr.disabled = false;
            }
        } else {
            grupoBbr.style.display = 'none';
            if (inputBbr) {
                inputBbr.required = false;
                inputBbr.disabled = true;
            }
        }
    }

    if (tipoSelect) {
        tipoSelect.addEventListener('change', toggleBbr);
        toggleBbr(); // Inicializar
    }

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const rut = rutInput ? rutInput.value : '';
            const password = document.querySelector('input[name="password_nueva"]').value;
            
            // Validar RUT
            if (!validarRutJs(rut)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'RUT Inválido',
                    text: 'Por favor, ingrese un RUT chileno válido.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar Robustez de contraseña nueva si no está vacía
            if (password !== '') {
                const robustRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/;
                if (!robustRegex.test(password)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Contraseña Débil',
                        text: 'La nueva contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.',
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
