<?php
include_once __DIR__ . '/../../session_config.php'; // Includi la configurazione della sessione
session_start(); // Avvia la sessione DOPO aver impostato i parametri

// 1. Recupera e verifica il token per la sicurezza
$token = $_GET['token'] ?? '';
$is_user_logged_in = false;

if (!empty($token)) {
    // Il file database.php si trova due livelli sopra
    include_once("../../database.php");
    $pdo1 = Database::getInstance('fillea');

    $sql = "SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([$token]);

    if ($stmt->fetch()) {
        $is_user_logged_in = true;
    }
    $pdo1 = null;
}

// 2. Se l'utente non è loggato o la richiesta non è POST, reindirizza
if (!$is_user_logged_in || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../login.php");
    exit;
}

// 3. Includi le librerie FPDF e FPDI
// NOTA: Assicurati che queste librerie siano presenti nel tuo progetto.
// Includo l'autoloader di Composer che si trova nella root del progetto.
// Il percorso `../../` risale da `servizi/moduli/` alla root `servizifillea/`.
require_once('../../vendor/autoload.php');

use setasign\Fpdi\Fpdi;

// 4. Recupera i dati dal form
$studente_nome_cognome = $_POST['studente_nome_cognome'] ?? '';
$studente_data_nascita = $_POST['studente_data_nascita'] ?? '';
$studente_luogo_nascita = $_POST['studente_luogo_nascita'] ?? '';
$studente_codice_fiscale = $_POST['studente_codice_fiscale'] ?? '';
$studente_indirizzo = $_POST['studente_indirizzo'] ?? '';
$studente_cap = $_POST['studente_cap'] ?? '';
$studente_comune = $_POST['studente_comune'] ?? '';

$lavoratore_nome_cognome = $_POST['lavoratore_nome_cognome'] ?? '';
$lavoratore_data_nascita = $_POST['lavoratore_data_nascita'] ?? '';
$lavoratore_codice_cassa = $_POST['lavoratore_codice_cassa'] ?? '';
$lavoratore_telefono = $_POST['lavoratore_telefono'] ?? '';
$lavoratore_impresa = $_POST['lavoratore_impresa'] ?? '';

$iban = $_POST['iban'] ?? '';
$intestatario_conto = $_POST['intestatario_conto'] ?? '';

$luogo_firma = $_POST['luogo_firma'] ?? '';
$data_firma = $_POST['data_firma'] ?? '';
$firma_richiedente = $_POST['firma_richiedente'] ?? '';

// ... recupera gli altri campi come 'prestazione[]', 'altri_redditi', etc.

// 5. Genera il PDF
try {
    $pdf = new Fpdi();

    // Imposta il file del template
    // NOTA: Assicurati di avere un file 'template_modulo1.pdf' in questa stessa cartella.
    $pageCount = $pdf->setSourceFile('template_modulo1.pdf');
    $templateId = $pdf->importPage(1);

    $pdf->addPage();
    $pdf->useTemplate($templateId, ['adjustPageSize' => true]);

    // Imposta il font, la dimensione e il colore
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    // Scrivi i dati sul PDF.
    // NOTA: Dovrai aggiustare le coordinate (X, Y) in base al tuo template!
    // $pdf->SetXY(x, y);
    // $pdf->Write(0, 'Testo da scrivere');

    // Esempio per il nome dello studente (coordinate ipotetiche)
    $pdf->SetXY(50, 60);
    $pdf->Write(0, utf8_decode($studente_nome_cognome));

    // Esempio per il nome del lavoratore (coordinate ipotetiche)
    $pdf->SetXY(50, 105);
    $pdf->Write(0, utf8_decode($lavoratore_nome_cognome));
    
    // Esempio per l'IBAN (coordinate ipotetiche)
    $pdf->SetXY(35, 200);
    $pdf->Write(0, utf8_decode($iban));

    // ... continua a posizionare e scrivere tutti gli altri dati ...

    // 6. Invia il PDF al browser
    $nome_file_output = 'richiesta_contributo_studio.pdf';
    $pdf->Output('D', $nome_file_output); // 'D' forza il download

} catch (Exception $e) {
    // Gestisci eventuali errori nella creazione del PDF
    error_log($e->getMessage());
    die('Si è verificato un errore durante la generazione del PDF. Si prega di riprovare.');
}

?>