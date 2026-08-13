<?php
/**
 * Test bootstrap.
 *
 * Stands in for the handful of WordPress and WooCommerce functions this plugin
 * touches, so the suite runs with nothing but PHP and PHPUnit - no WordPress
 * install, no database, no Patchwork. That portability is the point: the plugin
 * ships to several sites and has to be verifiable on any of them, and in CI,
 * without a fixture site to lean on.
 *
 * The stubs are spies rather than no-ops - they record what they were called
 * with, which is what the enqueue test asserts against.
 */

declare( strict_types=1 );

/*
 * This plugin is committed into consuming sites rather than composer-installed
 * (no build step on Pantheon WordPress), so tests/ ships to production and is
 * web-reachable. The usual ABSPATH guard is no use here - this file *defines*
 * ABSPATH - so gate on the SAPI instead. Requested over HTTP it now does
 * nothing at all.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// The plugin exits unless it believes it is inside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Used to build the renamed session cookie. The value is arbitrary; what
// matters is that the plugin concatenates it onto the STYXKEY_ prefix.
if ( ! defined( 'COOKIEHASH' ) ) {
	define( 'COOKIEHASH', 'testcookiehash' );
}

/**
 * Per-test recorder. Reset with ash_test_reset() in setUp().
 */
$GLOBALS['ash_test_spy'] = array(
	'enqueued' => array(),
	'inline'   => array(),
);

/**
 * Swappable WooCommerce double. Null models WooCommerce being absent or its
 * cart not yet built, which is the branch the plugin's own guard covers.
 */
$GLOBALS['ash_test_wc'] = null;

/**
 * Clears the per-test recorder and the WooCommerce double.
 */
function ash_test_reset(): void {
	$GLOBALS['ash_test_spy'] = array(
		'enqueued' => array(),
		'inline'   => array(),
	);
	$GLOBALS['ash_test_wc'] = null;
}

/**
 * Minimal stand-in for WC()->cart.
 */
final class Ash_Test_Cart {

	public function __construct(
		private int $count = 0,
		private string $hash = ''
	) {}

	public function get_cart_contents_count(): int {
		return $this->count;
	}

	public function get_cart_hash(): string {
		return $this->hash;
	}
}

/**
 * Stand-in for the WooCommerce container.
 */
final class Ash_Test_WC {

	public function __construct( public ?Ash_Test_Cart $cart = null ) {}
}

// -- WordPress function stubs ---------------------------------------------
//
// Hook registrations are captured at include time and snapshotted below, so
// the registration test can assert on them without re-including the plugin.

$GLOBALS['ash_test_hooks'] = array(
	'filters' => array(),
	'actions' => array(),
);

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['ash_test_hooks']['filters'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	return true;
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['ash_test_hooks']['actions'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	return true;
}

function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, $args = array() ): void {
	$GLOBALS['ash_test_spy']['enqueued'][] = compact( 'handle', 'src', 'deps', 'ver', 'args' );
}

function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
	$GLOBALS['ash_test_spy']['inline'][] = compact( 'handle', 'data', 'position' );
	return true;
}

function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function plugin_dir_url( string $file ): string {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

/**
 * Returns whatever double the current test installed.
 *
 * Defined unconditionally, so the plugin's `! function_exists( 'WC' )` arm is
 * not reachable here - a null cart exercises the same early return, and the
 * function_exists check exists for plugin load order rather than for logic.
 */
function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return $GLOBALS['ash_test_wc'];
}

// -- Load the plugin ------------------------------------------------------

require_once dirname( __DIR__ ) . '/ash-woocommerce-cookies.php';

// Snapshot what the plugin registered on load. Kept separate from the
// per-test recorder so ash_test_reset() cannot erase it.
$GLOBALS['ash_test_registered'] = $GLOBALS['ash_test_hooks'];
