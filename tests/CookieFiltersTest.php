<?php
/**
 * Covers the two cookie filters - the pair that actually make Varnish cache.
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group( 'aai' )]
#[Group( 'ash_woocommerce_cookies' )]
final class CookieFiltersTest extends TestCase {

	/**
	 * The two cookies WooCommerce sets server-side that bust the page cache.
	 *
	 * Spelled out here rather than read from the plugin: the test is worth
	 * having precisely because these are string literals, and a typo in the
	 * plugin fails silently - caching quietly degrades with nothing logged.
	 */
	public static function blocked_cookies(): array {
		return array(
			'cart item count' => array( 'woocommerce_items_in_cart' ),
			'cart hash'       => array( 'woocommerce_cart_hash' ),
		);
	}

	#[DataProvider( 'blocked_cookies' )]
	public function test_blocks_cache_busting_cookie( string $cookie ): void {
		$this->assertFalse(
			ash_wc_block_cart_cookies( true, $cookie ),
			"{$cookie} must not be set server-side or it busts the Varnish cache"
		);
	}

	/**
	 * The filter must be surgical. Returning false for everything would stop
	 * WooCommerce setting its session and auth cookies too, breaking carts
	 * outright - a one-line regression away from the code above.
	 */
	public static function untouched_cookies(): array {
		return array(
			'session cookie'   => array( 'wp_woocommerce_session_abc123' ),
			'recently viewed'  => array( 'woocommerce_recently_viewed' ),
			'unrelated cookie' => array( 'some_other_plugin_cookie' ),
		);
	}

	#[DataProvider( 'untouched_cookies' )]
	public function test_passes_other_cookies_through( string $cookie ): void {
		$this->assertTrue( ash_wc_block_cart_cookies( true, $cookie ) );
	}

	/**
	 * Pass-through means returning the incoming value, not hardcoding true -
	 * otherwise this filter would silently re-enable a cookie another filter
	 * had already disabled.
	 */
	public function test_preserves_disabled_state_from_earlier_filters(): void {
		$this->assertFalse( ash_wc_block_cart_cookies( false, 'some_other_plugin_cookie' ) );
	}

	/**
	 * The STYXKEY_ prefix is the whole fix: Pantheon treats a cookie with that
	 * prefix as cache-varying rather than cache-busting. Lose the prefix and
	 * every logged-in-ish request bypasses Varnish again.
	 */
	public function test_session_cookie_carries_styxkey_prefix(): void {
		$this->assertStringStartsWith( 'STYXKEY_', ash_wc_rename_session_cookie( 'wp_woocommerce_session_' . COOKIEHASH ) );
	}

	public function test_session_cookie_keeps_woocommerce_name_and_hash(): void {
		$this->assertSame(
			'STYXKEY_wp_woocommerce_session_' . COOKIEHASH,
			ash_wc_rename_session_cookie( 'wp_woocommerce_session_' . COOKIEHASH )
		);
	}

	/**
	 * Documents a deliberate quirk: the filter ignores the name handed to it
	 * and always returns the session cookie name. That is fine only because
	 * WooCommerce applies `woocommerce_cookie` solely to the session cookie.
	 * If that ever stops being true this test is where it will surface.
	 */
	public function test_overrides_input_name_unconditionally(): void {
		$this->assertSame(
			'STYXKEY_wp_woocommerce_session_' . COOKIEHASH,
			ash_wc_rename_session_cookie( 'something_else_entirely' )
		);
	}
}
