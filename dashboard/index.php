<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
$user = requirePageAuth();
$pageTitle = t('app.name');
$appShell = true;
include dirname(__DIR__) . '/includes/header.php';
?>
<div id="app"></div>
<noscript><p class="center"><?= e(t('err.noscript')) ?></p></noscript>
<script>window.I18N = <?= json_encode(Lang::strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
window.ME = <?= json_encode(UserRepository::publicView($user), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="/assets/js/api.js?v=<?= APP_VERSION ?>"></script>
<script src="/assets/js/ui.js?v=<?= APP_VERSION ?>"></script>
<script src="/assets/js/views.js?v=<?= APP_VERSION ?>"></script>
<script src="/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
