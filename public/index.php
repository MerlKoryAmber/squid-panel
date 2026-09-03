<?php
/**
 * Squid Proxy Manager (SPM)
 * Entry point
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/View.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/Audit.php';

// Load all controllers
foreach (glob(__DIR__ . '/../app/Controllers/*.php') as $file) {
    require_once $file;
}

require_once __DIR__ . '/../app/Services/SquidConfigParser.php';

// Load all services
foreach (glob(__DIR__ . '/../app/Services/*.php') as $file) {
    require_once $file;
}

// Load all models
foreach (glob(__DIR__ . '/../app/Models/*.php') as $file) {
    require_once $file;
}

// Session handling — robust start with cleanup of stale/broken sessions
@ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
@ini_set('session.cookie_lifetime', 0);
@ini_set('session.use_strict_mode', 1);

// Try to start session; if the session file is corrupted, destroy and restart
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    session_destroy();
    session_start();
}

// Initialize database
Database::init();

// Initialize router
$router = new Router();

// Auth routes
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

$router->get('/', 'DashboardController@index');
$router->get('/dashboard', 'DashboardController@index');

// Squid Service Management
$router->get('/service/status', 'SquidServiceController@status');
$router->post('/service/start', 'SquidServiceController@start');
$router->post('/service/stop', 'SquidServiceController@stop');
$router->post('/service/restart', 'SquidServiceController@restart');
$router->post('/service/reconfigure', 'SquidServiceController@reconfigure');

// ACL Management
$router->get('/acl', 'AclController@index');
$router->get('/acl/create', 'AclController@create');
$router->post('/acl/store', 'AclController@store');
$router->get('/acl/edit', 'AclController@edit');
$router->post('/acl/update', 'AclController@update');
$router->post('/acl/delete', 'AclController@delete');
$router->get('/acl/ad-groups', 'AdGroupController@index');
$router->post('/acl/ad-groups/ldap', 'AdGroupController@saveLdap');
$router->post('/acl/ad-groups/import', 'AdGroupController@import');

// HTTP Access Rules
$router->get('/http_access', 'HttpAccessController@index');
$router->get('/http_access/create', 'HttpAccessController@create');
$router->get('/http_access/edit', 'HttpAccessController@edit');
$router->post('/http_access/update', 'HttpAccessController@update');
$router->post('/http_access/reorder', 'HttpAccessController@reorder');
$router->post('/http_access/store', 'HttpAccessController@store');
$router->post('/http_access/delete', 'HttpAccessController@delete');
$router->post('/http_access/toggle', 'HttpAccessController@toggle');

// Cache Peers (Cascade)
$router->get('/peers', 'CachePeerController@index');
$router->get('/peers/create', 'CachePeerController@create');
$router->post('/peers/store', 'CachePeerController@store');
$router->get('/peers/edit', 'CachePeerController@edit');
$router->post('/peers/update', 'CachePeerController@update');
$router->post('/peers/delete', 'CachePeerController@delete');
$router->get('/peers/routing', 'CachePeerController@routing');
$router->post('/peers/routing/store', 'CachePeerController@storeRouting');
$router->post('/peers/routing/delete', 'CachePeerController@deleteRouting');
$router->post('/peers/routing/reorder', 'CachePeerController@reorderRouting');

$router->post('/peers/routes/store', 'CachePeerController@storeRoute');
$router->post('/peers/routes/delete', 'CachePeerController@deleteRoute');
$router->post('/peers/routes/reorder', 'CachePeerController@reorderRoutes');

// Authentication (Kerberos/NTLM/Basic)
$router->get('/auth', 'AuthConfigController@index');
$router->get('/auth/kerberos', 'AuthConfigController@kerberos');
$router->post('/auth/kerberos/save', 'AuthConfigController@saveKerberos');
$router->post('/auth/kerberos/upload', 'AuthConfigController@uploadKerberosKeytab');
$router->post('/auth/kerberos/test', 'AuthConfigController@testKerberos');
$router->get('/auth/ntlm', 'AuthConfigController@ntlm');
$router->post('/auth/ntlm/save', 'AuthConfigController@saveNtlm');
$router->get('/auth/basic', 'AuthConfigController@basic');
$router->post('/auth/basic/save', 'AuthConfigController@saveBasic');

// Users & Groups (for Basic Auth)
$router->get('/users', 'UserController@index');
$router->post('/users/store', 'UserController@store');
$router->post('/users/delete', 'UserController@delete');
$router->post('/users/password', 'UserController@password');

// Logs
$router->get('/logs', 'LogController@index');
$router->get('/logs/live', 'LogController@live');
$router->get('/logs/api/stream', 'LogController@stream');
$router->post('/logs/filter', 'LogController@filter');
$router->get('/logs/export', 'LogController@export');

// Statistics
$router->get('/stats', 'StatsController@index');
$router->get('/stats/api/data', 'StatsController@data');

// Audit
$router->get('/audit', 'AuditController@index');
$router->get('/live-config', 'SquidConfController@index');
$router->get('/squid-conf', 'SquidConfController@index');

$router->get('/instructions', 'InstructionsController@index');
$router->get('/instructions/{slug}', 'InstructionsController@show');

// Settings
$router->get('/settings', 'SettingsController@index');
$router->post('/settings/save', 'SettingsController@save');
$router->post('/settings/squid', 'SettingsController@saveSquid');
$router->post('/settings/allow', 'SettingsController@saveAllow');
$router->post('/settings/apply-policy', 'SettingsController@applyPolicy');

// Cache Peer Access Rules
$router->get('/peers/access', 'CachePeerController@access');
$router->get('/peers/access/edit', 'CachePeerController@editAccess');
$router->post('/peers/access/update', 'CachePeerController@updateAccess');
$router->post('/peers/access/store', 'CachePeerController@storeAccess');
$router->post('/peers/access/delete', 'CachePeerController@deleteAccess');
$router->post('/peers/access/reorder', 'CachePeerController@reorderAccess');

// API endpoints (AJAX)
$router->get('/api/squid/status', 'DashboardController@apiStatus');
$router->get('/api/squid/stats', 'DashboardController@apiStats');

// Run router
$router->dispatch();
