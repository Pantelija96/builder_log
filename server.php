<?php

// Local artisan serve router. PHP's parent index.html discovery otherwise
// assigns /app as SCRIPT_NAME's directory for missing /app/* requests.
$publicPath = __DIR__.'/public';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $publicPath.'/index.php';
