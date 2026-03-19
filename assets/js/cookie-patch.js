(function() {
	if ( typeof Cookies === 'undefined' ) return;
	var origGet = Cookies.get.bind(Cookies);
	var origSet = Cookies.set.bind(Cookies);
	var origRemove = Cookies.remove.bind(Cookies);
	var intercepted = ['woocommerce_items_in_cart', 'woocommerce_cart_hash'];

	Cookies.get = function(name) {
		if ( intercepted.indexOf(name) !== -1 && typeof sessionStorage !== 'undefined' ) {
			return sessionStorage.getItem('ash_' + name) || undefined;
		}
		return origGet(name);
	};
	Cookies.set = function(name, value, opts) {
		if ( intercepted.indexOf(name) !== -1 && typeof sessionStorage !== 'undefined' ) {
			sessionStorage.setItem('ash_' + name, value);
			return;
		}
		return origSet(name, value, opts);
	};
	Cookies.remove = function(name, opts) {
		if ( intercepted.indexOf(name) !== -1 && typeof sessionStorage !== 'undefined' ) {
			sessionStorage.removeItem('ash_' + name);
			return;
		}
		return origRemove(name, opts);
	};
})();
