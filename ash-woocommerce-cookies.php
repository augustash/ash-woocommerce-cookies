<?php
/**
 * Plugin Name: ASH WooCommerce Cookies
 * Description: Fixes WooCommerce cookie handling for Pantheon's Varnish cache. Prevents woocommerce_items_in_cart and woocommerce_cart_hash server-side cookies (JS sets them client-only). Renames the session cookie with STYXKEY prefix for cache-varying behavior.
 * Version: 1.0.0
 * Author: August Ash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent cart indicator cookies from being set server-side
// so they don't bust Pantheon's Varnish cache.
add_filter( 'woocommerce_set_cookie_enabled', 'ash_wc_block_cart_cookies', 10, 2 );
function ash_wc_block_cart_cookies( $enabled, $name ) {
	if ( 'woocommerce_items_in_cart' === $name || 'woocommerce_cart_hash' === $name ) {
		return false;
	}
	return $enabled;
}

// Redirect cart cookie reads/writes to sessionStorage via js-cookie patch,
// and seed sessionStorage with the current cart state from PHP.
add_action( 'wp_enqueue_scripts', 'ash_wc_patch_cart_cookies_js', 20 );
function ash_wc_patch_cart_cookies_js() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	wp_enqueue_script(
		'ash-wc-cookie-patch',
		plugin_dir_url( __FILE__ ) . 'assets/js/cookie-patch.js',
		array( 'wc-js-cookie' ),
		'1.0.0',
		array( 'strategy' => 'defer' )
	);

	$items_in_cart = WC()->cart->get_cart_contents_count() > 0 ? '1' : '0';
	$cart_hash     = WC()->cart->get_cart_hash();

	wp_add_inline_script( 'ash-wc-cookie-patch', sprintf(
		'if(typeof sessionStorage!=="undefined"){sessionStorage.setItem("ash_woocommerce_items_in_cart",%s);sessionStorage.setItem("ash_woocommerce_cart_hash",%s);}',
		wp_json_encode( $items_in_cart ),
		wp_json_encode( $cart_hash )
	), 'before' );
}

// Skip Pantheon's default Cache-Control override on pages WooCommerce
// marks as uncacheable (cart, checkout, my-account).
add_filter( 'pantheon_skip_cache_control', 'ash_wc_skip_pantheon_cache' );
function ash_wc_skip_pantheon_cache( $skip ) {
	if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
		return true;
	}
	return $skip;
}

// Rename the session cookie with STYXKEY prefix so Pantheon treats it
// as cache-varying instead of cache-busting.
add_filter( 'woocommerce_cookie', 'ash_wc_rename_session_cookie' );
function ash_wc_rename_session_cookie( $cookie_name ) {
	return 'STYXKEY_wp_woocommerce_session_' . COOKIEHASH;
}
