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
 * @param string              $view View name, relative to includes/views, without extension.
 * @param array<string,mixed> $vars Variables extracted into the view's scope.
 */
function view( string $view, array $vars = array() ): void {
	$file = DEFTESE_DIR . 'includes/views/' . $view . '.php';

	if ( ! is_readable( $file ) ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Controlled, internal view data.
	extract( $vars, EXTR_SKIP );
	require $file;
}

/**
 * Capture a view's output instead of echoing it.
 *
 * @param string              $view View name.
 * @param array<string,mixed> $vars View variables.
 * @return string Rendered markup.
 */
function view_to_string( string $view, array $vars = array() ): string {
	ob_start();
	view( $view, $vars );

	return (string) ob_get_clean();
}
