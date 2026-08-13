<?php
/**
 * Covers the client-side half: because the cart cookies are no longer set
 * server-side, the browser has to learn the cart state some other way. This
 * function is what seeds it.
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group( 'aai' )]
#[Group( 'ash_woocommerce_cookies' )]
final class CartScriptTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		ash_test_reset();
	}

	/**
	 * Runs on every front-end request, including ones where WooCommerce has not
	 * built a cart yet. Without the guard this fatals on ->get_cart_hash().
	 */
	public function test_does_nothing_without_cart(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( null );

		ash_wc_patch_cart_cookies_js();

		$this->assertSame( array(), $GLOBALS['ash_test_spy']['enqueued'] );
		$this->assertSame( array(), $GLOBALS['ash_test_spy']['inline'] );
	}

	public function test_enqueues_patch_script_with_js_cookie_dependency(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( new Ash_Test_Cart( 0, 'abc' ) );

		ash_wc_patch_cart_cookies_js();

		$this->assertCount( 1, $GLOBALS['ash_test_spy']['enqueued'] );

		$script = $GLOBALS['ash_test_spy']['enqueued'][0];

		// The patch overrides js-cookie's get/set, so it has to load after it.
		$this->assertContains( 'wc-js-cookie', $script['deps'] );
		$this->assertSame( 'ash-wc-cookie-patch', $script['handle'] );
		$this->assertStringEndsWith( 'assets/js/cookie-patch.js', $script['src'] );
	}

	/**
	 * The inline seed must run BEFORE the patch script, or the patch reads an
	 * empty sessionStorage and the cart indicator renders empty on a cached page.
	 */
	public function test_seeds_cart_state_before_patch_runs(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( new Ash_Test_Cart( 3, 'hash123' ) );

		ash_wc_patch_cart_cookies_js();

		$this->assertCount( 1, $GLOBALS['ash_test_spy']['inline'] );
		$this->assertSame( 'before', $GLOBALS['ash_test_spy']['inline'][0]['position'] );
		$this->assertSame( 'ash-wc-cookie-patch', $GLOBALS['ash_test_spy']['inline'][0]['handle'] );
	}

	/**
	 * A non-empty cart must seed "1". This is what keeps the cart indicator
	 * correct for a visitor served a cached page.
	 */
	public function test_seeds_flag_when_cart_has_items(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( new Ash_Test_Cart( 2, 'hash123' ) );

		ash_wc_patch_cart_cookies_js();
		$seed = $GLOBALS['ash_test_spy']['inline'][0]['data'];

		$this->assertStringContainsString( 'sessionStorage.setItem("ash_woocommerce_items_in_cart","1")', $seed );
		$this->assertStringContainsString( 'sessionStorage.setItem("ash_woocommerce_cart_hash","hash123")', $seed );
	}

	public function test_seeds_zero_flag_for_empty_cart(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( new Ash_Test_Cart( 0, '' ) );

		ash_wc_patch_cart_cookies_js();

		$this->assertStringContainsString( 'ash_woocommerce_items_in_cart","0"', $GLOBALS['ash_test_spy']['inline'][0]['data'] );
	}

	/**
	 * The seed is built with sprintf into a JS string literal, so a hash
	 * containing a quote would break the script and take the page's JS with it.
	 * wp_json_encode is what prevents that; this is the test that notices if
	 * someone swaps it for plain concatenation.
	 */
	public function test_escapes_cart_hash_for_javascript(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( new Ash_Test_Cart( 1, 'ab"cd' ) );

		ash_wc_patch_cart_cookies_js();
		$seed = $GLOBALS['ash_test_spy']['inline'][0]['data'];

		$this->assertStringContainsString( '"ab\"cd"', $seed );
		$this->assertStringNotContainsString( '"ab"cd"', $seed );
	}

	/**
	 * The whole seed is wrapped in a sessionStorage availability check -
	 * private browsing modes and some embedded webviews throw on access.
	 */
	public function test_guards_against_missing_session_storage(): void {
		$GLOBALS['ash_test_wc'] = new Ash_Test_WC( new Ash_Test_Cart( 1, 'x' ) );

		ash_wc_patch_cart_cookies_js();

		$this->assertStringStartsWith( 'if(typeof sessionStorage!=="undefined")', $GLOBALS['ash_test_spy']['inline'][0]['data'] );
	}
}
