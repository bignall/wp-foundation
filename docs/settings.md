# Settings

Most plugins keep their settings in one array option, and most write the same code to use it:
merge the stored value over defaults, cache it for the request, read a key, register the option
with a sanitize callback, and build `name="option[key]"` for form fields. `Settings` is that code.

## Describe the settings

```php
use Bignall\WPFoundation\Settings\Settings;

$settings = new Settings(
	'acme_settings',                              // Option name.
	[ 'mode' => 'simple', 'limit' => 10, 'sizes' => [] ],
	function ( array $input, Settings $current ): array {
		return [
			'mode'  => in_array( $input['mode'] ?? '', [ 'simple', 'advanced' ], true ) ? $input['mode'] : 'simple',
			'limit' => max( 1, min( 100, (int) ( $input['limit'] ?? 10 ) ) ),
			'sizes' => array_map( 'sanitize_key', (array) ( $input['sizes'] ?? [] ) ),
		];
	}
);

Plugin::create( __FILE__ )->settings( $settings );
```

`new Settings( $option, $defaults, $sanitize = null, $group = '', $autoload = true )`:

| Argument | |
| --- | --- |
| `$option` | Option name. |
| `$defaults` | Default values. Used until the option is saved, and for keys missing from the stored value. |
| `$sanitize` | Cleans a submitted form. It receives the submitted array (never anything else) and the `Settings` object, so it can keep a current value with `$current->get()`. Without one, only keys that exist in the defaults are kept. |
| `$group` | Settings API group, for `settings_fields()`. Defaults to the option name. |
| `$autoload` | Whether WordPress loads the option on every request. |

Passing the object to `Plugin::settings()` does the rest:

- On activation the defaults are added. On upgrade, keys added to the defaults since the last
  version are filled in, and stored values are never changed (like `options( ..., merge: true )`).
- The option is registered with the Settings API on `admin_init` and `rest_api_init`.
- The cached values are dropped whenever the option is added, updated or deleted.
- On uninstall the option is deleted when data deletion is enabled.

`$plugin->setting( 'acme_settings' )` returns the object again.

## Read values

```php
$settings->get( 'limit' );          // 10, or the saved value.
$settings->get( 'missing', 'x' );   // 'x': not stored and not in the defaults.
$settings->all();                   // Every value, stored ones merged over the defaults.
```

Values are read from the database once per request, per site on multisite.

## Save values from code

```php
$settings->update( [ 'limit' => 50 ] );   // Other keys keep their values.
```

`update()` goes through `update_option()`, so the sanitize callback runs as it does for a form.

## Build a settings form

```php
<form method="post" action="options.php">
	<?php settings_fields( $settings->group() ); ?>
	<input name="<?php echo esc_attr( $settings->field( 'limit' ) ); ?>" value="<?php echo esc_attr( $settings->get( 'limit' ) ); ?>">
	<input type="checkbox" name="<?php echo esc_attr( $settings->field( 'sizes', '' ) ); ?>" value="large">
	<?php submit_button(); ?>
</form>
```

`field( 'limit' )` is `acme_settings[limit]`. `field( 'sizes', '' )` is `acme_settings[sizes][]`.
`field( 'placements', 'header', 'enabled' )` is `acme_settings[placements][header][enabled]`.

A checkbox that isn't ticked isn't submitted at all, so the sanitize callback should treat a
missing key as "off" rather than keeping the old value.
