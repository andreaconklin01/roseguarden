<?php
/**
 * Global helpers.
 *
 * The legacy plugin declared a two-character global function `df()` from inside
 * a rendering function, which is far too collision-prone for the global
 * namespace. It was only ever used by the plugin's own certificate template, so
 * it is replaced here by a namespaced helper with a descriptive name.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Echo-safe accessor for a certificate field.
 *
 * @param array<string,mixed>|null $cert Certificate row.
 * @param string                   $key  Column name.
 * @return string Escaped value.
 */
function field( ?array $cert, string $key ): string {
	return esc_html( (string) ( $cert[ $key ] ?? '' ) );
}

/**
 * Render a view file with the given variables in scope.
 *
 * The locals here are deliberately prefixed. extract() with EXTR_SKIP will not
 * overwrite a variable that already exists in the scope, so a plain `$view`
 * parameter would silently shadow a `view` key in $vars — the view would read
 * its own filename instead of the value it was passed.
 *
 * @param string              $dp_view View name, relative to includes/views, without extension.
 * @param array<string,mixed> $dp_vars Variables extracted into the view's scope.
 */
function view( string $dp_view, array $dp_vars = array() ): void {
	$dp_file = DEFTESE_DIR . 'includes/views/' . $dp_view . '.php';

	if ( ! is_readable( $dp_file ) ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Controlled, internal view data.
	extract( $dp_vars, EXTR_SKIP );
	require $dp_file;
}

/**
 * Capture a view's output instead of echoing it.
 *
 * @param string              $dp_view View name.
 * @param array<string,mixed> $dp_vars View variables.
 * @return string Rendered markup.
 */
function view_to_string( string $dp_view, array $dp_vars = array() ): string {
	ob_start();
	view( $dp_view, $dp_vars );

	return (string) ob_get_clean();
}
