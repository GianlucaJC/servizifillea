const toast = $('#toast-message'); // Riferimento al toast
// Example starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  //'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  var forms = document.querySelectorAll('.needs-validation')

  // Loop over them and prevent submission
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()        
          form.classList.add('was-validated') // Mostra la validazione Bootstrap solo se fallisce
        }  else {
          // Se la validazione base è OK, previeni l'invio e fai i controlli custom
          event.preventDefault()
          event.stopPropagation()

          mail=$("#email").val() 
          mail1=$("#email1").val()

          if (mail!=mail1) {
            showToast(`Mail e verifica mail non coincidono`)
            // Non procedere oltre se le mail non corrispondono
            return; 
          }
          // Se tutto è ok, procedi con la ricerca
          cerca_nomi()
        }
      }, false)
    })

})()

$(document).ready( function () {
})

  // Funzione Semplice di Notifica (Toast)
  function showToast(message) {
      // 1. Aggiorna il contenuto del messaggio
      toast.text(message);
      
      // 2. Mostra il toast con animazione (utilizzando la classe CSS)
      toast.addClass('toast-visible');
      
      // 3. Nascondi il toast dopo 3 secondi
      setTimeout(() => {
          toast.removeClass('toast-visible');
      }, 3000);
  }

function cerca_nomi() {

    nome=$("#nome").val()
    cognome=$("#cognome").val()
    data_nascita=$("#data_nascita").val()    
    codfisc=$("#codfisc").val()

    $("#div_resp").empty()
    if (cognome.length >= 2) { 
      $("#div_wait").show();
      clearTimeout(this.debounceTimeout);
      this.debounceTimeout = setTimeout(() => {    
        fetch("register.php", {
            method: 'post',
            headers: {
              "Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: "cognome="+cognome+"&nome="+nome+"&data_nascita="+data_nascita+"&codfisc="+codfisc,
        })
        .then(response => {
            if (response.ok) {
              return response.json();
            }
        })
        .then(resp=>{
            if (resp.header=="OK") {
                $("#div_wait").hide(120);
                html=""
                if (resp.info) {
                  obj=resp.info
                  colo_sind="secondary"

                  if (obj.sindacato=="0") {sind=" Non iscritto al sindacato";colo_sind="warning";}
                  if (obj.sindacato=="1") {sind="Fillea CGIL";colo_sind="danger";}
                  if (obj.sindacato=="2") {sind="Filca CISL";colo_sind="success";}
                  if (obj.sindacato=="3") {sind="Feneal UIL";colo_sind="primary";}
                  iscr=""
                  if (obj.sindacato!="0" && obj.sindacato!=" " && obj.sindacato!="") iscr="Iscritto "
                  html+=
                    `<div class="alert alert-`+colo_sind+`" role="alert">
                        <b>`+nome+`</b> risulti `+iscr+sind+`
                    </div>`
                 
                } else {
                  html+=
                    `<div class="alert alert-secondary" role="alert">
                        <b>`+nome+`</b> risulti non presente nei nostri archivi
                    </div>`                
                }
                $("#div_resp").html(html)
               
            }
            else {
            }

        })
        .catch(status, err => {
            return console.log(status, err);
        })     

      }, 800)	
    }
}
