Woocommerce caching.
This is Pantheon host specific.

Woocommerce Universal behavior (all hosts):
  - Sets DONOTCACHEPAGE / no-cache headers on cart, checkout, and my-account pages. This is standard and expected — those pages should never be cached.

 On Pantheon:
  - WooCommerce also sets woocommerce_items_in_cart and woocommerce_cart_hash as HTTP cookies whenever someone has items in their cart. On most hosts this is harmless — those cookies are just used by JS for cart fragments.
  - But Pantheon's Varnish treats any unrecognized cookie as a signal to bypass cache entirely. So once a visitor adds something to their cart, every single page they visit becomes uncacheable — not just cart/checkout.
  - You also can't edit VCL on Pantheon, so you can't strip those cookies at the Varnish layer like you would on a self-managed Varnish setup.

What this plugin does:
  1. Blocks woocommerce_items_in_cart and woocommerce_cart_hash from being set server-side.
  2. Moves that data to sessionStorage via the JS patch so cart fragments still work.
  3. Renames the session cookie with STYXKEY_ prefix so Pantheon treats it as cache-varying (separate buckets) instead of cache-busting.

## Tests

```bash
composer install
vendor/bin/phpunit
```

No WordPress install, database or fixture site required — `tests/bootstrap.php` stands in for
the seven WordPress and WooCommerce functions the plugin touches, recording what it was called
with so the enqueue behaviour can be asserted. Runs on PHP 8.1+.

Tagged `aai` and `ash_woocommerce_cookies`, so `--group aai` targets it alongside other
August Ash suites.

What the suite is actually protecting, since every one of these fails **silently** in
production — caching quietly degrades with nothing in any log:

- the two blocked cookie names, and that the filter blocks *only* those two
- the `STYXKEY_` prefix on the session cookie, which is what makes Varnish vary rather than bust
- `DONOTCACHEPAGE` pass-through, so cart/checkout/my-account stay uncacheable
- the hook wiring: `accepted_args` on the cookie filter, and enqueue priority above WooCommerce's
- JSON-escaping of the cart hash seeded into `sessionStorage`

`assets/js/cookie-patch.js` is not covered — the suite verifies the PHP half seeds the right
state, not what the browser does with it.
