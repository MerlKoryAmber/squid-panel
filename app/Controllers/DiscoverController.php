<?php
class DiscoverController {
    public function index($params = []) {
        Auth::requireAuth();
        $flashError = $_SESSION['flash_error'] ?? '';
        $flashSuccess = $_SESSION['flash_success'] ?? '';
        $domains = $_SESSION['discover_domains'] ?? [];
        $lastUrl = $_SESSION['discover_url'] ?? '';
        $hideAds = array_key_exists('discover_hide_ads', $_SESSION)
            ? !empty($_SESSION['discover_hide_ads'])
            : true;
        $removedAds = (int)($_SESSION['discover_removed_ads'] ?? 0);
        unset(
            $_SESSION['flash_error'],
            $_SESSION['flash_success'],
            $_SESSION['discover_domains'],
            $_SESSION['discover_url'],
            $_SESSION['discover_removed_ads']
        );
        // keep hide preference across page views
        $_SESSION['discover_hide_ads'] = $hideAds ? 1 : 0;
        if (!is_array($domains)) {
            $domains = [];
        }
        echo View::render('discover.index', [
            'title' => 'Domain discover',
            'active' => 'discover',
            'isAdmin' => Auth::isAdmin(),
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
            'domains' => $domains,
            'lastUrl' => $lastUrl,
            'hideAds' => $hideAds,
            'removedAds' => $removedAds,
            'denylistCount' => count(DomainDiscover::adDenylistMap()),
        ]);
    }

    public function run($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $hideAds = !empty($_POST['hide_ads']);
        $_SESSION['discover_hide_ads'] = $hideAds ? 1 : 0;
        try {
            $url = DomainDiscover::normalizeUrl($_POST['url'] ?? '');
            DomainDiscover::writeStaging($url);
            $result = PrivilegedExecutor::execute('domain_discover');
            @unlink((defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp/' . DomainDiscover::STAGING);
            if (empty($result['success'])) {
                $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'discover failed')));
                throw new Exception($err);
            }
            $raw = trim((string)($result['stdout'] ?? ''));
            $hosts = preg_split('/\r\n|\n|\r/', $raw);
            $hosts = array_values(array_filter(array_map('trim', $hosts)));
            $hosts = DomainDiscover::uniqueSecondLevel($hosts);
            $removed = 0;
            if ($hideAds) {
                $filt = DomainDiscover::filterAdDomains($hosts);
                $hosts = $filt['kept'];
                $removed = (int)$filt['removed'];
            }
            $_SESSION['discover_domains'] = $hosts;
            $_SESSION['discover_url'] = $url;
            $_SESSION['discover_removed_ads'] = $removed;
            Audit::log(
                'domain_discover',
                $url . ' → ' . count($hosts) . ' domains'
                . ($hideAds ? (' (ads filtered ' . $removed . ')') : '')
            );
            $msg = count($hosts)
                ? ('Found ' . count($hosts) . ' second-level domain(s).')
                : 'No domains captured (page empty, blocked, filtered, or browser missing).';
            if ($hideAds && $removed > 0) {
                $msg .= ' Hid ' . $removed . ' common ad/analytics domain(s).';
            }
            $_SESSION['flash_success'] = $msg;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $_SESSION['discover_url'] = trim((string)($_POST['url'] ?? ''));
        }
        View::redirect('/discover');
    }
}
