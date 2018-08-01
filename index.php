<?php

// CONFIGURATION
define('DATABASE', true);
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', '');
define('DB_PORT', 3306);

define('TEMPLATES', true); // Enable/Disable templates. When set to false, features like View::load or layouts wont work (except error layouts)

define('LANG', true);
define('DEFAULT_LANG', 'en');

define('ROUTES_DIR', 'routes/');
define('VIEWS_DIR', 'views/');
define('LAYOUTS_DIR', 'layouts/');
define('QUERIES_DIR', 'queries/');
define('CORE_DIR', 'core/');
define('LANG_DIR', 'local/');


// Run
require_once CORE_DIR . 'Slid.php';

$slid = new Slid();
$slid->runRouting();