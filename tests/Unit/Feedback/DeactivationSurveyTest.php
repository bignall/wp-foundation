<?php
/**
 * Deactivation survey tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit\Feedback;

use Bignall\WPFoundation\Feedback\DeactivationSurvey;
use Bignall\WPFoundation\Feedback\Feedback;
use Bignall\WPFoundation\Feedback\FeedbackHandler;
use Bignall\WPFoundation\Feedback\Handlers\LocalHandler;
use Bignall\WPFoundation\Feedback\Handlers\RemoteHandler;
use Bignall\WPFoundation\Feedback\Reason;
use Bignall\WPFoundation\Plugin;
use Bignall\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use RuntimeException;

final class DeactivationSurveyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->alias( static fn ( string $k ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $k ) ) );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn ( string $s ): string => trim( strip_tags( $s ) ) );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
	}

	public function test_default_reasons(): void {
		$ids = array_map( static fn ( Reason $r ): string => $r->id, DeactivationSurvey::make()->reasons( $this->plugin() ) );

		self::assertSame(
			array( 'temporary', 'found_better', 'no_longer_needed', 'not_working', 'missing_feature', 'too_complicated', 'other' ),
			$ids
		);
	}

	public function test_reasons_can_be_customised(): void {
		$survey = DeactivationSurvey::make()
			->removeReason( 'too_complicated' )
			->addReason( new Reason( 'too_expensive', 'Too expensive' ) );
		$ids    = array_map( static fn ( Reason $r ): string => $r->id, $survey->reasons( $this->plugin() ) );

		self::assertNotContains( 'too_complicated', $ids );
		self::assertSame( 'too_expensive', end( $ids ) );
	}

	public function test_builds_feedback_from_valid_input(): void {
		$feedback = DeactivationSurvey::make()->buildFeedback(
			$this->plugin(),
			array(
				'reason'  => 'found_better',
				'details' => '<b>Other Plugin</b>',
				'consent' => '1',
			)
		);

		self::assertInstanceOf( Feedback::class, $feedback );
		self::assertSame( 'found_better', $feedback->reason );
		self::assertSame( 'Other Plugin', $feedback->details );
		self::assertSame( 'acme-bookings', $feedback->plugin );
		self::assertSame( '1.0.0', $feedback->pluginVersion );
		self::assertNull( $feedback->siteUrl, 'Consent is ignored when nothing leaves the site.' );
	}

	public function test_rejects_unknown_or_missing_reason(): void {
		$survey = DeactivationSurvey::make();

		self::assertNull( $survey->buildFeedback( $this->plugin(), array( 'reason' => 'nope' ) ) );
		self::assertNull( $survey->buildFeedback( $this->plugin(), array() ) );
	}

	public function test_truncates_details(): void {
		$feedback = DeactivationSurvey::make()->buildFeedback(
			$this->plugin(),
			array(
				'reason'  => 'other',
				'details' => str_repeat( 'a', 5000 ),
			)
		);

		self::assertSame( 1000, mb_strlen( $feedback->details ) );
	}

	public function test_site_url_only_with_remote_handler_and_consent(): void {
		$survey = DeactivationSurvey::make()->withHandler( new RemoteHandler( 'https://feedback.test' ) );

		self::assertTrue( $survey->sendsDataOffSite() );
		self::assertSame(
			'https://example.com',
			$survey->buildFeedback(
				$this->plugin(),
				array(
					'reason'  => 'other',
					'consent' => '1',
				)
			)->siteUrl
		);
		self::assertNull( $survey->buildFeedback( $this->plugin(), array( 'reason' => 'other' ) )->siteUrl );
	}

	public function test_dispatch_continues_after_a_handler_fails(): void {
		$failing = new class() implements FeedbackHandler {
			public function handle( Feedback $feedback, Plugin $plugin ): void {
				throw new RuntimeException( 'down' );
			}
			public function isRemote(): bool {
				return true;
			}
		};
		$plugin  = $this->plugin();
		$survey  = ( new DeactivationSurvey() )->addReason( new Reason( 'other', 'Other' ) )->withHandler( $failing )->withHandler( new LocalHandler() );

		Actions\expectDone( 'acme_bookings_deactivation_feedback_failed' )->once();
		Actions\expectDone( 'acme_bookings_deactivation_feedback' )->once();

		$survey->dispatch( $survey->buildFeedback( $plugin, array( 'reason' => 'other' ) ), $plugin );

		self::assertCount( 1, LocalHandler::entries( $plugin ) );
	}

	public function test_local_handler_keeps_latest_entries(): void {
		$plugin  = $this->plugin();
		$handler = new LocalHandler( 3 );
		$survey  = DeactivationSurvey::make();

		foreach ( array( 'temporary', 'found_better', 'no_longer_needed', 'not_working' ) as $reason ) {
			$handler->handle( $survey->buildFeedback( $plugin, array( 'reason' => $reason ) ), $plugin );
		}

		self::assertSame(
			array( 'found_better', 'no_longer_needed', 'not_working' ),
			array_column( LocalHandler::entries( $plugin ), 'reason' )
		);
	}

	public function test_without_local_storage(): void {
		self::assertSame( array(), DeactivationSurvey::make()->withoutLocalStorage()->handlers() );
	}

	public function test_remote_handler_posts_json(): void {
		$plugin = $this->plugin();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\expect( 'wp_remote_post' )->once()->andReturnUsing(
			static function ( string $url, array $args ): array {
				self::assertSame( 'https://feedback.test', $url );
				self::assertSame( 'secret', $args['headers']['X-Api-Key'] );
				self::assertFalse( $args['blocking'] );
				self::assertSame( 'other', json_decode( $args['body'], true )['reason'] );
				return array();
			}
		);

		$feedback = DeactivationSurvey::make()->buildFeedback( $plugin, array( 'reason' => 'other' ) );
		( new RemoteHandler( 'https://feedback.test', array( 'X-Api-Key' => 'secret' ) ) )->handle( $feedback, $plugin );
	}

	public function test_script_config(): void {
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/admin-ajax.php' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce123' );

		$config = DeactivationSurvey::make()->scriptConfig( $this->plugin() );

		self::assertSame( 'acme-bookings/acme-bookings.php', $config['basename'] );
		self::assertSame( 'acme_bookings_deactivation_feedback', $config['action'] );
		self::assertFalse( $config['askConsent'] );
		self::assertSame( '', $config['strings']['privacy'] );
		self::assertCount( 7, $config['reasons'] );
	}

	public function test_assets_live_inside_the_autoloaded_src_directory(): void {
		// Strauss and similar tools copy only a package's autoload directories, so assets kept
		// outside src/ would be missing from prefixed builds and the survey would never show.
		$src = realpath( dirname( __DIR__, 3 ) . '/src' );

		foreach ( array( 'deactivation-survey.js', 'deactivation-survey.css' ) as $file ) {
			$path = realpath( DeactivationSurvey::assetPath( $file ) );
			self::assertNotFalse( $path, "{$file} exists" );
			self::assertStringStartsWith( $src . DIRECTORY_SEPARATOR, $path );
		}
	}
}
