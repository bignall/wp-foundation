# Flash notices

After handling an admin form, the usual pattern is post/redirect/get: process the submission,
redirect, and tell the user what happened on the page they land on. A notice can't simply be
echoed, because the redirect discards the output. Flash notices are stored until the user's next
admin page:

```php
add_action( 'admin_post_acme_import', function () use ( $plugin ) {
	check_admin_referer( 'acme_import' );

	$result = Importer::run();

	if ( $result->ok() ) {
		$plugin->notices()->flash( 'success', __( 'Import finished.', 'acme' ) );
	} else {
		$plugin->notices()->flash( 'error', __( 'Some rows were skipped:', 'acme' ), $result->errors() );
	}

	wp_safe_redirect( wp_get_referer() ?: admin_url() );
	exit;
} );
```

`flash( $type, $message, $items = [] )`:

| Argument | |
| --- | --- |
| `$type` | `success`, `error`, `warning` or `info` (anything else becomes `info`). |
| `$message` | Plain text. It's escaped when printed, so don't pass HTML. |
| `$items` | Optional details, printed as a bulleted list under the message. |

It returns false, and stores nothing, when no user is logged in.

## How it works

- Notices are stored per user, in a transient named `{prefix}_notices_{user id}`, and expire
  after 10 minutes if they're never shown. Several flashes before a redirect all appear.
- They're printed once, as dismissible WordPress notices, on the next admin screen that user
  loads (including network and user admin screens), then deleted.
- Each plugin has its own store, so plugins built on the framework never print each other's
  notices.

`$plugin->notices()->pending()` returns the queued notices without printing them, which is
useful in tests.
