<?php
/**
 * Squid-specific configuration templates and defaults
 */

return [
    'default_port' => 3128,
    'icp_port' => 3130,
    'cache_dir' => 'ufs /var/spool/squid 100 16 256',
    'acl_types' => [
        'src' => 'IP addresses / subnets',
        'dst' => 'Destination IP',
        'dstdomain' => 'Destination domains',
        'srcdomain' => 'Source domains',
        'time' => 'Time ranges',
        'port' => 'Ports',
        'myport' => 'Local ports',
        'proto' => 'Protocols',
        'method' => 'HTTP methods',
        'url_regex' => 'URL regex',
        'urlpath_regex' => 'URL path regex',
        'proxy_auth' => 'Authenticated users',
        'ident' => 'Ident lookup',
        'external' => 'External helper',
        'arp' => 'MAC address',
        'req_header' => 'Request header',
        'rep_header' => 'Reply header',
    ],
    'peer_types' => [
        'parent' => 'Parent proxy',
        'sibling' => 'Sibling proxy',
        'multicast' => 'Multicast group',
    ],
    'auth_schemes' => [
        'basic' => 'Basic Authentication',
        'digest' => 'Digest Authentication',
        'negotiate' => 'Negotiate (Kerberos/NTLM)',
    ],
];
