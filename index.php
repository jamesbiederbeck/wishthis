<?php

/**
 * wishthis - Make a wish
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

define('VERSION', '1.2.3');
define('ROOT', __DIR__);
define('DEFAULT_LOCALE', 'en_GB');

/**
 * Include
 */
require 'vendor/autoload.php';

require_once ROOT . '/src/functions/getCookieDomain.php';
require_once ROOT . '/src/functions/gettext.php';
require_once ROOT . '/src/functions/getWishlistNameSuggestion.php';
require_once ROOT . '/src/functions/redirect.php';

spl_autoload_register(
    function (string $absoluteNamespace) {
        if (__NAMESPACE__ !== substr($absoluteNamespace, 0, strlen(__NAMESPACE__))) {
            return;
        }

        $absoluteNamespace = str_replace('\\', '/', $absoluteNamespace);

        $filepath = ROOT . '/src/classes/' . $absoluteNamespace . '.php';

        require $filepath;
    }
);

/**
 * Config
 */
$configPath = __DIR__ . '/' . 'src/config/config.php';
$config     = new Config($configPath);
$config->load();

require_once ROOT . '/src/functions/basePath.php';
// Environment works before the installer creates config.php. Conflicting settings fail explicitly.
$basePath = getenv('WISHTHIS_BASE_PATH');
$basePath = wishthis_base_path($basePath !== false ? $basePath : (defined('BASE_PATH') ? BASE_PATH : ''));
if (defined('BASE_PATH') && BASE_PATH !== $basePath) {
    throw new \InvalidArgumentException('BASE_PATH config must be normalized and agree with WISHTHIS_BASE_PATH.');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}


/**
 * Session
 *
 * Has to be setup first, before anything else, so translations can be loaded.
 * The configuration is the only exception, since `loadFromSession` needs the
 * database.
 */
session_start(
    [
        'name' => 'wishthis',
        'cookie_path' => BASE_PATH . '/',
    ]
);

$user = User::getCurrent();

/**
 * Database
 */
$database = false;
$options  = false;

if (
       defined('DATABASE_HOST')
    && defined('DATABASE_NAME')
    && defined('DATABASE_USER')
    && defined('DATABASE_PASSWORD')
) {
    $database = new Database(
        DATABASE_HOST,
        DATABASE_NAME,
        DATABASE_USER,
        DATABASE_PASSWORD
    );
    $database->connect();

    /**
     * Options
     */
    $options = new Options($database);

    /**
     * User session
     */
    $user->loadFromSession();
}

/**
 * Language
 */
\Locale::setDefault(DEFAULT_LOCALE);

/** Determine Locale */
$locales = array_filter(
    array_map(
        function ($value) {
            $extension = pathinfo($value, PATHINFO_EXTENSION);
            $filename  = pathinfo($value, PATHINFO_FILENAME);

            if ('po' === $extension) {
                return pathinfo($value, PATHINFO_FILENAME);
            }
        },
        scandir(ROOT . '/translations')
    )
);

$locale = isset($_REQUEST['locale']) ? $_REQUEST['locale'] : \Locale::lookup($locales, $user->getLocale(), false, 'en_GB');

/**
 * Wish
 */
Wish::initialize();

/**
 * Pretty URLs
 */
$url = new URL($_SERVER['REQUEST_URI']);

/**
 * Database Update
 */
if ($options && $options->getOption('isInstalled')) {
    if (-1 === version_compare($options->version, VERSION)) {
        $options->setOption('updateAvailable', true);
    }
}

/**
 * Page
 */
if (!isset($page)) {
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
}
$pagePath    = 'src/pages/' . $page . '.php';
$pagePathAlt = 'src/pages/' . $page . '/' . $page . '.php';

if (file_exists($pagePath)) {
    require $pagePath;
} elseif (\file_exists($pagePathAlt)) {
    require $pagePathAlt;
} else {
    http_response_code(404);
    ?>
    <h1>Not found</h1>
    <p>The requested URL was not found on this server.</p>
    <?php
    echo $pagePath;
    die();
}
