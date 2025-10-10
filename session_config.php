<?php

/**
 * Configurazione centralizzata della sessione per garantire la persistenza.
 * Questo file deve essere incluso prima di ogni chiamata a session_start().
 */

// Durata del cookie di sessione in secondi.
// Impostiamo una durata lunga (es. 30 giorni) per evitare che scada con lo standby del telefono.
$cookie_lifetime = 30 * 24 * 60 * 60; // 30 giorni

// Imposta i parametri del cookie di sessione prima di avviarla.
session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']), // Invia il cookie solo su HTTPS in produzione
    'httponly' => true, // Impedisce l'accesso al cookie tramite JavaScript
    'samesite' => 'Lax' // Protezione base contro attacchi CSRF
]);