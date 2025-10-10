<?php
// Avvia la sessione per poterla manipolare.
session_start();

/*
    // Svuota l'array di sessione.
    $_SESSION = array();

    // Se si desidera distruggere completamente la sessione, cancellare anche il cookie di sessione.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    // Infine, distrugge la sessione.
    session_destroy();
*/

// Reindirizza l'utente alla pagina di login.
header("Location: servizi.php");
exit;