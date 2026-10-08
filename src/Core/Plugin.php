<?php
/**
 * Raíz de composición del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core;

use Probolsas\Eventos\Core\Lifecycle\Activator;
use Probolsas\Eventos\Core\Lifecycle\Uninstaller;
use Probolsas\Eventos\Domains\EventType\EventTypeServiceProvider;
use Probolsas\Eventos\Domains\Media\MediaServiceProvider;
use Probolsas\Eventos\Shared\SharedServiceProvider;

/**
 * Construye el contenedor, registra los proveedores de cada módulo y conecta el plugin con WordPress.
 *
 * Es el único lugar donde se decide qué módulos componen el plugin.
 */
final class Plugin {

	/**
	 * Versión del plugin. Debe coincidir con la cabecera "Version" del archivo principal y con package.json.
	 */
	public const VERSION = '2.0.0';

	/**
	 * Instancia activa durante la petición.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Contenedor de dependencias.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Proveedores de los módulos del plugin.
	 *
	 * @var list<ServiceProvider>
	 */
	private array $providers;

	/**
	 * Crea el plugin y registra los servicios de todos los módulos.
	 *
	 * @param string $plugin_file Ruta absoluta del archivo principal del plugin.
	 */
	private function __construct( string $plugin_file ) {
		$this->container = new Container();
		$this->container->set(
			PluginContext::class,
			static fn(): PluginContext => PluginContext::from_plugin_file( $plugin_file, self::VERSION )
		);

		// Los dominios (eventos, medios, panel) se agregan aquí a medida que se construyen.
		$this->providers = [
			new CoreServiceProvider(),
			new SharedServiceProvider(),
			new EventTypeServiceProvider(),
			new MediaServiceProvider(),
		];

		foreach ( $this->providers as $provider ) {
			$provider->register( $this->container );
		}
	}

	/**
	 * Inicializa el plugin desde el archivo principal.
	 *
	 * @param string $plugin_file Ruta absoluta del archivo principal del plugin.
	 */
	public static function init( string $plugin_file ): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self( $plugin_file );

		register_activation_hook( $plugin_file, [ self::$instance, 'activate' ] );
		add_action( 'plugins_loaded', [ self::$instance, 'boot' ] );
	}

	/**
	 * Instancia activa, o null si el plugin no se ha inicializado.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Elimina todos los datos del plugin. Se invoca desde uninstall.php.
	 *
	 * @param string $plugin_file Ruta absoluta del archivo principal del plugin.
	 */
	public static function uninstall( string $plugin_file ): void {
		( new self( $plugin_file ) )->container->get( Uninstaller::class )->uninstall();
	}

	/**
	 * Arranca los módulos que necesitan conectarse con WordPress.
	 */
	public function boot(): void {
		foreach ( $this->providers as $provider ) {
			if ( $provider instanceof BootableProvider ) {
				$provider->boot( $this->container );
			}
		}
	}

	/**
	 * Ejecuta las tareas de activación.
	 */
	public function activate(): void {
		$this->container->get( Activator::class )->activate();
	}

	/**
	 * Contenedor de dependencias del plugin.
	 */
	public function container(): Container {
		return $this->container;
	}
}
