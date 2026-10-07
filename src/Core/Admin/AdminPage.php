<?php
/**
 * Contrato de una pantalla de administración.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Admin;

/**
 * Cada dominio aporta sus pantallas implementando esta interfaz y etiquetándolas con `AdminMenu::PAGES_TAG`.
 */
interface AdminPage {

	/**
	 * Slug único de la pantalla (parámetro `page` de la URL). La pantalla principal usa `AdminMenu::ROOT_SLUG`.
	 */
	public function slug(): string;

	/**
	 * Título de la pestaña del navegador.
	 */
	public function page_title(): string;

	/**
	 * Texto de la pantalla en el menú lateral.
	 */
	public function menu_title(): string;

	/**
	 * Capability requerida para ver la pantalla.
	 */
	public function capability(): string;

	/**
	 * Orden dentro del submenú (menor primero).
	 */
	public function position(): int;

	/**
	 * Imprime la pantalla.
	 */
	public function render(): void;
}
