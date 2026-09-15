/**
 * Nizam School System - Service Worker
 * Provides offline capability, smart caching, and background sync
 * Version: 1.0.0
 */

const CACHE_VERSION = 'nizam-v1.0.2-navigation';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const IMAGE_CACHE = `${CACHE_VERSION}-images`;

// Assets that should always be cached (offline-first)
const STATIC_ASSETS = [
  '/',
  '/login',
  '/dashboard',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/vendor/bootstrap/css/bootstrap.min.css',
  '/assets/vendor/bootstrap/css/bootstrap.rtl.min.css',
  '/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
  '/offline.html'
];

// Cache limits to prevent storage overflow
const CACHE_LIMITS = {
  dynamic: 50,    // Max 50 dynamic pages
  images: 100     // Max 100 images
};

/**
 * Install Event - Cache static assets
 */
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...');
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log('[SW] Static assets cached successfully');
        return self.skipWaiting(); // Activate immediately
      })
      .catch((error) => {
        console.error('[SW] Failed to cache static assets:', error);
      })
  );
});

/**
 * Activate Event - Clean up old caches
 */
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((cacheName) => {
              // Delete caches that don't match current version
              return cacheName.startsWith('nizam-') && !cacheName.startsWith(CACHE_VERSION);
            })
            .map((cacheName) => {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            })
        );
      })
      .then(() => {
        console.log('[SW] Service worker activated');
        return self.clients.claim(); // Take control immediately
      })
  );
});

/**
 * Fetch Event - Smart caching strategy
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests and chrome-extension requests
  if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
    return;
  }

  // Different strategies for different resource types
  if (isStaticAsset(url)) {
    // Static assets: Cache-first strategy
    event.respondWith(cacheFirst(request, STATIC_CACHE));
  } else if (isImage(url)) {
    // Images: Cache-first with cleanup
    event.respondWith(cacheFirst(request, IMAGE_CACHE, CACHE_LIMITS.images));
  } else if (isAPIRequest(url)) {
    // API requests: Network-first with cache fallback
    event.respondWith(networkFirst(request, DYNAMIC_CACHE));
  } else {
    // HTML pages: Network-first with cache fallback
    event.respondWith(networkFirst(request, DYNAMIC_CACHE, CACHE_LIMITS.dynamic));
  }
});

/**
 * Cache-first strategy: Check cache, fallback to network
 */
async function cacheFirst(request, cacheName, limit = null) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      console.log('[SW] Cache hit:', request.url);
      return cachedResponse;
    }

    console.log('[SW] Cache miss, fetching:', request.url);
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      const cache = await caches.open(cacheName);
      
      // Apply cache limit if specified
      if (limit) {
        await trimCache(cacheName, limit);
      }
      
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.error('[SW] Cache-first failed:', error);
    return createOfflineResponse();
  }
}

/**
 * Network-first strategy: Try network, fallback to cache
 */
async function networkFirst(request, cacheName, limit = null) {
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      const cache = await caches.open(cacheName);
      
      // Apply cache limit if specified
      if (limit) {
        await trimCache(cacheName, limit);
      }
      
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('[SW] Network failed, checking cache:', request.url);
    
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      console.log('[SW] Cache fallback used');
      return cachedResponse;
    }
    
    console.error('[SW] Network-first failed:', error);
    return createOfflineResponse();
  }
}

/**
 * Trim cache to specified limit (LRU eviction)
 */
async function trimCache(cacheName, maxItems) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  
  if (keys.length > maxItems) {
    // Delete oldest entries
    const deleteCount = keys.length - maxItems;
    await Promise.all(
      keys.slice(0, deleteCount).map(request => cache.delete(request))
    );
    console.log(`[SW] Trimmed ${deleteCount} items from ${cacheName}`);
  }
}

/**
 * Helper functions to identify request types
 */
function isStaticAsset(url) {
  return url.pathname.match(/\.(css|js|woff2?|ttf|eot)$/);
}

function isImage(url) {
  return url.pathname.match(/\.(png|jpg|jpeg|gif|svg|webp|ico)$/);
}

function isAPIRequest(url) {
  return url.pathname.startsWith('/api/') || 
         url.pathname.includes('/notifications/api') ||
         url.pathname.includes('/scheduled/');
}

/**
 * Create offline fallback response
 */
function createOfflineResponse() {
  return new Response(
    `<!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Offline - Hadaba Al-Ahram Language School</title>
      <style>
        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          margin: 0;
          background: linear-gradient(135deg, #1f6f5c 0%, #14503f 100%);
          color: white;
          text-align: center;
          padding: 20px;
        }
        .offline-container {
          max-width: 400px;
        }
        .offline-icon {
          width: 80px;
          height: 80px;
          margin: 0 auto 20px;
          opacity: 0.9;
        }
        h1 {
          font-size: 1.5rem;
          margin-bottom: 10px;
        }
        p {
          opacity: 0.9;
          line-height: 1.6;
        }
        button {
          background: white;
          color: #1f6f5c;
          border: none;
          padding: 12px 24px;
          border-radius: 8px;
          font-weight: 600;
          margin-top: 20px;
          cursor: pointer;
          font-size: 0.95rem;
        }
        button:hover {
          transform: scale(1.05);
        }
      </style>
    </head>
    <body>
      <div class="offline-container">
        <svg class="offline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414"/>
        </svg>
        <h1>You're Offline</h1>
        <p>No internet connection detected. Some features may be limited. Your work is being saved locally and will sync when you're back online.</p>
        <button onclick="window.location.reload()">Try Again</button>
      </div>
    </body>
    </html>`,
    {
      status: 503,
      statusText: 'Service Unavailable',
      headers: new Headers({
        'Content-Type': 'text/html; charset=utf-8',
        'Cache-Control': 'no-store'
      })
    }
  );
}

/**
 * Background Sync Event - Sync queued data when online
 */
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync triggered:', event.tag);
  
  if (event.tag === 'sync-data') {
    event.waitUntil(syncQueuedData());
  }
});

/**
 * Sync queued data to server
 */
async function syncQueuedData() {
  try {
    console.log('[SW] Syncing queued data...');
    
    // Get sync queue from IndexedDB (implementation in separate file)
    const queue = await getSyncQueue();
    
    for (const item of queue) {
      try {
        await fetch(item.url, {
          method: item.method,
          headers: item.headers,
          body: item.body
        });
        
        // Remove from queue on success
        await removeFromQueue(item.id);
        console.log('[SW] Synced:', item.url);
      } catch (error) {
        console.error('[SW] Failed to sync:', item.url, error);
        // Keep in queue for retry
      }
    }
    
    console.log('[SW] Sync complete');
  } catch (error) {
    console.error('[SW] Sync queue error:', error);
  }
}

/**
 * Push Notification Event
 */
self.addEventListener('push', (event) => {
  console.log('[SW] Push notification received');
  
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'Hadaba Al-Ahram Language School';
  const options = {
    body: data.body || 'You have a new notification',
    icon: '/assets/images/icon-192x192.png',
    badge: '/assets/images/badge-72x72.png',
    vibrate: [200, 100, 200],
    tag: data.tag || 'default',
    requireInteraction: data.requireInteraction || false,
    data: data.data || {},
    actions: data.actions || []
  };
  
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

/**
 * Notification Click Event
 */
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked:', event.notification.tag);
  
  event.notification.close();
  
  const url = event.notification.data.url || '/notifications';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Check if app is already open
        for (const client of clientList) {
          if (client.url === url && 'focus' in client) {
            return client.focus();
          }
        }
        // Open new window if not
        if (clients.openWindow) {
          return clients.openWindow(url);
        }
      })
  );
});

/**
 * Message Event - Communication with main thread
 */
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);
  
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
  
  if (event.data.action === 'clearCache') {
    event.waitUntil(
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => caches.delete(cacheName))
        );
      })
    );
  }
});

/**
 * Placeholder functions for IndexedDB operations
 * These will be implemented in the sync-queue.js file
 */
async function getSyncQueue() {
  // TODO: Implement IndexedDB retrieval
  return [];
}

async function removeFromQueue(id) {
  // TODO: Implement IndexedDB deletion
  return Promise.resolve();
}

console.log('[SW] Service Worker loaded successfully');
