<?php
class AclController {
    public function index($params = []) {
        Auth::requireAuth();
        $acls = Database::fetchAll("SELECT * FROM acls ORDER BY name, id");
        foreach ($acls as &$acl) {
            $acl['list_count'] = self::entryCount($acl);
        }
        unset($acl);
        $types = (require SPM_CONFIG . '/squid.php')['acl_types'];
        echo View::render('acl.index', ['title' => 'ACL Management', 'active' => 'acl', 'acls' => $acls, 'types' => $types]);
    }

    public function create($params = []) {
        Auth::requireAdmin();
        $types = (require SPM_CONFIG . '/squid.php')['acl_types'];
        echo View::render('acl.edit', [
            'title' => 'Create ACL',
            'active' => 'acl',
            'types' => $types,
            'acl' => self::takeDraft([]),
            'isAdmin' => true,
            'installNote' => '',
        ]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $saved = $this->saveFromPost(null);
        SquidLiveApply::redirect('/acl/edit?id=' . (int)$saved['id']);
    }

    public function edit($params = []) {
        Auth::requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $acl = Database::fetch("SELECT * FROM acls WHERE id = ?", [$id]);
        if (!$acl) {
            http_response_code(404);
            die('ACL not found');
        }
        if (($acl['storage'] ?? 'inline') === 'file') {
            $acl['entries'] = json_encode(AclListFile::readWorkFile($acl['name']));
        }
        $acl = self::takeDraft($acl);
        $types = (require SPM_CONFIG . '/squid.php')['acl_types'];
        echo View::render('acl.edit', [
            'title' => 'Edit ACL',
            'active' => 'acl',
            'types' => $types,
            'acl' => $acl,
            'isAdmin' => Auth::isAdmin(),
            'installNote' => $_GET['installed'] ?? '',
        ]);
    }

    public function update($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $acl = Database::fetch("SELECT * FROM acls WHERE id = ?", [$id]);
        if (!$acl) {
            http_response_code(404);
            die('ACL not found');
        }
        $saved = $this->saveFromPost($acl);
        $q = !empty($saved['installed']) ? '&installed=1' : '';
        SquidLiveApply::redirect('/acl/edit?id=' . (int)$saved['id'] . $q);
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $acl = Database::fetch("SELECT * FROM acls WHERE id = ?", [$id]);
        if ($acl) {
            Database::query("DELETE FROM acls WHERE id = ?", [$id]);
            if (($acl['type'] ?? '') === 'external') {
                $entries = json_decode($acl['entries'] ?? '[]', true);
                $helper = is_array($entries) ? trim((string)($entries[0] ?? '')) : '';
                if ($helper !== '' && strpos($helper, AdGroupAcl::HELPER_PREFIX) === 0) {
                    $still = Database::fetch(
                        "SELECT id FROM acls WHERE type = 'external' AND entries = ?",
                        [json_encode([$helper])]
                    );
                    if (!$still) {
                        Database::query("DELETE FROM external_acl_types WHERE name = ?", [$helper]);
                    }
                }
            }
            $work = AclListFile::workDir() . '/' . preg_replace('/[^A-Za-z0-9._-]/', '', $acl['name']) . '.txt';
            if (is_file($work)) {
                @unlink($work);
            }
            Audit::log('acl_delete', "Deleted ACL {$acl['name']}");
        }
        SquidLiveApply::redirect('/acl');
    }

    private function saveFromPost($existing) {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? ($existing['name'] ?? ''));
        $type = $_POST['type'] ?? ($existing['type'] ?? '');
        if ($existing) {
            $type = $existing['type'];
        }
        $raw = $_POST['entries'] ?? $_POST['values'] ?? '';
        $values = AclListFile::parseLines($raw);
        $description = $_POST['description'] ?? ($existing['description'] ?? '');
        $group = $_POST['group_name'] ?? ($existing['group_name'] ?? '');
        $wantFile = !empty($_POST['storage_file']);

        if ($name === '' || $type === '' || empty($values)) {
            $this->bounceSave($existing, 'Name, type and values are required');
        }

        foreach ($values as $val) {
            if (!$this->validateAclValue($type, $val)) {
                $this->bounceSave($existing, self::invalidValueMessage($type, $val));
            }
        }

        $useFile = $wantFile || (AclListFile::isFileType($type) && count($values) >= AclListFile::AUTO_FILE_MIN);
        if ($useFile && !AclListFile::isFileType($type)) {
            $useFile = false;
        }

        $storage = $useFile ? 'file' : 'inline';
        $entriesJson = $useFile ? json_encode([]) : json_encode(array_values($values));
        $installed = false;

        if ($useFile) {
            AclListFile::writeWorkFile($name, $values);
            $copy = AclListFile::installLive($name);
            $installed = !empty($copy['success']);
        }

        if ($existing) {
            Database::query(
                "UPDATE acls SET entries = ?, storage = ?, description = ?, group_name = ?, updated_at = datetime('now') WHERE id = ?",
                [$entriesJson, $storage, $description, $group, (int)$existing['id']]
            );
            $id = (int)$existing['id'];
            Audit::log('acl_update', "Updated ACL {$name} ({$storage}, " . count($values) . " values)");
        } else {
            $id = (int)Database::insert(
                "INSERT INTO acls (name, type, entries, storage, description, group_name, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
                [$name, $type, $entriesJson, $storage, $description, $group]
            );
            Audit::log('acl_create', "Created ACL {$name} ({$type}, {$storage})");
        }

        return ['id' => $id, 'installed' => $installed];
    }

    private static function takeDraft($acl) {
        $d = $_SESSION['acl_draft'] ?? null;
        unset($_SESSION['acl_draft']);
        if (!is_array($d)) {
            return $acl;
        }
        if (is_array($acl) && !empty($acl['id']) && (int)($d['id'] ?? 0) !== (int)$acl['id']) {
            return $acl;
        }
        if ($acl === null) {
            unset($d['id']);
            return $d;
        }
        return array_merge($acl, $d);
    }

    private function bounceSave($existing, $error) {
        $existing = is_array($existing) ? $existing : [];
        $_SESSION['flash_error'] = $error;
        $raw = (string)($_POST['entries'] ?? $_POST['values'] ?? '');
        $_SESSION['acl_draft'] = [
            'id' => $existing['id'] ?? null,
            'name' => (string)($_POST['name'] ?? ($existing['name'] ?? '')),
            'type' => (string)($_POST['type'] ?? ($existing['type'] ?? '')),
            'entries' => json_encode(AclListFile::parseLines($raw)),
            'storage' => !empty($_POST['storage_file']) ? 'file' : 'inline',
            'description' => (string)($_POST['description'] ?? ($existing['description'] ?? '')),
            'group_name' => (string)($_POST['group_name'] ?? ($existing['group_name'] ?? '')),
        ];
        if (!empty($existing['id'])) {
            View::redirect('/acl/edit?id=' . (int)$existing['id']);
        }
        View::redirect('/acl/create');
    }

    private static function invalidValueMessage($type, $val) {
        $msg = 'Invalid value for type ' . $type . ': ' . $val;
        if (($type === 'dst' || $type === 'src') && preg_match('/[A-Za-z]/', $val)) {
            $msg .= '. Hostnames use dstdomain or srcdomain, not dst/src.';
        }
        return $msg;
    }

    private static function entryCount($acl) {
        if (($acl['storage'] ?? 'inline') === 'file') {
            return AclListFile::countWorkFile($acl['name'] ?? '');
        }
        $vals = json_decode($acl['entries'] ?? '[]', true);
        return is_array($vals) ? count($vals) : 0;
    }

    private function validateAclValue($type, $value) {
        switch ($type) {
            case 'src':
            case 'dst':
                return filter_var($value, FILTER_VALIDATE_IP) !== false ||
                       preg_match('/^\d+\.\d+\.\d+\.\d+\/\d+$/', $value) ||
                       preg_match('/^\d+\.\d+\.\d+\.\d+-\d+\.\d+\.\d+\.\d+$/', $value);
            case 'dstdomain':
            case 'srcdomain':
                return (bool)preg_match('/^[a-zA-Z0-9.*-]+$/', $value);
            case 'time':
                return (bool)preg_match('/^[A-Z]{1,7}\s+\d{2}:\d{2}-\d{2}:\d{2}$/', $value);
            case 'port':
            case 'myport':
                return (bool)preg_match('/^\d+(-\d+)?$/', $value);
            case 'url_regex':
            case 'urlpath_regex':
                return @preg_match($value, '') !== false;
            default:
                return strlen($value) > 0;
        }
    }
}
