<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Asegurar que el usuario esté logueado
verificar_autenticado();

$error = '';
$success = '';

// Obtener lista de propietarios activos para el Administrador
$propietarios = [];
if (es_admin()) {
    $propietarios = $pdo->query("SELECT id, nombre, tipo FROM usuarios WHERE estado = 'Activo' AND (tipo = 'Propietario' OR tipo = 'Gestor Freelance') ORDER BY nombre ASC")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $provincia = $_POST['provincia'] ?? '';
    $comuna = $_POST['comuna'] ?? '';
    $sector = $_POST['sector'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    $precio_clp = (isset($_POST['precio_clp']) && $_POST['precio_clp'] !== '') ? (int)$_POST['precio_clp'] : 0;
    $precio_uf = (isset($_POST['precio_uf']) && $_POST['precio_uf'] !== '') ? (int)$_POST['precio_uf'] : 0;

    // Procesar Comodidades
    $comodidades_list = [];
    if (isset($_POST['bodega'])) $comodidades_list[] = 'Bodega';
    if (isset($_POST['estac'])) $comodidades_list[] = 'Estacionamiento';
    if (isset($_POST['logia'])) $comodidades_list[] = 'Logia';
    if (isset($_POST['cocina'])) $comodidades_list[] = 'Cocina amoblada';
    if (isset($_POST['antej'])) $comodidades_list[] = 'Antejardín';
    if (isset($_POST['patio'])) $comodidades_list[] = 'Patio trasero';
    if (isset($_POST['piscina'])) $comodidades_list[] = 'Piscina';
    
    $comodidades_str = implode(', ', $comodidades_list);

    // Validar campos obligatorios generales
    if (empty($tipo) || empty($provincia) || empty($comuna) || empty($sector) || empty($descripcion) || $precio_clp <= 0 || $precio_uf <= 0) {
        $error = 'Por favor, complete todos los campos obligatorios (*) y especifique precios válidos.';
    } else {
        // Validaciones específicas según el tipo de propiedad
        if ($tipo === 'Casa') {
            if (!isset($_POST['dormitorios']) || $_POST['dormitorios'] === '' || (int)$_POST['dormitorios'] < 0) {
                $error = 'Para una Casa, debe ingresar la cantidad de dormitorios (mínimo 0).';
            } elseif (!isset($_POST['banos']) || $_POST['banos'] === '' || (int)$_POST['banos'] < 0) {
                $error = 'Para una Casa, debe ingresar la cantidad de baños (mínimo 0).';
            } elseif (empty($_POST['area_construida']) || (int)$_POST['area_construida'] <= 0) {
                $error = 'Para una Casa, debe ingresar el área construida (mínimo 1 m²).';
            } elseif (empty($_POST['area_terreno']) || (int)$_POST['area_terreno'] <= 0) {
                $error = 'Para una Casa, debe ingresar el área de terreno (mínimo 1 m²).';
            }
        } elseif ($tipo === 'Departamento') {
            if (!isset($_POST['dormitorios']) || $_POST['dormitorios'] === '' || (int)$_POST['dormitorios'] < 0) {
                $error = 'Para un Departamento, debe ingresar la cantidad de dormitorios (mínimo 0).';
            } elseif (!isset($_POST['banos']) || $_POST['banos'] === '' || (int)$_POST['banos'] < 0) {
                $error = 'Para un Departamento, debe ingresar la cantidad de baños (mínimo 0).';
            } elseif (empty($_POST['area_construida']) || (int)$_POST['area_construida'] <= 0) {
                $error = 'Para un Departamento, debe ingresar el área construida (mínimo 1 m²).';
            }
        } elseif ($tipo === 'Terreno') {
            if (empty($_POST['area_terreno']) || (int)$_POST['area_terreno'] <= 0) {
                $error = 'Para un Terreno, debe ingresar el área de terreno (mínimo 1 m²).';
            }
        }
        
        // Validar fotos: requerido subir al menos 1 foto, máximo 10
        if (empty($error)) {
            if (!isset($_FILES['fotos']) || empty($_FILES['fotos']['name'][0])) {
                $error = 'Debe cargar al menos 1 fotografía de la propiedad (máximo 10).';
            } else {
                $files = $_FILES['fotos'];
                $total_archivos = count($files['name']);
                if ($total_archivos > 10) {
                    $error = 'No puede subir más de 10 fotografías por propiedad.';
                } else {
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    for ($i = 0; $i < $total_archivos; $i++) {
                        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                            $err_code = $files['error'][$i];
                            $filename = htmlspecialchars($files['name'][$i]);
                            if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
                                $error = "La imagen '$filename' excede el límite de tamaño permitido por el servidor (máximo 128 MB por archivo).";
                            } elseif ($err_code === UPLOAD_ERR_PARTIAL) {
                                $error = "La imagen '$filename' se subió parcialmente. Inténtelo de nuevo.";
                            } elseif ($err_code === UPLOAD_ERR_CANT_WRITE) {
                                $error = "No se pudo escribir la imagen '$filename' en el servidor. Verifique los permisos de la carpeta 'uploads/propiedades/'.";
                            } else {
                                $error = "Error al subir la fotografía '$filename' (Código de error: $err_code).";
                            }
                            break;
                        }
                        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_exts)) {
                            $error = 'Formato de imagen no válido en: ' . htmlspecialchars($files['name'][$i]) . '. Formatos aceptados: JPG, JPEG, PNG, WEBP.';
                            break;
                        }
                    }
                }
            }
        }

        if (empty($error)) {
            try {
                // Generar código único de propiedad
                $prefix = 'P';
                if ($tipo === 'Casa') $prefix = 'C';
                elseif ($tipo === 'Departamento') $prefix = 'D';
                elseif ($tipo === 'Terreno') $prefix = 'T';
                
                $codigo_unico = '';
                $intentos = 0;
                do {
                    $rand = str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT);
                    $codigo_unico = $prefix . $rand;
                    
                    $stmt = $pdo->prepare("SELECT id FROM propiedades WHERE codigo = :code");
                    $stmt->execute(['code' => $codigo_unico]);
                    $exists = $stmt->fetch();
                    $intentos++;
                } while ($exists && $intentos < 50);

                // Estado inicial
                $estado_inicial = es_admin() ? 'Activa' : 'Pendiente';
                
                // Determinar a qué usuario asociar la propiedad
                if (es_admin() && isset($_POST['usuario_id']) && $_POST['usuario_id'] !== '') {
                    $usuario_id = (int)$_POST['usuario_id'];
                } else {
                    $usuario_id = $_SESSION['usuario_id'];
                }

                // Ajustar variables dinámicas según tipo
                if ($tipo === 'Casa') {
                    $dormitorios = (int)$_POST['dormitorios'];
                    $banos = (int)$_POST['banos'];
                    $area_construida = $_POST['area_construida'] . 'm²';
                    $area_terreno = $_POST['area_terreno'] . 'm²';
                } elseif ($tipo === 'Departamento') {
                    $dormitorios = (int)$_POST['dormitorios'];
                    $banos = (int)$_POST['banos'];
                    $area_construida = $_POST['area_construida'] . 'm²';
                    $area_terreno = null;
                } elseif ($tipo === 'Terreno') {
                    $dormitorios = null;
                    $banos = null;
                    $area_construida = null;
                    $area_terreno = $_POST['area_terreno'] . 'm²';
                }

                // Insertar propiedad
                $stmt = $pdo->prepare("INSERT INTO propiedades (codigo, titulo, descripcion, tipo, provincia, comuna, sector, dormitorios, banos, area_construida, area_terreno, precio_clp, precio_uf, comodidades, estado, fecha_publicacion, usuario_id) 
                                       VALUES (:codigo, :titulo, :descripcion, :tipo, :provincia, :comuna, :sector, :dormitorios, :banos, :area_construida, :area_terreno, :precio_clp, :precio_uf, :comodidades, :estado, CURDATE(), :usuario_id)");
                
                $titulo = "$tipo en $sector, $comuna";
                
                $stmt->execute([
                    'codigo' => $codigo_unico,
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'tipo' => $tipo,
                    'provincia' => $provincia,
                    'comuna' => $comuna,
                    'sector' => $sector,
                    'dormitorios' => $dormitorios,
                    'banos' => $banos,
                    'area_construida' => $area_construida,
                    'area_terreno' => $area_terreno,
                    'precio_clp' => $precio_clp,
                    'precio_uf' => $precio_uf,
                    'comodidades' => $comodidades_str,
                    'estado' => $estado_inicial,
                    'usuario_id' => $usuario_id
                ]);
                
                $propiedad_id = $pdo->lastInsertId();

                // Procesar subida de imágenes (máximo 10)
                $files = $_FILES['fotos'];
                $total_archivos = count($files['name']);
                for ($i = 0; $i < $total_archivos; $i++) {
                    $tmp_name = $files['tmp_name'][$i];
                    $orig_name = basename($files['name'][$i]);
                    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    
                    $new_img_name = uniqid('prop_' . $propiedad_id . '_', true) . '.' . $ext;
                    $dest_path = __DIR__ . '/uploads/propiedades/' . $new_img_name;
                    
                    if (move_uploaded_file($tmp_name, $dest_path)) {
                        $db_path = 'uploads/propiedades/' . $new_img_name;
                        // La primera foto subida es la principal
                        $es_principal = ($i === 0) ? 1 : 0;
                        
                        $stmt_img = $pdo->prepare("INSERT INTO propiedades_imagenes (propiedad_id, ruta_imagen, es_principal) VALUES (:prop_id, :ruta, :principal)");
                        $stmt_img->execute([
                            'prop_id' => $propiedad_id,
                            'ruta' => $db_path,
                            'principal' => $es_principal
                        ]);
                    }
                }

                $_SESSION['alerta_success'] = es_admin() ? 
                    "Propiedad publicada exitosamente con código único: $codigo_unico" : 
                    "Propiedad registrada exitosamente con código único: $codigo_unico. Quedará en estado pendiente de aprobación por el Administrador.";
                
                header("Location: dashboard.php");
                exit;
            } catch (\PDOException $e) {
                $error = 'Ocurrió un error al guardar la propiedad en la base de datos: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════
 REGISTRO PROPIEDAD
══════════════════════════════════════════ -->
<div id="reg-propiedad" class="py-5">
    <div class="container my-4">
        <div class="form-card">
            <div class="section-title">
                <span>&#127959;</span>
                <h2>Registro de Propiedad</h2>
                <p class="text-muted">Complete todos los antecedentes de la propiedad a publicar.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success small mb-4"><?php echo htmlspecialchars($success); ?></div>
                <div class="text-center mt-4">
                    <?php if (es_admin()): ?>
                        <a href="dashboard.php" class="btn btn-primary px-4">Ir al Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-primary px-4">Ir al Dashboard</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" action="agregar_propiedad.php" enctype="multipart/form-data">
                    <h6 class="text-muted text-uppercase small mb-3 border-bottom pb-2">Información General</h6>
                    <div class="row">
                        <?php if (es_admin()): ?>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Propietario / Gestor Asociado <span class="text-danger">*</span></label>
                                <select name="usuario_id" class="form-select" required>
                                    <option value="" disabled selected>Seleccione el propietario o gestor freelance</option>
                                    <?php foreach ($propietarios as $prop_usr): ?>
                                        <option value="<?php echo $prop_usr['id']; ?>" <?php echo (isset($_POST['usuario_id']) && $_POST['usuario_id'] == $prop_usr['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prop_usr['nombre'] . ' (' . $prop_usr['tipo'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Propiedad <span class="text-danger">*</span></label>
                            <select id="tipoProp" name="tipo" class="form-select" required>
                                <option value="" disabled selected>Seleccione tipo</option>
                                <option value="Casa" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'Casa') ? 'selected' : ''; ?>>Casa</option>
                                <option value="Departamento" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'Departamento') ? 'selected' : ''; ?>>Departamento</option>
                                <option value="Terreno" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] === 'Terreno') ? 'selected' : ''; ?>>Terreno</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Publicación</label>
                            <input type="text" class="form-control" value="<?php echo date('d-m-Y'); ?>" disabled>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Provincia <span class="text-danger">*</span></label>
                            <select id="provincia2" name="provincia" class="form-select" onchange="cargarComunas2()" required>
                                <option value="">Seleccione provincia</option>
                                <option value="Elqui">Elqui</option>
                                <option value="Limari">Limarí</option>
                                <option value="Choapa">Choapa</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Comuna <span class="text-danger">*</span></label>
                            <select id="comuna2" name="comuna" class="form-select" onchange="cargarSectores2()" required>
                                <option value="">Seleccione comuna</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sector <span class="text-danger">*</span></label>
                            <select id="sector2" name="sector" class="form-select" required>
                                <option value="">Seleccione sector</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Descripción <span class="text-danger">*</span></label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe las características principales de la propiedad..." required><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Características</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3" id="grupo-dormitorios">
                            <label class="form-label">Dormitorios <span class="text-danger req-mark">*</span></label>
                            <input type="number" name="dormitorios" class="form-control" min="0" placeholder="Ej: 3" value="<?php echo isset($_POST['dormitorios']) ? htmlspecialchars($_POST['dormitorios']) : ''; ?>">
                        </div>
                        <div class="col-md-3 mb-3" id="grupo-banos">
                            <label class="form-label">Baños <span class="text-danger req-mark">*</span></label>
                            <input type="number" name="banos" class="form-control" min="0" placeholder="Ej: 2" value="<?php echo isset($_POST['banos']) ? htmlspecialchars($_POST['banos']) : ''; ?>">
                        </div>
                        <div class="col-md-3 mb-3" id="grupo-terreno">
                            <label class="form-label">Área Terreno (m²) <span class="text-danger req-mark">*</span></label>
                            <input type="number" name="area_terreno" class="form-control" min="0" placeholder="Ej: 250" value="<?php echo isset($_POST['area_terreno']) ? htmlspecialchars($_POST['area_terreno']) : ''; ?>">
                        </div>
                        <div class="col-md-3 mb-3" id="grupo-construida">
                            <label class="form-label">Área Construida (m²) <span class="text-danger req-mark">*</span></label>
                            <input type="number" name="area_construida" class="form-control" min="0" placeholder="Ej: 150" value="<?php echo isset($_POST['area_construida']) ? htmlspecialchars($_POST['area_construida']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio en $ <span class="text-danger">*</span></label>
                            <input type="number" name="precio_clp" class="form-control" placeholder="Ej: 154000000" required value="<?php echo isset($_POST['precio_clp']) ? htmlspecialchars($_POST['precio_clp']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio en UF <span class="text-danger">*</span></label>
                            <input type="number" name="precio_uf" class="form-control" placeholder="Ej: 5650" required value="<?php echo isset($_POST['precio_uf']) ? htmlspecialchars($_POST['precio_uf']) : ''; ?>">
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Comodidades</h6>
                    <div class="row mb-3">
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="bodega" id="bodega" <?php echo isset($_POST['bodega']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bodega">Bodega</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="estac" id="estac" <?php echo isset($_POST['estac']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="estac">Estacionamiento</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="logia" id="logia" <?php echo isset($_POST['logia']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="logia">Logia</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cocina" id="cocina" <?php echo isset($_POST['cocina']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cocina">Cocina amoblada</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="antej" id="antej" <?php echo isset($_POST['antej']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="antej">Antejardín</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="patio" id="patio" <?php echo isset($_POST['patio']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="patio">Patio trasero</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="piscina" id="piscina" <?php echo isset($_POST['piscina']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="piscina">Piscina</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Fotografías (1 a 10) <span class="text-danger">*</span></h6>
                    <div class="mb-3">
                        <input type="file" name="fotos[]" class="form-control" accept="image/*" multiple required>
                        <div class="form-text">Debe subir entre 1 y 10 fotografías. Formatos permitidos: JPG, PNG, WEBP.</div>
                    </div>

                    <div class="text-center mt-4 d-flex gap-3 justify-content-center">
                        <?php if (es_admin()): ?>
                            <a class="btn btn-outline-secondary px-4" href="dashboard.php">Cancelar</a>
                        <?php else: ?>
                            <a class="btn btn-outline-secondary px-4" href="index.php">Cancelar</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-5 py-2">Publicar Propiedad</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const precioClpInput = document.querySelector('input[name="precio_clp"]');
    const precioUfInput = document.querySelector('input[name="precio_uf"]');
    const VALOR_UF = 37500;

    if (precioClpInput && precioUfInput) {
        precioClpInput.addEventListener('input', () => {
            const clpVal = parseFloat(precioClpInput.value) || 0;
            if (clpVal > 0) {
                precioUfInput.value = Math.round(clpVal / VALOR_UF);
            } else {
                precioUfInput.value = '';
            }
        });

        precioUfInput.addEventListener('input', () => {
            const ufVal = parseFloat(precioUfInput.value) || 0;
            if (ufVal > 0) {
                precioClpInput.value = Math.round(ufVal * VALOR_UF);
            } else {
                precioClpInput.value = '';
            }
        });
    }

    const tipoSelect = document.getElementById('tipoProp');
    const gDorm = document.getElementById('grupo-dormitorios');
    const gBanos = document.getElementById('grupo-banos');
    const gTerreno = document.getElementById('grupo-terreno');
    const gConst = document.getElementById('grupo-construida');

    function actualizarCampos() {
        const tipo = tipoSelect.value;
        if (tipo === 'Casa') {
            mostrarYRequerir(gDorm, true);
            mostrarYRequerir(gBanos, true);
            mostrarYRequerir(gConst, true);
            mostrarYRequerir(gTerreno, true);
        } else if (tipo === 'Departamento') {
            mostrarYRequerir(gDorm, true);
            mostrarYRequerir(gBanos, true);
            mostrarYRequerir(gConst, true);
            mostrarYRequerir(gTerreno, false);
        } else if (tipo === 'Terreno') {
            mostrarYRequerir(gDorm, false);
            mostrarYRequerir(gBanos, false);
            mostrarYRequerir(gConst, false);
            mostrarYRequerir(gTerreno, true);
        } else {
            // Ocultar todos por defecto si no hay tipo
            mostrarYRequerir(gDorm, false);
            mostrarYRequerir(gBanos, false);
            mostrarYRequerir(gConst, false);
            mostrarYRequerir(gTerreno, false);
        }
    }

    function mostrarYRequerir(grupo, visible) {
        if (!grupo) return;
        const input = grupo.querySelector('input');
        const labelReq = grupo.querySelector('.req-mark');
        
        if (visible) {
            grupo.style.display = 'block';
            if (input) {
                input.disabled = false;
                input.required = true;
            }
            if (labelReq) labelReq.style.display = 'inline';
        } else {
            grupo.style.display = 'none';
            if (input) {
                input.disabled = true;
                input.required = false;
                input.value = '';
            }
            if (labelReq) labelReq.style.display = 'none';
        }
    }

    if (tipoSelect) {
        tipoSelect.addEventListener('change', actualizarCampos);
        actualizarCampos(); // Ejecutar inicialmente
    }

    // Validación del formulario antes de enviar
    const form = document.querySelector('#reg-propiedad form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const precioClp = parseInt(form.querySelector('input[name="precio_clp"]').value) || 0;
            const precioUf = parseInt(form.querySelector('input[name="precio_uf"]').value) || 0;
            const filesInput = form.querySelector('input[name="fotos[]"]');

            if (precioClp <= 0 || precioUf <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Precios Inválidos',
                    text: 'Los precios en pesos y UF deben ser mayores que cero.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar archivos
            if (filesInput) {
                const totalFiles = filesInput.files.length;
                if (totalFiles === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Falta Imagen',
                        text: 'Debe cargar al menos 1 fotografía de la propiedad.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }
                if (totalFiles > 10) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Demasiadas Imágenes',
                        text: 'No se permiten subir más de 10 fotografías por propiedad.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }

                // Validar tamaño individual (máximo 128MB por archivo debido a upload_max_filesize de PHP)
                const maxIndividualSize = 128 * 1024 * 1024; // 128MB
                for (let i = 0; i < totalFiles; i++) {
                    if (filesInput.files[i].size > maxIndividualSize) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Imagen muy grande',
                            text: 'La imagen "' + filesInput.files[i].name + '" pesa ' + (filesInput.files[i].size / (1024 * 1024)).toFixed(2) + ' MB. El límite máximo permitido es de 128 MB por archivo. Reduzca la resolución o elija otra foto.',
                            confirmButtonColor: '#0f766e'
                        });
                        return;
                    }
                }

                // Validar tamaño de archivos (límite de 256MB de POST)
                let totalSize = 0;
                const maxSize = 256 * 1024 * 1024; // 256MB
                for (let i = 0; i < totalFiles; i++) {
                    totalSize += filesInput.files[i].size;
                }
                if (totalSize > maxSize) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivos muy pesados',
                        text: 'El tamaño total de las imágenes (' + (totalSize / (1024 * 1024)).toFixed(2) + ' MB) supera el límite de subida permitido de 256 MB. Reduzca la resolución de las fotos o suba menos imágenes.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }

                // Validar extensiones
                const allowed = ['jpg', 'jpeg', 'png', 'webp'];
                for (let i = 0; i < totalFiles; i++) {
                    const ext = filesInput.files[i].name.split('.').pop().toLowerCase();
                    if (!allowed.includes(ext)) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Archivo no permitido',
                            text: 'El archivo "' + filesInput.files[i].name + '" no es una imagen válida. Formatos permitidos: JPG, JPEG, PNG, WEBP.',
                            confirmButtonColor: '#0f766e'
                        });
                        return;
                    }
                }
            }
        });
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
