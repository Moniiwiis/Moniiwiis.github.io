/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS pnk_inmobiliaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pnk_inmobiliaria;


-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(15) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    sexo VARCHAR(30) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    tipo ENUM('Administrador', 'Propietario', 'Gestor Freelance') NOT NULL,
    estado ENUM('Pendiente', 'Activo', 'Rechazado') NOT NULL DEFAULT 'Pendiente',
    nro_registro_bbr VARCHAR(50) NULL, -- Exclusivo para Propietarios
    certificado_antecedentes VARCHAR(255) NULL, -- Exclusivo para Gestores Freelance (nombre de archivo)
    motivo_rechazo TEXT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Propiedades
CREATE TABLE IF NOT EXISTS propiedades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('Casa', 'Departamento', 'Terreno') NOT NULL,
    provincia VARCHAR(50) NOT NULL,
    comuna VARCHAR(50) NOT NULL,
    sector VARCHAR(50) NOT NULL,
    dormitorios INT NULL,
    banos INT NULL,
    area_construida VARCHAR(20) NULL,
    area_terreno VARCHAR(20) NULL,
    precio_clp BIGINT NOT NULL,
    precio_uf INT NOT NULL,
    comodidades TEXT NULL, -- Comma-separated list of features
    estado ENUM('Pendiente', 'Activa', 'Rechazada') NOT NULL DEFAULT 'Pendiente',
    fecha_publicacion DATE NOT NULL,
    usuario_id INT NULL, -- Quien la registró (página pública o gestor/propietario)
    motivo_rechazo TEXT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de Imágenes de Propiedades
CREATE TABLE IF NOT EXISTS propiedades_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT NOT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- SEED DATA (DATOS INICIALES)
-- --------------------------------------------------------

-- Inserción de Usuarios de Prueba (Contraseña para todos es 'admin123' encriptada con bcrypt)
-- Hash de bcrypt para 'admin123': $2y$10$E8uWRF6Px1SQgfnBTUP05u2IYT1pe5Np3gvvDnXOGE1h1CMPXojRK
INSERT INTO usuarios (id, rut, nombre, fecha_nacimiento, correo, password, sexo, telefono, tipo, estado, nro_registro_bbr, certificado_antecedentes) VALUES
(1, '1-9', 'Admin PNK', '1980-01-01', 'admin@pnkinmobiliaria.cl', '$2y$10$E8uWRF6Px1SQgfnBTUP05u2IYT1pe5Np3gvvDnXOGE1h1CMPXojRK', 'Prefiero no indicar', '+56 9 1234 5678', 'Administrador', 'Activo', NULL, NULL),
(2, '12.345.678-9', 'Juan Pérez González', '1985-03-24', 'juan@email.com', '$2y$10$E8uWRF6Px1SQgfnBTUP05u2IYT1pe5Np3gvvDnXOGE1h1CMPXojRK', 'Masculino', '+56 9 1111 2222', 'Propietario', 'Activo', '458796-1', NULL),
(3, '13.456.789-0', 'María Soto Ramos', '1988-11-04', 'maria@email.com', '$2y$10$E8uWRF6Px1SQgfnBTUP05u2IYT1pe5Np3gvvDnXOGE1h1CMPXojRK', 'Femenino', '+56 9 3333 4444', 'Gestor Freelance', 'Activo', NULL, 'antecedentes_maria.pdf'),
(4, '15.432.109-K', 'Carlos Muñoz López', '1992-05-12', 'carlos@email.com', '$2y$10$E8uWRF6Px1SQgfnBTUP05u2IYT1pe5Np3gvvDnXOGE1h1CMPXojRK', 'Masculino', '+56 9 8765 4321', 'Gestor Freelance', 'Pendiente', NULL, 'antecedentes_carlos.pdf');

-- Inserción de Propiedades
INSERT INTO propiedades (id, codigo, titulo, descripcion, tipo, provincia, comuna, sector, dormitorios, banos, area_construida, area_terreno, precio_clp, precio_uf, comodidades, estado, fecha_publicacion, usuario_id) VALUES
(1, 'C0125457', 'Casa Sector El Milagro', 'Hermosa casa familiar con amplio patio y excelente iluminación natural en el sector El Milagro.', 'Casa', 'Elqui', 'La Serena', 'El Milagro', 3, 2, '150m²', '200m²', 154000000, 5650, 'Estacionamiento (2), Antejardín, Terraza Techada, Cocina Amoblada, Quincho', 'Activa', CURDATE(), 1),
(2, 'D0234891', 'Depto Av. del Mar', 'Moderno departamento con vista panorámica a la playa, ideal para inversión o descanso vacacional.', 'Departamento', 'Elqui', 'Coquimbo', 'Peñuelas', 2, 1, '75m²', '75m²', 89000000, 3200, 'Conserjería 24/7, Piscina, Gimnasio, Estacionamiento, Balcón vista al mar', 'Activa', CURDATE(), 1),
(3, 'T0387652', 'Terreno Centro Ovalle', 'Terreno plano, ideal para construir la casa de tus sueños.', 'Terreno', 'Limari', 'Ovalle', 'Centro', NULL, NULL, '500m²', '0m²', 45000000, 1620, 'Cierre Perimetral, Agua de riego, Factibilidad Eléctrica, Terreno Plano', 'Activa', CURDATE(), 1),
(4, 'C0312340', 'Casa Las Compañías', 'Casa moderna con amplios espacios y excelente iluminación.', 'Casa', 'Elqui', 'La Serena', 'Las Compañías', 4, 3, '220m²', '350m²', 200000000, 7200, 'Logia, Cobertizo, Bodega, Patio Trasero', 'Activa', CURDATE(), 1),
(5, 'C0445210', 'Casa Puertas del Mar', 'Casa moderna con amplios espacios, ideal para familias que buscan comodidad y tranquilidad.', 'Casa', 'Elqui', 'La Serena', 'Puertas del Mar', 5, 4, '350m²', '600m²', 320000000, 11500, 'Condominio Privado, Piscina, Juegos Infantiles, Sala Multiuso', 'Activa', CURDATE(), 1),
(6, 'D0523774', 'Depto Tierras Blancas', 'Departamento acogedor, ideal para parejas o personas que buscan comodidad y tranquilidad.', 'Departamento', 'Elqui', 'Coquimbo', 'Tierras Blancas', 2, 1, '60m²', '60m²', 65000000, 2340, 'Cerca de colegios, Excelente Locomoción, Piso flotante', 'Activa', CURDATE(), 1),
(7, 'T0387659', 'Terreno El Milagro', 'Terreno plano.', 'Terreno', 'Elqui', 'La Serena', 'El Milagro', NULL, NULL, '500m²', '0m²', 45000000, 1620, 'Cierre Perimetral', 'Pendiente', CURDATE(), 2);

-- Inserción de Imágenes de Propiedades
INSERT INTO propiedades_imagenes (propiedad_id, ruta_imagen, es_principal) VALUES
(1, 'img/c1-1.png', 1), (1, 'img/c1-2.jpg', 0), (1, 'img/c1-3.jpg', 0), (1, 'img/c1-4.jpg', 0),
(2, 'img/d1-1.jpg', 1), (2, 'img/d1-2.jpg', 0), (2, 'img/d1-3.jpg', 0), (2, 'img/d1-4.jpg', 0),
(3, 'img/t1-1.jpg', 1), (3, 'img/t1-2.jpg', 0), (3, 'img/t1-3.jpg', 0),
(4, 'img/c2-1.jpg', 1), (4, 'img/c2-2.jpg', 0), (4, 'img/c2-3.jpg', 0), (4, 'img/c2-4.jpg', 0), (4, 'img/c2-5.jpg', 0),
(5, 'img/c3-1.jpg', 1), (5, 'img/c3-2.jpg', 0), (5, 'img/c3-3.jpg', 0), (5, 'img/c3-4.jpg', 0), (5, 'img/c3-5.jpg', 0), (5, 'img/c3-6.jpg', 0),
(6, 'img/d2-1.jpg', 1), (6, 'img/d2-2.png', 0), (6, 'img/d2-3.png', 0), (6, 'img/d2-4.png', 0), (6, 'img/d2-5.png', 0), (6, 'img/d2-6.png', 0),
(7, 'img/t1-1.jpg', 1);
