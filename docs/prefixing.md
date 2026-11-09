# Prefixing with Strauss

WordPress loads every plugin into one PHP process. If two plugins bundle different versions of
`Bignall\WPFoundation`, whichever loads first wins, and the other plugin may break. Prefixing
rewrites the library's namespace inside your plugin, e.g. to `AcmeBookings\Vendor\Bignall\WPFoundation`,
so each plugin has its own copy.

[Strauss](https://github.com/BrianHenryIE/strauss) does this as part of `composer install`:

```json
{
	"require": {
		"bignall/wp-foundation": "^0.1"
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

While the library is below 1.0, use `^0.1`: Composer treats each 0.x minor version as
potentially breaking, so `^0.1` accepts 0.1.x updates but not 0.2. Check the
[releases](https://github.com/bignall/wp-foundation/releases) for the current version.

In your main file, load `vendor-prefixed/autoload.php` before `vendor/autoload.php`.

## What Strauss changes, and when

**Strauss rewrites your own code.** Besides copying the library into `vendor-prefixed/`, it
rewrites references in your plugin's `src/` in place:

```php
use Bignall\WPFoundation\Plugin;                        // What you write.
use AcmeBookings\Vendor\Bignall\WPFoundation\Plugin;    // What Strauss leaves in the file.
```

That's expected. **Commit the rewritten lines**: they're what your plugin runs.

**It only happens on `composer install` and `composer update`.** A class you write afterwards
still says `use Bignall\WPFoundation\...`, and your plugin fatally errors with
`Class "Bignall\WPFoundation\Plugin" not found` until Strauss runs again. After adding a file
that uses the library, either write the prefixed name yourself or reinstall.

**Re-run Strauss with a clean install.** Running `composer run prefix-namespaces` on its own
can fail with `Class "Composer\Factory" not found`. Instead, run:

```bash
rm -rf vendor vendor-prefixed && composer install
```

## Assets

The deactivation survey's JavaScript and CSS live inside the library's `src/` directory,
because Strauss copies only a package's autoloaded directories. They're read from disk and
inlined into the page, so they work at whatever path the library ends up.

The uninstall callback is stored by class name, which after prefixing is your plugin's own copy.

## Shipping

Don't zip your plugin with its `vendor/` directory as-is. Strauss and its dependencies make it
hundreds of megabytes. See [Release builds](releasing.md).

See the [example plugin](../examples/acme-bookings/composer.json) for a working setup.
