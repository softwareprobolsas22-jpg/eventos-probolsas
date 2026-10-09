<?php
/**
 * Feed del calendario (FullCalendar).
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Event\Presentation;

use DateTimeImmutable;
use DateTimeZone;
use Probolsas\Eventos\Domains\Event\Application\CalendarService;
use Probolsas\Eventos\Domains\Event\Domain\Event;
use Probolsas\Eventos\Domains\Event\Domain\EventSchedule;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Ui\ColorContrast;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET eventos/v1/calendar?start=&end=&types[]=`: eventos del rango visible con el formato `EventInput`
 * de FullCalendar (docs/api/events.md). Las fechas y horas van **sin zona**: el calendario se configura
 * con `timeZone: 'UTC'` y muestra la hora de pared de Colombia en cualquier equipo (R-08).
 */
final class CalendarFeedController extends RestController {

	/**
	 * Color de un evento cuyo tipo ya no existe (no debería pasar: un tipo con eventos no se elimina).
	 */
	private const FALLBACK_COLOR = '#155728';

	/**
	 * Crea el controlador.
	 *
	 * @param CalendarService $calendar  Consultas del calendario.
	 * @param EventPresenter  $presenter Tipos de los eventos, leídos una vez por petición.
	 * @param ColorContrast   $contrast  Color de texto legible.
	 */
	public function __construct(
		private readonly CalendarService $calendar,
		private readonly EventPresenter $presenter,
		private readonly ColorContrast $contrast
	) {}

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'calendar';
	}

	/**
	 * Rutas del recurso.
	 */
	public function register_routes(): void {
		$this->add_route(
			'',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'can_view' ],
				'callback'            => [ $this, 'index' ],
			]
		);
	}

	/**
	 * Eventos del rango.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			fn(): WP_REST_Response => $this->ok(
				array_map(
					[ $this, 'event_input' ],
					$this->calendar->feed(
						[
							'start' => self::text_param( $request->get_param( 'start' ) ),
							'end'   => self::text_param( $request->get_param( 'end' ) ),
							'types' => self::list_param( $request->get_param( 'types' ) ),
						]
					)
				)
			)
		);
	}

	/**
	 * Un evento con el formato `EventInput` de FullCalendar.
	 *
	 * @param Event $event Evento.
	 *
	 * @return array<string, mixed>
	 */
	public function event_input( Event $event ): array {
		$schedule = $event->schedule;
		$type     = $this->presenter->type( $event->type_id );
		$color    = $type->color ?? self::FALLBACK_COLOR;

		return [
			'id'              => (string) $event->id,
			'title'           => $event->title,
			'start'           => self::moment( $schedule->start_date, $schedule->start_time ),
			'end'             => self::end( $schedule ),
			'allDay'          => $schedule->is_all_day(),
			'backgroundColor' => $color,
			'borderColor'     => $color,
			'textColor'       => $this->contrast->readable_color( $color ),
			'extendedProps'   => [
				'typeId'        => $event->type_id,
				'icon'          => $type->icon ?? '',
				'hasAttachment' => null !== $event->attachment_id,
			],
		];
	}

	/**
	 * Fin para FullCalendar: `null` mientras el evento no tenga fin (v1). Con D-7 (v1.1), el de un evento
	 * de día completo es exclusivo (el día siguiente al último).
	 *
	 * @param EventSchedule $schedule Horario.
	 */
	private static function end( EventSchedule $schedule ): ?string {
		if ( null === $schedule->end_date && null === $schedule->end_time ) {
			return null;
		}

		if ( $schedule->is_all_day() ) {
			return ( new DateTimeImmutable( $schedule->last_date() . ' 00:00:00', new DateTimeZone( 'UTC' ) ) )->modify( '+1 day' )->format( 'Y-m-d' );
		}

		return self::moment( $schedule->last_date(), $schedule->end_time ?? $schedule->start_time );
	}

	/**
	 * Fecha (`2026-10-07`) o fecha y hora sin zona (`2026-10-07T15:00:00`).
	 *
	 * @param string      $date Fecha `Y-m-d`.
	 * @param string|null $time Hora `H:i` o null.
	 */
	private static function moment( string $date, ?string $time ): string {
		return null === $time ? $date : "{$date}T{$time}:00";
	}
}
