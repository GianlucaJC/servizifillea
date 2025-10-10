const CACHE_NAME = 'fillea-app-cache-v2'; // IMPORTANTE: Incrementa la versione della cache
const URLS_TO_CACHE = [
  '/servizifillea/servizi.php',
  '/servizifillea/login.php',
  '/servizifillea/register.php',
  '/servizifillea/style.css',
  '/servizifillea/servizi.js',  
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
  'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js'
];

// 1. Installazione del Service Worker e caching dei file statici
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Cache aperta');
      // Aggiungi skipWaiting() per forzare l'attivazione immediata del nuovo SW
      return cache.addAll(URLS_TO_CACHE).then(() => self.skipWaiting());
    })
  );
});

// 2. Intercettazione delle richieste di rete
self.addEventListener('fetch', event => {
  // Strategia: Network falling back to cache.
  // Prova prima a ottenere la risorsa dalla rete. Se fallisce (es. offline),
  // cerca la risorsa nella cache.
  event.respondWith(
    fetch(event.request)
      .then(networkResponse => {
        // Se la richiesta di rete ha successo, la usiamo.
        // Opzionale: potremmo anche aggiornare la cache qui.
        return networkResponse;
      })
      .catch(() => {
        // Se la richiesta di rete fallisce, cerchiamo nella cache.
        console.log('[Service Worker] Fetch fallito, cerco nella cache:', event.request.url);
        return caches.match(event.request)
          .then(cachedResponse => {
            // Se troviamo una corrispondenza nella cache, la restituiamo.
            // Altrimenti, la richiesta fallisce (questo accade per le richieste API offline).
            return cachedResponse || Promise.reject('Risorsa non trovata né in rete né in cache.');
          });
      })
  );
});

// 3. Ascolta gli eventi 'push' in arrivo dal server
self.addEventListener('push', event => {
  console.log('[Service Worker] Push Ricevuto.');
  
  // Estrai i dati dal payload della notifica
  const data = event.data.json();
  console.log('[Service Worker] Dati push:', data);

  const title = data.title || 'Nuova Notifica';
  const options = {
    body: data.body || 'Hai un nuovo aggiornamento.',
    icon: 'https://placehold.co/192x192/d0112b/ffffff?text=F', // Icona per la notifica
    badge: 'https://placehold.co/96x96/d0112b/ffffff?text=F', // Icona piccola per la barra di stato (Android)
    data: {
      url: data.url || '/' // URL da aprire al click
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// 4. Gestisce il click sulla notifica
self.addEventListener('notificationclick', event => {
  console.log('[Service Worker] Click sulla notifica ricevuto.');

  // Chiude la notifica
  event.notification.close();

  // Estrai l'URL dai dati della notifica
  const urlToOpen = event.notification.data.url;

  // Apri una nuova finestra o metti a fuoco una finestra esistente con l'URL specificato
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      // Se c'è già una finestra aperta con lo stesso URL, mettila a fuoco.
      // Altrimenti, apri una nuova finestra.
      const matchingClient = windowClients.find(client => client.url === urlToOpen);
      if (matchingClient) {
        return matchingClient.focus();
      }
      return clients.openWindow(urlToOpen);
    })
  );
});

// 4. Evento 'activate' per pulire le vecchie cache
self.addEventListener('activate', event => {
  console.log('[Service Worker] Attivato.');
  const cacheWhitelist = [CACHE_NAME]; // Mantiene solo la cache con il nuovo nome

  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          // Se la cache non è nella whitelist, cancellala
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            console.log('[Service Worker] Cancellazione vecchia cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
    // Aggiungi clients.claim() per prendere il controllo immediato delle pagine aperte
    .then(() => self.clients.claim())
  );
});