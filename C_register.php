<?php
    // Includi il file di configurazione del database
    include_once 'database.php'; 
    
    // Ottieni l'istanza del database 'fillea' per recuperare i funzionari
    $pdo_fillea = Database::getInstance('fillea');
    $stmt_funzionari = $pdo_fillea->query("SELECT id, funzionario, zona FROM `fillea-app`.funzionari WHERE is_super_admin = 0 ORDER BY funzionario ASC");
    $funzionari = $stmt_funzionari->fetchAll(PDO::FETCH_ASSOC);
    // La connessione non viene chiusa qui per permettere ad altri script di usarla
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
        //intercettazione chiamate AJAX
        $cognome = $_POST['cognome'] ?? '';
        $cognome = trim($cognome);
        $nome = $_POST['nome'] ?? '';
        $nome = trim($nome);
        $codfisc = $_POST['codfisc'] ?? '';
        $codfisc = strtoupper(trim($codfisc)); // Converti in maiuscolo per coerenza
        $nominativo=strtoupper(trim("$cognome $nome"));
        
        if (strlen($codfisc)==0) $codfisc="xxx!!!";
        $data_nascita = $_POST['data_nascita'] ?? '';

        // Ottieni l'istanza del database 'anagrafe'
        $pdo = Database::getInstance('anagrafe');

        // Query SQL
        $sql="SELECT sindacato FROM anagrafe.t2_tosc_a WHERE (nome = ? AND attivi = 'S' AND CAST(datanasc AS DATE) = ?) OR codfisc = ? LIMIT 0,1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nominativo, $data_nascita, $codfisc]);
        $check_nome = $stmt->fetch(PDO::FETCH_ASSOC);
        $pdo = null; // Don't close the connection here if it's shared

        $resp=array();
        $resp['header']="OK";
        $resp['info']=$check_nome;
        print json_encode($resp);
        exit;
    }
?>