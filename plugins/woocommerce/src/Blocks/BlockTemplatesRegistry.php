<?php
declare( strict_types=1 );
namespace Automattic\WooCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Utils\BlockTemplateUtils;
use Automattic\WooCommerce\Blocks\Templates\AbstractTemplate;
use Automattic\WooCommerce\Blocks\Templates\AbstractTemplatePart;
use Automattic\WooCommerce\Blocks\Templates\MiniCartTemplate;
use Automattic\WooCommerce\Blocks\Templates\CartTemplate;
use Automattic\WooCommerce\Blocks\Templates\CheckoutTemplate;
use Automattic\WooCommerce\Blocks\Templates\CheckoutHeaderTemplate;
use Automattic\WooCommerce\Blocks\Templates\ComingSoonTemplate;
use Automattic\WooCommerce\Blocks\Templates\OrderConfirmationTemplate;
use Automattic\WooCommerce\Blocks\Templates\ProductAttributeTemplate;
use Automattic\WooCommerce\Blocks\Templates\ProductBrandTemplate;
use Automattic\WooCommerce\Blocks\Templates\ProductCatalogTemplate;
use Automattic\WooCommerce\Blocks\Templates\ProductCategoryTemplate;
use Automattic\WooCommerce\Blocks\Templates\ProductTagTemplate;
use Automattic\WooCommerce\Blocks\Templates\ProductSearchResultsTemplate;
use Automattic\WooCommerce\Blocks\Templates\SingleProductTemplate;
use Automattic\WooCommerce\Blocks\Templates\SimpleProductAddToCartWithOptionsTemplate;
use Automattic\WooCommerce\Blocks\Templates\ExternalProductAddToCartWithOptionsTemplate;
use Automattic\WooCommerce\Blocks\Templates\VariableProductAddToCartWithOptionsTemplate;
use Automattic\WooCommerce\Blocks\Templates\GroupedProductAddToCartWithOptionsTemplate;
use Automattic\WooCommerce\Enums\ProductType;

/**
 * BlockTemplatesRegistry class.
 *
 * @internal
 */
class BlockTemplatesRegistry {

	/**
	 * The array of registered templates.
	 *
	 * @var AbstractTemplate[]|AbstractTemplatePart[]
	 */
	private $templates = array();

	/**
	 * Authoritative map for registered template name ('woocommerce//slug') to its HTML file path.
	 *
	 * @var array<string,string>
	 */
	private $template_paths = array();

	/**
	 * Initialization method.
	 */
	public function init() {
		$templates = array();
		if ( BlockTemplateUtils::supports_block_templates( 'wp_template' ) ) {
			$templates = array(
				ProductCatalogTemplate::SLUG       => new ProductCatalogTemplate(),
				ProductCategoryTemplate::SLUG      => new ProductCategoryTemplate(),
				ProductTagTemplate::SLUG           => new ProductTagTemplate(),
				ProductAttributeTemplate::SLUG     => new ProductAttributeTemplate(),
				ProductBrandTemplate::SLUG         => new ProductBrandTemplate(),
				ProductSearchResultsTemplate::SLUG => new ProductSearchResultsTemplate(),
				CartTemplate::SLUG                 => new CartTemplate(),
				CheckoutTemplate::SLUG             => new CheckoutTemplate(),
				OrderConfirmationTemplate::SLUG    => new OrderConfirmationTemplate(),
				SingleProductTemplate::SLUG        => new SingleProductTemplate(),
			);
		}
		$templates[ ComingSoonTemplate::SLUG ] = new ComingSoonTemplate();

		$template_parts = array();
		if ( BlockTemplateUtils::supports_block_templates( 'wp_template_part' ) ) {
			$template_parts = array(
				MiniCartTemplate::SLUG       => new MiniCartTemplate(),
				CheckoutHeaderTemplate::SLUG => new CheckoutHeaderTemplate(),
			);
			if ( wp_is_block_theme() ) {
				$product_types = wc_get_product_types();
				if ( count( $product_types ) > 0 ) {
					add_filter( 'default_wp_template_part_areas', array( $this, 'register_add_to_cart_with_options_template_part_area' ), 10, 1 );
					if ( array_key_exists( ProductType::SIMPLE, $product_types ) ) {
						$template_parts[ SimpleProductAddToCartWithOptionsTemplate::SLUG ] = new SimpleProductAddToCartWithOptionsTemplate();
					}
					if ( array_key_exists( ProductType::EXTERNAL, $product_types ) ) {
						$template_parts[ ExternalProductAddToCartWithOptionsTemplate::SLUG ] = new ExternalProductAddToCartWithOptionsTemplate();
					}
					if ( array_key_exists( ProductType::VARIABLE, $product_types ) ) {
						$template_parts[ VariableProductAddToCartWithOptionsTemplate::SLUG ] = new VariableProductAddToCartWithOptionsTemplate();
					}
					if ( array_key_exists( ProductType::GROUPED, $product_types ) ) {
						$template_parts[ GroupedProductAddToCartWithOptionsTemplate::SLUG ] = new GroupedProductAddToCartWithOptionsTemplate();
					}
				}
			}
		}

		// Init all templates. Content is loaded lazily via the get_block_templates filter for better performance.
		$directory = BlockTemplateUtils::get_templates_directory( 'wp_template' );
		foreach ( $templates as $template ) {
			$template->init();

			// Taxonomy templates are registered automatically by WordPress and are added through the Add Template menu.
			if ( ! $template->is_taxonomy_template ) {
				$template_name                          = 'woocommerce//' . $template::SLUG;
				$this->template_paths[ $template_name ] = $directory . '/' . $template::SLUG . '.html';
				register_block_template(
					$template_name,
					array(
						'title'       => $template->get_template_title(),
						'description' => $template->get_template_description(),
					)
				);
			}
		}
		// Lazy-load template content via two independent core resolution paths (plural for listings/editor/REST, singular for front-end rendering).
		add_filter( 'get_block_templates', array( $this, 'populate_contents_for_get_block_templates'), 11, 3 );
		add_filter( 'get_block_file_template', array( $this, 'populate_contents_for_get_block_template'), 11, 3 );

		foreach ( $template_parts as $template_part ) {
			$template_part->init();
		}

		$this->templates = array_merge( $templates, $template_parts );
	}

	/**
	 * Add Add to Cart + Options to the default template part areas.
	 *
	 * @param array $default_area_definitions An array of supported area objects.
	 * @return array The supported template part areas including the Add to Cart + Options one.
	 */
	public function register_add_to_cart_with_options_template_part_area( $default_area_definitions ) {
		$add_to_cart_with_options_template_part_area = array(
			'area'        => 'add-to-cart-with-options',
			'label'       => __( 'Add to Cart + Options', 'woocommerce' ),
			'description' => __( 'The Add to Cart + Options templates allow defining a different layout for each product type.', 'woocommerce' ),
			'icon'        => 'add-to-cart-with-options',
			'area_tag'    => 'add-to-cart-with-options',
		);
		return array_merge( $default_area_definitions, array( $add_to_cart_with_options_template_part_area ) );
	}

	/**
	 * Lazily inject template content into query results (covers resolve_block_template path).
	 *
	 * @since 11.3.0
	 * @internal
	 *
	 * @param \WP_Block_Template[] $templates     Array of found block templates.
	 * @param array                $query         Query arguments.
	 * @param string               $template_type Template type.
	 * @return \WP_Block_Template[]
	 */
	public function populate_contents_for_get_block_templates( $templates, $query, $template_type ) {
		if ( 'wp_template' === $template_type ) {
			foreach ( $templates as $template ) {
				$this->populate_contents_for_get_block_template( $template, '', $template_type );
			}
		}

		return $templates;
	}

	/**
	 * Lazily inject template content for a single template (covers get_block_file_template fallback path).
	 *
	 * @since 11.3.0
	 * @internal
	 *
	 * @param \WP_Block_Template|null $template      The resolved template, or null.
	 * @param string                  $id            Template identifier (e.g. 'theme_name//slug').
	 * @param string                  $template_type Template type.
	 * @return \WP_Block_Template|null
	 */
	public function populate_contents_for_get_block_template( $template, $id, $template_type ) {
		if ( 'wp_template' === $template_type && 'woocommerce' === ( $template->plugin ?? null ) ) {
			$template_path = $this->template_paths[ 'woocommerce//' . $template->slug ] ?? null;
			if ( null !== $template_path && '' === $template->content) {
				$template->content = apply_block_hooks_to_content(
					(string) file_get_contents( $template_path ), // phpcs:ignore WordPress.WP.AlternativeFunctions
					$template,
					'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata'
				);
			}
		}

		return $template;
	}

	/**
	 * Returns the template matching the slug
	 *
	 * @param string $template_slug Slug of the template to retrieve.
	 *
	 * @return AbstractTemplate|AbstractTemplatePart|null
	 */
	public function get_template( $template_slug ) {
		return $this->templates[ $template_slug ] ?? null;
	}
}
