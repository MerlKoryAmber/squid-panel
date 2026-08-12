<?php
class User {
    public static function find($id) {
        return Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public static function findByUsername($username) {
        return Database::fetch("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public static function all() {
        return Database::fetchAll("SELECT id, username, role, language, created_at FROM users ORDER BY id");
    }

    public static function create($data) {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        return Database::insert(
            "INSERT INTO users (username, password_hash, role, language, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$data['username'], $hash, $data['role'], $data['language'] ?? 'ru']
        );
    }

    public static function update($id, $data) {
        $fields = [];
        $params = [];

        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        if (isset($data['language'])) {
            $fields[] = "language = ?";
            $params[] = $data['language'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) return false;

        $params[] = $id;
        Database::query("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        return true;
    }

    public static function delete($id) {
        Database::query("DELETE FROM users WHERE id = ?", [$id]);
        return true;
    }
}
