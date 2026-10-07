<?php
/**
 * Íconos permitidos para los tipos de evento (fuente única de verdad).
 *
 * Clave: nombre del ícono sólido de Font Awesome Free 7 (se muestra con `fa-solid fa-<clave>`).
 * Valor: [ etiqueta en español, palabras clave para el buscador del selector ].
 * Se expone al navegador en `window.epConfig.icons` (ver IconCatalog). Agregar un ícono aquí lo habilita
 * en la API y en el selector a la vez; antes, verificar que exista en Font Awesome Free (estilo sólido).
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return [
	// Celebraciones.
	'cake-candles'         => [ 'Pastel', 'cumpleaños celebración aniversario' ],
	'gift'                 => [ 'Regalo', 'cumpleaños obsequio sorpresa' ],
	'gifts'                => [ 'Regalos', 'navidad celebración obsequios' ],
	'champagne-glasses'    => [ 'Brindis', 'celebración fiesta aniversario' ],
	'trophy'               => [ 'Trofeo', 'reconocimiento premio logro' ],
	'award'                => [ 'Distinción', 'reconocimiento calidad certificación' ],
	'medal'                => [ 'Medalla', 'reconocimiento logro' ],
	'star'                 => [ 'Estrella', 'especial destacado importante' ],
	'tree'                 => [ 'Árbol', 'navidad naturaleza' ],
	'music'                => [ 'Música', 'fiesta concierto integración' ],
	'utensils'             => [ 'Cubiertos', 'almuerzo comida integración' ],
	'mug-hot'              => [ 'Café', 'reunión informal pausa activa' ],

	// Formación.
	'graduation-cap'       => [ 'Birrete', 'capacitación formación grado' ],
	'chalkboard-user'      => [ 'Tablero', 'capacitación clase taller charla' ],
	'book-open-reader'     => [ 'Lectura', 'formación curso estudio' ],
	'lightbulb'            => [ 'Idea', 'innovación taller creatividad' ],
	'laptop'               => [ 'Portátil', 'virtual en línea curso' ],
	'video'                => [ 'Video', 'videollamada reunión virtual' ],

	// Reuniones y trabajo.
	'briefcase'            => [ 'Maletín', 'reunión laboral trabajo' ],
	'handshake'            => [ 'Acuerdo', 'reunión clientes proveedores' ],
	'people-group'         => [ 'Grupo', 'equipo reunión colaboradores' ],
	'users'                => [ 'Personas', 'equipo talento humano' ],
	'user-tie'             => [ 'Directivo', 'gerencia junta comité' ],
	'comments'             => [ 'Conversación', 'reunión diálogo comité' ],
	'bullhorn'             => [ 'Megáfono', 'anuncio comunicado lanzamiento' ],
	'microphone'           => [ 'Micrófono', 'conferencia charla presentación' ],
	'chart-line'           => [ 'Gráfica', 'resultados indicadores informe' ],
	'clipboard-check'      => [ 'Lista verificada', 'auditoría inspección revisión' ],
	'calendar-check'       => [ 'Calendario', 'agenda cita programación' ],
	'calendar-day'         => [ 'Día', 'fecha jornada' ],

	// Bienestar y salud.
	'heart-pulse'          => [ 'Salud', 'bienestar médico jornada de salud' ],
	'stethoscope'          => [ 'Estetoscopio', 'médico examen salud' ],
	'hand-holding-heart'   => [ 'Solidaridad', 'donación voluntariado bienestar' ],
	'ribbon'               => [ 'Lazo', 'campaña conmemoración causa' ],
	'baby'                 => [ 'Bebé', 'nacimiento familia' ],
	'futbol'               => [ 'Fútbol', 'deporte campeonato torneo' ],
	'person-running'       => [ 'Carrera', 'deporte pausa activa actividad física' ],

	// Seguridad y ambiente.
	'helmet-safety'        => [ 'Casco', 'seguridad industrial sst simulacro' ],
	'shield-halved'        => [ 'Escudo', 'seguridad protección' ],
	'fire-extinguisher'    => [ 'Extintor', 'emergencias simulacro brigada' ],
	'triangle-exclamation' => [ 'Advertencia', 'riesgo alerta importante' ],
	'leaf'                 => [ 'Hoja', 'ambiental sostenibilidad' ],
	'recycle'              => [ 'Reciclaje', 'ambiental residuos' ],
	'seedling'             => [ 'Brote', 'siembra ambiental crecimiento' ],

	// Empresa y operación.
	'building'             => [ 'Edificio', 'sede instalaciones empresa' ],
	'industry'             => [ 'Industria', 'planta producción visita' ],
	'gears'                => [ 'Engranajes', 'operaciones mantenimiento procesos' ],
	'truck'                => [ 'Camión', 'logística despacho' ],
	'plane'                => [ 'Avión', 'viaje feria internacional' ],
	'bus'                  => [ 'Bus', 'salida paseo transporte' ],
	'coins'                => [ 'Monedas', 'finanzas nómina pagos' ],
	'landmark'             => [ 'Institución', 'entidad gobierno legal' ],
	'flag'                 => [ 'Bandera', 'fecha patria festivo' ],
	'church'               => [ 'Iglesia', 'ceremonia religiosa' ],
	'circle-info'          => [ 'Información', 'aviso general' ],
];
