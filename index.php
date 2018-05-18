<?php

// CONFIGURATION
define('TEMPLATES', true); // Enable/Disable templates. When set to false, features like View::load or layouts wont work
define('ROUTES_DIR', 'routes/');

// Run
require_once "core/slid.php";
$slid = new Slid();
$slid->run();