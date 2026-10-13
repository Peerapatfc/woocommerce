<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Admin\Settings;

use Automattic\WooCommerce\Internal\Admin\Settings\SettingsScreens;
use Automattic\WooCommerce\Internal\Admin\Settings\SettingsScreensPage;
use WC_Unit_Test_Case;

/**
 * Tests for the SettingsScreensPage class.
 */
class SettingsScreensPageTest extends WC_Unit_Test_Case {

	/**
	 * The System Under Test.
	 *
	 * @var SettingsScreensPage
	 */
	private $sut;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->sut = new SettingsScreensPage();
		$this->sut->init( new SettingsScreens() );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tearDown(): void {
		try {
			unset( $_GET['p'] );
			wp_deregister_script( 'wc-settings-screen' );
		} finally {
			parent::tearDown();
		}
	}

	/**
	 * @testdox Should pass the requested screen to the route and load its scripts.
	 *
	 * @testWith ["/settings/payments/example"]
	 *           ["/settings/payments/example?view=support"]
	 *
	 * @param string $route The requested route.
	 */
	public function test_page_init_loads_requested_screen( string $route ): void {
		wp_register_script( 'example-screen-script', 'https://example.org/example.js', array(), '1.0', true );
		$this->register_example_screen();
		$_GET['p'] = $route;

		$this->sut->handle_page_init();

		$this->assertTrue( wp_script_is( 'wc-settings-screen', 'enqueued' ), 'The screen data should be enqueued' );
		$this->assertTrue( wp_script_is( 'example-screen-script', 'enqueued' ), 'The screen\'s own script should be enqueued' );
		$data = implode( '', (array) wp_scripts()->get_data( 'wc-settings-screen', 'before' ) );
		$this->assertStringContainsString( '"baseURL":"\/example\/v1\/settings"', $data );
		$this->assertStringContainsString( '"kind":"woo_settings_payments","name":"example"', $data );
	}

	/**
	 * @testdox Should load nothing for a route that doesn't name a registered screen.
	 *
	 * @testWith ["/settings/payments/unknown"]
	 *           ["/settings/emails/example"]
	 *           ["/settings/example"]
	 *
	 * @param string $route The requested route.
	 */
	public function test_page_init_ignores_unknown_screen( string $route ): void {
		$this->register_example_screen();
		$_GET['p'] = $route;

		$this->sut->handle_page_init();

		$this->assertFalse( wp_script_is( 'wc-settings-screen', 'registered' ), 'No screen data should be registered' );
	}

	/**
	 * Register an example screen through the filter.
	 */
	private function register_example_screen(): void {
		add_filter(
			'__experimental_woocommerce_settings_screens',
			function ( $screens ) {
				$screens['payments']['example'] = array(
					'title'     => 'Example settings',
					'rest_path' => '/example/v1/settings',
					'scripts'   => array( 'example-screen-script' ),
				);
				return $screens;
			}
		);
	}
}
