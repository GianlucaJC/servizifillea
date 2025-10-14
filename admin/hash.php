<?php
// Inserisci qui la password che vuoi usare
$password_da_criptare = 'test2025!';

// Genera l'hash sicuro
$hash_generato = password_hash($password_da_criptare, PASSWORD_DEFAULT);

// Stampa l'hash a schermo in un formato facile da copiare
echo "<h1>Hash Generato</h1>";
echo "<p>Copia e incolla questa stringa nel tuo comando SQL UPDATE:</p>";
echo "<pre style='background-color:#f0f0f0; padding:10px; border:1px solid #ccc; font-size:1.2em;'>" . htmlspecialchars($hash_generato) . "</pre>";
?>
