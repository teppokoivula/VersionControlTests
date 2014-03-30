<?php

// Force error reporting on
error_reporting(E_ALL | E_STRICT);

// Bootstrap ProcessWire
$root_path = substr(__FILE__, 0, strrpos(__FILE__, '/site/modules/'));
require_once "$root_path/index.php";