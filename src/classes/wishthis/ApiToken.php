<?php

namespace wishthis;

/** One revocable, add-only API credential per account. Never persist the raw token. */
class ApiToken
{
    public static ?User $user = null;

    public static function generate(int $userId): string
    {
        global $database;
        $token = 'wt_' . bin2hex(random_bytes(32));
        $database->query(
            'REPLACE INTO `api_tokens` (`user`, `token_hash`) VALUES (:user, :hash)',
            ['user' => $userId, 'hash' => hash('sha256', $token)]
        );
        return $token;
    }

    public static function revoke(int $userId): void
    {
        global $database;
        $database->query('DELETE FROM `api_tokens` WHERE `user` = :user', ['user' => $userId]);
    }

    public static function exists(int $userId): bool
    {
        global $database;
        return false !== $database->query('SELECT `user` FROM `api_tokens` WHERE `user` = :user', ['user' => $userId])->fetch();
    }

    public static function authenticate(string $header): ?User
    {
        global $database;
        if (!preg_match('/^Bearer (wt_[a-f0-9]{64})$/iD', $header, $matches)) {
            return null;
        }
        $row = $database->query(
            'SELECT users.* FROM `users` JOIN `api_tokens` ON users.id = api_tokens.user WHERE api_tokens.token_hash = :hash',
            ['hash' => hash('sha256', $matches[1])]
        )->fetch(\PDO::FETCH_ASSOC);
        return $row ? new User($row) : null;
    }

    /** Explicit scope allowlist; no access to other API modules or account pages. */
    public static function permits(string $method, string $module, array $get, array $post, int $userId): bool
    {
        global $database;
        if ($method === 'GET' && $module === 'wishlists') {
            return count(array_diff(array_keys($get), ['page', 'module'])) === 0;
        }
        if ($method !== 'POST' || $module !== 'wishes' || isset($post['wish_id'])) {
            return false;
        }
        if (array_diff(array_keys($get), ['page', 'module']) || array_diff(array_keys($post), [
            'wishlist_id', 'wish_title', 'wish_description', 'wish_url', 'wish_image',
            'wish_price', 'wish_priority', 'wish_is_purchasable',
        ])) {
            return false;
        }
        foreach ($post as $value) {
            if (!is_scalar($value)) {
                return false;
            }
        }
        $id = $post['wishlist_id'] ?? '';
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
            return false;
        }
        return false !== $database->query(
            'SELECT `id` FROM `wishlists` WHERE `id` = :id AND `user` = :user',
            ['id' => (int) $id, 'user' => $userId]
        )->fetch();
    }

    public static function error(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ($status === 401) {
            header('WWW-Authenticate: Bearer');
        }
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
