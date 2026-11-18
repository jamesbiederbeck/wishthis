<?php

/** Public URL prefix. Filesystem ROOT is deliberately independent of this value. */
function wishthis_base_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return '';
    }
    if (!preg_match('~^/?[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*/?$~D', $path)) {
        throw new InvalidArgumentException('BASE_PATH must be a path such as /wishthis, without a host, query or dot segments.');
    }
    return '/' . trim($path, '/');
}

/** Remove a public prefix only at a path boundary; also accepts proxy-stripped paths. */
function wishthis_internal_path(string $path): string
{
    if (BASE_PATH !== '' && ($path === BASE_PATH || str_starts_with($path, BASE_PATH . '/') || str_starts_with($path, BASE_PATH . '?'))) {
        $path = substr($path, strlen(BASE_PATH));
        return $path === '' ? '/' : ($path[0] === '?' ? '/' . $path : $path);
    }
    return $path;
}

function wishthis_url(string $path): string
{
    return BASE_PATH . '/' . ltrim(wishthis_internal_path($path), '/');
}
