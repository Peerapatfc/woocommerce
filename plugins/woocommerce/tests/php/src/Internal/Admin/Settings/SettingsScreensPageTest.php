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
			wp_dequeue_script( 'example-screen-script' );
			wp_dequeue_script( 'example-advanced-script' );
			wp_deregister_script( 'wc-settings-screen' );
		} finally {
			parent::tearDown();
		}
	}

	/**
	 * @testdox Should pass the requested screen to the route and load its scripts.
	 *
	 * @testWith ["/settings/payments/example", "example", "example-screen-script"]
	 *           ["/settings/payments/example?view=support", "example", "example-screen-script"]
	 *           ["/settings/payments/example/advanced", "example/advanced", "example-advanced-script"]
	 *           ["/settings/payments/example/advanced/", "example/advanced", "example-advanced-script"]
	 *
	 * @param string $route  The requested route.
	 * @param string $id     The expected screen ID.
	 * @param string $script The expected screen script.
	 */
	public function test_page_init_loads_requested_screen( string $route, string $id, string $script ): void {
		wp_register_script( 'example-screen-script', 'https://example.org/example.js', array(), '1.0', true );
		wp_register_script( 'example-advanced-script', 'https://example.org/advanced.js', array(), '1.0', true );
		$this->register_example_screen();
		$this->register_advanced_screen();
		$_GET['p'] = $route;

		$this->sut->handle_page_init();

		$this->assertTrue( wp_script_is( 'wc-settings-screen', 'enqueued' ), 'The screen data should be enqueued' );
		$this->assertTrue( wp_script_is( $script, 'enqueued' ), 'The screen\'s own script should be enqueued' );
		$this->assertCount( 1, array_intersect( array( 'example-screen-script', 'example-advanced-script' ), wp_scripts()->queue ), 'Only the requested screen\'s script should be enqueued' );
		$screen = $this->get_screen_data();
		$this->assertSame( $id, $screen['id'] );
		$this->assertSame( 'woo_settings_payments', $screen['entity']['kind'] );
		$this->assertSame( $id, $screen['entity']['name'] );
		$this->assertSame( '/example/v1/settings', $screen['entity']['baseURL'] );
	}

	/**
	 * @testdox Should load nothing for a route that doesn't name a registered screen.
	 *
	 * @testWith ["/settings/payments/unknown"]
	 *           ["/settings/emails/example"]
	 *           ["/settings/example"]
	 *           ["/settings/payments/example/unknown"]
	 *           ["/settings/payments/example/advanced/deeper"]
	 *
	 * @param string $route The requested route.
	 */
	public function test_page_init_ignores_unknown_screen( string $route ): void {
		$this->register_example_screen();
		$this->register_advanced_screen();
		$_GET['p'] = $route;

		$this->sut->handle_page_init();

		$this->assertFalse( wp_script_is( 'wc-settings-screen', 'registered' ), 'No screen data should be registered' );
	}

	/**
	 * @testdox Should pass a header that links to the Payments list and names the screen.
	 */
	public function test_page_init_passes_breadcrumbs(): void {
		$this->register_example_screen();
		$_GET['p'] = '/settings/payments/example';

		$this->sut->handle_page_init();

		$this->assertSame(
			array(
				array(
					'label' => 'Payments',
					'to'    => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
				),
				array( 'label' => 'Example settings' ),
			),
			$this->get_screen_data()['breadcrumbs']
		);
	}

	/**
	 * @testdox Should add the registered parent screen to a sub-page's header, linking to the parent.
	 */
	public function test_page_init_passes_sub_page_breadcrumbs(): void {
		$this->register_example_screen();
		$this->register_advanced_screen();
		$_GET['p'] = '/settings/payments/example/advanced';

		$this->sut->handle_page_init();

		$breadcrumbs = $this->get_screen_data()['breadcrumbs'];
		$this->assertSame( array( 'Payments', 'Example settings', 'Advanced settings' ), array_column( $breadcrumbs, 'label' ) );
		$this->assertSame( admin_url( 'admin.php?page=wc-settings-screens-wp-admin&p=/settings/payments/example' ), $breadcrumbs[1]['to'] );
		$this->assertArrayNotHasKey( 'to', $breadcrumbs[2], 'The current page should not link to itself' );
	}

	/**
	 * @testdox Should load a sub-page whose parent screen isn't registered, without a parent crumb.
	 */
	public function test_page_init_loads_sub_page_without_parent(): void {
		$this->register_advanced_screen();
		$_GET['p'] = '/settings/payments/example/advanced';

		$this->sut->handle_page_init();

		$this->assertSame( array( 'Payments', 'Advanced settings' ), array_column( $this->get_screen_data()['breadcrumbs'], 'label' ) );
	}

	/**
	 * Get the screen data passed to the route.
	 *
	 * @return array<string, mixed>
	 */
	private function get_screen_data(): array {
		$data = implode( '', (array) wp_scripts()->get_data( 'wc-settings-screen', 'before' ) );
		return json_decode( substr( $data, strpos( $data, '{' ), -1 ), true );
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

	/**
	 * Register an example sub-page through the filter.
	 */
	private function register_advanced_screen(): void {
		add_filter(
			'__experimental_woocommerce_settings_screens',
			function ( $screens ) {
				$screens['payments']['example/advanced'] = array(
					'title'     => 'Advanced settings',
					'rest_path' => '/example/v1/settings',
					'scripts'   => array( 'example-advanced-script' ),
				);
				return $screens;
			}
		);
	}
}
