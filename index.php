<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Obtener parámetros de búsqueda
$provincia_sel = isset($_GET['provincia']) ? $_GET['provincia'] : '';
$comuna_sel = isset($_GET['comuna']) ? $_GET['comuna'] : '';
$sector_sel = isset($_GET['sector']) ? $_GET['sector'] : '';
$tipo_sel = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Construir consulta SQL para buscar propiedades activas
$sql = "SELECT p.*, GROUP_CONCAT(pi.ruta_imagen ORDER BY pi.es_principal DESC, pi.id ASC) as imagenes_str 
        FROM propiedades p 
        LEFT JOIN propiedades_imagenes pi ON p.id = pi.propiedad_id 
        WHERE p.estado = 'Activa'";

$params = [];

if (!empty($provincia_sel)) {
    $sql .= " AND p.provincia = :provincia";
    $params['provincia'] = $provincia_sel;
}
if (!empty($comuna_sel)) {
    $sql .= " AND p.comuna = :comuna";
    $params['comuna'] = $comuna_sel;
}
if (!empty($sector_sel)) {
    $sql .= " AND p.sector = :sector";
    $params['sector'] = $sector_sel;
}
if (!empty($tipo_sel)) {
    $sql .= " AND p.tipo = :tipo";
    $params['tipo'] = $tipo_sel;
}

$sql .= " GROUP BY p.id ORDER BY p.fecha_publicacion DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$propiedades = $stmt->fetchAll();
?>

<!-- ══════════════════════════════════════════
HOME
══════════════════════════════════════════ -->
<div id="home">
    <div class="hero">
        <div class="container text-center">
            <div class="hero-badge">Región de Coquimbo</div>
            <h1>Encuentra tu Hogar Ideal</h1>
            <p>Casas, departamentos y terrenos en toda la Región de Coquimbo.<br>
                También puedes ser un <strong style="color:var(--sky)">Gestor Inmobiliario Freelance</strong> y
                comisionar.</p>
        </div>
    </div>

    <div class="container">
        <!-- Buscador -->
        <div class="search-box mb-5">
            <h5 class="mb-3">Buscar Propiedades</h5>
            <form method="GET" action="index.php">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Provincia</label>
                        <select id="provincia" name="provincia" class="form-select" onchange="cargarComunas()">
                            <option value="">Seleccione provincia</option>
                            <option value="Elqui" <?php echo ($provincia_sel === 'Elqui') ? 'selected' : ''; ?>>Elqui</option>
                            <option value="Limari" <?php echo ($provincia_sel === 'Limari') ? 'selected' : ''; ?>>Limarí</option>
                            <option value="Choapa" <?php echo ($provincia_sel === 'Choapa') ? 'selected' : ''; ?>>Choapa</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Comuna</label>
                        <select id="comuna" name="comuna" class="form-select" onchange="cargarSectores()">
                            <option value="">Seleccione comuna</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sector</label>
                        <select id="sector" name="sector" class="form-select">
                            <option value="">Seleccione sector</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <option value="Casa" <?php echo ($tipo_sel === 'Casa') ? 'selected' : ''; ?>>Casa</option>
                            <option value="Departamento" <?php echo ($tipo_sel === 'Departamento') ? 'selected' : ''; ?>>Departamento</option>
                            <option value="Terreno" <?php echo ($tipo_sel === 'Terreno') ? 'selected' : ''; ?>>Terreno</option>
                        </select>
                    </div>
                    <div class="col-12 text-end d-flex justify-content-end gap-2">
                        <?php if (!empty($provincia_sel) || !empty($comuna_sel) || !empty($sector_sel) || !empty($tipo_sel)): ?>
                            <a href="index.php" class="btn btn-outline-secondary px-4">Limpiar Filtros</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-4">Buscar</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Propiedades -->
        <h4 class="mb-4">Propiedades Destacadas</h4>
        
        <?php if (empty($propiedades)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                No se encontraron propiedades activas que coincidan con los criterios de búsqueda.
            </div>
        <?php else: ?>
            <div class="row g-4 mb-5">
                <?php foreach ($propiedades as $prop): 
                    // Procesar lista de imágenes
                    $imagenes = [];
                    if (!empty($prop['imagenes_str'])) {
                        $imagenes = explode(',', $prop['imagenes_str']);
                    }
                    
                    // Procesar comodidades para JS
                    $comodidades_arr = [];
                    if (!empty($prop['comodidades'])) {
                        $comodidades_arr = array_map('trim', explode(',', $prop['comodidades']));
                    }
                    
                    // Formatear precios
                    $precio_clp_fmt = '$' . number_format($prop['precio_clp'], 0, ',', '.');
                    $precio_uf_fmt = 'UF ' . number_format($prop['precio_uf'], 0, ',', '.');
                    
                    // JSON para pasar a JS
                    $imagenes_json = json_encode($imagenes);
                    $comodidades_json = json_encode($comodidades_arr);
                    $titulo_escaped = htmlspecialchars($prop['titulo'], ENT_QUOTES, 'UTF-8');
                    $descripcion_escaped = htmlspecialchars($prop['descripcion'], ENT_QUOTES, 'UTF-8');
                    
                    $dorm = !is_null($prop['dormitorios']) ? $prop['dormitorios'] : '-';
                    $ban = !is_null($prop['banos']) ? $prop['banos'] : '-';
                    $const = !empty($prop['area_construida']) ? $prop['area_construida'] : '-';
                    $terr = !empty($prop['area_terreno']) ? $prop['area_terreno'] : '-';
                ?>
                    <div class="col-md-4">
                        <div class="prop-card">
                            <div id="carouselCard<?php echo $prop['id']; ?>" class="carousel slide" data-bs-ride="false">
                                <div class="carousel-inner" style="cursor: pointer;"
                                     onclick="abrirDetalle('<?php echo $prop['codigo']; ?>', '<?php echo $titulo_escaped; ?>', '<?php echo $precio_clp_fmt; ?>', '<?php echo $precio_uf_fmt; ?>', '<?php echo $dorm; ?>', '<?php echo $ban; ?>', '<?php echo $const; ?>', '<?php echo $terr; ?>', <?php echo htmlspecialchars($imagenes_json); ?>, '<?php echo $descripcion_escaped; ?>', <?php echo htmlspecialchars($comodidades_json); ?>)">
                                    
                                    <?php if (empty($imagenes)): ?>
                                        <div class="carousel-item active">
                                            <img src="https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=600&q=80" class="d-block w-100" alt="Sin imagen">
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($imagenes as $idx => $img): ?>
                                            <div class="carousel-item <?php echo ($idx === 0) ? 'active' : ''; ?>">
                                                <img src="<?php echo htmlspecialchars($img); ?>" class="d-block w-100" alt="Foto <?php echo $idx + 1; ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (count($imagenes) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselCard<?php echo $prop['id']; ?>"
                                        data-bs-slide="prev" onclick="event.stopPropagation();">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"
                                            style="background-color: rgba(0,0,0,0.3); border-radius: 50%;"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#carouselCard<?php echo $prop['id']; ?>"
                                        data-bs-slide="next" onclick="event.stopPropagation();">
                                        <span class="carousel-control-next-icon" aria-hidden="true"
                                            style="background-color: rgba(0,0,0,0.3); border-radius: 50%;"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="prop-badge <?php echo ($prop['tipo'] === 'Terreno') ? 'bg-primary' : ''; ?>"><?php echo htmlspecialchars($prop['tipo']); ?></span>
                                    <small class="text-muted">Cód: <?php echo htmlspecialchars($prop['codigo']); ?></small>
                                </div>
                                <h6 class="mt-2 mb-1"><?php echo htmlspecialchars($prop['sector'] . ', ' . $prop['comuna']); ?></h6>
                                <p class="text-muted small mb-1">Provincia <?php echo htmlspecialchars($prop['provincia']); ?></p>
                                <p class="fw-bold mb-2" style="color:var(--teal)"><?php echo $precio_clp_fmt; ?> <span
                                        class="text-muted fw-normal small">/ <?php echo $precio_uf_fmt; ?></span></p>
                                <button class="btn-ver" onclick="abrirDetalle(
                                    '<?php echo $prop['codigo']; ?>', 
                                    '<?php echo $titulo_escaped; ?>', 
                                    '<?php echo $precio_clp_fmt; ?>', 
                                    '<?php echo $precio_uf_fmt; ?>', 
                                    '<?php echo $dorm; ?>', 
                                    '<?php echo $ban; ?>', 
                                    '<?php echo $const; ?>', 
                                    '<?php echo $terr; ?>',
                                    <?php echo htmlspecialchars($imagenes_json); ?>,
                                    '<?php echo $descripcion_escaped; ?>',
                                    <?php echo htmlspecialchars($comodidades_json); ?>
                                )">Quiero saber más!</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DETALLE -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Propiedad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <!-- Carrusel de imágenes -->
                        <div id="carouselDetalle" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner rounded-3" id="det-carrusel-inner"
                                style="height:220px; overflow:hidden;">
                                <!-- Las imágenes se inyectan acá -->
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselDetalle"
                                data-bs-slide="prev">
                                <span class="carousel-control-prev-icon bg-dark rounded-circle p-2"
                                    aria-hidden="true" style="opacity:0.7;"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselDetalle"
                                data-bs-slide="next">
                                <span class="carousel-control-next-icon bg-dark rounded-circle p-2"
                                    aria-hidden="true" style="opacity:0.7;"></span>
                                <span class="visually-hidden">Siguiente</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex flex-column justify-content-between pt-3 pt-md-0">
                        <div>
                            <small class="text-muted" id="det-cod"></small>
                            <h4 id="det-nombre" class="mt-1"></h4>
                            <p class="text-muted small" id="det-fecha"></p>
                        </div>
                        <div>
                            <h4 style="color:var(--teal)" id="det-precio"></h4>
                            <h6 class="text-muted" id="det-uf"></h6>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-door-open fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Dormitorios</p>
                            <strong id="det-dorm"></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-droplet fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Baños</p>
                            <strong id="det-banos"></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-aspect-ratio fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Área Construida</p>
                            <strong id="det-construida"></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-map fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Área Terreno</p>
                            <strong id="det-terreno"></strong>
                        </div>
                    </div>
                </div>
                <p class="text-muted mb-3" id="det-desc"></p>
                <h6 class="mb-2">Características:</h6>
                <div class="mb-3" id="det-caracteristicas">
                    <!-- Características inyectadas -->
                </div>
                <div class="ratio ratio-16x9 rounded-3 overflow-hidden mb-3">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d26949.52!2d-71.2524!3d-29.9027!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9691df4a27777777%3A0x7e7b5a9d5d5d5d5d!2sLa%20Serena%2C%20Coquimbo%20Region!5e0!3m2!1ses!2scl!4v1"
                        style="border:0" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" onclick="Swal.fire({ icon: 'success', title: 'Solicitud Enviada', text: 'Tu solicitud de visita ha sido enviada. Nos contactaremos a la brevedad.', confirmButtonColor: '#0f766e' })">Solicitar Visita</button>
                    <a href="https://www.facebook.com/sharer/sharer.php" target="_blank"
                        class="btn btn-outline-secondary">
                        <i class="bi bi-facebook me-1"></i>Compartir
                    </a>
                    <a href="https://wa.me/?text=Mira+esta+propiedad" target="_blank"
                        class="btn btn-outline-success">
                        <i class="bi bi-whatsapp me-1"></i>WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lógica para poblar dinámicamente comunas y sectores al recargar si hay una búsqueda previa -->
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const provSel = "<?php echo htmlspecialchars($provincia_sel); ?>";
        const comSel = "<?php echo htmlspecialchars($comuna_sel); ?>";
        const sectSel = "<?php echo htmlspecialchars($sector_sel); ?>";

        if (provSel) {
            cargarComunas();
            const comEl = document.getElementById('comuna');
            if (comEl) {
                comEl.value = comSel;
                cargarSectores();
                const sectEl = document.getElementById('sector');
                if (sectEl) {
                    sectEl.value = sectSel;
                }
            }
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
