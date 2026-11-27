---
paths:
  - 'resources/js/lib/notifications.ts, app/Http/Controllers/NotificationController.php, app/Http/Controllers/PushSubscriptionController.php'
---

# Http Controllers Http Controllers

## Push notifikasi: jangan bayangi prop bersama & batas endpoint
- Jangan pernah memberi prop halaman bernama `notifications`: itu prop bersama (unread + push_public_key) dari HandleInertiaRequests; halaman Notifikasi memakai `items`. Kalau dibayangi, tombol "Izinkan notifikasi" hilang.
- Validasi endpoint push memakai PushSubscription::ENDPOINT_MAX_LENGTH (1024), bukan 500: endpoint Edge/WNS lebih dari 500 karakter.
- enablePush melempar PushSetupError dengan pesan per langkah; jangan kembali ke `await navigator.serviceWorker.ready` (macet bila SW ditolak). Langganan dengan kunci VAPID lain diganti otomatis.
- Panel browser bawaan Claude tidak bisa mendaftarkan service worker; uji push di Chrome asli.
