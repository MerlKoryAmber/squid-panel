<?php
class Auth {
    public static function check() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['expires']) && $_SESSION['expires'] < time()) {
            self::logout();
            return false;
        }
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
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user['username'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['expires'] = time() + SESSION_LIFETIME;
            Audit::log('login', "User {$username} logged in");
            return true;
        }
        Audit::log('login_failed', "Failed login attempt for {$username}");
        return false;
    }

    public static function logout() {
        $user = self::user();
        if ($user) {
            Audit::log('logout', "User {$user['username']} logged out");
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Strict'
            ]);
        }
        session_destroy();
        View::redirect('/login');
    }
}
