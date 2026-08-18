<?php
class AuthConfigController {
    public function index($params = []) {
        Auth::requireAuth();
        echo View::render('auth.index', ['title' => 'Authentication']);
    }

    public function kerberos($params = []) {
        Auth::requireAuth();
        $config = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $krb5 = file_exists('/etc/krb5.conf') ? file_get_contents('/etc/krb5.conf') : '';
        echo View::render('auth.kerberos', ['title' => 'Kerberos (Negotiate)', 'config' => $config, 'krb5' => $krb5]);
    }

    public function saveKerberos($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $realm = $_POST['realm'] ?? '';
        $kdc = $_POST['kdc'] ?? '';
        $admin_server = $_POST['admin_server'] ?? '';
        $principal = trim($_POST['principal'] ?? '');
        try {
            $keytab_path = PrivilegedExecutor::squidKeytabPath($_POST['keytab_path'] ?? '/etc/squid/proxy.keytab');
        } catch (Exception $e) {
            http_response_code(400);
            die($e->getMessage());
        }
        $program = $_POST['program'] ?? '/usr/lib64/squid/negotiate_kerberos_auth';
        $children = (int)($_POST['children'] ?? 10);

        // Write krb5.conf
        $krb5Content = "[libdefaults]
    default_realm = {$realm}
    dns_lookup_realm = false
    dns_lookup_kdc = false
    ticket_lifetime = 24h
    renew_lifetime = 7d
    forwardable = true

[realms]
    {$realm} = {
        kdc = {$kdc}
        admin_server = {$admin_server}
    }
";

        if (is_writable('/etc/krb5.conf') || is_writable('/etc')) {
            file_put_contents('/etc/krb5.conf', $krb5Content);
        }

        // Save to DB
        Database::query("DELETE FROM auth_config WHERE scheme = 'negotiate'");
        Database::query(
            "INSERT INTO auth_config (scheme, program, children, realm, keytab_path, principal, kdc, admin_server, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            ['negotiate', $program, $children, $realm, $keytab_path, $principal, $kdc, $admin_server]
        );

        Audit::log('kerberos_save', "Updated Kerberos config for realm {$realm}");
        View::redirect('/auth/kerberos');
    }

    public function testKerberos($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        try {
            $keytab = PrivilegedExecutor::squidKeytabPath($_POST['keytab_path'] ?? '/etc/squid/proxy.keytab', true);
            $result = PrivilegedExecutor::execute('kinit_test', [$keytab]);
            header('Content-Type: application/json');
            echo json_encode(['success' => $result['success'], 'output' => $result['stdout'] ?: $result['stderr']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ntlm($params = []) {
        Auth::requireAuth();
        $config = Database::fetch("SELECT * FROM auth_config WHERE scheme = 'ntlm' LIMIT 1") ?: [];

        $winbindStatus = ['status' => 'unknown'];
        $domainInfo = '';
        try {
            $wb = PrivilegedExecutor::execute('winbind_status');
            $winbindStatus['status'] = $wb['success'] ? 'running' : 'stopped';
            $winbindStatus['raw'] = $wb['stdout'];

            $net = PrivilegedExecutor::execute('net_ads_info');
            $domainInfo = $net['stdout'];
        } catch (Exception $e) {
            $winbindStatus['error'] = $e->getMessage();
        }

        echo View::render('auth.ntlm', [
            'title' => 'NTLM (Winbind)',
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
        echo View::render('auth.basic', ['title' => 'Basic Authentication', 'config' => $config]);
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
}
