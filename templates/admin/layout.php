<?php
/**
 * Layout base de las pantallas de administración.
 *
 * Datos ($data):
 * - screen   (string) Identificador de la pantalla (slug de la página); assets/src/js/pages/admin.js monta
 *              la interfaz según este valor.
 * - icon     (string) Clases de Font Awesome del ícono del encabezado.
 * - title    (string) Título de la pantalla.
 * - subtitle (string) Texto de apoyo. Opcional.
 * - actions  (string) HTML de las acciones del encabezado, ya escapado por su plantilla. Opcional; las
 *              pantallas JS pueden agregar botones en el contenedor [data-ep-header-actions].
 * - content  (string) HTML del cuerpo, ya escapado por su plantilla.
 *
 * @package Probolsas\Eventos
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ep-app" data-ep-screen="<?php echo esc_attr( $data['screen'] ); ?>">
	<header class="ep-page-header">
		<div class="ep-page-header__heading">
			<?php if ( ! empty( $data['icon'] ) ) : ?>
				<span class="ep-page-header__icon" aria-hidden="true">
					<i class="<?php echo esc_attr( $data['icon'] ); ?>"></i>
				</span>
			<?php endif; ?>
			<div class="ep-page-header__text">
				<h1 class="ep-page-header__title"><?php echo esc_html( $data['title'] ); ?></h1>
				<?php if ( ! empty( $data['subtitle'] ) ) : ?>
					<p class="ep-page-header__subtitle"><?php echo esc_html( $data['subtitle'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php // Siempre presente: las pantallas con interfaz JS agregan aquí sus botones (data-ep-header-actions). ?>
		<div class="ep-page-header__actions" data-ep-header-actions><?php echo $data['actions'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ya escapado por su plantilla parcial. ?></div>
	</header>

	<hr class="wp-header-end">

	<div class="ep-page-body">
		<?php echo $data['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ya escapado por su plantilla parcial. ?>
	</div>
</div>
