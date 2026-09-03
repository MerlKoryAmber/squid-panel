<?php
class AuthConfigController {
    private const KEYTAB_MAX = 524288;

    public function index($params = []) {
        Auth::requireAuth();
        echo View::render('auth.index', ['title' => 'Authentication', 'active' => 'auth']);
    }

    public function kerberos($params = []) {
        Auth::requireAuth();
        $config = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $krb5 = is_readable('/etc/krb5.conf') ? (string)file_get_contents('/etc/krb5.conf') : '';
        $keytabPath = trim((string)($config['keytab_path'] ?? ''));
        $managed = $this->isManagedKeytab($keytabPath);
        $keytabExists = false;
        if ($managed) {
            try {
                PrivilegedExecutor::squidKeytabPath($keytabPath, true);
                $keytabExists = true;
            } catch (Exception $e) {
                $keytabExists = false;
            }
        }
        $destName = 'proxy.keytab';
        if ($keytabPath !== '' && preg_match('/^[A-Za-z0-9._-]+\.keytab$/', basename($keytabPath))) {
            $destName = basename($keytabPath);
        }
        $flashError = $_SESSION['flash_error'] ?? '';
        $flashSuccess = $_SESSION['flash_success'] ?? '';
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        $childOpts = self::childrenStartupIdle((string)($config['children_extra'] ?? ''));
        echo View::render('auth.kerberos', [
            'title' => 'Kerberos (Negotiate)',
            'active' => 'auth',
            'config' => $config,
            'krb5' => $krb5,
            'isAdmin' => Auth::isAdmin(),
            'keytabManaged' => $managed,
            'keytabExists' => $keytabExists,
            'destName' => $destName,
            'childrenStartup' => $childOpts['startup'],
            'childrenIdle' => $childOpts['idle'],
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
        ]);
    }

    public function saveKerberos($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $existing = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $realm = trim((string)($_POST['realm'] ?? ''));
        $kdc = trim((string)($_POST['kdc'] ?? ''));
        $admin_server = trim((string)($_POST['admin_server'] ?? ''));
        try {
            $ldapHosts = AdGroupAcl::parseLdapServers((string)($_POST['ldap_servers'] ?? ''));
        } catch (Exception $e) {
            $this->flashRedirect('/auth/kerberos', $e->getMessage());
            return;
        }
        $ldap_servers = AdGroupAcl::storeLdapServers($ldapHosts);
        $principal = trim((string)($_POST['principal'] ?? ''));
        $keep_alive = (($_POST['keep_alive'] ?? 'on') === 'off') ? 'off' : 'on';
        $children = (int)($_POST['children'] ?? 20);
        if ($children < 1) {
            $children = 20;
        }
        if ($children > 1024) {
            $children = 1024;
        }
        $startup = (int)($_POST['startup'] ?? 0);
        $idle = (int)($_POST['idle'] ?? 10);
        if ($startup < 0) {
            $startup = 0;
        }
        if ($idle < 0) {
            $idle = 0;
        }
        if ($startup > $children) {
            $startup = $children;
        }
        if ($idle > $children) {
            $idle = $children;
        }
        $children_extra = self::composeChildrenExtra($startup, $idle, (string)($existing['children_extra'] ?? ''));
        try {
            $keytab_path = $this->resolveKeytabForSave($_POST['keytab_path'] ?? '', $existing);
        } catch (Exception $e) {
            $this->flashRedirect('/auth/kerberos', $e->getMessage());
            return;
        }
        $helper = $this->stripNegotiateHelper(trim((string)($_POST['program'] ?? '')));
        if ($helper === '') {
            $helper = $this->stripNegotiateHelper(trim((string)($existing['helper'] ?? ''))) ?: '/usr/lib64/squid/negotiate_kerberos_auth';
        }
        $program = $this->buildNegotiateProgram($helper, $keytab_path, $principal);

        Database::query("DELETE FROM auth_config WHERE scheme = 'negotiate'");
        Database::query(
            "INSERT INTO auth_config (scheme, program, helper, children, children_extra, realm, keep_alive, keytab_path, principal, kdc, admin_server, ldap_servers, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            ['negotiate', $program, $helper, $children, $children_extra, $realm, $keep_alive, $keytab_path, $principal, $kdc, $admin_server, $ldap_servers]
        );

        $synced = AdGroupAcl::syncLdapServersIntoHelpers($ldapHosts, $realm);
        Audit::log(
            'kerberos_save',
            "Updated Kerberos config for realm {$realm}; ldap_servers=" . count($ldapHosts) . "; synced_ext_acl={$synced}"
        );

        $msg = 'Kerberos settings saved';
        if (!empty($ldapHosts)) {
            $msg .= '. LDAP servers pinned for ' . $synced . ' group helper(s) (-S).';
        } else {
            $msg .= '. LDAP servers cleared — helpers use DNS SRV again.';
        }
        $msg .= ' Applying live squid.conf…';
        $_SESSION['flash_success'] = $msg;
        if (!SquidLiveApply::remember()) {
            View::redirect('/auth/kerberos');
            return;
        }
        View::redirect('/auth/kerberos');
    }

    public function uploadKerberosKeytab($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        if (empty($_FILES['keytab']) || !is_array($_FILES['keytab'])) {
            $this->flashRedirect('/auth/kerberos', 'No keytab file uploaded');
            return;
        }
        $file = $_FILES['keytab'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashRedirect('/auth/kerberos', 'Upload failed (code ' . (int)($file['error'] ?? 0) . ')');
            return;
        }
        $tmpUpload = (string)($file['tmp_name'] ?? '');
        if ($tmpUpload === '' || !is_uploaded_file($tmpUpload)) {
            $this->flashRedirect('/auth/kerberos', 'Invalid upload');
            return;
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 2 || $size > self::KEYTAB_MAX) {
            $this->flashRedirect('/auth/kerberos', 'Keytab must be between 2 bytes and 512 KB');
            return;
        }
        $raw = file_get_contents($tmpUpload);
        if ($raw === false || !$this->keytabMagicOk($raw)) {
            $this->flashRedirect('/auth/kerberos', 'File is not a MIT keytab (expected version 0x0501 or 0x0502)');
            return;
        }

        $destName = trim((string)($_POST['dest_name'] ?? ''));
        if ($destName === '') {
            $destName = 'proxy.keytab';
        }
        try {
            $destPath = PrivilegedExecutor::squidKeytabPath($destName);
            $base = basename($destPath);
        } catch (Exception $e) {
            $this->flashRedirect('/auth/kerberos', $e->getMessage());
            return;
        }

        $stageDir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp';
        if (!is_dir($stageDir) && !mkdir($stageDir, 0750, true) && !is_dir($stageDir)) {
            $this->flashRedirect('/auth/kerberos', 'Cannot create staging directory');
            return;
        }
        $stage = $stageDir . '/' . $base;
        if (file_put_contents($stage, $raw) === false) {
            $this->flashRedirect('/auth/kerberos', 'Cannot write staging keytab');
            return;
        }
        @chmod($stage, 0600);

        try {
            $result = PrivilegedExecutor::execute('keytab_install', [$base]);
        } catch (Exception $e) {
            @unlink($stage);
            $this->flashRedirect('/auth/kerberos', $e->getMessage());
            return;
        }
        @unlink($stage);

        if (empty($result['success'])) {
            $msg = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'keytab install failed')));
            $this->flashRedirect('/auth/kerberos', $msg);
            return;
        }

        $existing = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'negotiate' LIMIT 1");
        if ($existing) {
            $helper = $this->stripNegotiateHelper((string)($existing['helper'] ?? '')) ?: '/usr/lib64/squid/negotiate_kerberos_auth';
            $principal = trim((string)($existing['principal'] ?? ''));
            $program = $this->buildNegotiateProgram($helper, $destPath, $principal);
            Database::query(
                "UPDATE auth_config SET keytab_path = ?, program = ?, helper = ?, updated_at = datetime('now') WHERE id = ?",
                [$destPath, $program, $helper, (int)$existing['id']]
            );
        } else {
            $helper = '/usr/lib64/squid/negotiate_kerberos_auth';
            $program = $this->buildNegotiateProgram($helper, $destPath, '');
            Database::query(
                "INSERT INTO auth_config (scheme, program, helper, children, keep_alive, keytab_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
                ['negotiate', $program, $helper, 20, 'on', $destPath]
            );
        }

        Audit::log('kerberos_keytab_upload', 'Installed keytab ' . $base);
        $this->flashRedirect('/auth/kerberos', '', 'Keytab installed as ' . $destPath);
    }

    public function testKerberos($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $config = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $posted = trim((string)($_POST['keytab_path'] ?? ''));
        $path = $posted !== '' ? $posted : (string)($config['keytab_path'] ?? '');
        try {
            $keytab = PrivilegedExecutor::squidKeytabPath($path, true);
            $principal = self::kinitPrincipal($config);
            if ($principal === '') {
                $this->flashRedirect('/auth/kerberos', 'Set Service Principal (HTTP/fqdn@REALM) before kinit test');
            }
            $result = PrivilegedExecutor::execute('kinit_test', [$keytab, $principal]);
            $ok = !empty($result['success']);
            $out = trim((string)(($result['stdout'] ?? '') ?: ($result['stderr'] ?? '') ?: ($result['error'] ?? '')));
            if ($ok) {
                $this->flashRedirect('/auth/kerberos', '', $out !== '' ? $out : 'kinit succeeded');
            } else {
                $this->flashRedirect('/auth/kerberos', $out !== '' ? $out : 'kinit failed');
            }
        } catch (Exception $e) {
            $this->flashRedirect('/auth/kerberos', $e->getMessage());
        }
    }

    public function ntlm($params = []) {
        Auth::requireAuth();
        $config = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'ntlm' LIMIT 1") ?: [];

        $winbindStatus = ['status' => 'unknown'];
        $domainInfo = '';
        try {
            $wb = PrivilegedExecutor::execute('winbind_status');
            $state = strtolower(trim((string)($wb['stdout'] ?? '')));
            $winbindStatus['status'] = ($state === 'active') ? 'running' : 'stopped';
            $winbindStatus['raw'] = $wb['stdout'];

            $net = PrivilegedExecutor::execute('net_ads_info');
            $domainInfo = $net['stdout'];
        } catch (Exception $e) {
            $winbindStatus['error'] = $e->getMessage();
        }

        echo View::render('auth.ntlm', [
            'title' => 'NTLM (Winbind)',
            'active' => 'auth',
            'config' => $config,
            'winbind' => $winbindStatus,
            'domainInfo' => $domainInfo
        ]);
    }

    public function saveNtlm($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $helper = $_POST['helper'] ?? 'ntlm_auth';
        if (!in_array($helper, ['ntlm_auth', 'winbind'], true)) {
            $helper = 'ntlm_auth';
        }
        $defaultProgram = '/usr/bin/ntlm_auth --helper-protocol=squid-2.5-ntlmssp';
        $program = trim($_POST['program'] ?? '') !== '' ? $_POST['program'] : $defaultProgram;
        $children = (int)($_POST['children'] ?? 10);
        $domain = $_POST['domain'] ?? '';
        $dc = $_POST['dc'] ?? '';
        $backup_dc = $_POST['backup_dc'] ?? '';

        Database::query("DELETE FROM auth_config WHERE scheme = 'ntlm'");
        Database::query(
            "INSERT INTO auth_config (scheme, program, children, domain, dc, backup_dc, helper, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            ['ntlm', $program, $children, $domain, $dc, $backup_dc, $helper]
        );

        Audit::log('ntlm_save', "Updated NTLM config for domain {$domain}");
        View::redirect('/auth/ntlm');
    }

    public function basic($params = []) {
        Auth::requireAuth();
        $config = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'basic' LIMIT 1") ?: [];
        echo View::render('auth.basic', ['title' => 'Basic Authentication', 'active' => 'auth', 'config' => $config]);
    }

    public function saveBasic($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $program = $_POST['program'] ?? '/usr/lib64/squid/basic_ncsa_auth /etc/squid/passwd';
        $children = (int)($_POST['children'] ?? 5);
        $realm = $_POST['realm'] ?? 'Squid Proxy';
        $credentialsttl = $_POST['credentialsttl'] ?? '2 hours';

        Database::query("DELETE FROM auth_config WHERE scheme = 'basic'");
        Database::query(
            "INSERT INTO auth_config (scheme, program, children, realm, credentialsttl, created_at, updated_at) VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            ['basic', $program, $children, $realm, $credentialsttl]
        );

        Audit::log('basic_save', "Updated Basic auth config");
        View::redirect('/auth/basic');
    }

    private function flashRedirect($url, $error = '', $success = '') {
        if ($error !== '') {
            $_SESSION['flash_error'] = $error;
        }
        if ($success !== '') {
            $_SESSION['flash_success'] = $success;
        }
        View::redirect($url);
    }

    private function isManagedKeytab($path) {
        $path = trim((string)$path);
        if ($path === '') {
            return false;
        }
        try {
            PrivilegedExecutor::squidKeytabPath($path);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function resolveKeytabForSave($posted, $existing) {
        $posted = trim((string)$posted);
        try {
            return PrivilegedExecutor::squidKeytabPath($posted !== '' ? $posted : '/etc/squid/proxy.keytab');
        } catch (Exception $e) {
            $old = trim((string)($existing['keytab_path'] ?? ''));
            if ($posted !== '' && $posted === $old) {
                return $old;
            }
            throw $e;
        }
    }

    private function stripNegotiateHelper($helper) {
        $helper = trim((string)$helper);
        if ($helper === '') {
            return '';
        }
        $tokens = preg_split('/\s+/', $helper);
        $kept = [];
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            if (($tokens[$i] === '-k' || $tokens[$i] === '-s') && isset($tokens[$i + 1])) {
                $i++;
                continue;
            }
            $kept[] = $tokens[$i];
        }
        return implode(' ', $kept);
    }

    private static function kinitPrincipal(array $config) {
        $principal = trim((string)($config['principal'] ?? ''));
        if ($principal !== '' && preg_match('/^[A-Za-z0-9.\/_@-]+$/', $principal)) {
            return $principal;
        }
        $program = (string)($config['program'] ?? '');
        if (preg_match('/(?:^|\s)-s\s+(\S+)/', $program, $m)) {
            $fromProg = trim($m[1]);
            if (preg_match('/^[A-Za-z0-9.\/_@-]+$/', $fromProg)) {
                return $fromProg;
            }
        }
        return '';
    }

    private function buildNegotiateProgram($helper, $keytab, $principal) {
        $line = $this->stripNegotiateHelper($helper);
        if ($line === '') {
            $line = '/usr/lib64/squid/negotiate_kerberos_auth';
        }
        if (trim((string)$keytab) !== '') {
            $line .= ' -k ' . trim($keytab);
        }
        if (trim((string)$principal) !== '') {
            $line .= ' -s ' . trim($principal);
        }
        return $line;
    }

    private function keytabMagicOk($bytes) {
        if (!is_string($bytes) || strlen($bytes) < 2) {
            return false;
        }
        $ver = unpack('n', substr($bytes, 0, 2));
        $code = (int)($ver[1] ?? 0);
        return $code === 0x0501 || $code === 0x0502;
    }

    /** Squid 5/6: auth_param negotiate children N startup=A idle=B */
    private static function childrenStartupIdle($extra) {
        $startup = 0;
        $idle = 10;
        if (preg_match('/\bstartup=(\d+)/', $extra, $m)) {
            $startup = (int)$m[1];
        }
        if (preg_match('/\bidle=(\d+)/', $extra, $m)) {
            $idle = (int)$m[1];
        }
        return ['startup' => $startup, 'idle' => $idle];
    }

    private static function composeChildrenExtra($startup, $idle, $existingExtra) {
        $rest = [];
        foreach (preg_split('/\s+/', trim((string)$existingExtra)) as $token) {
            if ($token === '' || preg_match('/^(startup|idle)=/', $token)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9._=-]+$/', $token)) {
                continue;
            }
            $rest[] = $token;
        }
        $parts = ['startup=' . (int)$startup, 'idle=' . (int)$idle];
        return implode(' ', array_merge($parts, $rest));
    }
}
