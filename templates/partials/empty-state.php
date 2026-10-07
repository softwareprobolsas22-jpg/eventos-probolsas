<?php
/**
 * Estado vacío reutilizable.
 *
 * Datos ($data):
 * - icon    (string) Clases de Font Awesome.
 * - title   (string) Título.
 * - message (string) Texto de apoyo.
 *
 * @package Probolsas\Eventos
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ep-empty-state">
	<span class="ep-empty-state__icon" aria-hidden="true">
		<i class="<?php echo esc_attr( $data['icon'] ); ?>"></i>
	</span>
	<h2 class="ep-empty-state__title"><?php echo esc_html( $data['title'] ); ?></h2>
	<p class="ep-empty-state__message"><?php echo esc_html( $data['message'] ); ?></p>
</div>
