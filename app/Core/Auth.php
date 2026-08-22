<?php
class Auth {
    public static function check() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['expires']) && $_SESSION['expires'] < time()) {
            $_SESSION = [];
            return false;
        }
        $_SESSION['expires'] = time() + SESSION_LIFETIME;
        return true;
    }

    public static function user() {
        if (!self::check()) return null;
        return User::find($_SESSION['user_id']);
    }

    public static function isAdmin() {
        $user = self::user();
        return $user && $user['role'] === 'admin';
    }

    public static function isOperator() {
        $user = self::user();
        return $user && $user['role'] === 'operator';
    }

    public static function requireAuth() {
        if (!self::check()) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/api/') !== false) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'unauthorized']);
                exit;
            }
            View::redirect('/login');
        }
    }

    public static function requireAdmin() {
        self::requireAuth();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Access denied: administrator role required');
        }
    }

    public static function login($username, $password) {
        $user = User::findByUsername($username);
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['policy_ui'] = 'expert';
            $_SESSION['expires'] = time() + SESSION_LIFETIME;
            Audit::log('login', "User {$username} logged in");
            return true;
        }
        Audit::log('login_failed', "Failed login attempt for {$username}");
        return false;
    }

    public static function logout() {
        $username = $_SESSION['username'] ?? null;
        if ($username) {
            Audit::log('logout', "User {$username} logged out");
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            }
            session_destroy();
        }
        View::redirect('/login');
    }
}
