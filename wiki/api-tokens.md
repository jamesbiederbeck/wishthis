# Agent API tokens

After updating to version 1.2.4, run the normal database update as an administrator.
New installations include the token table automatically.

Go to **Account → Profile → Account** and use **Generate token**, above Delete
account. The raw token is shown once. Only its SHA-256 digest is stored in the
database. Each account has one token; regeneration replaces it, and revocation
invalidates it immediately. Deleting an account also deletes its token.

Tokens are restricted to discovering your lists and adding new wishes to them.
They cannot edit/delete wishes, access other users' lists, or change account
settings. Existing browser sessions still work as before. Token requests never
sign the browser session in. An invalid Authorization header fails rather than
falling back to browser cookies. Tokens are accepted only in the Authorization
header, not URL parameters. Use HTTPS.

```bash
# Set WISHTHIS_TOKEN from your secret store.
base=https://wishthis.hiddencove.xyz
curl --fail-with-body -H "Authorization: Bearer $WISHTHIS_TOKEN" \
  "$base/index.php?page=api&module=wishlists"

curl --fail-with-body -H "Authorization: Bearer $WISHTHIS_TOKEN" \
  "$base/index.php?page=api&module=wishes" \
  --data-urlencode 'wishlist_id=1' \
  --data-urlencode 'wish_url=https://www.amazon.com/dp/PRODUCT_ASIN' \
  --data-urlencode 'wish_title=Product name' \
  --data-urlencode 'wish_description=Selected size, model, and color' \
  --data-urlencode 'wish_is_purchasable=1'
```

For a subfolder deployment, append your configured prefix to `base`.
List discovery returns `{"success":true,"wishlists":[...]}`. Creation uses the
existing form-encoded endpoint and returns `lastInsertId`; require that ID to
confirm insertion. Amazon metadata extraction is best effort, so supply a title.
The server still tries fetching product metadata when a URL is supplied.

Errors: invalid/revoked credentials return 401; requests outside the token scope
return 403; installation or token migration not completed returns 503.
Apache's rewrite rules forward Authorization to PHP. Other reverse proxies/PHP
setups must preserve the Authorization header too.
