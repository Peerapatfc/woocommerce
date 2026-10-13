# Admin screens

Admin screens built with [wp-build](https://www.npmjs.com/package/@wordpress/build), DataViews and DataForm. Each folder in `routes/` is one route.

The build writes to `plugins/woocommerce/assets/client/routes`, which `Automattic\WooCommerce\Internal\Admin\Settings\SettingsScreensPage` loads.

```sh
pnpm --filter=@woocommerce/plugin-woocommerce build:project:admin-screens
pnpm --filter=@woocommerce/plugin-woocommerce watch:build:project:admin-screens
```

## Settings screens

Experimental settings screens that extensions register. They are behind the `settings-screens` feature flag, and every part of them can change in any release. See `plugins/woocommerce/tests/e2e/test-plugins/payment-settings-example` for a working example.

Each screen belongs to an area, and each area has its own route in `routes/`. The only area so far is `payments`. A screen ID only has to be unique within its area.

1. Register the screen with the `__experimental_woocommerce_settings_screens` filter, under its area and ID (`$screens['payments']['my-gateway']`). Give it a `title`, the `rest_path` of an endpoint that reads (`GET`) and saves (`POST`) the settings as one flat object and returns the stored settings after a save, and any `scripts` to load on the screen.
2. Register fields with the Fields API on the `fields_api_init` action, using the entity kind `woo_settings_{area}` and the screen ID as the entity name.
3. Set the layout with the `get_entity_view_config_woo_settings_{area}_{id}` filter.

The screen opens at `admin.php?page=wc-settings-screens-wp-admin&p=/settings/{area}/{id}`.
