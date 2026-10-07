<?php
/**
 * Aviso en lugar de un widget de la intranet (visitante sin sesión o sin permiso, D-1). No carga JS ni
 * los estilos del plugin: usa marcado simple que el tema de la intranet puede mostrar.
 *
 * Datos ($data):
 * - icon    (string) Clases de Font Awesome (se ven solo si el tema carga Font Awesome).
 * - message (string) Mensaje.
 * - link    (array)  Opcional: url y label de una acción (por ejemplo, iniciar sesión).
 *
 * @package Probolsas\Eventos
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ep-public ep-public-notice" role="status">
	<p class="ep-public-notice__message">
		<i class="<?php echo esc_attr( $data['icon'] ); ?>" aria-hidden="true"></i>
		<?php echo esc_html( $data['message'] ); ?>
	</p>
	<?php if ( ! empty( $data['link'] ) ) : ?>
		<p class="ep-public-notice__action">
			<a href="<?php echo esc_url( $data['link']['url'] ); ?>"><?php echo esc_html( $data['link']['label'] ); ?></a>
		</p>
	<?php endif; ?>
</div>
