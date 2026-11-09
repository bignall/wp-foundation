# Release builds

Prefixing needs Composer's dev dependencies: Strauss pulls in `composer/composer` and more,
which install into `vendor/` alongside your autoloader. Zip `vendor/` as it is and the plugin
comes out around 230 MB. A release needs only a few of those files.

## The recipe

1. **Run a full `composer install`**, so Strauss runs and writes `vendor-prefixed/`.
2. **Regenerate the autoloader for production:**
   ```bash
   composer dump-autoload --no-dev --classmap-authoritative
   ```
3. **Copy only what runs:**
   - your main file, `src/` and any other runtime files (templates, languages, `readme.txt`)
   - `vendor-prefixed/`
   - `vendor/autoload.php`
   - the **top-level files** of `vendor/composer/` (`autoload_*.php`, `ClassLoader.php`,
     `installed.php`, ...), without any of its subdirectories
4. **Check the result.** Fail the build if `Bignall\WPFoundation\` appears anywhere without your
   prefix in front of it. That means a reference Strauss didn't rewrite, and the plugin would
   fatally error, or load another plugin's copy of the library.
5. **Restore your development autoloader** with `composer dump-autoload`.

A real plugin built this way came out at 77 files and 358 KB.

## The script

The example plugin ships this recipe as
[`bin/build-release.sh`](../examples/acme-bookings/bin/build-release.sh). Copy it into your
plugin and list your runtime files in `RUNTIME_FILES`:

```bash
bin/build-release.sh
# ...
# Built build/acme-bookings.zip
# 43 files, 448K
```

It also checks that the survey's script made it into `vendor-prefixed/`, lints every PHP file,
and always restores the development autoloader, even when the build fails.

Add `/build/` to your `.gitignore`.
