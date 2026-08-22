<?php
class SquidLiveApply {
    public static function run() {
        SquidPolicyApply::stageFromDatabase();
        return PrivilegedExecutor::execute('squid_policy_apply');
    }

    public static function errorText($result) {
        return trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'squid apply failed')));
    }

    public static function remember() {
        try {
            $result = self::run();
            if (empty($result['success'])) {
                $_SESSION['flash_error'] = self::errorText($result);
                return false;
            }
            $prev = trim((string)($_SESSION['flash_success'] ?? ''));
            $msg = 'Config saved';
            $_SESSION['flash_success'] = $prev === '' ? $msg : ($prev . '. ' . $msg);
            return true;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            return false;
        }
    }

    public static function redirect($url) {
        self::remember();
        View::redirect($url);
    }

    public static function jsonFinish($extra = []) {
        $ok = false;
        $err = '';
        try {
            $result = self::run();
            $ok = !empty($result['success']);
            if (!$ok) {
                $err = self::errorText($result);
            }
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
        if ($ok) {
            $_SESSION['flash_success'] = 'Config saved';
        } elseif ($err !== '') {
            $_SESSION['flash_error'] = $err;
        }
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $ok, 'error' => $err, 'message' => $ok ? 'Config saved' : $err], $extra));
    }
}
