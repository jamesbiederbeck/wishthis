<?php
/** Standalone regression suite; run each prefix in a separate PHP process. */
define('ROOT', dirname(__DIR__));
define('BASE_PATH', $argv[1] ?? '');
require ROOT . '/src/functions/basePath.php';
require ROOT . '/src/classes/wishthis/URL.php';
require ROOT . '/src/classes/wishthis/Page.php';
require ROOT . '/src/classes/wishthis/Wish.php';
function same($expected, $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException(var_export([$expected, $actual], true));
    }
}
foreach (['' => '', '/' => '', 'wishthis/' => '/wishthis', '/apps/wishes/' => '/apps/wishes'] as $input => $expected) {
    same($expected, wishthis_base_path($input));
}
foreach (['//evil.test', '/a/../b', '/a?b', '/a#b', '/a\\b', '/a"b', '/a%2fb'] as $input) {
    try {
        wishthis_base_path($input);
        throw new RuntimeException('Accepted invalid prefix: ' . $input);
    } catch (InvalidArgumentException $e) {
    }
}
same(BASE_PATH . '/', wishthis_url('/'));
same(BASE_PATH . '/login', wishthis_url('/login'));
same(BASE_PATH . '/login', wishthis_url(BASE_PATH . '/login'));
same('/wishthis-other/login', wishthis_internal_path('/wishthis-other/login'));
same(BASE_PATH . '/index.php?page=install', wishthis\Page::PAGE_INSTALL);
same(BASE_PATH . '/src/assets/img/no-image.svg', wishthis\Wish::NO_IMAGE);
$routes = [
    '/index.php?page=install' => ['page' => 'install'],
    '/index.php?page=api&module=database-test' => ['page' => 'api', 'module' => 'database-test'],
    '/api/database-test' => ['page' => 'api', 'module' => 'database-test'],
    '/wishlists/42' => ['page' => 'wishlists', 'id' => '42'],
    '/wishlist/' . str_repeat('a', 40) => ['page' => 'wishlist', 'hash' => str_repeat('a', 40)],
    '/wishlists/42/add' => ['page' => 'wishlists', 'id' => '42', 'wish_add' => 'true'],
];
foreach ($routes as $path => $expected) {
    foreach ([$path, BASE_PATH . $path] as $request) {
        $url = new wishthis\URL($request);
        same($expected, $_GET);
        $pretty = $url->getPretty();
        if (BASE_PATH !== '' && !str_starts_with($pretty, BASE_PATH . '/')) {
            throw new RuntimeException('Missing prefix: ' . $pretty);
        }
        new wishthis\URL($pretty);
        same($expected, $_GET);
    }
}
echo 'PASS prefix ' . (BASE_PATH ?: '/') . PHP_EOL;
