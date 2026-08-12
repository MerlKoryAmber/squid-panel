<?php
class SchedulerController {
    public function index($params = []) {
        Auth::requireAuth();
        $jobs = Database::fetchAll("SELECT * FROM scheduled_jobs ORDER BY id");
        echo View::render('scheduler.index', ['title' => 'Scheduler', 'jobs' => $jobs]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? '';
        $schedule = $_POST['schedule'] ?? '';
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        if (empty($name) || empty($type) || empty($schedule)) {
            http_response_code(400);
            die('All fields required');
        }

        Database::query(
            "INSERT INTO scheduled_jobs (name, type, schedule, enabled, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$name, $type, $schedule, $enabled]
        );

        Audit::log('scheduler_create', "Created scheduled job {$name}");
        View::redirect('/scheduler');
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        Database::query("DELETE FROM scheduled_jobs WHERE id = ?", [$id]);
        Audit::log('scheduler_delete', "Deleted scheduled job {$id}");
        View::redirect('/scheduler');
    }

    public function toggle($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $job = Database::fetch("SELECT enabled FROM scheduled_jobs WHERE id = ?", [$id]);
        if ($job) {
            $newState = $job['enabled'] ? 0 : 1;
            Database::query("UPDATE scheduled_jobs SET enabled = ? WHERE id = ?", [$newState, $id]);
            Audit::log('scheduler_toggle', "Toggled job {$id} to " . ($newState ? 'enabled' : 'disabled'));
        }
        View::redirect('/scheduler');
    }
}
