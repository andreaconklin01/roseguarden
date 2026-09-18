<?php
/**
 * A single inline notice.
 *
 * @package DeftesePro
 *
 * @var string $tone    One of success, warning, danger, info.
 * @var string $message Notice text.
 */

defined( 'ABSPATH' ) || exit;

$dp_tone = in_array( $tone ?? '', array( 'success', 'warning', 'danger', 'info' ), true ) ? $tone : 'info';
?>
<div class="dp-notice dp-notice--<?php echo esc_attr( $dp_tone ); ?>" role="status">
	<span class="dp-notice__icon" aria-hidden="true"></span>
	<p class="dp-notice__text"><?php echo esc_html( $message ?? '' ); ?></p>
</div>
