<?php
class UiModeController {
    public function set($params = []) {
        Auth::requireAuth();
        View::verifyCsrf();
        PolicyUi::set($_POST['mode'] ?? 'expert');
        View::redirect(PolicyUi::safeReturn($_POST['return'] ?? '/http_access'));
    }

    public function unlock($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        PolicyUi::unlockSimple();
        PolicyUi::set('simple');
        Audit::log('simple_ui_unlock', 'Simple policy UI enabled (panel-owned rules)');
        View::redirect(PolicyUi::safeReturn($_POST['return'] ?? '/http_access'));
    }
}
