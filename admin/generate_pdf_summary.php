<?php

// Includi l'autoloader di Composer per caricare le librerie necessarie (FPDI, FPDI-FPDF)
require_once __DIR__ . '/../vendor/autoload.php';
use setasign\Fpdi\Fpdi;

class PDFTemplateFiller {

    private $pdf;

    public function __construct() {
        $this->pdf = new Fpdi();
        // Disabilita i margini e l'interruzione di pagina automatica per avere il pieno controllo sul posizionamento.
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetAutoPageBreak(false);
    }

    private function getTemplatePath($module_type, $prestazione) {
        $studi_prestazioni = [
            'asili_nido', 'centri_estivi', 'scuole_obbligo', 
            'superiori_iscrizione', 'universita_iscrizione'
        ];

        if ($module_type === 'modulo1_richieste' && in_array($prestazione, $studi_prestazioni)) {
            return dirname(__DIR__) . '/studi.pdf';
        }
        // Aggiunta per il nuovo modulo di autocertificazione
        if ($module_type === 'autocertificazione_stato_famiglia') {
            return dirname(__DIR__) . '/studi.pdf'; // Usa la pagina 2 di questo file
        }
        // Aggiunta per il nuovo modulo di dichiarazione frequenza
        if ($module_type === 'dichiarazione_frequenza') {
            return dirname(__DIR__) . '/studi.pdf'; // Usa la pagina 1 di questo file come template
        }
        return dirname(__DIR__) . '/modulo.pdf';
    }

    private function fillModuloGenerico($data) {
        // Coordinate per modulo.pdf
        $this->pdf->SetFont('Helvetica', '', 9);
        
        // Dati lavoratore
        $this->pdf->SetXY(32, 37); $this->pdf->Write(0, $data['nome_completo'] ?? '');
        $this->pdf->SetXY(168, 37); $this->pdf->Write(0, $data['pos_cassa_edile'] ?? '');
        $this->pdf->SetXY(21, 43); $this->pdf->Write(0, $data['data_nascita'] ? date('d/m/Y', strtotime($data['data_nascita'])) : '');
        $this->pdf->SetXY(120, 59.5); $this->pdf->Write(0, $data['domicilio_a'] ?? '');
        $this->pdf->SetXY(48, 66); $this->pdf->Write(0, $data['codice_fiscale'] ?? '');
        $this->pdf->SetXY(42, 72.5); $this->pdf->Write(0, $data['via_piazza'] ?? '');
        $this->pdf->SetXY(150, 72.5); $this->pdf->Write(0, $data['cap'] ?? '');
        $this->pdf->SetXY(175, 72.5); $this->pdf->Write(0, $data['telefono'] ?? '');
        $this->pdf->SetXY(70, 79); $this->pdf->Write(0, $data['impresa_occupazione'] ?? '');
        

        // Checkbox Prestazione
        $prestazione = key($data['prestazioni_decoded']);
        $coords = [
            'premio_giovani' => [19.5, 101],
            'premio_matrimoniale' => [19.5, 108.5],
            'bonus_nascita' => [19.5, 116],
            'donazioni_sangue' => [19.5, 126],
            'contributo_disabilita' => [19.5, 133.5],
            'insinuazione_passivo' => [19.5, 146], // Da verificare se esiste
            'post_licenziamento' => [19.5, 153.5],
            'contributo_affitto' => [19.5, 163.5],
            'contributo_sfratto' => [19.5, 181],
            'permesso_soggiorno' => [19.5, 198.5],
            'attivita_sportive' => [19.5, 213],
        ];
        if (isset($coords[$prestazione])) {
            $this->pdf->SetXY($coords[$prestazione][0], $coords[$prestazione][1]);
            $this->pdf->Write(0, 'X');
        }

        // Firma
        if (!empty($data['firma_data'])) {
            $this->pdf->Image($data['firma_data'], 120, 245, 60, 15, 'PNG');
        }
        $this->pdf->SetXY(30, 250); $this->pdf->Write(0, $data['data_firma'] ? date('d/m/Y', strtotime($data['data_firma'])) : '');
    }

    private function fillModuloStudi($data) {
        // Coordinate per studi.pdf (ipotetiche, da verificare)
        $this->pdf->SetFont('Helvetica', '', 9);

        // Dati studente
        $this->pdf->SetXY(50, 60); $this->pdf->Write(0, $data['studente_nome_cognome'] ?? '');
        $this->pdf->SetXY(50, 65); $this->pdf->Write(0, $data['studente_codice_fiscale'] ?? '');
        $this->pdf->SetXY(150, 65); $this->pdf->Write(0, $data['studente_data_nascita'] ? date('d/m/Y', strtotime($data['studente_data_nascita'])) : '');

        // Dati lavoratore
        $this->pdf->SetXY(50, 80); $this->pdf->Write(0, $data['lavoratore_nome_cognome'] ?? '');
        $this->pdf->SetXY(50, 85); $this->pdf->Write(0, $data['lavoratore_codice_cassa'] ?? '');
        $this->pdf->SetXY(150, 85); $this->pdf->Write(0, $data['lavoratore_impresa'] ?? '');

        // Dati pagamento
        $this->pdf->SetXY(40, 150); $this->pdf->Write(0, $data['iban'] ?? '');
        $this->pdf->SetXY(40, 155); $this->pdf->Write(0, $data['intestatario_conto'] ?? '');

        // Checkbox Prestazione (ipotetico)
        $prestazione = key($data['prestazioni_decoded']);
        $coords = [
            'centri_estivi' => [20, 100],
            'scuole_obbligo' => [20, 110], // Elementari e medie
            'superiori_iscrizione' => [20, 120],
            'universita_iscrizione' => [20, 130],
            'asili_nido' => [20, 140],
        ];
        if (isset($coords[$prestazione])) {
            $this->pdf->SetXY($coords[$prestazione][0], $coords[$prestazione][1]);
            $this->pdf->Write(0, 'X');
        }

        // Firma
        if (!empty($data['firma_data'])) {
            $this->pdf->Image($data['firma_data'], 132, 285, 60, 15, 'PNG');
        }
        $this->pdf->SetXY(25, 292); $this->pdf->Write(0, $data['data_firma'] ? date('d/m/Y', strtotime($data['data_firma'])) : '');


        
    }

    private function fillModuloAutocertificazione($data) {
        // Usa la pagina 2 del template 'studi.pdf'
        $this->pdf->SetFont('Helvetica', '', 9);

        // Dati sottoscrittore
        $this->pdf->SetXY(38, 56); $this->pdf->Write(0, $data['sottoscrittore_nome_cognome'] ?? '');
        $this->pdf->SetXY(40, 62); $this->pdf->Write(0, $data['sottoscrittore_luogo_nascita'] ?? '');
        $this->pdf->SetXY(115, 62); $this->pdf->Write(0, isset($data['sottoscrittore_data_nascita']) && $data['sottoscrittore_data_nascita'] ? date('d/m/Y', strtotime($data['sottoscrittore_data_nascita'])) : '');
        $this->pdf->SetXY(50, 68); $this->pdf->Write(0, $data['sottoscrittore_residenza_comune'] ?? '');
        $this->pdf->SetXY(45, 74); $this->pdf->Write(0, $data['sottoscrittore_residenza_indirizzo'] ?? '');

        // Membri famiglia
        // Correzione: $data['membri_famiglia'] è già un array quando viene passato dal modulo di salvataggio.
        // Non è necessario un json_decode.
        if (isset($data['membri_famiglia']) && is_string($data['membri_famiglia'])) {
            $membri = json_decode($data['membri_famiglia'], true);
        } else {
            $membri = $data['membri_famiglia'] ?? [];
        }
        if (is_array($membri)) {
            $y = 101; // Posizione Y iniziale della prima riga della tabella
            foreach ($membri as $membro) {
                $this->pdf->SetXY(25, $y); $this->pdf->Write(0, $membro['nome_cognome'] ?? '');
                $this->pdf->SetXY(80, $y); $this->pdf->Write(0, isset($membro['data_nascita']) && $membro['data_nascita'] ? date('d/m/Y', strtotime($membro['data_nascita'])) : '');
                $this->pdf->SetXY(115, $y); $this->pdf->Write(0, $membro['luogo_nascita'] ?? '');
                $this->pdf->SetXY(160, $y); $this->pdf->Write(0, $membro['parentela'] ?? '');
                $y += 6; // Incrementa Y per la riga successiva
            }
        }

        // Data, luogo e firma
        $this->pdf->SetXY(45, 168); $this->pdf->Write(0, (isset($data['data_firma']) && $data['data_firma'] ? date('d/m/Y', strtotime($data['data_firma'])) : '') . ' ' . ($data['luogo_firma'] ?? ''));
        if (!empty($data['firma_data'])) { $this->pdf->Image($data['firma_data'], 60, 175, 60, 15, 'PNG'); }
    }

    private function fillModuloDichiarazioneFrequenza($data) {
        // Usa la pagina 1 del template 'studi.pdf'
        $this->pdf->SetFont('Helvetica', '', 9);

        // Dati sottoscrittore
        $this->pdf->SetXY(30, 50); $this->pdf->Write(0, $data['sottoscrittore_nome_cognome'] ?? '');
        $this->pdf->SetXY(30, 55); $this->pdf->Write(0, $data['sottoscrittore_luogo_nascita'] ?? '');
        $this->pdf->SetXY(100, 55); $this->pdf->Write(0, isset($data['sottoscrittore_data_nascita']) ? date('d/m/Y', strtotime($data['sottoscrittore_data_nascita'])) : '');
        $this->pdf->SetXY(30, 60); $this->pdf->Write(0, $data['sottoscrittore_residenza_comune'] ?? '');
        $this->pdf->SetXY(30, 65); $this->pdf->Write(0, $data['sottoscrittore_residenza_indirizzo'] ?? '');

        // Qualità dichiarante e dati minore
        $qualita = $data['qualita_dichiarante'] ?? '';
        if ($qualita === 'Altro') {
            $qualita .= ' (' . ($data['qualita_altro_specifica'] ?? '') . ')';
        }
        $this->pdf->SetXY(50, 80); $this->pdf->Write(0, $qualita);

        if ($qualita !== 'Dichiarante') {
            $this->pdf->SetXY(30, 85); $this->pdf->Write(0, $data['minore_nome_cognome'] ?? '');
            $this->pdf->SetXY(30, 90); $this->pdf->Write(0, $data['minore_luogo_nascita'] ?? '');
            $this->pdf->SetXY(100, 90); $this->pdf->Write(0, isset($data['minore_data_nascita']) ? date('d/m/Y', strtotime($data['minore_data_nascita'])) : '');
        }

        // Ciclo studi
        $ciclo_coords = [
            'primaria' => [20, 110],
            'secondaria_primo' => [20, 115],
            'secondaria_secondo' => [20, 120],
            'superiore' => [20, 125],
        ];
        if (isset($ciclo_coords[$data['ciclo_studi'] ?? ''])) {
            $this->pdf->SetXY($ciclo_coords[$data['ciclo_studi']][0], $ciclo_coords[$data['ciclo_studi']][1]);
            $this->pdf->Write(0, 'X');
        }
    }

    private function process($data, $module_type) {
        // Per l'autocertificazione non c'è una prestazione specifica, per gli altri moduli sì.
        $prestazione = null;
        if (!in_array($module_type, ['autocertificazione_stato_famiglia', 'dichiarazione_frequenza'])) {
            $prestazione = isset($data['prestazioni_decoded']) ? key($data['prestazioni_decoded']) : null;
        }
        $templatePath = $this->getTemplatePath($module_type, $prestazione);

        if (!file_exists($templatePath)) {
            throw new Exception("File template non trovato: " . basename($templatePath));
        }

        $pageCount = $this->pdf->setSourceFile($templatePath);

        // Caso speciale per l'autocertificazione che usa solo la pagina 2
        if ($module_type === 'autocertificazione_stato_famiglia') {
            $templateId = $this->pdf->importPage(2);
            $this->pdf->AddPage();
            $this->pdf->useTemplate($templateId, ['adjustPageSize' => true]);
            $this->fillModuloAutocertificazione($data);
            return; // Termina qui per questo modulo
        }

        // Caso speciale per la dichiarazione di frequenza che usa solo la pagina 1
        if ($module_type === 'dichiarazione_frequenza') {
            $templateId = $this->pdf->importPage(1);
            $this->pdf->AddPage();
            $this->pdf->useTemplate($templateId, ['adjustPageSize' => true]);
            $this->fillModuloDichiarazioneFrequenza($data);
            return; // Termina qui per questo modulo
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $this->pdf->importPage($pageNo);
            $this->pdf->AddPage();
            $this->pdf->useTemplate($templateId, ['adjustPageSize' => true]);

            // Compila i dati solo sulla prima pagina
            if ($pageNo == 1) {
                if ($module_type === 'modulo1_richieste') {
                    $this->fillModuloStudi($data);
                } else {
                    $this->fillModuloGenerico($data);
                }
            }
        }

        // --- INIZIO: Logica per allegare l'autocertificazione dello stato di famiglia (per modulo1 e modulo2) ---
        $autocert_pdf_basename = 'autocert_' . ($data['form_name'] ?? '');
        $autocert_pdf_path = dirname(__DIR__) . '/servizi/moduli/uploads/' . $autocert_pdf_basename . '.pdf';

        if (file_exists($autocert_pdf_path)) {
            try {
                // Importa la pagina dal PDF dell'autocertificazione generato
                $pageCountAutocert = $this->pdf->setSourceFile($autocert_pdf_path);
                $templateIdAutocert = $this->pdf->importPage(1);
                $this->pdf->AddPage();
                $this->pdf->useTemplate($templateIdAutocert, ['adjustPageSize' => true]);
            } catch (Exception $e) {
                // Se c'è un errore nell'importazione, lo logghiamo ma non blocchiamo la generazione del PDF principale.
                error_log("Impossibile allegare l'autocertificazione PDF '{$autocert_pdf_path}': " . $e->getMessage());
            }
        }

        // --- INIZIO: Logica per allegare la dichiarazione di frequenza (per modulo1 e modulo2) ---
        $dich_freq_pdf_basename = 'dich_frequenza_' . ($data['form_name'] ?? '');
        $dich_freq_pdf_path = dirname(__DIR__) . '/servizi/moduli/uploads/' . $dich_freq_pdf_basename . '.pdf';

        if (file_exists($dich_freq_pdf_path)) {
            try {
                // Importa la pagina dal PDF della dichiarazione generato
                $pageCountDich = $this->pdf->setSourceFile($dich_freq_pdf_path);
                $templateIdDich = $this->pdf->importPage(1);
                $this->pdf->AddPage();
                $this->pdf->useTemplate($templateIdDich, ['adjustPageSize' => true]);
            } catch (Exception $e) {
                // Se c'è un errore nell'importazione, lo logghiamo ma non blocchiamo la generazione del PDF principale.
                error_log("Impossibile allegare la dichiarazione di frequenza PDF '{$dich_freq_pdf_path}': " . $e->getMessage());
            }
        }

        // --- INIZIO: Logica per allegare sempre il PDF della privacy come ultima pagina ---
        $privacy_pdf_path = dirname(__DIR__) . '/privacy.pdf';
        if (file_exists($privacy_pdf_path)) {
            try {
                // Importa la pagina dal PDF della privacy
                $pageCountPrivacy = $this->pdf->setSourceFile($privacy_pdf_path);
                $templateIdPrivacy = $this->pdf->importPage(1);
                $this->pdf->AddPage();
                $this->pdf->useTemplate($templateIdPrivacy, ['adjustPageSize' => true]);
            } catch (Exception $e) {
                // Se c'è un errore nell'importazione, lo logghiamo ma non blocchiamo la generazione del PDF principale.
                error_log("Impossibile allegare il PDF della privacy '{$privacy_pdf_path}': " . $e->getMessage());
            }
        }
    }

    public function generate($data, $module_type, $custom_basename = null) {
        $this->process($data, $module_type);
        
        // Salva i PDF generati automaticamente nella cartella uploads per coerenza
        $pdf_dir = __DIR__ . '/../servizi/moduli/uploads';
        if (!is_dir($pdf_dir)) { mkdir($pdf_dir, 0755, true); }
        
        // Usa un nome base personalizzato se fornito, altrimenti usa il form_name
        $basename = $custom_basename ?? ($data['form_name'] ?? 'documento_' . uniqid());
        $pdf_path = $pdf_dir . '/' . $basename . '.pdf';

        $this->pdf->Output($pdf_path, 'F'); // Salva su file
        return $pdf_path;
    }

    public function generateAndOutputToBrowser($data, $module_type) {
        $this->process($data, $module_type);
        $this->pdf->Output('modulo_compilato_' . $data['form_name'] . '.pdf', 'I'); // Mostra nel browser
    }
}