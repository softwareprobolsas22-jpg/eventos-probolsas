# Prueba de aceptación de la v1 (H-404)

> Para el PO, antes de llevar la v1 (2.0.0) a producción (D-14). Se hace en staging o, si no hay staging, en producción después de un respaldo completo (base de datos y `wp-content/uploads`). Entorno esperado: WordPress 7.1.3 con PHP 8.3 (Hostinger).

**Paquete:** el `.zip` lo arma el CI en cada push (trabajo «Paquete de release», artefacto `eventos-probolsas-zip`) o localmente con `npm run package` → `build/eventos-probolsas-2.0.0.zip`. Contiene solo lo que WordPress necesita (PHP, plantillas, configuración y `assets/dist` compilado); nada de pruebas, fuentes ni herramientas de desarrollo.

Marca cada casilla; si algo falla, anota el paso, lo que esperabas y lo que pasó.

## 1. Instalación

- [ ] **Plugins → Añadir nuevo → Subir plugin**, elegir el `.zip` e instalar. Activar sin avisos ni errores.
- [ ] En el menú aparece **Eventos** con los submenús «Eventos», «Tipos de evento» y «Ajustes».
- [ ] «Tipos de evento» trae los 4 tipos iniciales con sus colores (D-13).
- [ ] En **Herramientas → Salud del sitio** no aparecen errores nuevos de PHP.

## 2. Permisos (D-1, D-10, R-22)

- [ ] Un administrador ve y usa todo el menú Eventos.
- [ ] Un usuario sin rol de administrador (por ejemplo, suscriptor) no ve el menú en wp-admin, pero sí el calendario en la intranet.
- [ ] Un visitante sin sesión ve en la página del calendario el aviso «Inicia sesión para ver el calendario de eventos.» con el enlace, y al iniciar sesión vuelve a la página.

## 3. Gestión de eventos (wp-admin)

- [ ] Crear un evento con hora (por ejemplo, 7 de octubre, 3:00 p. m.): la tabla muestra «07/10/2026» y «03:00 p. m.».
- [ ] Crear un evento sin hora: la tabla dice «Todo el día».
- [ ] Un tipo que exige adjunto no deja guardar sin imagen o PDF; el selector de la Biblioteca de Medios solo ofrece imágenes y PDF.
- [ ] Una fecha pasada muestra el aviso «Esta fecha ya pasó» pero deja guardar (D-3).
- [ ] Buscar sin tildes ni mayúsculas encuentra el evento; filtrar por tipo y por fechas funciona.
- [ ] Las tarjetas de arriba (Hoy, Próximos 30 días, Total, por tipo) cuadran con la tabla y al hacer clic la filtran (D-17).
- [ ] «Exportar CSV» abre bien en Excel (tildes, «;», fechas `dd/mm/aaaa`).
- [ ] Eliminar un evento con imagen: el evento desaparece y **la imagen sigue en la Biblioteca de Medios** (D-4).

## 4. Calendario en la intranet

- [ ] Crear una página con `[eventos_calendario]` y otra (o la misma) con `[eventos_proximos]`.
- [ ] En el computador: vista de mes, semana desde el día que configura WordPress (Ajustes → Generales), «hoy» resaltado.
- [ ] En el celular: empieza en la vista de lista y no hay que desplazarse a los lados.
- [ ] **Zona horaria:** cambiar la zona horaria del computador (por ejemplo, a Madrid o a Tokio) y recargar: el evento de las 3:00 p. m. sigue en su día y dice «03:00 p. m.».
- [ ] **Después de las 7:00 p. m. de Colombia**, «hoy» sigue siendo el día de hoy (el defecto principal del plugin anterior).
- [ ] Clic en un evento: el detalle muestra fecha, hora, descripción y la imagen o el PDF; con varios eventos ese día se puede pasar al anterior y al siguiente.
- [ ] **«Añadir a mi calendario»** con un evento con hora y otro de todo el día:
  - [ ] Google Calendar: el evento queda a las 3:00 p. m. de Colombia y dura una hora (D-15).
  - [ ] Outlook: lo mismo.
  - [ ] El de todo el día queda como evento de día completo.
- [ ] `[eventos_proximos]` lista los que vienen desde hoy; un clic abre el mismo detalle.
- [ ] El filtro de tipos del calendario muestra solo los eventos de los tipos elegidos.
- [ ] Después de editar un evento en wp-admin, el calendario lo muestra actualizado (la caché se invalida, H-401).

## 5. Archivos (QA-035)

- [ ] Las imágenes y los PDF de los eventos se ven y se descargan desde el calendario. Si Hostinger o un plugin de optimización sirven los archivos desde un CDN, confirmar que el adjunto se acepta igual al guardar (el plugin revisa el tipo real del archivo en el disco).

## 6. Desinstalación (D-16)

> Hacer esto en staging, no en producción.

- [ ] Con «Borrar todos los datos al desinstalar» **desactivado**: desactivar y borrar el plugin, volver a instalarlo y activarlo → los eventos y tipos siguen ahí.
- [ ] Con la casilla **activada**: desinstalar → al reinstalar no hay eventos y aparecen solo los 4 tipos iniciales.
- [ ] En ambos casos, los archivos siguen en la Biblioteca de Medios.

## 7. Resultado

| Fecha | Entorno | Resultado | Observaciones |
|---|---|---|---|
| | | ☐ Aceptada · ☐ Con observaciones · ☐ Rechazada | |
