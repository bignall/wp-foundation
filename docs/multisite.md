# Multisite

| Event | Behaviour |
| --- | --- |
| Network activation | Activation runs on every site of the current network. |
| Single-site activation | Runs on that site only. |
| New site created while network-active | The site is set up automatically (`wp_initialize_site`). |
| Network deactivation | Deactivation runs on every site. |
| Uninstall | Data is removed from every site of the network. |
| Site deleted | Its custom tables are dropped (`wpmu_drop_tables`). Its options go with its options table. |
| Upgrade | Each site upgrades itself on its first request after the update, since the version is stored per site. |

## Large networks

Looping over thousands of sites in one request can time out. Filter the site list to process a
subset, then handle the rest in the background, for example with Action Scheduler:

```php
add_filter( 'acme_network_site_ids', function ( array $ids ) {
	if ( count( $ids ) <= 100 ) {
		return $ids;
	}
	foreach ( array_slice( $ids, 100 ) as $id ) {
		as_enqueue_async_action( 'acme_activate_site', [ $id ] );
	}
	return array_slice( $ids, 0, 100 );
} );

add_action( 'acme_activate_site', function ( int $id ) {
	switch_to_blog( $id );
	Bignall\WPFoundation\Plugin::instance( 'acme/acme.php' )->lifecycle()->activateSite();
	restore_current_blog();
} );
```

Per-site upgrades are already lazy, so they don't need this.
