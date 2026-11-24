</main>
<footer class="footer">
  <div>
    <strong>Pangkalan MAN 4 Banjar — Gugus Depan Gerakan Pramuka</strong>
  </div>
  <div class="footer-compliance-pill">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
    <span>Sistem Presensi Ekstrakurikuler Pramuka MAN 4 Banjar • Versi <?= e(APP_VERSION) ?></span>
  </div>
  <div style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">
    &copy; <?= date('Y') ?> Gugus Depan MAN 4 Banjar. Seluruh hak cipta dilindungi undang-undang.
  </div>
</footer>
<script>
// Auto update service worker & purge stale browser caches
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(regs => {
    for (const reg of regs) {
      reg.update();
    }
  });
  navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js').then(reg => {
    reg.update();
  }).catch(() => {});
}
if ('caches' in window) {
  caches.keys().then(keys => {
    keys.forEach(k => {
      if (k === 'absen-mpp-v1' || k === 'absen-mpp-v2') {
        caches.delete(k).then(() => {
          window.location.reload();
        });
      }
    });
  });
}
</script>
</body>
</html>
