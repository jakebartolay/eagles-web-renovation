# Production Client API Proxy Links

Upload the latest frontend/admin builds, then configure `/client-api/` on each public hostname.

## Required Proxy Roots

Website non-www:

```txt
https://tfoepe-inc.com.ph/client-api/ -> https://api.tfoepe-inc.com.ph/
```

Website www:

```txt
https://www.tfoepe-inc.com.ph/client-api/ -> https://api.tfoepe-inc.com.ph/
```

Admin:

```txt
https://admin.tfoepe-inc.com.ph/client-api/ -> https://api.tfoepe-inc.com.ph/
```

## LiteSpeed / Apache .htaccess Rule

Put this near the top of the `.htaccess` file for each document root above:

```apache
RewriteEngine On
RewriteRule ^client-api/(.*)$ https://api.tfoepe-inc.com.ph/$1 [P,L,QSA]
```

If this causes 500 or still returns HTML/404, the host likely disabled proxy rewrites. Ask hosting support to create a LiteSpeed reverse proxy context:

```txt
URI: /client-api/
Target: https://api.tfoepe-inc.com.ph/
```

## Test Links

These must return API JSON, not HTML:

```txt
https://tfoepe-inc.com.ph/client-api/api/public/home.php
https://www.tfoepe-inc.com.ph/client-api/api/public/home.php
https://www.tfoepe-inc.com.ph/client-api/v1/client/officers/get_all.php?category=national_officers
https://admin.tfoepe-inc.com.ph/client-api/api/admin/session.php
```

Admin login should POST here:

```txt
https://admin.tfoepe-inc.com.ph/client-api/api/admin/login.php
```

Expected browser Network URLs:

```txt
https://www.tfoepe-inc.com.ph/client-api/...
https://admin.tfoepe-inc.com.ph/client-api/...
```

The browser should not call:

```txt
https://api.tfoepe-inc.com.ph/...
```
