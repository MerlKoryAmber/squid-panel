<?php
class PolicyMapController {
    public function index($params = []) {
        Auth::requireAuth();
        $map = PolicyMapBuilder::build();
        echo View::render('policy_map.index', [
            'title' => 'Policy map',
            'active' => 'policy_map',
            'svgAccess' => $map['svg_access'],
            'svgCascade' => $map['svg_cascade'],
            'httpAccessCount' => count($map['http_access']),
            'peerCount' => count($map['peers']),
            'routingCount' => count($map['routing']),
        ]);
    }
}
