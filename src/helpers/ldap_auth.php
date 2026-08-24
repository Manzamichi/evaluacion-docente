<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/ldap.php';

function construirCorreoInstitucional(string $matricula): string
{
    $config = getLdapConfig();

    return strtolower(trim($matricula)) . '@' . $config['domain'];
}

function autenticarContraAD(string $matricula, string $password): bool
{
    $config = getLdapConfig();

    if ($config['auth_mode'] === 'simulado') {
        return autenticarSimulado($matricula, $password);
    }

    return autenticarLDAP($matricula, $password, $config);
}

function autenticarLDAP(string $matricula, string $password, array $config): bool
{
    if (!extension_loaded('ldap')) {
        throw new RuntimeException('La extensión ldap de PHP no está habilitada (falta ext-ldap).');
    }

    $conexion = ldap_connect($config['host'], $config['port']);
    if ($conexion === false) {
        return false;
    }

    ldap_set_option($conexion, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conexion, LDAP_OPT_REFERRALS, 0);

    if ($config['use_tls'] && !@ldap_start_tls($conexion)) {
        return false;
    }

    $upn = construirCorreoInstitucional($matricula);

    /*"Simple bind": AD valida usuario+contraseña al intentar autenticar la conexión. Si la credencial es incorrecta, ldap_bind regresa 
    false; no hace falta comparar nada a mano.*/

    $bindOk = @ldap_bind($conexion, $upn, $password);

    ldap_unbind($conexion);

    return $bindOk;
}

function autenticarSimulado(string $matricula, string $password): bool
{
    /* Cuentas de prueba mientras no hay acceso al Directorio Activo real de la universidad, solo para desarrollo local: nunca debe usarse 
    con AUTH_MODE=simulado en producción. */
    
    $cuentasDePrueba = [
        'a22245245' => 'Prueba123#',
    ];

    $matricula = strtolower(trim($matricula));

    return isset($cuentasDePrueba[$matricula]) && hash_equals($cuentasDePrueba[$matricula], $password);
}
