<?php

// CONFIGURATION
define('TEMPLATES', true); // Enable/Disable templates. When set to false, features like View::load or layouts wont work (except error layouts)
define('ROUTES_DIR', 'routes/');
define('VIEWS_DIR', 'views/');
define('LAYOUTS_DIR', 'layouts/');
define('CORE_DIR', 'core/');

// Run
require_once CORE_DIR . 'slid.php';

$slid = new Slid();
$slid->runRouting();