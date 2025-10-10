<?php

/**
 * Un semplice script per ricevere errori dal client (JavaScript)
 * e scriverli in un file di log sul server.
 */

// Rispondiamo subito per non bloccare il client.
http_response_code(204); // 204 No Content

$log_file = 'client_errors.log';
$timestamp = date('Y-m-d H:i:s');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data && isset($data['error'])) {
    $log_message = "[$timestamp] - CLIENT-SIDE ERROR\n";
    $log_message .= "STEP: " . ($data['step'] ?? 'N/A') . "\n";
    $log_message .= "ERROR: " . json_encode($data['error'], JSON_PRETTY_PRINT) . "\n";
    $log_message .= "OPTIONS: " . json_encode($data['options'], JSON_PRETTY_PRINT) . "\n\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}