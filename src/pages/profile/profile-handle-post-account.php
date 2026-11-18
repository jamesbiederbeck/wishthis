<?php

/**
 * The "Account" section of the users profile.
 */

namespace wishthis;

/**
 * Token management requires an authenticated browser session and CSRF proof.
 */
if (isset($_POST['api-token-action'])) {
    if (!$user->isLoggedIn() || !isset($_SESSION['api_token_csrf']) || !is_string($_POST['csrf'] ?? null)
        || !hash_equals($_SESSION['api_token_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        die('Invalid token management request.');
    }
    if ($_POST['api-token-action'] === 'generate') {
        $_SESSION['api_token_new'] = ApiToken::generate($user->getId());
    } elseif ($_POST['api-token-action'] === 'revoke') {
        ApiToken::revoke($user->getId());
        unset($_SESSION['api_token_new']);
    }
    redirect(Page::PAGE_PROFILE . '&section=account');
}

/**
 * Account
 */
if (isset($_POST['account-delete'])) {
    $user->delete();
    $user->logOut();

    redirect(Page::PAGE_HOME);
}
