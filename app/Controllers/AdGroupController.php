<?php
class AdGroupController {
    public function index($params = []) {
        Auth::requireAuth();
        $listed = ['ok' => false, 'groups' => [], 'error' => ''];
        $imported = [];
        $realm = '';
        $ldap = AdLdapConfig::get();
        $syncMeta = [];
        try {
            AdGroupAcl::migrateLegacyExternalToProxyAuth();
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
            $syncMeta = AdGroupMemberSync::meta();
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
            'ldap' => $ldap,
            'ldapCaInstalled' => PanelTls::ldapCaInstalled(),
            'listed' => $listed,
            'imported' => is_array($imported) ? $imported : [],
            'syncMeta' => $syncMeta,
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
        ]);
    }

    public function saveLdap($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $cfg = AdLdapConfig::save([
                'servers' => $_POST['servers'] ?? '',
                'port' => $_POST['port'] ?? 389,
                'use_ssl' => !empty($_POST['use_ssl']),
                'bind_dn' => $_POST['bind_dn'] ?? '',
                'bind_password' => $_POST['bind_password'] ?? '',
                'base_dn' => $_POST['base_dn'] ?? '',
            ]);
            $migrated = AdGroupAcl::migrateLegacyExternalToProxyAuth();
            Audit::log(
                'ad_ldap_save',
                'simple servers=' . count(preg_split('/\s+/', trim($cfg['servers']))) . ' migrated=' . $migrated
            );
            $_SESSION['flash_success'] = 'LDAP settings saved (for member sync only; no password in squid.conf).'
                . ($migrated ? ' Migrated ' . $migrated . ' legacy helper ACL(s).' : '');
            if ($migrated) {
                SquidLiveApply::remember();
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect('/acl/ad-groups');
    }

    public function syncMembers($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $result = AdGroupMemberSync::syncAll();
            Audit::log('ad_group_sync', $result['message']);
            if (!empty($result['errors'])) {
                $_SESSION['flash_error'] = $result['message'];
            } else {
                $_SESSION['flash_success'] = $result['message'];
            }
            if (!empty($result['changed'])) {
                SquidLiveApply::remember();
            }
        } catch (Throwable $e) {
            AdGroupMemberSync::setMeta(false, $e->getMessage());
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect('/acl/ad-groups');
    }

    public function import($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $names = $_POST['groups'] ?? [];
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
            $msg[] = 'Created: ' . implode(', ', $created) . ' (run Sync members to fill lists)';
        }
        if ($skipped) {
            $msg[] = 'Already present: ' . implode(', ', $skipped);
        }
        if ($errors) {
            $_SESSION['flash_error'] = implode('; ', $errors);
        }
        if ($msg) {
            $_SESSION['flash_success'] = implode('. ', $msg) . '.';
        } elseif (!$errors) {
            $_SESSION['flash_error'] = 'No groups selected';
        }
        if ($created) {
            SquidLiveApply::remember();
        }
        View::redirect('/acl/ad-groups');
    }

    public function uploadCa($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $file = $_FILES['ca_pem'] ?? null;
            if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('CA certificate upload required (PEM)');
            }
            $tmp = (string)($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new Exception('Invalid upload');
            }
            $raw = file_get_contents($tmp);
            if ($raw === false) {
                throw new Exception('Cannot read upload');
            }
            PanelTls::assertPemCert($raw);
            PanelTls::writeStage(PanelTls::STAGE_CA, $raw);
            $result = PrivilegedExecutor::execute('ca_trust_install');
            @unlink(PanelTls::stageDir() . '/' . PanelTls::STAGE_CA);
            if (empty($result['success'])) {
                $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'CA install failed')));
                throw new Exception($err);
            }
            Audit::log('ldap_ca_upload', 'CA → system trust');
            $_SESSION['flash_success'] = 'Root CA installed into system trust (used by member sync LDAPS).';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect('/acl/ad-groups');
    }
}
