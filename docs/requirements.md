# Requirements

```php
Plugin::create( __FILE__ )
	->requires(
		php: '8.1',
		wp: '6.4',
		extensions: [ 'intl', 'mbstring' ],
		plugins: [ 'woocommerce/woocommerce.php' => 'WooCommerce' ],
	)
	->requirement( function (): ?string {
		return wp_using_ext_object_cache() ? null : __( 'A persistent object cache is required.', 'acme' );
	} );
```

A custom requirement returns `null` when it passes, or an error message.

| When | If a requirement fails |
| --- | --- |
| Activation | The plugin is deactivated, and WordPress shows the errors with a "Go back" link. |
| Every request | Users with `activate_plugins` see an admin notice listing the errors. `onReady()` callbacks and upgrades don't run. |

## The PHP version guard

The framework itself needs PHP 8.1, so it can't report an old PHP version once it's loaded. Your
main file should check `PHP_VERSION` before requiring the autoloader, and use only syntax that
old PHP versions can parse. See the [example plugin](../examples/acme-bookings/acme-bookings.php).
Also add `Requires PHP: 8.1` to the plugin header, which WordPress itself enforces on install.
