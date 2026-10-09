# Eventos Probolsas 2.0.0 — notas de la versión

> Primera versión del plugin reconstruido (v1, Sprints 0 a 4). Reemplaza al plugin anterior (19.8.9), que ya se retiró de producción (D-9): se instala en limpio, sin migrar datos.

## Para los colaboradores (intranet)

- **Calendario** `[eventos_calendario]` con vista de mes y de lista (lista en el celular), filtro por tipo y detalle de cada evento.
- **Sin desfases de fecha ni de hora**: un evento de las 3:00 p. m. del 7 de octubre se ve así en cualquier zona horaria del equipo, y «hoy» es el día de Colombia también después de las 7:00 p. m. y pasada la medianoche.
- **Añadir a mi calendario** (`.ics`) que abre a la hora correcta en Google Calendar y Outlook.
- **Próximos eventos** `[eventos_proximos]` para portadas y barras laterales.
- Solo con sesión iniciada; los visitantes ven un aviso para iniciar sesión.

## Para los gestores (wp-admin)

- **Eventos**: tabla paginada con búsqueda sin tildes, filtros por tipo y fechas, exportación a CSV para Excel y un resumen con los eventos de hoy, de los próximos 30 días y por tipo.
- **Formulario** con validación mientras se escribe, aviso de fecha pasada e imagen o PDF desde la Biblioteca de Medios.
- **Tipos de evento** gestionables: nombre, color, ícono, orden y si exigen adjunto.
- **Ajustes**: si desinstalar borra los datos (por defecto los conserva).
- Borrar un evento nunca borra el archivo de la Biblioteca de Medios.

## Requisitos

WordPress 6.4 o superior (probado con 7.1.3) · PHP 8.3 o superior.

## Calidad

Pruebas unitarias y de integración en WordPress real, pruebas en el navegador (con la zona horaria de Tokio y auditoría de accesibilidad axe-core) y verificación del paquete en cada cambio. Los 8 defectos verificados del plugin anterior tienen su prueba automática.
