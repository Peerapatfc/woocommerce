<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\Tests\Internal\Admin\Settings;

use Automattic\WooCommerce\Internal\Admin\Settings\SettingsScreens;
use WC_Unit_Test_Case;

/**
 * Tests for the SettingsScreens class.
 */
class SettingsScreensTest extends WC_Unit_Test_Case {

	/**
	 * The System Under Test.
	 *
	 * @var SettingsScreens
	 */
	private $sut;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->sut = new SettingsScreens();
	}

	/**
	 * @testdox Should return a valid screen with its area and only string script handles.
	 */
	public function test_returns_valid_screen(): void {
		$this->register_screens(
			array(
				'payments' => array(
					'example' => array(
						'title'     => 'Example settings',
						'rest_path' => '/example/v1/settings',
						'scripts'   => array( 'example-script', 42 ),
					),
				),
			)
		);

		$screen = $this->sut->get_screen( 'payments', 'example' );

		$this->assertIsArray( $screen, 'The example screen should be registered' );
		$this->assertSame( 'payments', $screen['area'] );
		$this->assertSame( 'Example settings', $screen['title'] );
		$this->assertSame( '/example/v1/settings', $screen['rest_path'] );
		$this->assertSame( array( 'example-script' ), $screen['scripts'], 'Non-string handles should be dropped' );
	}

	/**
	 * @testdox Should only find a screen in the area it was registered in.
	 */
	public function test_finds_screen_only_in_its_area(): void {
		$this->register_screens(
			array(
				'payments' => array(
					'example' => array(
						'title'     => 'Example settings',
						'rest_path' => '/example/v1/settings',
					),
				),
			)
		);

		$this->assertNull( $this->sut->get_screen( 'emails', 'example' ), 'A screen should not be found in another area' );
	}

	/**
	 * @testdox Should keep a valid parent screen ID and drop an invalid one.
	 *
	 * @testWith ["example", "example"]
	 *           ["child", null]
	 *           ["Example", null]
	 *           [42, null]
	 *
	 * @param mixed       $parent_id The registered parent.
	 * @param string|null $expected  The expected parent.
	 */
	public function test_normalizes_parent( $parent_id, ?string $expected ): void {
		$this->register_screens(
			array(
				'payments' => array(
					'child' => array(
						'title'     => 'Child settings',
						'rest_path' => '/example/v1/settings',
						'parent'    => $parent_id,
					),
				),
			)
		);

		$screen = $this->sut->get_screen( 'payments', 'child' );
		$this->assertNotNull( $screen );
		$this->assertSame( $expected, $screen['parent'] );
	}

	/**
	 * @testdox Should skip screens in an area whose key can't be used in URLs.
	 */
	public function test_skips_invalid_area(): void {
		$this->register_screens(
			array(
				'Payments' => array(
					'example' => array(
						'title'     => 'Example settings',
						'rest_path' => '/example/v1/settings',
					),
				),
			)
		);

		$this->assertSame( array(), $this->sut->get_screens() );
	}

	/**
	 * @testdox Should skip screens that can't be used.
	 *
	 * @dataProvider provide_invalid_screens
	 *
	 * @param mixed $id     The screen ID.
	 * @param mixed $screen The screen arguments.
	 */
	public function test_skips_invalid_screens( $id, $screen ): void {
		$this->register_screens( array( 'payments' => array( $id => $screen ) ) );

		$this->assertSame( array(), $this->sut->get_screens() );
	}

	/**
	 * Invalid screens.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function provide_invalid_screens(): array {
		$valid = array(
			'title'     => 'Example settings',
			'rest_path' => '/example/v1/settings',
		);
		return array(
			'ID with uppercase letters'  => array( 'Example', $valid ),
			'numeric ID'                 => array( 0, $valid ),
			'arguments are not an array' => array( 'example', 'example' ),
			'missing title'              => array( 'example', array( 'rest_path' => '/example/v1/settings' ) ),
			'relative REST path'         => array(
				'example',
				array(
					'title'     => 'Example settings',
					'rest_path' => 'example/v1/settings',
				),
			),
		);
	}

	/**
	 * @testdox Should return no screens when the filter or an area isn't an array.
	 *
	 * @testWith ["not an array"]
	 *           [{"payments": "not an array"}]
	 *
	 * @param mixed $screens The filter result.
	 */
	public function test_returns_no_screens_for_invalid_filter_result( $screens ): void {
		add_filter( '__experimental_woocommerce_settings_screens', fn() => $screens );

		$this->assertSame( array(), $this->sut->get_screens() );
	}

	/**
	 * Register screens through the filter.
	 *
	 * @param array $screens The screens to register.
	 */
	private function register_screens( array $screens ): void {
		add_filter( '__experimental_woocommerce_settings_screens', fn() => $screens );
	}
}
