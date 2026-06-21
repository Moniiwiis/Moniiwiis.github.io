<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario actual ha iniciado sesión.
 * @return bool
 */
function esta_autenticado() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

/**
 * Verifica si el usuario actual tiene el rol de Administrador.
 * @return bool
 */
function es_admin() {
    return esta_autenticado() && isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'Administrador';
}

/**
 * Protege páginas privadas. Si no está autenticado, redirige a login.php
 */
function verificar_autenticado() {
    if (!esta_autenticado()) {
        header("Location: login.php?msg=debes_iniciar_sesion");
        exit;
    }
}

/**
 * Protege páginas exclusivas para administradores.
 */
function verificar_admin() {
    verificar_autenticado();
    if (!es_admin()) {
        header("Location: index.php?msg=acceso_denegado");
        exit;
    }
}
?>
