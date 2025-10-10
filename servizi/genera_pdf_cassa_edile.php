<?php
// 1. Includi l'autoloader di Composer per caricare le librerie
require_once('../vendor/autoload.php');

// Usa le classi FPDI (per importare) e FPDF (per scrivere)
use setasign\Fpdi\Fpdi;

// 2. Verifica che la richiesta sia di tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non consentito.');
}

// --- Inizio della generazione PDF ---

// 3. Inizializza FPDI
$pdf = new Fpdi();

// Imposta il file template.
// ASSICURATI DI CREARE UN FILE PDF VUOTO CON QUESTO NOME E DI METTERLO NELLA STESSA CARTELLA
$templatePath = 'template_cassa_edile.pdf';

try {
    // Imposta il numero di pagine del template
    $pageCount = $pdf->setSourceFile($templatePath);
} catch (\Exception $e) {
    die('Errore: Impossibile trovare o leggere il file template PDF. Assicurati che il file "' . $templatePath . '" esista e sia leggibile.');
}

// Importa la prima pagina del template
$templateId = $pdf->importPage(1);

// Aggiungi una nuova pagina al documento usando le dimensioni del template
$pdf->addPage('P', $pdf->getTemplateSize($templateId));
$pdf->useTemplate($templateId);

// 4. Imposta il font e inizia a scrivere i dati
// (Puoi usare font standard come 'Arial', 'Helvetica', 'Times')
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0); // Colore testo: nero

// --- SCRITTURA DEI DATI SUL PDF ---
// QUI DEVI REGOLARE LE COORDINATE (X, Y) IN BASE AL TUO MODELLO
// L'origine (0,0) è l'angolo in alto a sinistra. Le unità sono in millimetri.

// Dati del sottoscrittore
$pdf->SetXY(40, 55); // Esempio: 40mm da sinistra, 55mm dall'alto
$pdf->Write(0, $_POST['nome'] ?? '');

$pdf->SetXY(120, 55);
$pdf->Write(0, $_POST['cognome'] ?? '');

$pdf->SetXY(45, 65);
$pdf->Write(0, $_POST['codiceFiscale'] ?? '');

$pdf->SetXY(130, 65);
$pdf->Write(0, date("d/m/Y", strtotime($_POST['dataNascita'] ?? '')));

$pdf->SetXY(50, 75);
$pdf->Write(0, $_POST['residenteVia'] ?? '');

$pdf->SetXY(30, 85);
$pdf->Write(0, $_POST['residenteCAP'] ?? '');

$pdf->SetXY(95, 85);
$pdf->Write(0, $_POST['residenteComune'] ?? '');

$pdf->SetXY(35, 95);
$pdf->Write(0, $_POST['email'] ?? '');

$pdf->SetXY(130, 95);
$pdf->Write(0, $_POST['cellulare'] ?? '');

// Dati bancari
$pdf->SetXY(55, 122);
$pdf->Write(0, $_POST['iban'] ?? '');

$pdf->SetXY(80, 132);
$pdf->Write(0, $_POST['intestatario'] ?? '');

// Consenso (scriviamo una 'X' se la checkbox è stata spuntata)
if (isset($_POST['privacyConsent'])) {
    $pdf->SetFont('Helvetica', 'B', 12); // Grassetto per la X
    $pdf->SetXY(22, 180); // Esempio: coordinate del quadratino della privacy
    $pdf->Write(0, 'X');
    $pdf->SetFont('Helvetica', '', 10); // Ripristina il font normale
}

// Luogo, Data e Firma
$pdf->SetXY(30, 198);
$pdf->Write(0, $_POST['luogoFirma'] ?? '');

$pdf->SetXY(85, 198);
$pdf->Write(0, date("d/m/Y", strtotime($_POST['dataFirma'] ?? '')));

$pdf->SetXY(140, 198);
$pdf->Write(0, $_POST['firma'] ?? '');


// 5. Finalizza e invia il PDF al browser
$nomeFile = 'richiesta_cassa_edile_' . str_replace(' ', '_', strtolower($_POST['cognome'] ?? '')) . '.pdf';

// 'I': Invia il file inline al browser.
// 'D': Forza il download del file.
$pdf->Output($nomeFile, 'I');

exit;