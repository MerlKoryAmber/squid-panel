<?php
class View {
    private static $jsonInput = null;

    public static function render($view, $data = []) {
        extract($data);

        $contentFile = SPM_VIEWS . '/' . str_replace('.', '/', $view) . '.php';

        if (isset($layout) && $layout === false) {
            if (file_exists($contentFile)) {
                ob_start();
                include $contentFile;
                return ob_get_clean();
            }
            return '';
        }

        $layoutFile = SPM_VIEWS . '/layout.php';

        ob_start();
        if (file_exists($contentFile)) {
            include $contentFile;
        }
        $content = ob_get_clean();

        $toastSuccess = (string)($data['flashSuccess'] ?? $_SESSION['flash_success'] ?? '');
        $toastError = (string)($data['flashError'] ?? $_SESSION['flash_error'] ?? '');
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $panelTheme = class_exists('PanelTheme') ? PanelTheme::current() : 'gold';

        ob_start();
        if (file_exists($layoutFile)) {
            include $layoutFile;
        }
        return ob_get_clean();
    }

    public static function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect($url) {
        header('Location: ' . $url);
        exit;
    }

    public static function csrfToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function csrf() {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    public static function asset($path) {
        $path = '/' . ltrim((string)$path, '/');
        $full = SPM_ROOT . '/public' . $path;
        $ver = is_file($full) ? (string)filemtime($full) : (string)SPM_VERSION;
        return $path . '?v=' . rawurlencode($ver);
    }

    public static function jsonInput() {
        if (self::$jsonInput !== null) {
            return self::$jsonInput;
        }
        self::$jsonInput = [];
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') === false) {
            return self::$jsonInput;
        }
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        self::$jsonInput = is_array($data) ? $data : [];
        return self::$jsonInput;
    }

    public static function verifyCsrf() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        if ($sessionToken === '') {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            self::csrfFail('CSRF token missing. Please refresh the page and try again.');
        }
        $json = self::jsonInput();
        $token = $_POST[CSRF_TOKEN_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ($json[CSRF_TOKEN_NAME] ?? '');
        $token = is_string($token) ? $token : '';
        if ($token === '' || !hash_equals($sessionToken, $token)) {
            self::csrfFail('CSRF token validation failed. Please refresh the page.');
        }
    }

    private static function csrfFail($message) {
        http_response_code(403);
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($ct, 'application/json') !== false
            || strpos($uri, '/api/') !== false
            || stripos($accept, 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message]);
            exit;
        }
        die($message);
    }

    private static $aclCatalog = null;

    public static function aclCatalog() {
        if (self::$aclCatalog !== null) {
            return self::$aclCatalog;
        }
        self::$aclCatalog = [];
        try {
            $rows = Database::fetchAll("SELECT id, name, type, entries, description, storage FROM acls ORDER BY id");
        } catch (Exception $e) {
            return self::$aclCatalog;
        }
        foreach ($rows as $acl) {
            $entries = json_decode($acl['entries'] ?? '[]', true);
            if (!is_array($entries)) {
                $entries = [];
            }
            $storage = ($acl['storage'] ?? 'inline') === 'file' ? 'file' : 'inline';
            $count = count($entries);
            if ($storage === 'file') {
                $count = AclListFile::countWorkFile($acl['name']);
                $entries = [];
            }
            self::$aclCatalog[$acl['name']] = [
                'id' => (int)$acl['id'],
                'type' => (string)$acl['type'],
                'entries' => $entries,
                'description' => (string)($acl['description'] ?? ''),
                'storage' => $storage,
                'count' => $count,
            ];
        }
        return self::$aclCatalog;
    }

    public static function aclTipText($token) {
        $token = trim((string)$token);
        $name = (strpos($token, '!') === 0) ? substr($token, 1) : $token;
        if ($name === '') {
            return '';
        }
        $meta = self::aclCatalog()[$name] ?? null;
        $builtins = [
            'all' => 'Built-in Squid ACL (matches everything)',
            'localhost' => 'Built-in Squid ACL (this host)',
            'to_localhost' => 'Built-in Squid ACL (destined to this host)',
            'manager' => 'Built-in Squid ACL (cache manager)',
            'CONNECT' => 'Built-in method ACL',
        ];
        $lines = [];
        if ($meta) {
            $lines[] = $name . ' · ' . $meta['type'];
            if (($meta['storage'] ?? 'inline') === 'file') {
                $lines[] = 'File list · ' . (int)($meta['count'] ?? 0) . ' values';
                $lines[] = AclListFile::livePath($name);
            } elseif ($meta['description'] !== '') {
                $lines[] = $meta['description'];
            }
            if (($meta['storage'] ?? 'inline') !== 'file') {
            $max = 16;
            $shown = array_slice($meta['entries'], 0, $max);
            if (empty($shown)) {
                $lines[] = '(no values)';
            } else {
                foreach ($shown as $entry) {
                    $lines[] = $entry;
                }
                $more = count($meta['entries']) - count($shown);
                if ($more > 0) {
                    $lines[] = '… +' . $more . ' more';
                }
            }
            }
        } elseif (isset($builtins[$name])) {
            $lines[] = $name;
            $lines[] = $builtins[$name];
        } else {
            $lines[] = $name;
            $lines[] = 'Not found in panel ACLs';
        }
        if (strpos($token, '!') === 0) {
            array_unshift($lines, 'Negated (!)');
        }
        return implode("\n", $lines);
    }

    public static function aclBadge($token) {
        $token = trim((string)$token);
        if ($token === '') {
            return '';
        }
        $name = (strpos($token, '!') === 0) ? substr($token, 1) : $token;
        $meta = self::aclCatalog()[$name] ?? null;
        $tip = htmlspecialchars(self::aclTipText($token), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $neg = strpos($token, '!') === 0 ? ' acl-ref-neg' : '';
        $badge = '<span class="badge badge-default' . $neg . '">' . $label . '</span>';
        if ($meta) {
            return '<a class="acl-ref" href="/acl/edit?id=' . (int)$meta['id'] . '" data-acl-tip="' . $tip . '">' . $badge . '</a>';
        }
        return '<span class="acl-ref acl-ref-static" data-acl-tip="' . $tip . '">' . $badge . '</span>';
    }
}
