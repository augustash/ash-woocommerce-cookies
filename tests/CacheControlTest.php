<?php
/**
 * Covers the Pantheon cache-control filter.
 *
 * DONOTCACHEPAGE is a constant, so it cannot be unset once defined. The two
 * states therefore cannot share a process, and the defined case runs isolated.
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[Group( 'aai' )]
#[Group( 'ash_woocommerce_cookies' )]
final class CacheControlTest extends TestCase {

	/**
	 * With DONOTCACHEPAGE unset the filter must be transparent - Pantheon's own
	 * cache-control handling stays in charge of ordinary pages. Returning true
	 * here would hand every page on the site to WooCommerce's no-cache headers.
	 */
	public function test_passes_through_on_cacheable_pages(): void {
		$this->assertFalse( defined( 'DONOTCACHEPAGE' ), 'guard: another test defined the constant first' );
		$this->assertFalse( ash_wc_skip_pantheon_cache( false ) );
	}

	public function test_preserves_skip_requested_by_earlier_filter(): void {
		$this->assertTrue( ash_wc_skip_pantheon_cache( true ) );
	}

	/**
	 * Cart, checkout and my-account set DONOTCACHEPAGE. There the plugin must
	 * skip Pantheon's Cache-Control override so WooCommerce's own no-cache
	 * headers survive - without this those pages get cached and served to the
	 * next visitor, which is the original bug this plugin exists for.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_skips_cache_control_on_uncacheable_pages(): void {
		define( 'DONOTCACHEPAGE', true );

		$this->assertTrue( ash_wc_skip_pantheon_cache( false ) );
	}
}
