<?php

declare(strict_types=1);

function requerirSesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}
