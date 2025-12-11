self.addEventListener("install", (e) => {
  e.waitUntil(
    caches.open("monitoring-v1").then((cache) => {
      return cache.addAll(["index.php", "style.css", "layout.php"]);
    })
  );
});

self.addEventListener("fetch", (e) => {
  e.respondWith(caches.match(e.request).then((res) => res || fetch(e.request)));
});
