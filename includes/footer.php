<script>window.I18N = <?= json_encode(Lang::strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= url('/assets/js/api.js') ?>?v=<?= APP_VERSION ?>"></script>
<script src="<?= url('/assets/js/auth.js') ?>?v=<?= APP_VERSION ?>"></script>
<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register(window.BASE + '/sw.js').catch(function () {});</script>
</body>
</html>
