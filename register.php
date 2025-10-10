<?php
session_start();
include("C_register.php");
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione Lavoratore</title>
    <!-- Icona rossa per la pagina (favicon) -->
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
        }
        .container {
            max-width: 900px;
            background-color: #fff;
            padding: 1rem;
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

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Servizi Fillea</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="servizi.php">Home servizi</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Registrazione</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <form method="POST" action="register.php" class="container mt-5 needs-validation" novalidate>

        
        <h3 class="text-center mb-3">INSERISCI I TUOI DATI</h3>
       
        <div class="mb-3">
            <label for="funzionario" class="form-label text-warning">SCEGLI UN FUNZIONARIO DI RIFERIMENTO</label>
            <select id="funzionario" name="funzionario" class="form-select" required>
                <option value="">Seleziona un funzionario</option>
                <?php foreach ($funzionari as $funzionario): ?>
                    <option value="<?php echo $funzionario['id']; ?>"><?php echo $funzionario['funzionario'] . ' (' . $funzionario['zona'] . ')'; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Per favore, seleziona un funzionario.</div>
        </div>

        <!-- Settore -->
        <div class="mb-1">
            <label for="settore" class="form-label text-warning">SETTORE</label>
            <select id="settore" name="settore" class="form-select" required >
                <option value="">Seleziona un settore</option>
                <option value="edilizia">Edilizia</option>
                <option value="legno">Legno</option>
                <option value="cemento">Cemento</option>
                <option value="lapidei">Lapidei</option>
                <option value="manufatti">Manufatti</option>
                <option value="laterizi">Laterizi</option>
            </select>
            <div class="invalid-feedback">Per favore, seleziona un settore.</div>

        </div>


        <!-- Nome e Cognome -->
        <div class="row">
            <div class="col-md-6 mb-2">
                <label for="cognome" class="form-label text-warning">COGNOME</label>
                <input type="text" id="cognome" name="cognome" class="form-control" required>
                <div class="invalid-feedback">Per favore, specifica il cognome.</div>
            </div>
            <div class="col-md-6 mb-2">
                <label for="nome" class="form-label text-warning">NOME</label>
                <input type="text" id="nome" name="nome" class="form-control" required >
                <div class="invalid-feedback">Per favore, specifica il nome.</div>
            </div>
        </div>

        <!-- Data di Nascita e Codice Fiscale -->
        <div class="row">
            <div class="col-md-6 mb-2">
                <label for="data_nascita" class="form-label text-warning">DATA DI NASCITA*</label>
                <input type="date" id="data_nascita" name="data_nascita" class="form-control" required>
                <div class="invalid-feedback">Per favore, specifica la data di nascita.</div>
            </div>
            <div class="col-md-6 mb-2">
                <label for="codfisc" class="form-label text-warning">CODICE FISCALE</label>
                <input type="text" id="codfisc" name="codfisc" class="form-control" required>
                <div class="invalid-feedback">Per favore, specifica il codice fiscale.</div>
            </div>
        </div>

        <!-- Telefono e Email -->
        <div class="row">
            <div class="col-md-4 mb-2">
                <label for="telefono" class="form-label text-warning">TELEFONO CELLULARE*</label>
                <input type="tel" id="telefono" name="telefono" class="form-control" required>
                <div class="invalid-feedback">Per favore, specifica il telefono.</div>
            </div>
            <div class="col-md-4 mb-2">
                <label for="email" class="form-label text-warning">EMAIL</label>
                <input type="email" id="email" name="email" class="form-control" required>
                <div class="invalid-feedback">Per favore, specifica una mail valida.</div>
            </div>
            <div class="col-md-4 mb-2">
                <label for="email1" class="form-label text-warning">Verifica EMAIL</label>
                <input type="email" id="email1" name="email1" class="form-control" required>
                <div class="invalid-feedback">Specifica la mail.</div>
            </div>

        </div>
        <div id='div_wait' style='display:none'><i class='fas fa-spinner fa-spin'></i></div>
        <div id='div_resp'></div>

        <div class="d-grid mt-3">
            <button type="submit" class="btn btn-primary btn-lg">CLICCA PER INIZIARE</button>
        </div>
        <div class="text-center mt-1">
            <p class="text-warning">IN GIALLO I CAMPI OBBLIGATORI</p>
        </div>        
        <!-- Contenitore per il Toast (Notifica) - Posizionato in alto per visibilità su mobile -->
        <div id="toast-message" class="fixed top-5 right-5 p-4 rounded-lg shadow-xl text-white bg-warning transition-all duration-300 opacity-0 transform -translate-y-20 z-[1001]">
            <!-- Il messaggio verrà inserito qui -->
        </div>

    

    </form>



    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    


    <!--script js utente-->
    <script src="register.js?ver=<?= time() ?>"></script>

</body>
</html>
