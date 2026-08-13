<?php
/**
 * Covers the wiring rather than the logic.
 *
 * Every other test calls the plugin's functions directly, which proves they
 * behave but not that WordPress ever reaches them. A correct function left
 * unhooked - or hooked to a renamed filter after a WooCommerce update - fails
 * exactly like the bug this plugin fixes: silently, with caching degrading and
 * nothing in any log.
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group( 'aai' )]
#[Group( 'ash_woocommerce_cookies' )]
final class HookRegistrationTest extends TestCase {

	private static function registered( string $type ): array {
		return $GLOBALS['ash_test_registered'][ $type ];
	}

	private static function find( string $type, string $hook, string $callback ): ?array {
		foreach ( self::registered( $type ) as $entry ) {
			if ( $entry['hook'] === $hook && $entry['callback'] === $callback ) {
				return $entry;
			}
		}
		return null;
	}

	public static function expected_filters(): array {
		return array(
			'blocks cart cookies'   => array( 'woocommerce_set_cookie_enabled', 'ash_wc_block_cart_cookies' ),
			'renames session cookie' => array( 'woocommerce_cookie', 'ash_wc_rename_session_cookie' ),
			'skips cache control'   => array( 'pantheon_skip_cache_control', 'ash_wc_skip_pantheon_cache' ),
		);
	}

	#[DataProvider( 'expected_filters' )]
	public function test_registers_filter( string $hook, string $callback ): void {
		$this->assertNotNull(
			self::find( 'filters', $hook, $callback ),
			"{$callback} is not hooked to {$hook}"
		);
	}

	public function test_registers_script_action(): void {
		$this->assertNotNull( self::find( 'actions', 'wp_enqueue_scripts', 'ash_wc_patch_cart_cookies_js' ) );
	}

	/**
	 * woocommerce_set_cookie_enabled passes the cookie name as the second
	 * argument. Registering with the default accepted_args of 1 would leave
	 * $name null, the name comparisons would never match, and both cookies
	 * would go back to being set server-side - with no error anywhere.
	 */
	public function test_cookie_filter_accepts_name_argument(): void {
		$entry = self::find( 'filters', 'woocommerce_set_cookie_enabled', 'ash_wc_block_cart_cookies' );

		$this->assertNotNull( $entry );
		$this->assertGreaterThanOrEqual( 2, $entry['accepted_args'] );
	}

	/**
	 * The script enqueue runs at priority 20 so it lands after WooCommerce has
	 * registered wc-js-cookie at the default 10; at 10 the dependency would not
	 * yet exist and the patch would silently never load.
	 */
	public function test_script_enqueues_after_woocommerce(): void {
		$entry = self::find( 'actions', 'wp_enqueue_scripts', 'ash_wc_patch_cart_cookies_js' );

		$this->assertNotNull( $entry );
		$this->assertGreaterThan( 10, $entry['priority'] );
	}
}
