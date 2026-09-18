<?php
// Copy to config.php on the VPS. Values come from container environment.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$env = static function ($key, $default = '') {
    $val = getenv($key);
    return ($val === false || $val === '') ? $default : $val;
};

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = $env('MOODLE_DATABASE_HOST', 'mysql');
$CFG->dbname    = $env('MOODLE_DATABASE_NAME', 'db_moodle');
$CFG->dbuser    = $env('MOODLE_DATABASE_USER', 'moodle_user');
$CFG->dbpass    = $env('MOODLE_DATABASE_PASSWORD', 'moodle_pass');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport' => 3306,
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];

$CFG->wwwroot   = rtrim($env('MOODLE_WWWROOT', 'http://moodle.localtest.me:8081'), '/');
$CFG->dataroot  = $env('MOODLE_DATAROOT', '/var/www/moodledata');
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

if ($env('MOODLE_SSLPROXY', '') === '1') {
    // TLS terminates at nginx; Apache still sees the public hostname.
    // reverseproxy must stay off — Moodle refuses wwwroot when it is on.
    $CFG->sslproxy = true;
}

$redispass = $env('MOODLE_REDIS_PASSWORD', $env('WP_REDIS_PASSWORD', ''));
$redishost = $env('MOODLE_REDIS_HOST', 'redis');
if ($redishost !== '' && extension_loaded('redis')) {
    $CFG->session_handler_class = '\core\session\redis';
    $CFG->session_redis_host = $redishost;
    $CFG->session_redis_port = 6379;
    $CFG->session_redis_acquire_lock_timeout = 120;
    $CFG->session_redis_lock_expire = 7200;
    if ($redispass !== '') {
        $CFG->session_redis_auth = $redispass;
    }
}

if ($env('MOODLE_BLOCK_PRIVATE_HOSTS', '') === '1') {
    $CFG->curlsecurityblockedhosts = "127.0.0.0/8\n10.0.0.0/8\n172.16.0.0/12\n192.168.0.0/16\nlocalhost\n169.254.169.254";
}

require_once(__DIR__ . '/lib/setup.php');
