const CACHE_NAME = "monitoring-v1";

// Hanya cache file statis yang pasti ada
const STATIC_ASSETS = ["style.css", "script.js"];

self.addEventListener("install", (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }),
  );
  self.skipWaiting(); // langsung aktif tanpa tunggu tab ditutup
});

self.addEventListener("activate", (e) => {
  // Hapus cache lama jika ada
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))));
  self.clients.claim();
});

self.addEventListener("fetch", (e) => {
  // Lewati request non-GET (POST form, dll)
  if (e.request.method !== "GET") return;

  e.respondWith(
    caches.match(e.request).then((cached) => {
      if (cached) return cached;

      // Fetch dari network, tangkap error jika gagal
      return fetch(e.request).catch(() => {
        // Fallback jika offline dan tidak ada cache
        return new Response("Offline - koneksi tidak tersedia", {
          status: 503,
          statusText: "Service Unavailable",
        });
      });
    }),
  );
});
