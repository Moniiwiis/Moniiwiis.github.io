    <!-- FOOTER -->
    <footer class="mt-5 pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="d-flex align-items-center gap-2">
                        <img src="img/logo_fondo.png" alt="PNK Inmobiliaria" height="60" style="border-radius: 6px;">
                        PNK Inmobiliaria
                    </h4>
                    <p class="text-secondary">Encuentra la propiedad ideal en la Región de Coquimbo. Casas,
                        departamentos y terrenos en venta.</p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="#"><i class="bi bi-facebook fs-5"></i></a>
                        <a href="#"><i class="bi bi-instagram fs-5"></i></a>
                        <a href="#"><i class="bi bi-linkedin fs-5"></i></a>
                        <a href="#"><i class="bi bi-whatsapp fs-5"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Contacto</h5>
                    <p class="text-secondary mb-1">+56 9 1234 5678</p>
                    <p class="text-secondary mb-1">contacto@pnkinmobiliaria.cl</p>
                    <p class="text-secondary">La Serena, Región de Coquimbo</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Accesos</h5>
                    <a href="registro_propietario.php" class="d-block text-secondary mb-2">Registro Propietario</a>
                    <a href="registro_gestor.php" class="d-block text-secondary mb-2">Registro Gestor</a>
                    <a href="login.php" class="d-block text-secondary mb-2">Acceder al Panel</a>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-secondary">
                <small>© 2026 PNK Inmobiliaria - Todos los derechos reservados | Región de Coquimbo</small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Application JS -->
    <script src="js/app.js"></script>

    <!-- SweetAlert2 Alertas Globales -->
    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['alerta_success'])) {
        echo "<script>Swal.fire({ icon: 'success', title: '¡Éxito!', text: '" . addslashes($_SESSION['alerta_success']) . "', confirmButtonColor: '#0f766e' });</script>";
        unset($_SESSION['alerta_success']);
    }
    if (isset($_SESSION['alerta_error'])) {
        echo "<script>Swal.fire({ icon: 'error', title: '¡Error!', text: '" . addslashes($_SESSION['alerta_error']) . "', confirmButtonColor: '#0f766e' });</script>";
        unset($_SESSION['alerta_error']);
    }
    if (isset($_SESSION['alerta_info'])) {
        echo "<script>Swal.fire({ icon: 'info', title: 'Información', text: '" . addslashes($_SESSION['alerta_info']) . "', confirmButtonColor: '#0f766e' });</script>";
        unset($_SESSION['alerta_info']);
    }

    $msg_map = [
        'usuario_aprobado' => ['success', '¡Usuario Aprobado!', 'El usuario ha sido activado correctamente.'],
        'usuario_eliminado' => ['warning', 'Usuario Eliminado', 'El usuario y sus antecedentes fueron removidos.'],
        'usuario_creado' => ['success', 'Usuario Creado', 'El nuevo usuario ha sido registrado con éxito.'],
        'usuario_rechazado' => ['warning', 'Usuario Rechazado', 'El registro de usuario fue rechazado con éxito.'],
        'propiedad_aprobada' => ['success', 'Propiedad Aprobada', 'La propiedad ha sido activada y es visible en el buscador.'],
        'propiedad_eliminada' => ['warning', 'Propiedad Eliminada', 'La propiedad y sus imágenes fueron removidas.'],
        'propiedad_rechazada' => ['warning', 'Propiedad Rechazada', 'La publicación de la propiedad fue rechazada.'],
        'propiedad_no_encontrada' => ['error', 'No Encontrada', 'La propiedad especificada no existe.'],
        'debes_iniciar_sesion' => ['info', 'Acceso Restringido', 'Debes iniciar sesión para acceder a este panel.'],
        'sesion_cerrada' => ['success', 'Sesión Cerrada', 'Sesión cerrada correctamente. ¡Hasta luego!'],
        'acceso_denegado' => ['error', 'Acceso Denegado', 'No tienes permisos para ingresar a esta sección.'],
        'perfil_actualizado' => ['success', 'Perfil Actualizado', 'Sus datos han sido guardados con éxito y su cuenta ha sido enviada a revisión.']
    ];
    $get_msg = $_GET['msg'] ?? '';
    if (array_key_exists($get_msg, $msg_map)) {
        $alert = $msg_map[$get_msg];
        echo "<script>Swal.fire({ icon: '{$alert[0]}', title: '{$alert[1]}', text: '" . addslashes($alert[2]) . "', confirmButtonColor: '#0f766e' });</script>";
    }
    ?>

    <!-- SweetAlert2 Confirmaciones Globales -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.confirmar-accion').forEach(element => {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                const mensaje = this.getAttribute('data-mensaje') || '¿Está seguro de realizar esta acción?';
                
                Swal.fire({
                    title: '¿Confirmar acción?',
                    text: mensaje,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0f766e',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = href;
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
