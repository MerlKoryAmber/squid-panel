<?php
class AdGroupController {
    public function index($params = []) {
        Auth::requireAuth();
        $listed = ['ok' => false, 'groups' => [], 'error' => ''];
        $imported = [];
        $realm = '';
        try {
            $listed = AdGroupAcl::listFromDirectory();
            if (!is_array($listed) || !isset($listed['groups']) || !is_array($listed['groups'])) {
                $listed = ['ok' => false, 'groups' => [], 'error' => 'LDAP group list failed'];
            }
        } catch (Throwable $e) {
            $listed = ['ok' => false, 'groups' => [], 'error' => $e->getMessage()];
        }
        try {
            $imported = AdGroupAcl::importedMap();
            $realm = AdGroupAcl::realm();
        } catch (Throwable $e) {
            if (($listed['error'] ?? '') === '') {
                $listed['error'] = $e->getMessage();
            }
        }
        $flashError = $_SESSION['flash_error'] ?? '';
        $flashSuccess = $_SESSION['flash_success'] ?? '';
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        echo View::render('acl.ad_groups', [
            'title' => 'AD groups',
            'active' => 'acl',
            'isAdmin' => Auth::isAdmin(),
            'realm' => $realm,
            'listed' => $listed,
            'imported' => is_array($imported) ? $imported : [],
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
        ]);
    }

    public function import($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $names = $_POST['groups'] ?? [];
        $manual = trim((string)($_POST['manual'] ?? ''));
        if ($manual !== '') {
            $names[] = $manual;
        }
        if (!is_array($names)) {
            $names = [];
        }
        $created = [];
        $skipped = [];
        $errors = [];
        foreach ($names as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') {
                continue;
            }
            try {
                $result = AdGroupAcl::ensureImported($raw);
                if ($result['created']) {
                    $created[] = $result['name'];
                } else {
                    $skipped[] = $result['name'];
                }
            } catch (Exception $e) {
                $errors[] = $raw . ': ' . $e->getMessage();
            }
        }
        if (!empty($created)) {
            Audit::log('ad_group_import', 'Created ACLs ' . implode(', ', $created));
        }
        $msg = [];
        if ($created) {
            $msg[] = 'Created: ' . implode(', ', $created);
        }
        if ($skipped) {
            $msg[] = 'Already present: ' . implode(', ', $skipped);
        }
        if ($errors) {
            $_SESSION['flash_error'] = implode('; ', $errors);
        }
        if ($msg) {
            $_SESSION['flash_success'] = implode('. ', $msg) . '. Live squid.conf was not rewritten. Use these ACLs in HTTP Access / Cascade.';
        } elseif (!$errors) {
            $_SESSION['flash_error'] = 'No groups selected';
        }
        View::redirect('/acl/ad-groups');
    }
}
