<?php
/**
 * Global path and URL constants.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

if (!defined('DOCS_PATH')) {
    define('DOCS_PATH', BASE_PATH . '/docs');
}

if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $baseReal = BASE_PATH ? str_replace('\\', '/', BASE_PATH) : '';
    $docReal = $docRoot ? str_replace('\\', '/', $docRoot) : '';
    $baseUrl = '';

    if ($docReal !== '' && strpos($baseReal, $docReal) === 0) {
        $baseUrl = substr($baseReal, strlen($docReal));
    }

    define('BASE_URL', rtrim(str_replace('\\', '/', $baseUrl), '/'));
}
?>
