# lylyrose.ir per-host credentials checklist

Human-only. Enter these in `/secure-login/` (admin login) or in each plugin's own settings page. Do not paste values into chat, commits, or docs. Source: CONTINUATION.md "Item 2", re-verified 2026-09-29 and 2026-10-11.

Work in this order. The first item is the one that blocks login today.

## 1. PWSMS (Persian SMS). Blocks OTP login

- **Status:** SMS is a silent no-op. `get_sms_gateway()` returns `PW\PWSMS\Gateways\Logger`. OTP login does not work on live.
- **Needed:** a real gateway account and its API credentials (username, password or API key, sender number).
- **Where:** the PWSMS settings page in wp-admin, then set the gateway to the real provider (not `Logger`).
- **Verify after:** send a test SMS to your own phone from the plugin's test button, then log in with OTP on `/secure-login/`.

## 2. ZarinPal (payment gateway). Before launch

- **Status:** checkout is live but uses the all-zero dummy merchant code with `sandbox: yes`. Payments go to `sandbox.zarinpal.com`.
- **Needed:** a real ZarinPal merchant code (36 characters, not all zeros), and `sandbox: no`.
- **Where:** WooCommerce > Settings > Payments > ZarinPal.
- **Verify after:** place a real low-value test order, confirm it reaches the real ZarinPal gateway, then cancel or refund it.
- **Alternative:** if you can't get a merchant code yet, disable the ZarinPal gateway so customers can't reach the sandbox.

## 3. WP Mail SMTP. Mail today goes through PHP `mail()`

- **Status:** from-identity is set (`info@lylyrose.ir`, "Lyly Rose"). No SMTP provider, so delivery is unreliable.
- **Needed:** an SMTP provider account (host, port, username, API key or password) for the `info@lylyrose.ir` domain.
- **Where:** WP Mail SMTP settings in wp-admin.
- **Verify after:** send a test email from the plugin's test tab and check it arrives outside spam.

## 4. Wordfence (security). Judgement call, not a blocker

- **Status:** installed but inactive. The `wordfence` option is empty.
- **Decide:** activate it or leave it off. If activated, run a scan and pick a firewall mode. Activating it can lock out admins if misconfigured, so keep a second admin session open.
- **Verify after:** `/secure-login/` still loads and the admin can log in.

## After each item

Record the result in `docs/CONTINUATION.md` Item 2, with the date and what was verified. Don't record the credential values.
