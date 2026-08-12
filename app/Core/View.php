<?php
class View {
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

    public static function csrf() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME]) . '">';
    }

    public static function verifyCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[CSRF_TOKEN_NAME] ?? '';
            if (empty($token) || !hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
}
