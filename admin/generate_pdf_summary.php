<?php

use setasign\Fpdi\Fpdi;

class PDFTemplateFiller {

    private $pdf;

    public function __construct() {
        $this->pdf = new Fpdi();
    }

    private function getTemplatePath($module_type, $prestazione) {
        $studi_prestazioni = [
            'asili_nido', 'centri_estivi', 'scuole_obbligo', 
            'superiori_iscrizione', 'universita_iscrizione'
        ];

        if ($module_type === 'modulo1_richieste' && in_array($prestazione, $studi_prestazioni)) {
            return dirname(__DIR__) . '/studi.pdf';
        }
        return dirname(__DIR__) . '/modulo.pdf';
    }

    private function fillModuloGenerico($data) {
        // Coordinate per modulo.pdf
        $this->pdf->SetFont('Helvetica', '', 9);
        
        // Dati lavoratore
        $this->pdf->SetXY(42, 53); $this->pdf->Write(0, $data['nome_completo'] ?? '');
        $this->pdf->SetXY(42, 59.5); $this->pdf->Write(0, $data['data_nascita'] ? date('d/m/Y', strtotime($data['data_nascita'])) : '');
        $this->pdf->SetXY(120, 59.5); $this->pdf->Write(0, $data['domicilio_a'] ?? '');
        $this->pdf->SetXY(48, 66); $this->pdf->Write(0, $data['codice_fiscale'] ?? '');
        $this->pdf->SetXY(42, 72.5); $this->pdf->Write(0, $data['via_piazza'] ?? '');
        $this->pdf->SetXY(150, 72.5); $this->pdf->Write(0, $data['cap'] ?? '');
        $this->pdf->SetXY(175, 72.5); $this->pdf->Write(0, $data['telefono'] ?? '');
        $this->pdf->SetXY(70, 79); $this->pdf->Write(0, $data['impresa_occupazione'] ?? '');
        $this->pdf->SetXY(175, 53); $this->pdf->Write(0, $data['pos_cassa_edile'] ?? '');

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
            $this->pdf->Image($data['firma_data'], 120, 245, 60, 15, 'PNG');
        }
        $this->pdf->SetXY(30, 250); $this->pdf->Write(0, $data['data_firma'] ? date('d/m/Y', strtotime($data['data_firma'])) : '');
    }

    private function process($data, $module_type) {
        $prestazione = key($data['prestazioni_decoded']);
        $templatePath = $this->getTemplatePath($module_type, $prestazione);

        if (!file_exists($templatePath)) {
            throw new Exception("File template non trovato: " . basename($templatePath));
        }

        $pageCount = $this->pdf->setSourceFile($templatePath);
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
    }

    public function generate($data, $module_type) {
        $this->process($data, $module_type);
        
        $pdf_dir = __DIR__ . '/../temp_pdf';
        if (!is_dir($pdf_dir)) { mkdir($pdf_dir, 0755, true); }
        $pdf_path = $pdf_dir . '/' . $data['form_name'] . '.pdf';
        
        $this->pdf->Output($pdf_path, 'F'); // Salva su file
        return $pdf_path;
    }

    public function generateAndOutputToBrowser($data, $module_type) {
        $this->process($data, $module_type);
        $this->pdf->Output('modulo_compilato_' . $data['form_name'] . '.pdf', 'I'); // Mostra nel browser
    }
}