<?php
class UserController {
    public function index($params = []) {
        Auth::requireAuth();
        $users = User::all();
        echo View::render('users.index', ['title' => 'Users', 'users' => $users]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'operator']) ? $_POST['role'] : 'operator';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            die('Username and password required');
        }

        if (User::findByUsername($username)) {
            http_response_code(400);
            die('Username already exists');
        }

        User::create(['username' => $username, 'password' => $password, 'role' => $role]);
        Audit::log('user_create', "Created user {$username} with role {$role}");
        View::redirect('/users');
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
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

        $id = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if (empty($password)) {
            http_response_code(400);
            die('Password required');
        }

        $user = User::find($id);
        if (!$user) {
            http_response_code(404);
            die('User not found');
        }

        // Only admin can change others' passwords
        if (!Auth::isAdmin() && Auth::user()['id'] != $id) {
            http_response_code(403);
            die('Access denied');
        }

        User::update($id, ['password' => $password]);
        Audit::log('user_password', "Changed password for {$user['username']}");
        View::redirect('/users');
    }
}
