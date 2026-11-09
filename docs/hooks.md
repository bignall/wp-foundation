# Hooks reference

`{prefix}` is the plugin's prefix, e.g. `acme_bookings`.

## Actions

| Hook | Arguments | Fires |
| --- | --- | --- |
| `{prefix}_ready` | `Plugin $plugin` | After requirements pass and upgrades run, after `onReady()` callbacks. |
| `{prefix}_activated` | `Plugin $plugin, bool $firstTime` | After activation on a site. |
| `{prefix}_deactivated` | `Plugin $plugin` | After deactivation on a site. |
| `{prefix}_upgraded` | `string $from, string $to, Plugin $plugin` | After an upgrade on a site. |
| `{prefix}_uninstalled` | `Plugin $plugin` | After data has been removed from every site. |
| `{prefix}_migration_failed` | `Throwable $e, Migration $migration, Plugin $plugin` | When a migration throws. |
| `{prefix}_deactivation_feedback` | `Feedback $feedback, Plugin $plugin` | After a survey response is handled. |
| `{prefix}_deactivation_feedback_failed` | `Throwable $e, FeedbackHandler $handler, Plugin $plugin` | When a feedback handler throws. |

## Filters

| Hook | Value | Purpose |
| --- | --- | --- |
| `{prefix}_delete_data_on_uninstall` | `bool` | Final say on whether uninstall deletes data. |
| `{prefix}_network_site_ids` | `list<int>` | Sites a network-wide operation runs on. |
| `{prefix}_deactivation_reasons` | `list<Reason>` | Survey reasons. |
| `{prefix}_show_deactivation_survey` | `bool` | Whether to show the survey. |
| `{prefix}_deactivation_feedback_request` | `array` | `wp_remote_post()` arguments used by `RemoteHandler`. |

## Options created by the framework

| Option | Contents |
| --- | --- |
| `{prefix}_version` | Installed version (per site). |
| `{prefix}_installed_at` | First install timestamp. |
| `{prefix}_upgrade_lock` | Present while an upgrade runs. |
| `{prefix}_upgrade_error` | Last migration error, cleared on success. |
| `{prefix}_flush_rewrite_rules` | Pending rewrite flush flag. |
| `{prefix}_deactivation_feedback` | Survey responses stored by `LocalHandler`. |

All are deleted on uninstall when data deletion is enabled.
