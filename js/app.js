// ── Redirección de Secciones Legacy ────────────────────────────────
function mostrar(id) {
    if (id === 'home') {
        window.location.href = 'index.php';
    } else if (id === 'login-section') {
        window.location.href = 'login.php';
    } else if (id === 'propietario') {
        window.location.href = 'registro_propietario.php';
    } else if (id === 'gestor') {
        window.location.href = 'registro_gestor.php';
    } else if (id === 'dashboard') {
        window.location.href = 'dashboard.php';
    } else if (id === 'reg-propiedad') {
        window.location.href = 'agregar_propiedad.php';
    }
}

// ── Login / Recuperar Contraseña ───────────────────────────────────
function toggleRecuperar(show) {
    const loginForm = document.getElementById('login-form');
    const recuperarForm = document.getElementById('recuperar-form');
    if (loginForm && recuperarForm) {
        loginForm.style.display = show ? 'none' : 'block';
        recuperarForm.style.display = show ? 'block' : 'none';
    }
}

// ── Tabs del Dashboard ─────────────────────────────────────────────
function mostrarTab(id) {
    const tabs = ['tab-usuarios', 'tab-propiedades'];
    tabs.forEach(function (t) {
        const el = document.getElementById(t);
        if (el) {
            el.style.display = (t === id) ? 'block' : 'none';
        }
    });
}

// ── Datos de la Región (Provincias, Comunas y Sectores) ─────────────
const datos = {
    Elqui: {
        'La Serena': ['Centro', 'El Milagro', 'Las Compañías', 'Puertas del Mar', 'La Florida'],
        'Coquimbo': ['Sindempart', 'Tierras Blancas', 'La Herradura', 'Peñuelas'],
        'Vicuña': ['Centro', 'Rivadavia'],
        'Andacollo': ['Centro'],
        'Paihuano': ['Montegrande', 'Pisco Elqui'],
        'La Higuera': ['Caleta Los Hornos']
    },
    Limari: {
        'Ovalle': ['Centro', 'Media Hacienda', 'Las Mercedes'],
        'Monte Patria': ['Centro'],
        'Combarbalá': ['Centro'],
        'Punitaqui': ['Centro'],
        'Río Hurtado': ['Samo Alto']
    },
    Choapa: {
        'Illapel': ['Centro'],
        'Salamanca': ['Centro', 'El Tambo'],
        'Los Vilos': ['Pichidangui', 'Centro'],
        'Canela': ['Canela Baja', 'Canela Alta']
    }
};

function poblarComunas(idProv, idComuna, idSector) {
    const provEl = document.getElementById(idProv);
    const comunaEl = document.getElementById(idComuna);
    const sectorEl = document.getElementById(idSector);
    if (!provEl || !comunaEl || !sectorEl) return;

    const prov = provEl.value;
    comunaEl.innerHTML = '<option value="">Seleccione comuna</option>';
    sectorEl.innerHTML = '<option value="">Seleccione sector</option>';
    
    if (prov && datos[prov]) {
        Object.keys(datos[prov]).forEach(function (c) {
            comunaEl.innerHTML += '<option value="' + c + '">' + c + '</option>';
        });
    }
}

function poblarSectores(idProv, idComuna, idSector) {
    const provEl = document.getElementById(idProv);
    const comEl = document.getElementById(idComuna);
    const sectorEl = document.getElementById(idSector);
    if (!provEl || !comEl || !sectorEl) return;

    const prov = provEl.value;
    const com = comEl.value;
    sectorEl.innerHTML = '<option value="">Seleccione sector</option>';
    
    if (prov && com && datos[prov] && datos[prov][com]) {
        datos[prov][com].forEach(function (s) {
            sectorEl.innerHTML += '<option value="' + s + '">' + s + '</option>';
        });
    }
}

function cargarComunas() { poblarComunas('provincia', 'comuna', 'sector'); }
function cargarSectores() { poblarSectores('provincia', 'comuna', 'sector'); }
function cargarComunas2() { poblarComunas('provincia2', 'comuna2', 'sector2'); }
function cargarSectores2() { poblarSectores('provincia2', 'comuna2', 'sector2'); }

// ── Modal de Detalle de Propiedad ──────────────────────────────────
function abrirDetalle(cod, nombre, precio, uf, dorm, banos, construida, terreno, imagenes, descripcion, caracteristicas) {
    const carruselInner = document.getElementById('det-carrusel-inner');
    if (!carruselInner) return;
    
    carruselInner.innerHTML = '';

    if (imagenes && imagenes.length > 0) {
        imagenes.forEach(function (url, index) {
            const isActivo = index === 0 ? 'active' : '';
            carruselInner.innerHTML += `
                <div class="carousel-item ${isActivo}">
                    <img src="${url}" class="d-block w-100" style="object-fit:cover; height:220px;" alt="Foto ${index + 1}">
                </div>`;
        });
    } else {
        carruselInner.innerHTML = `
            <div class="carousel-item active">
                <img src="https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=600&q=80" class="d-block w-100" style="object-fit:cover; height:220px;" alt="Default Image">
            </div>`;
    }

    document.getElementById('det-cod').textContent = 'Cód: ' + cod;
    document.getElementById('det-nombre').textContent = nombre;
    document.getElementById('det-precio').textContent = precio;
    document.getElementById('det-uf').textContent = uf;
    document.getElementById('det-dorm').textContent = dorm;
    document.getElementById('det-banos').textContent = banos;
    document.getElementById('det-construida').textContent = construida;
    document.getElementById('det-terreno').textContent = terreno;
    document.getElementById('det-fecha').textContent = 'Publicado: ' + new Date().toLocaleDateString('es-CL');

    document.getElementById('det-desc').textContent = descripcion || 'Hermosa propiedad ubicada en sector privilegiado con excelente acceso y conectividad. Cuenta con todos los servicios básicos y está en perfectas condiciones.';

    const contenedorCaract = document.getElementById('det-caracteristicas');
    if (contenedorCaract) {
        contenedorCaract.innerHTML = '';
        const listaCaract = caracteristicas || ['Estacionamiento', 'Bodega', 'Antejardín', 'Patio trasero', 'Cocina amoblada'];
        const iconMap = {
            'Estacionamiento': '<i class="bi bi-car-front me-1"></i>',
            'Bodega': '<i class="bi bi-box me-1"></i>',
            'Logia': '<i class="bi bi-ui-checks me-1"></i>',
            'Cocina amoblada': '<i class="bi bi-fire me-1"></i>',
            'Antejardín': '<i class="bi bi-flower1 me-1"></i>',
            'Patio trasero': '<i class="bi bi-tree me-1"></i>',
            'Piscina': '<i class="bi bi-water me-1"></i>'
        };
        listaCaract.forEach(function (feat) {
            const icon = iconMap[feat] || '<i class="bi bi-check-circle me-1"></i>';
            contenedorCaract.innerHTML += `<span class="feature-chip">${icon}${feat}</span>`;
        });
    }

    const modalEl = document.getElementById('modalDetalle');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}

// ── Modal de Información de Usuario ────────────────────────────────
function verUsuario(user) {
    document.getElementById('ver-user-id').textContent = user.id;
    document.getElementById('ver-user-nombre').textContent = user.nombre;
    document.getElementById('ver-user-rut').textContent = user.rut;
    document.getElementById('ver-user-correo').textContent = user.correo;
    document.getElementById('ver-user-tipo').textContent = user.tipo;
    document.getElementById('ver-user-estado').textContent = user.estado;
    
    // Fecha de Nacimiento
    if (user.fecha_nacimiento) {
        const parts = user.fecha_nacimiento.split('-');
        if (parts.length === 3) {
            document.getElementById('ver-user-fecha-nacimiento').textContent = `${parts[2]}-${parts[1]}-${parts[0]}`;
        } else {
            document.getElementById('ver-user-fecha-nacimiento').textContent = user.fecha_nacimiento;
        }
    } else {
        document.getElementById('ver-user-fecha-nacimiento').textContent = '-';
    }

    // Sexo y Teléfono
    document.getElementById('ver-user-sexo').textContent = user.sexo || '-';
    document.getElementById('ver-user-telefono').textContent = user.telefono || '-';

    // N° Registro Bienes Raíces (Propietario)
    const bbrContainer = document.getElementById('ver-user-bbr-container');
    const bbrEl = document.getElementById('ver-user-bbr');
    if (user.nro_registro_bbr) {
        bbrEl.textContent = user.nro_registro_bbr;
        if (bbrContainer) {
            bbrContainer.classList.remove('d-none');
            bbrContainer.classList.add('d-flex');
        }
    } else {
        if (bbrContainer) {
            bbrContainer.classList.remove('d-flex');
            bbrContainer.classList.add('d-none');
        }
    }

    // Certificado Antecedentes (Gestor)
    const certContainer = document.getElementById('ver-user-certificado-container');
    const certEl = document.getElementById('ver-user-certificado');
    if (user.certificado_antecedentes) {
        certEl.innerHTML = `<a href="uploads/certificados/${user.certificado_antecedentes}" target="_blank" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-file-earmark-pdf me-1"></i>Ver archivo</a>`;
        if (certContainer) {
            certContainer.classList.remove('d-none');
            certContainer.classList.add('d-flex');
        }
    } else {
        if (certContainer) {
            certContainer.classList.remove('d-flex');
            certContainer.classList.add('d-none');
        }
    }

    // Fecha Registro
    if (user.fecha_registro) {
        const parts = user.fecha_registro.split(' ');
        if (parts.length >= 1) {
            const dateParts = parts[0].split('-');
            if (dateParts.length === 3) {
                const formattedDate = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
                const formattedTime = parts[1] ? parts[1].substring(0, 5) : '';
                document.getElementById('ver-user-fecha-registro').textContent = formattedTime ? `${formattedDate} ${formattedTime}` : formattedDate;
            } else {
                document.getElementById('ver-user-fecha-registro').textContent = user.fecha_registro;
            }
        } else {
            document.getElementById('ver-user-fecha-registro').textContent = user.fecha_registro;
        }
    } else {
        document.getElementById('ver-user-fecha-registro').textContent = '-';
    }
    
    const modalEl = document.getElementById('modalVerUsuario');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}
