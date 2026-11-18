<?php
// Real SQL regression tests: SQLite locally and MariaDB in CI.
define('ROOT', dirname(__DIR__));
define('DEFAULT_LOCALE', 'en_GB');
require ROOT . '/vendor/autoload.php';
require ROOT . '/src/classes/wishthis/Database.php';
require ROOT . '/src/classes/wishthis/User.php';
require ROOT . '/src/classes/wishthis/ApiToken.php';
class TokenTestDatabase extends wishthis\Database {
    public function __construct() {
        $this->pdo = new PDO(getenv('TOKEN_TEST_DSN') ?: 'sqlite::memory:', getenv('TOKEN_TEST_USER') ?: null, getenv('TOKEN_TEST_PASSWORD') ?: null);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function connect(): void {}
}
function check($value, string $message): void {
    if (!$value) { throw new RuntimeException($message); }
}
$database = new TokenTestDatabase();
$mysql = $database->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
if (!$mysql) { $database->pdo->exec('PRAGMA foreign_keys = ON'); }
$database->pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(100), password VARCHAR(100), password_reset_valid_until VARCHAR(30), last_login VARCHAR(30), power INT, language VARCHAR(6), currency VARCHAR(3), advertisements INT)');
$database->pdo->exec('CREATE TABLE wishlists (id INT PRIMARY KEY, user INT)');
if ($mysql) {
    $database->pdo->exec(file_get_contents(ROOT . '/src/update/1-2-4.sql'));
} else {
    $database->pdo->exec('CREATE TABLE api_tokens (user INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, token_hash CHAR(64) UNIQUE, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
}
foreach ([1, 2] as $id) {
    $database->query('INSERT INTO users VALUES (:id, :email, :password, :until, :last, 1, :language, :currency, 0)', ['id'=>$id,'email'=>"user$id@example.test",'password'=>'unused','until'=>'2026-01-01','last'=>'2026-01-01','language'=>'en_GB','currency'=>'USD']);
    $database->query('INSERT INTO wishlists VALUES (:id, :user)', ['id'=>$id,'user'=>$id]);
}
$token = wishthis\ApiToken::generate(1);
check(strlen($token) === 67, 'Strong random token');
$row = $database->query('SELECT * FROM api_tokens')->fetch(PDO::FETCH_ASSOC);
check($row['token_hash'] === hash('sha256', $token) && $row['token_hash'] !== $token, 'Only digest stored');
check(wishthis\ApiToken::exists(1), 'Token exists');
check(wishthis\ApiToken::authenticate('Bearer '.$token)->getId() === 1, 'Authenticate owner');
check(wishthis\ApiToken::authenticate('Basic '.$token) === null, 'Reject wrong scheme');
check(wishthis\ApiToken::authenticate('Bearer wt_'.str_repeat('0',64)) === null, 'Reject unknown token');
$old = $token;
$token = wishthis\ApiToken::generate(1);
check(wishthis\ApiToken::authenticate('Bearer '.$old) === null, 'Rotation invalidates previous token');
$get = ['page'=>'api','module'=>'wishes'];
check(wishthis\ApiToken::permits('POST','wishes',$get,['wishlist_id'=>1],1), 'Allow own list');
check(!wishthis\ApiToken::permits('POST','wishes',$get,['wishlist_id'=>2],1), 'Deny other owner');
check(!wishthis\ApiToken::permits('POST','wishes',$get,['wishlist_id'=>999],1), 'Deny missing list');
check(!wishthis\ApiToken::permits('POST','wishes',$get,['wishlist_id'=>1,'wish_id'=>1],1), 'Deny editing');
check(!wishthis\ApiToken::permits('POST','wishes',$get,['wishlist_id'=>1,'module'=>'settings'],1), 'Deny module override');
check(!wishthis\ApiToken::permits('POST','wishes',$get,['wishlist_id'=>[1]],1), 'Deny array input');
check(!wishthis\ApiToken::permits('DELETE','wishlists',[],['wishlist_id'=>1],1), 'Deny deleting');
check(!wishthis\ApiToken::permits('GET','settings',[],[],1), 'Deny other module');
check(wishthis\ApiToken::permits('GET','wishlists',['module'=>'wishlists'],[],1), 'Allow own list discovery');
check(!wishthis\ApiToken::permits('GET','wishlists',['wishlist_hash'=>'other'],[],1), 'Deny alternate read routes');
$_SESSION = ['untouched'=>true];
$user = wishthis\ApiToken::authenticate('Bearer '.$token);
wishthis\ApiToken::$user = $user;
check(wishthis\User::getCurrent() === $user && $user->isLoggedIn(), 'Request identity');
$user->refreshSession();
check($_SESSION === ['untouched'=>true] && empty($_COOKIE), 'No browser login created');
wishthis\ApiToken::$user = null;
check(!$user->isLoggedIn(), 'Token identity does not persist');
wishthis\ApiToken::revoke(1);
check(wishthis\ApiToken::authenticate('Bearer '.$token) === null, 'Revocation invalidates credential');
$token = wishthis\ApiToken::generate(1);
$database->query('DELETE FROM users WHERE id = :id', ['id'=>1]);
check(!wishthis\ApiToken::exists(1), 'Account deletion cascades');
check(wishthis\ApiToken::authenticate('Bearer '.$token) === null, 'Deleted user cannot authenticate');
echo "PASS API token lifecycle, scope, ownership, and session isolation\n";
