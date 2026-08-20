<?php
class SquidServiceController {
    public function status($params = []) {
        Auth::requireAuth();
        header('Content-Type: application/json');
        echo json_encode(PrivilegedExecutor::getSquidStatus());
    }

    public function start($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        try {
            $result = PrivilegedExecutor::execute('squid_start');
            Audit::log('service_start', 'Squid service started');
            header('Content-Type: application/json');
            echo json_encode(['success' => $result['success'], 'message' => $result['stdout'] ?: $result['stderr']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function stop($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        try {
            $result = PrivilegedExecutor::execute('squid_stop');
            Audit::log('service_stop', 'Squid service stopped');
            header('Content-Type: application/json');
            echo json_encode(['success' => $result['success'], 'message' => $result['stdout'] ?: $result['stderr']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function restart($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        try {
            $result = PrivilegedExecutor::execute('squid_restart');
            Audit::log('service_restart', 'Squid service restarted');
            header('Content-Type: application/json');
            echo json_encode(['success' => $result['success'], 'message' => $result['stdout'] ?: $result['stderr']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function reconfigure($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        try {
            // First validate current config
            $check = SquidSyntaxChecker::validateConfig();
            if (!$check['valid']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Config has syntax errors: ' . $check['error']]);
                return;
            }

            $result = PrivilegedExecutor::execute('squid_reconfigure');
            Audit::log('service_reconfigure', 'Squid reconfigured without restart');
            header('Content-Type: application/json');
            echo json_encode(['success' => $result['success'], 'message' => $result['stdout'] ?: $result['stderr']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
