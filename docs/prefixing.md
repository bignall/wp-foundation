# Prefixing with Strauss

WordPress loads every plugin into one PHP process. If two plugins bundle different versions of
`Arbe\WPFoundation`, whichever loads first wins, and the other plugin may break. Prefixing
rewrites the library's namespace inside your plugin, e.g. to `AcmeBookings\Vendor\Arbe\WPFoundation`,
so each plugin has its own copy.

[Strauss](https://github.com/BrianHenryIE/strauss) does this as part of `composer install`:

```json
{
	"require": {
		"bignall/wp-foundation": "^1.0"
	},
	"require-dev": {
		"brianhenryie/strauss": "^0.19"
	},
	"extra": {
		"strauss": {
			"target_directory": "vendor-prefixed",
			"namespace_prefix": "AcmeBookings\\Vendor\\",
			"classmap_prefix": "AcmeBookings_Vendor_",
			"packages": ["bignall/wp-foundation"],
			"delete_vendor_packages": true
		}
	},
	"scripts": {
		"prefix-namespaces": ["strauss", "@composer dump-autoload"],
		"post-install-cmd": ["@prefix-namespaces"],
		"post-update-cmd": ["@prefix-namespaces"]
	},
	"config": {
		"platform-check": false
	}
}
```

In your main file, load `vendor-prefixed/autoload.php` before `vendor/autoload.php`. Strauss
also updates `use` statements in your own `src/` code.

The survey's JavaScript and CSS are read from disk and inlined, so they work at whatever path
the library ends up. The uninstall callback is stored by class name, which after prefixing is
your plugin's own copy.

See the [example plugin](../examples/acme-bookings/composer.json) for a working setup.
