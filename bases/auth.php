<?php
// includes/auth.php
// -----------------------------------------------------------------------------
// Utilidades de sesión, control de acceso y filtro por RFC para usuarios externos
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/** ¿Hay sesión? */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/** Exigir sesión o redirigir a login */
function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: login.php'); exit(); }
}

/** ¿El usuario es externo? ('S' / 'N') */
function isExterno(): bool {
    return (($_SESSION['user_externo'] ?? 'N') === 'S');
}

/** RFC vigente en sesión (para externos = username = RFC) */
function currentRFC(): ?string {
    return $_SESSION['user_rfc'] ?? $_SESSION['user_username'] ?? null;
}

/** Corta acceso si (externo) y el RFC del registro no coincide con el del usuario */
function abortIfExternoAndDifferentRFC(?string $registroRFC): void {
    if (isExterno() && strtoupper((string)$registroRFC) !== strtoupper((string)currentRFC())) {
        http_response_code(403);
        exit('No autorizado para ver este registro.');
    }
}

?>