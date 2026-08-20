<?php
class AuthController {
    public function loginForm($params = []) {
        if (Auth::check()) {
            View::redirect('/');
        }
        echo View::render('login', ['title' => 'Login', 'layout' => false]);
    }

    public function login($params = []) {
        View::verifyCsrf();

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (Auth::login($username, $password)) {
            View::redirect('/');
        }

        echo View::render('login', [
            'title' => 'Login',
            'layout' => false,
            'error' => 'Invalid username or password'
        ]);
    }

    public function logout($params = []) {
        Auth::logout();
    }
}
