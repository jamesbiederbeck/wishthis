<?php

/**
 * API
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

// Apache may expose Authorization through either variable after rewriting.
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
if ($authorization !== null) {
    if (!$database || !$options || !$options->getOption('isInstalled')) {
        ApiToken::error(503, 'API unavailable until installation is complete.');
    }
    if ($options->getOption('updateAvailable') || !$database->tableExists('api_tokens')) {
        ApiToken::error(503, 'Run the database update before using API tokens.');
    }
    $tokenUser = ApiToken::authenticate($authorization);
    if ($tokenUser === null) {
        ApiToken::error(401, 'Invalid or revoked API token.');
    }
    $module = $_GET['module'] ?? '';
    if (!is_string($module) || !ApiToken::permits($_SERVER['REQUEST_METHOD'], $module, $_GET, $_POST, $tokenUser->getId())) {
        ApiToken::error(403, 'This token may only list your wishlists and add wishes to lists you own.');
    }
    // Request-scoped identity only: never upgrade the browser session using a token.
    ApiToken::$user = $tokenUser;
    header('Cache-Control: no-store');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'wishlists' => $tokenUser->getWishlists()]);
        exit;
    }
}

$page = new Page(__FILE__, __('API'));
$api  = new API();
$api->do();
