# Client API Reverse Proxy Deployment Notes

## NGINX

Place `nginx-client-api.conf` inside the HTTPS `server` block for `tfoepe-inc.com.ph`.
Also add it to the `www.tfoepe-inc.com.ph` server block if visitors use the `www` hostname.
If the admin app also uses `/client-api`, place the same `location` block inside the HTTPS `server` block for `admin.tfoepe-inc.com.ph`.

After editing NGINX:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## LiteSpeed / Apache

The current production server identifies as LiteSpeed. If NGINX is not actually in front of the sites, the NGINX `location` block will not run.

If proxy rewrites are enabled, place the rules from `litespeed-client-api.htaccess` near the top of the `.htaccess` file in each document root:

- `tfoepe-inc.com.ph`
- `www.tfoepe-inc.com.ph`
- `admin.tfoepe-inc.com.ph`

If `[P]` proxy rewrites are disabled by hosting, ask the host to enable reverse proxying or configure a LiteSpeed/OpenLiteSpeed proxy context for `/client-api/` to `https://api.tfoepe-inc.com.ph/`.

## Security Checklist

- Redirect HTTP to HTTPS for `tfoepe-inc.com.ph`, `admin.tfoepe-inc.com.ph`, and `api.tfoepe-inc.com.ph`.
- Disable public stack traces and verbose PHP errors in production. Log errors server-side instead.
- Keep admin and member API authentication checks enabled. The reverse proxy only masks the origin; it is not authentication.
- Add rate limiting on `/client-api/`, especially login, signup, member verification, and admin endpoints.
- If all browser traffic is same-origin through `/client-api`, reduce or remove broad CORS exposure on the API.
- Use `HttpOnly`, `Secure`, and appropriate `SameSite` cookies for sessions. For same-origin proxy requests, `SameSite=Lax` is usually enough; use `SameSite=None; Secure` only when cross-site cookies are required.
- Ensure proxy responses do not expose unnecessary backend headers such as `X-Powered-By`.

## Test

1. Open `https://tfoepe-inc.com.ph`.
2. Open browser DevTools Network tab.
3. Reload the page and use pages that load news, events, officers, videos, and member verification.
4. Confirm requests appear as `https://tfoepe-inc.com.ph/client-api/...`.
5. Confirm no browser request goes to `https://api.tfoepe-inc.com.ph`.
6. Run:

```bash
curl -I https://tfoepe-inc.com.ph/client-api/api/public/home.php
curl -I https://www.tfoepe-inc.com.ph/client-api/api/public/home.php
curl -I https://admin.tfoepe-inc.com.ph/client-api/api/admin/session.php
```

Expected: a normal API response status from the proxied backend, not an NGINX 404.
