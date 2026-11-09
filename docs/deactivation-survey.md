# Deactivation survey

```php
->deactivationSurvey()
```

This adds a modal to the Plugins screen (single site and network admin). It opens when a user
clicks your plugin's **Deactivate** link. Users can pick a reason, optionally add details, then
**Submit & deactivate**, **Skip & deactivate** or **Cancel**. Bulk deactivation isn't intercepted.

Deactivation always goes ahead: the request times out after 4 seconds, and handler errors are
caught.

## Default reasons

| id | Label | Asks for details |
| --- | --- | --- |
| `temporary` | It's a temporary deactivation, I'm troubleshooting | |
| `found_better` | I found a better plugin | Which plugin? |
| `no_longer_needed` | I no longer need the plugin | |
| `not_working` | It isn't working or broke my site | What went wrong? |
| `missing_feature` | It's missing a feature I need | Which feature? |
| `too_complicated` | It's too hard to set up or use | What was confusing? |
| `other` | Other | Please tell us more |

## Customising

```php
use Bignall\WPFoundation\Feedback\DeactivationSurvey;
use Bignall\WPFoundation\Feedback\Reason;

->deactivationSurvey(
	DeactivationSurvey::make()
		->withTitle( __( 'Sorry to see you go', 'acme' ) )
		->removeReason( 'too_complicated' )
		->addReason( new Reason( 'too_expensive', __( 'Too expensive', 'acme' ), __( 'What price would work?', 'acme' ) ) )
)
```

Use `withReasons( [ ... ] )` to replace the list entirely, or filter
`{prefix}_deactivation_reasons`. To hide the survey, for example on staging sites, return false
from `{prefix}_show_deactivation_survey`.

## Where responses go

Each response is a `Feedback` object with the reason id, details (sanitized, at most 1,000
characters), plugin slug and version, WordPress and PHP versions, locale, a multisite flag, a
timestamp, and the site URL *only if the user consented*.

### Stored on the site (default)

`LocalHandler` keeps the last 50 responses in the non-autoloaded option
`{prefix}_deactivation_feedback`. Read them, for example after reactivation, with:

```php
use Bignall\WPFoundation\Feedback\Handlers\LocalHandler;

$entries = LocalHandler::entries( $plugin );
```

They're deleted on uninstall when data deletion is enabled. To stop storing locally, use
`->withoutLocalStorage()`.

### Sent to your server

```php
use Bignall\WPFoundation\Feedback\Handlers\RemoteHandler;

DeactivationSurvey::make()
	->withHandler( new RemoteHandler(
		'https://api.example.com/deactivation-feedback',
		headers: [ 'X-Api-Key' => 'public-ingest-key' ],
	) );
```

The handler POSTs the response as JSON, non-blocking by default. When any handler sends data off
the site, the modal tells the user so, and it adds an unchecked box asking whether to include the
site address. Filter the request with `{prefix}_deactivation_feedback_request`.

If your plugin is on wordpress.org, the guidelines require informed consent before sending data
off the site. Mention the survey in your readme and privacy policy.

### Your own handler

```php
use Bignall\WPFoundation\Feedback\Feedback;
use Bignall\WPFoundation\Feedback\FeedbackHandler;
use Bignall\WPFoundation\Plugin;

final class SlackHandler implements FeedbackHandler {
	public function handle( Feedback $feedback, Plugin $plugin ): void {
		wp_remote_post( SLACK_WEBHOOK, [ 'body' => wp_json_encode( [ 'text' => "{$feedback->reason}: {$feedback->details}" ] ), 'blocking' => false ] );
	}

	public function isRemote(): bool {
		return true;
	}
}
```

Or listen to the `{prefix}_deactivation_feedback` action, which fires after the handlers.

## Security

The endpoint is `wp_ajax_{prefix}_deactivation_feedback`. It checks a nonce and the
`activate_plugins` capability, accepts only known reason ids, and sanitizes the details. The
modal's text is set with `textContent`, never as HTML. Script and styles are inlined, so they
work wherever the library is installed or prefixed. They live under the library's `src/`
directory so that Strauss copies them; if they're ever missing, the survey is skipped rather than
breaking the Plugins screen.
