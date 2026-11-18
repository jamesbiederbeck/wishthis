# Subfolder deployment

Set the public prefix on the **wishthis PHP container**, including during initial installation:

```yaml
environment:
  WISHTHIS_BASE_PATH: /wishthis
```

Alternatively put `define('BASE_PATH', '/wishthis');` in `src/config/config.php`.
Config values must have a leading slash and no trailing slash. Environment values
accept either form and are normalized. If both are supplied, they must agree.
The default is an empty prefix (domain root). Nested prefixes such as `/apps/wishes`
are supported. This setting does not change filesystem paths.

## SWAG / nginx

The proxy must strip the public prefix before forwarding to the container's Apache
web root. Keep the container's `.htaccess` and `RewriteBase /` unchanged.
For example, adapt the upstream name/port to your existing Docker network:

```nginx
location = /wishthis {
    return 301 /wishthis/$is_args$args;
}

location ^~ /wishthis/ {
    include /config/nginx/proxy.conf;
    include /config/nginx/resolver.conf;
    set $upstream_app wishthis;
    set $upstream_port 80;
    set $upstream_proto http;
    rewrite ^/wishthis/(.*)$ /$1 break;
    proxy_pass $upstream_proto://$upstream_app:$upstream_port;
}
```

PHP generates public links with the prefix even though Apache receives stripped
paths. No response body substitution or redirect rewriting is needed. Serving the
application directly from a physical Apache subdirectory is outside this setup;
its Apache configuration must be adjusted separately.

Rebuild your image using this source branch/commit; setting the variable on an old
image does not add support. If PHP-FPM is used instead of mod_php, ensure the variable
is passed into its worker environment.

## Verification

```bash
php tests/base-path.php ''
php tests/base-path.php /wishthis
php tests/base-path.php /apps/wishes
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

After deployment check installation, login/logout, list and wish creation/editing,
shared links, password-reset email links, and browser network requests. Requests
should stay under the configured prefix. Cookies are scoped to that prefix;
changing it requires signing in again. The manifest uses relative URLs so icons,
launching, and share targets remain inside the mount path.
