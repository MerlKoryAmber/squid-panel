<?php
class InstructionsController {
    public static function catalog() {
        return [
            'keytab' => [
                'title' => 'Creating a keytab file',
                'view' => 'instructions.keytab',
            ],
            'keytab-merge' => [
                'title' => 'Merging two keytab files',
                'view' => 'instructions.keytab_merge',
            ],
        ];
    }

    public function index($params = []) {
        Auth::requireAuth();
        $slug = $this->requestedSlug($params);
        if ($slug !== '') {
            $this->renderGuide($slug);
            return;
        }
        echo View::render('instructions.index', [
            'title' => 'Instructions',
            'active' => 'instructions',
            'guides' => self::catalog(),
        ]);
    }

    public function show($params = []) {
        Auth::requireAuth();
        $this->renderGuide($this->requestedSlug($params));
    }

    private function requestedSlug($params) {
        $slug = (string)($params['slug'] ?? $_GET['g'] ?? $_GET['slug'] ?? '');
        return preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
    }

    private function renderGuide($slug) {
        $guides = self::catalog();
        if ($slug === '' || !isset($guides[$slug])) {
            http_response_code(404);
            echo View::render('instructions.index', [
                'title' => 'Instructions',
                'active' => 'instructions',
                'guides' => $guides,
            ]);
            return;
        }
        $guide = $guides[$slug];
        echo View::render($guide['view'], [
            'title' => $guide['title'],
            'active' => 'instructions',
            'slug' => $slug,
            'guides' => $guides,
        ]);
    }
}
