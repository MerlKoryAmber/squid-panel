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
}
