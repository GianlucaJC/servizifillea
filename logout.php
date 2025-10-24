<?php
// Avvia la sessione per poterla manipolare.
session_start();
// Svuota l'array di sessione.
$_SESSION = array();

// Se si desidera distruggere completamente la sessione, cancellare anche il cookie di sessione.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// Elimina il cookie "Ricordami" in modo robusto.
// È FONDAMENTALE usare gli stessi parametri con cui il cookie è stato creato in login.php
// per garantirne la cancellazione corretta dal browser.
if (isset($_COOKIE['auth_token'])) {
    unset($_COOKIE['auth_token']); // Rimuove la variabile per lo script corrente
    setcookie('auth_token', '', [
        'expires' => time() - 3600, // Imposta una data di scadenza nel passato
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']), // Deve corrispondere all'impostazione di login
        'httponly' => true, // Deve corrispondere all'impostazione di login
        'samesite' => 'Lax' // Deve corrispondere all'impostazione di login
    ]);
}

// Infine, distrugge la sessione.
session_destroy();
// Reindirizza l'utente alla pagina di login.
header("Location: servizi.php");
exit;