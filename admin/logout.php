<?php
session_start();

// Rimuove le variabili di sessione specifiche dell'admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);

// Distrugge la sessione
session_destroy();

// Reindirizza alla pagina di login dell'admin
header('Location: index.php');
exit;