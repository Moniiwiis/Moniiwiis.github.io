<?php
/**
 * Valida un RUT chileno (formato y dígito verificador).
 * Soporta formatos con o sin puntos y guion (ej. 12.345.678-9 o 123456789).
 * @param string $rut
 * @return bool
 */
function validarRut($rut) {
    // Quitar puntos, guion y espacios, dejar solo números y la letra K/k
    $rut = preg_replace('/[^0-9kK]/', '', $rut);
    
    if (strlen($rut) < 2) {
        return false;
    }
    
    // Obtener número y dígito verificador
    $numero = substr($rut, 0, -1);
    $dv = strtoupper(substr($rut, -1));
    
    if (!ctype_digit($numero)) {
        return false;
    }
    
    // Calcular dígito verificador esperado
    $factor = 2;
    $suma = 0;
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += $numero[$i] * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }
    
    $res = 11 - ($suma % 11);
    $dv_esperado = $res === 11 ? '0' : ($res === 10 ? 'K' : (string)$res);
    
    return $dv === $dv_esperado;
}

/**
 * Valida la robustez de una contraseña.
 * Criterio: Mínimo 8 caracteres, al menos 1 mayúscula, 1 minúscula, 1 número y 1 carácter especial.
 * @param string $password
 * @return bool
 */
function validarPasswordRobusta($password) {
    if (strlen($password) < 8) {
        return false;
    }
    // Al menos una minúscula
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    // Al menos una mayúscula
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    // Al menos un número
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    // Al menos un carácter especial
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return false;
    }
    return true;
}

/**
 * Formatea un RUT al formato estándar (ej: 12.345.678-K).
 * @param string $rut
 * @return string
 */
function formatearRut($rut) {
    $rut = preg_replace('/[^0-9kK]/', '', $rut);
    if (strlen($rut) < 2) {
        return $rut;
    }
    $cuerpo = substr($rut, 0, -1);
    $dv = strtoupper(substr($rut, -1));
    
    $cuerpo_format = '';
    while (strlen($cuerpo) > 3) {
        $cuerpo_format = '.' . substr($cuerpo, -3) . $cuerpo_format;
        $cuerpo = substr($cuerpo, 0, -3);
    }
    $cuerpo_format = $cuerpo . $cuerpo_format;
    
    return $cuerpo_format . '-' . $dv;
}
?>
