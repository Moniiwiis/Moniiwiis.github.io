<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Solo exige estar logueado; el control de "dueño" se valida más abajo
verificar_autenticado();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$my_user_id = $_SESSION['usuario_id'];

// Obtener datos de la propiedad
$stmt = $pdo->prepare("SELECT * FROM propiedades WHERE id = :id");
$stmt->execute(['id' => $id]);
$prop = $stmt->fetch();

if (!$prop) {
    header("Location: dashboard.php?msg=propiedad_no_encontrada");
    exit;
}

// Control de acceso: solo el Administrador o el propietario dueño de la propiedad pueden editarla
if (!es_admin() && (int)$prop['usuario_id'] !== (int)$my_user_id) {
    header("Location: dashboard.php?msg=acceso_denegado");
    exit;
}

// Obtener lista de propietarios activos para el Administrador
$propietarios = [];
if (es_admin()) {
    $propietarios = $pdo->query("SELECT id, nombre, tipo FROM usuarios WHERE estado = 'Activo' AND (tipo = 'Propietario' OR tipo = 'Gestor Freelance') ORDER BY nombre ASC")->fetchAll();
}

$error = '';
$success = '';

// ── Función auxiliar para releer imágenes de la propiedad ──────────
function obtenerImagenesPropiedad($pdo, $propiedad_id) {
    $stmt = $pdo->prepare("SELECT * FROM propiedades_imagenes WHERE propiedad_id = :id ORDER BY es_principal DESC, id ASC");
    $stmt->execute(['id' => $propiedad_id]);
    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $provincia = $_POST['provincia'] ?? '';
    $comuna = $_POST['comuna'] ?? '';
    $sector = $_POST['sector'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    $dormitorios = (isset($_POST['dormitorios']) && $_POST['dormitorios'] !== '') ? (int)$_POST['dormitorios'] : null;
    $banos = (isset($_POST['banos']) && $_POST['banos'] !== '') ? (int)$_POST['banos'] : null;

    $area_terreno = (isset($_POST['area_terreno']) && $_POST['area_terreno'] !== '') ? $_POST['area_terreno'] : '';
    if (!empty($area_terreno) && strpos($area_terreno, 'm²') === false) {
        $area_terreno .= 'm²';
    }

    $area_construida = (isset($_POST['area_construida']) && $_POST['area_construida'] !== '') ? $_POST['area_construida'] : '';
    if (!empty($area_construida) && strpos($area_construida, 'm²') === false) {
        $area_construida .= 'm²';
    }

    $precio_clp = $_POST['precio_clp'] !== '' ? (int)$_POST['precio_clp'] : 0;
    $precio_uf = $_POST['precio_uf'] !== '' ? (int)$_POST['precio_uf'] : 0;

    // Solo el Administrador puede cambiar libremente el estado.
    // Si un propietario edita su propiedad, esta vuelve a quedar Pendiente de revisión.
    if (es_admin()) {
        $estado = $_POST['estado'] ?? $prop['estado'];
    } else {
        $estado = 'Pendiente';
    }

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

        if (empty($error)) {
            // Ajustar variables dinámicas según tipo
            if ($tipo === 'Casa') {
                $dormitorios = (int)$_POST['dormitorios'];
                $banos = (int)$_POST['banos'];
                $area_construida = $_POST['area_construida'];
                if (!empty($area_construida) && strpos($area_construida, 'm²') === false) {
                    $area_construida .= 'm²';
                }
                $area_terreno = $_POST['area_terreno'];
                if (!empty($area_terreno) && strpos($area_terreno, 'm²') === false) {
                    $area_terreno .= 'm²';
                }
            } elseif ($tipo === 'Departamento') {
                $dormitorios = (int)$_POST['dormitorios'];
                $banos = (int)$_POST['banos'];
                $area_construida = $_POST['area_construida'];
                if (!empty($area_construida) && strpos($area_construida, 'm²') === false) {
                    $area_construida .= 'm²';
                }
                $area_terreno = null;
            } elseif ($tipo === 'Terreno') {
                $dormitorios = null;
                $banos = null;
                $area_construida = null;
                $area_terreno = $_POST['area_terreno'];
                if (!empty($area_terreno) && strpos($area_terreno, 'm²') === false) {
                    $area_terreno .= 'm²';
                }
            }
        }

        if (empty($error)) {
            // ── Validar gestión de imágenes ──────────────────────────────
            $imagenes_actuales = obtenerImagenesPropiedad($pdo, $id);
            $ids_eliminar = isset($_POST['eliminar_imagen']) ? array_map('intval', (array)$_POST['eliminar_imagen']) : [];

            $cantidad_restantes = count($imagenes_actuales) - count($ids_eliminar);

            $nuevas_fotos_count = 0;
            if (isset($_FILES['fotos_nuevas']) && !empty($_FILES['fotos_nuevas']['name'][0])) {
                $nuevas_fotos_count = count($_FILES['fotos_nuevas']['name']);
            }

            $total_final = $cantidad_restantes + $nuevas_fotos_count;

            if ($total_final < 1) {
                $error = 'La propiedad debe tener al menos 1 fotografía. No puede eliminar todas las imágenes sin agregar otras.';
            } elseif ($total_final > 10) {
                $error = 'No puede tener más de 10 fotografías en total para esta propiedad.';
            } else {
                // Validar formatos de las fotos nuevas
                if ($nuevas_fotos_count > 0) {
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    for ($i = 0; $i < $nuevas_fotos_count; $i++) {
                        if ($_FILES['fotos_nuevas']['error'][$i] !== UPLOAD_ERR_OK) {
                            $err_code = $_FILES['fotos_nuevas']['error'][$i];
                            $filename = htmlspecialchars($_FILES['fotos_nuevas']['name'][$i]);
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
                        $ext = strtolower(pathinfo($_FILES['fotos_nuevas']['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_exts)) {
                            $error = 'Formato de imagen no válido en: ' . htmlspecialchars($_FILES['fotos_nuevas']['name'][$i]) . '. Formatos aceptados: JPG, JPEG, PNG, WEBP.';
                            break;
                        }
                    }
                }
            }
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                // 1. Actualizar datos generales de la propiedad
                $sql_update = "UPDATE propiedades SET 
                                tipo = :tipo, 
                                provincia = :provincia, 
                                comuna = :comuna, 
                                sector = :sector, 
                                descripcion = :descripcion, 
                                dormitorios = :dormitorios, 
                                banos = :banos, 
                                area_construida = :area_construida, 
                                area_terreno = :area_terreno, 
                                precio_clp = :precio_clp, 
                                precio_uf = :precio_uf, 
                                comodidades = :comodidades,
                                estado = :estado";
                
                $params_update = [
                    'tipo' => $tipo,
                    'provincia' => $provincia,
                    'comuna' => $comuna,
                    'sector' => $sector,
                    'descripcion' => $descripcion,
                    'dormitorios' => $dormitorios,
                    'banos' => $banos,
                    'area_construida' => $area_construida,
                    'area_terreno' => $area_terreno,
                    'precio_clp' => $precio_clp,
                    'precio_uf' => $precio_uf,
                    'comodidades' => $comodidades_str,
                    'estado' => $estado,
                    'id' => $id
                ];

                if (es_admin() && isset($_POST['usuario_id'])) {
                    $sql_update .= ", usuario_id = :usuario_id";
                    $params_update['usuario_id'] = (int)$_POST['usuario_id'];
                }

                $sql_update .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute($params_update);

                // 2. Eliminar imágenes marcadas
                if (!empty($ids_eliminar)) {
                    $stmt_img = $pdo->prepare("SELECT * FROM propiedades_imagenes WHERE id = :img_id AND propiedad_id = :prop_id");
                    foreach ($ids_eliminar as $img_id) {
                        $stmt_img->execute(['img_id' => $img_id, 'prop_id' => $id]);
                        $img = $stmt_img->fetch();
                        if ($img) {
                            $file_path = __DIR__ . '/' . $img['ruta_imagen'];
                            if (strpos($img['ruta_imagen'], 'uploads/propiedades/') === 0 && file_exists($file_path)) {
                                @unlink($file_path);
                            }
                            $del = $pdo->prepare("DELETE FROM propiedades_imagenes WHERE id = :img_id");
                            $del->execute(['img_id' => $img_id]);
                        }
                    }
                }

                // 3. Subir nuevas fotos (si las hay)
                if ($nuevas_fotos_count > 0) {
                    for ($i = 0; $i < $nuevas_fotos_count; $i++) {
                        $tmp_name = $_FILES['fotos_nuevas']['tmp_name'][$i];
                        $orig_name = basename($_FILES['fotos_nuevas']['name'][$i]);
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                        $new_img_name = uniqid('prop_' . $id . '_', true) . '.' . $ext;
                        $dest_path = __DIR__ . '/uploads/propiedades/' . $new_img_name;

                        if (move_uploaded_file($tmp_name, $dest_path)) {
                            $db_path = 'uploads/propiedades/' . $new_img_name;
                            $stmt_ins = $pdo->prepare("INSERT INTO propiedades_imagenes (propiedad_id, ruta_imagen, es_principal) VALUES (:prop_id, :ruta, 0)");
                            $stmt_ins->execute([
                                'prop_id' => $id,
                                'ruta' => $db_path
                            ]);
                        }
                    }
                }

                // 4. Definir imagen principal
                $principal_id = isset($_POST['imagen_principal']) ? (int)$_POST['imagen_principal'] : 0;

                // Quitar marca de principal a todas
                $pdo->prepare("UPDATE propiedades_imagenes SET es_principal = 0 WHERE propiedad_id = :id")
                    ->execute(['id' => $id]);

                if ($principal_id > 0) {
                    // Verificar que la imagen pertenezca a esta propiedad y no haya sido eliminada
                    $check = $pdo->prepare("SELECT id FROM propiedades_imagenes WHERE id = :img_id AND propiedad_id = :prop_id");
                    $check->execute(['img_id' => $principal_id, 'prop_id' => $id]);
                    if ($check->fetch()) {
                        $pdo->prepare("UPDATE propiedades_imagenes SET es_principal = 1 WHERE id = :img_id")
                            ->execute(['img_id' => $principal_id]);
                    } else {
                        $principal_id = 0;
                    }
                }

                if ($principal_id === 0) {
                    // Si no se definió una principal válida, se asigna la primera imagen restante como principal
                    $pdo->prepare("UPDATE propiedades_imagenes SET es_principal = 1 WHERE id = (
                        SELECT id FROM (SELECT id FROM propiedades_imagenes WHERE propiedad_id = :id ORDER BY id ASC LIMIT 1) AS sub
                    )")->execute(['id' => $id]);
                }

                $pdo->commit();

                $success = es_admin()
                    ? 'Propiedad actualizada exitosamente.'
                    : 'Propiedad actualizada exitosamente. Volverá a quedar en estado Pendiente hasta ser revisada por el Administrador.';

                // Recargar datos
                $stmt = $pdo->prepare("SELECT * FROM propiedades WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $prop = $stmt->fetch();

            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Ocurrió un error al actualizar los datos: ' . $e->getMessage();
            }
        }
    }
}

// Convertir comodidades a array para marcar checkboxes
$comodidades_actuales = !empty($prop['comodidades']) ? array_map('trim', explode(',', $prop['comodidades'])) : [];

// Limpiar m² de los inputs
$area_terr_val = str_replace('m²', '', $prop['area_terreno'] ?? '');
$area_const_val = str_replace('m²', '', $prop['area_construida'] ?? '');

// Imágenes actuales para mostrar en el formulario
$imagenes_propiedad = obtenerImagenesPropiedad($pdo, $id);

require_once __DIR__ . '/includes/header.php';
?>

<div id="edit-propiedad" class="py-5">
    <div class="container my-4">
        <div class="form-card">
            <div class="section-title">
                <span>&#128221;</span>
                <h2>Editar Propiedad (Cód: <?php echo htmlspecialchars($prop['codigo']); ?>)</h2>
                <p class="text-muted">Modifique los antecedentes necesarios de la propiedad.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success small mb-4"><?php echo htmlspecialchars($success); ?></div>
                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-primary px-4">Volver al Dashboard</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="editar_propiedad.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
                <h6 class="text-muted text-uppercase small mb-3 border-bottom pb-2">Información General</h6>
                <div class="row">
                        <?php if (es_admin()): ?>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Propietario / Gestor Freelance Asociado <span class="text-danger">*</span></label>
                                <select name="usuario_id" class="form-select" required>
                                    <option value="" disabled>Seleccione el propietario o gestor freelance</option>
                                    <?php foreach ($propietarios as $prop_usr): ?>
                                        <option value="<?php echo $prop_usr['id']; ?>" <?php echo ($prop['usuario_id'] == $prop_usr['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prop_usr['nombre'] . ' (' . $prop_usr['tipo'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Propiedad <span class="text-danger">*</span></label>
                            <select id="tipoProp" name="tipo" class="form-select" required>
                                <option value="Casa" <?php echo ($prop['tipo'] === 'Casa') ? 'selected' : ''; ?>>Casa</option>
                                <option value="Departamento" <?php echo ($prop['tipo'] === 'Departamento') ? 'selected' : ''; ?>>Departamento</option>
                                <option value="Terreno" <?php echo ($prop['tipo'] === 'Terreno') ? 'selected' : ''; ?>>Terreno</option>
                            </select>
                        </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estado de la Publicación</label>
                        <?php if (es_admin()): ?>
                            <select name="estado" class="form-select" required>
                                <option value="Pendiente" <?php echo ($prop['estado'] === 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="Activa" <?php echo ($prop['estado'] === 'Activa') ? 'selected' : ''; ?>>Activa</option>
                                <option value="Rechazada" <?php echo ($prop['estado'] === 'Rechazada') ? 'selected' : ''; ?>>Rechazada</option>
                            </select>
                        <?php else: ?>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($prop['estado']); ?>" disabled>
                            <div class="form-text">Al guardar cambios, la propiedad vuelve a estado Pendiente para revisión.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Provincia <span class="text-danger">*</span></label>
                        <select id="provincia2" name="provincia" class="form-select" onchange="cargarComunas2()" required>
                            <option value="Elqui" <?php echo ($prop['provincia'] === 'Elqui') ? 'selected' : ''; ?>>Elqui</option>
                            <option value="Limari" <?php echo ($prop['provincia'] === 'Limari' || $prop['provincia'] === 'Limarí') ? 'selected' : ''; ?>>Limarí</option>
                            <option value="Choapa" <?php echo ($prop['provincia'] === 'Choapa') ? 'selected' : ''; ?>>Choapa</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Comuna <span class="text-danger">*</span></label>
                        <select id="comuna2" name="comuna" class="form-select" onchange="cargarSectores2()" required>
                            <!-- Se poblará con JS -->
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sector <span class="text-danger">*</span></label>
                        <select id="sector2" name="sector" class="form-select" required>
                            <!-- Se poblará con JS -->
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Descripción <span class="text-danger">*</span></label>
                        <textarea name="descripcion" class="form-control" rows="3" required><?php echo htmlspecialchars($prop['descripcion']); ?></textarea>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Características</h6>
                <div class="row">
                    <div class="col-md-3 mb-3" id="grupo-dormitorios">
                        <label class="form-label">Dormitorios <span class="text-danger req-mark">*</span></label>
                        <input type="number" name="dormitorios" class="form-control" min="0" value="<?php echo htmlspecialchars($prop['dormitorios'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3" id="grupo-banos">
                        <label class="form-label">Baños <span class="text-danger req-mark">*</span></label>
                        <input type="number" name="banos" class="form-control" min="0" value="<?php echo htmlspecialchars($prop['banos'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3" id="grupo-terreno">
                        <label class="form-label">Área Terreno (m²) <span class="text-danger req-mark">*</span></label>
                        <input type="number" name="area_terreno" class="form-control" min="0" value="<?php echo htmlspecialchars($area_terr_val); ?>">
                    </div>
                    <div class="col-md-3 mb-3" id="grupo-construida">
                        <label class="form-label">Área Construida (m²) <span class="text-danger req-mark">*</span></label>
                        <input type="number" name="area_construida" class="form-control" min="0" value="<?php echo htmlspecialchars($area_const_val); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Precio en $ <span class="text-danger">*</span></label>
                        <input type="number" name="precio_clp" class="form-control" required value="<?php echo htmlspecialchars($prop['precio_clp']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Precio en UF <span class="text-danger">*</span></label>
                        <input type="number" name="precio_uf" class="form-control" required value="<?php echo htmlspecialchars($prop['precio_uf']); ?>">
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Comodidades</h6>
                <div class="row mb-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bodega" id="bodega" <?php echo in_array('Bodega', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="bodega">Bodega</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="estac" id="estac" <?php echo in_array('Estacionamiento', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="estac">Estacionamiento</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="logia" id="logia" <?php echo in_array('Logia', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="logia">Logia</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cocina" id="cocina" <?php echo in_array('Cocina amoblada', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="cocina">Cocina amoblada</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="antej" id="antej" <?php echo in_array('Antejardín', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="antej">Antejardín</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="patio" id="patio" <?php echo in_array('Patio trasero', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="patio">Patio trasero</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="piscina" id="piscina" <?php echo in_array('Piscina', $comodidades_actuales) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="piscina">Piscina</label>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════
                GESTIÓN DE FOTOGRAFÍAS
                ════════════════════════════════════════════ -->
                <h6 class="text-muted text-uppercase small mb-3 mt-3 border-bottom pb-2">Fotografías de la Propiedad</h6>

                <?php if (empty($imagenes_propiedad)): ?>
                    <p class="text-muted small">Esta propiedad aún no tiene fotografías.</p>
                <?php else: ?>
                    <p class="text-muted small mb-2">
                        Marque la imagen principal con el botón <i class="bi bi-star-fill text-warning"></i> y use
                        <i class="bi bi-trash text-danger"></i> para eliminar una foto. Debe quedar al menos 1 fotografía.
                    </p>
                    <div class="row g-3 mb-3" id="galeria-imagenes">
                        <?php foreach ($imagenes_propiedad as $img): ?>
                            <div class="col-6 col-md-3 text-center imagen-item" data-img-id="<?php echo $img['id']; ?>">
                                <div class="position-relative">
                                    <img src="<?php echo htmlspecialchars($img['ruta_imagen']); ?>" class="img-fluid rounded-3 mb-1" style="height:110px;width:100%;object-fit:cover;">
                                    <?php if ($img['es_principal']): ?>
                                        <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-1"><i class="bi bi-star-fill"></i> Principal</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-center gap-2 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="imagen_principal" id="principal-<?php echo $img['id']; ?>" value="<?php echo $img['id']; ?>" <?php echo $img['es_principal'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="principal-<?php echo $img['id']; ?>">Principal</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="eliminar_imagen[]" id="eliminar-<?php echo $img['id']; ?>" value="<?php echo $img['id']; ?>">
                                        <label class="form-check-label small text-danger" for="eliminar-<?php echo $img['id']; ?>">Eliminar</label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Agregar nuevas fotografías</label>
                    <input type="file" name="fotos_nuevas[]" class="form-control" accept="image/*" multiple>
                    <div class="form-text">Formatos permitidos: JPG, PNG, WEBP. El total de fotos (actuales + nuevas - eliminadas) no puede superar 10 ni ser menor a 1.</div>
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
    // Inicializar inputs de ubicación
    window.addEventListener('DOMContentLoaded', () => {
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

        const comVal = "<?php echo htmlspecialchars($prop['comuna']); ?>";
        const sectVal = "<?php echo htmlspecialchars($prop['sector']); ?>";

        cargarComunas2();

        const comEl = document.getElementById('comuna2');
        if (comEl) {
            comEl.value = comVal;
            cargarSectores2();

            const sectEl = document.getElementById('sector2');
            if (sectEl) {
                sectEl.value = sectVal;
            }
        }

        // Mostrar/Ocultar dinámicamente inputs por tipo de propiedad
        const tipoSelect = document.getElementById('tipoProp');
        const gDorm = document.getElementById('grupo-dormitorios');
        const gBanos = document.getElementById('grupo-banos');
        const gTerreno = document.getElementById('grupo-terreno');
        const gConst = document.getElementById('grupo-construida');

        function actualizarCampos() {
            if (!tipoSelect) return;
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
                }
                if (labelReq) labelReq.style.display = 'none';
            }
        }

        if (tipoSelect) {
            tipoSelect.addEventListener('change', actualizarCampos);
            actualizarCampos(); // Ejecutar inicialmente
        }

        // Si se marca "eliminar" en la imagen que es la principal, desmarcar su radio de principal
        document.querySelectorAll('input[name="eliminar_imagen[]"]').forEach(chk => {
            chk.addEventListener('change', () => {
                const item = chk.closest('.imagen-item');
                const radio = item ? item.querySelector('input[name="imagen_principal"]') : null;
                if (chk.checked && radio) {
                    radio.checked = false;
                    radio.disabled = true;
                } else if (radio) {
                    radio.disabled = false;
                }
            });
        });

        // Validación antes de enviar: no permitir eliminar todas las fotos sin agregar nuevas
        const form = document.querySelector('#edit-propiedad form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const totalActuales = document.querySelectorAll('.imagen-item').length;
                const totalEliminar = document.querySelectorAll('input[name="eliminar_imagen[]"]:checked').length;
                const inputNuevas = document.querySelector('input[name="fotos_nuevas[]"]');
                const totalNuevas = inputNuevas ? inputNuevas.files.length : 0;
                const totalFinal = (totalActuales - totalEliminar) + totalNuevas;

                if (totalFinal < 1) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin fotografías',
                        text: 'Debe conservar o agregar al menos 1 fotografía para la propiedad.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }
                if (totalFinal > 10) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Demasiadas fotografías',
                        text: 'El total de fotografías no puede superar las 10 por propiedad.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }

                // Validar tamaño individual de nuevas fotos (máximo 128MB por archivo)
                if (inputNuevas && inputNuevas.files.length > 0) {
                    const maxIndividualSize = 128 * 1024 * 1024; // 128MB
                    for (let i = 0; i < inputNuevas.files.length; i++) {
                        if (inputNuevas.files[i].size > maxIndividualSize) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Imagen muy grande',
                                text: 'La imagen "' + inputNuevas.files[i].name + '" pesa ' + (inputNuevas.files[i].size / (1024 * 1024)).toFixed(2) + ' MB. El límite máximo permitido es de 128 MB por archivo. Reduzca la resolución o elija otra foto.',
                                confirmButtonColor: '#0f766e'
                            });
                            return;
                        }
                    }
                }

                // Validar tamaño total de nuevas fotos (límite de 256MB de POST)
                if (inputNuevas && inputNuevas.files.length > 0) {
                    let totalSize = 0;
                    const maxSize = 256 * 1024 * 1024; // 256MB
                    for (let i = 0; i < inputNuevas.files.length; i++) {
                        totalSize += inputNuevas.files[i].size;
                    }
                    if (totalSize > maxSize) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Archivos muy pesados',
                            text: 'El tamaño total de las imágenes nuevas (' + (totalSize / (1024 * 1024)).toFixed(2) + ' MB) supera el límite de subida permitido de 256 MB. Reduzca la resolución de las fotos o suba menos imágenes.',
                            confirmButtonColor: '#0f766e'
                        });
                        return;
                    }
                }

                const precioClp = parseInt(form.querySelector('input[name="precio_clp"]').value) || 0;
                const precioUf = parseInt(form.querySelector('input[name="precio_uf"]').value) || 0;
                if (precioClp <= 0 || precioUf <= 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Precios Inválidos',
                        text: 'Los precios en pesos y UF deben ser mayores que cero.',
                        confirmButtonColor: '#0f766e'
                    });
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

<?php if (!empty($success)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'success',
            title: '¡Operación Exitosa!',
            text: '<?php echo addslashes($success); ?>',
            confirmButtonColor: '#0f766e'
        });
    });
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
