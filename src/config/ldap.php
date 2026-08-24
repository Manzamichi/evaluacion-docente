<?php

declare(strict_types=1);

function getLdapConfig(): array
{
    return [
        // 'ldap'     -> se conecta a un Directorio Activo real (requiere estar en la red/VPN de la universidad).
        // 'simulado' -> valida contra una lista local fija, para poder probar el flujo sin acceso al AD real.
        'auth_mode' => getenv('AUTH_MODE') ?: 'simulado',

        'host'    => getenv('AD_HOST') ?: 'ldap://dc.alumnos.universidad.mx',
        'port'    => (int) (getenv('AD_PORT') ?: 389),
        'domain'  => getenv('AD_DOMAIN') ?: 'alumnos.universidad.mx',
        'base_dn' => getenv('AD_BASE_DN') ?: 'DC=alumnos,DC=universidad,DC=mx',
        'use_tls' => (getenv('AD_USE_TLS') ?: 'true') === 'true',
    ];
}
