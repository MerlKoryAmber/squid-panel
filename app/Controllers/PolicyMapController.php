<?php
class PolicyMapController {
    public function index($params = []) {
        Auth::requireAuth();
        $map = PolicyMapBuilder::build();
        echo View::render('policy_map.index', [
            'title' => 'Policy map',
            'active' => 'policy_map',
            'svgAccess1' => $map['svg_access_1'],
            'svgAccess2' => $map['svg_access_2'],
            'svgCascade' => $map['svg_cascade'],
            'httpAccessCount' => count($map['http_access']),
            'peerCount' => count($map['peers']),
            'routingCount' => count($map['routing']),
            'accessMeta' => $map['access_meta'],
            'cascadeMeta' => $map['cascade_meta'],
        ]);
    }
}
