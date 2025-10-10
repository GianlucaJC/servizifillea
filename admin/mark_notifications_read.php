<?php
session_start();

// Proteggi lo script: solo l'admin loggato può eseguire questa azione.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato.']);
    exit;
}

// Includi il file di connessione al database
include_once('../database.php');

try {
    // Aggiorna lo stato delle notifiche da 'nuovo' a 'letto'
    $pdo1 = Database::getInstance('fillea');
    $sql = "UPDATE `fillea-app`.`richieste_master` SET is_new = 0 WHERE is_new = 1";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute();

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Errore del database: ' . $e->getMessage()]);
}