<?php
session_start();
include("C_register.php");
?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione Lavoratore</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=R">
    <link rel="apple-touch-icon" href="https://placehold.co/192x192/d0112b/ffffff?text=R">
    <meta name="theme-color" content="#d0112b">    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #d0112b;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        .container {
            max-width: 900px;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        /* Stili per il Toast (notifica) */
        #toast-message {
            transition: opacity 0.3s, transform 0.3s;
        }
        .toast-visible {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    </style>
</head>
<body>

    <div class="container p-4 p-lg-5">
        <form method="POST" action="register.php" class="needs-validation" novalidate autocomplete="off">
            
            <div class="text-center mb-4">
                <img src="logo.jpg" alt="Logo Fillea CGIL Firenze" class="img-fluid mx-auto" style="max-width: 300px;">
            </div>
            
            <h3 class="text-center mb-4 fw-bold">Crea il tuo Account</h3>
            <p class="text-center text-muted small mb-4">Compila i campi per registrarti e accedere ai servizi.</p>
        
            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-industry me-2"></i>Settore</span>
                <select id="settore" name="settore" class="form-select" required>
                    <option value="" disabled selected>Scegli il tuo settore</option>
                    <option value="edilizia">Edilizia</option>
                    <option value="legno">Legno</option>
                    <option value="cemento">Cemento</option>
                    <option value="lapidei">Lapidei</option>
                    <option value="manufatti">Manufatti</option>
                    <option value="laterizi">Laterizi</option>
                </select>
                <div class="invalid-feedback">Per favore, seleziona un settore.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-user me-2"></i>Cognome</span>
                <input type="text" id="cognome" name="cognome" class="form-control" placeholder="Il tuo cognome" required oninput="this.value = this.value.toUpperCase()">
                <div class="invalid-feedback">Per favore, specifica il cognome.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-user me-2"></i>Nome</span>
                <input type="text" id="nome" name="nome" class="form-control" placeholder="Il tuo nome" required oninput="this.value = this.value.toUpperCase()">
                <div class="invalid-feedback">Per favore, specifica il nome.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-calendar-alt me-2"></i>Nato il</span>
                <input type="date" id="data_nascita" name="data_nascita" class="form-control" required>
                <div class="invalid-feedback">Per favore, specifica la data di nascita.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-id-card me-2"></i>C. Fiscale</span>
                <input type="text" id="codfisc" name="codfisc" class="form-control text-uppercase" placeholder="Il tuo codice fiscale" required>
                <div id="codfisc-feedback" class="invalid-feedback">Codice fiscale non valido.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-mobile-alt me-2"></i>Telefono</span>
                <input type="tel" id="telefono" name="telefono" class="form-control" placeholder="Il tuo numero di cellulare" required>
                <div class="invalid-feedback">Per favore, specifica il telefono.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-envelope me-2"></i>Email</span>
                <input type="email" id="email" name="email" class="form-control" placeholder="La tua email" required>
                <div class="invalid-feedback">Per favore, specifica una mail valida.</div>
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" style="width: 130px;"><i class="fas fa-envelope-circle-check me-2"></i>Ripeti Email</span>
                <input type="email" id="email1" name="email1" class="form-control" placeholder="Ripeti la tua email" required>
                <div class="invalid-feedback">Le email non coincidono.</div>
            </div>

            <div id='div_wait' class="text-center my-3" style='display:none;'><i class='fas fa-spinner fa-spin fa-2x'></i></div>
            <div id='div_resp'></div>

            <!-- Sezione Password (inizialmente nascosta) -->
            <div id="password-section" class="mt-4 pt-4 border-top" style="display: none;">
                <h4 class="text-center mb-3 fw-bold">Crea la tua Password</h4>
                <div class="input-group mb-3">
                    <span class="input-group-text" style="width: 130px;"><i class="fas fa-lock me-2"></i>Password</span>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="invalid-feedback">La password deve essere di almeno 8 caratteri.</div>
                </div>
                <div class="input-group mb-3">
                    <span class="input-group-text" style="width: 130px;"><i class="fas fa-lock me-2"></i>Ripeti Pass.</span>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirm">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="invalid-feedback">Le password non coincidono.</div>
                </div>
            </div>
            <!-- Fine Sezione Password -->

            <div class="d-grid gap-2 mt-4">
                <button type="button" id="verify-btn" class="btn btn-primary btn-lg fw-bold">Verifica se puoi registrare un account</button>
                <button type="submit" id="register-btn" class="btn btn-success btn-lg fw-bold" style="display: none;">Crea il mio Account</button>
                <a href="login.php" class="btn btn-secondary">Hai già un account? Accedi</a>
            </div>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    

    <!-- Script per validazione codice fiscale (deve essere prima di register.js) -->
    <script src="cf-validator.js"></script>
    <!-- Script JS utente -->
    <script src="register.js?ver=<?= time() ?>"></script>

</body>
</html>
