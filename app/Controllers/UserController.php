<?php
class UserController {
    public function index($params = []) {
        Auth::requireAuth();
        $users = User::all();
        echo View::render('users.index', [
            'title' => 'Users',
            'users' => $users,
            'currentUser' => Auth::user(),
            'flashError' => $_SESSION['flash_error'] ?? '',
            'flashSuccess' => $_SESSION['flash_success'] ?? '',
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'operator']) ? $_POST['role'] : 'operator';

        if (empty($username) || empty($password)) {
            $this->flashRedirect('/users', 'Username and password required');
        }

        if (strlen($password) < 8) {
            $this->flashRedirect('/users', 'Password must be at least 8 characters');
        }

        if (User::findByUsername($username)) {
            $this->flashRedirect('/users', 'Username already exists');
        }

        User::create(['username' => $username, 'password' => $password, 'role' => $role]);
        Audit::log('user_create', "Created user {$username} with role {$role}");
        $this->flashRedirect('/users', '', 'User created');
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $me = Auth::user();
        if ($me && (int)$me['id'] === $id) {
            $this->flashRedirect('/users', 'Cannot delete the current user');
        }

        $user = User::find($id);
        if ($user) {
            User::delete($id);
            Audit::log('user_delete', "Deleted user {$user['username']}");
        }
        View::redirect('/users');
    }

    public function password($params = []) {
        Auth::requireAuth();
        View::verifyCsrf();

        $me = Auth::user();
        $redirect = ($_POST['redirect'] ?? '') === '/settings' ? '/settings' : '/users';
        $id = (int)($_POST['id'] ?? ($me['id'] ?? 0));
        $new = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $current = $_POST['current_password'] ?? '';

        if (!$me) {
            View::redirect('/login');
        }

        if (strlen($new) < 8) {
            $this->flashRedirect($redirect, 'Password must be at least 8 characters');
        }
        if ($new !== $confirm) {
            $this->flashRedirect($redirect, 'New password and confirmation do not match');
        }

        $user = User::find($id);
        if (!$user) {
            $this->flashRedirect($redirect, 'User not found');
        }

        $changingSelf = ((int)$me['id'] === $id);
        if (!$changingSelf && !Auth::isAdmin()) {
            http_response_code(403);
            die('Access denied');
        }

        if ($changingSelf) {
            if ($current === '' || !password_verify($current, $user['password_hash'])) {
                $this->flashRedirect($redirect, 'Current password is incorrect');
            }
        }

        User::update($id, ['password' => $new]);
        Audit::log('user_password', "Changed password for {$user['username']}");

        if ($changingSelf) {
            session_regenerate_id(true);
        }

        $this->flashRedirect($redirect, '', 'Password updated');
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
}
