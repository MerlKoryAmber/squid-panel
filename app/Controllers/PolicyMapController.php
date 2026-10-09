<?php
class PolicyMapController {
    public function index($params = []) {
        Auth::requireAuth();
        $map = PolicyMapBuilder::build();
        echo View::render('policy_map.index', [
            'title' => 'Policy map',
            'active' => 'policy_map',
            'httpAccess' => $map['http_access'],
            'peers' => $map['peers'],
            'peerAccess' => $map['peer_access'],
            'routing' => $map['routing'],
            'aclIndex' => $map['acl_index'],
        ]);
    }
}
