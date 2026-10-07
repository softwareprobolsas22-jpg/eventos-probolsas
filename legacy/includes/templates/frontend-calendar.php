<?php
/**
 * Template del calendario para frontend
 * 
 * Variables disponibles:
 * - $atts: Atributos del shortcode
 * - $calendar_id: ID único del calendario
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar que las variables estén disponibles
if (!isset($atts) || !isset($calendar_id)) {
    return;
}
?>

<div class="eventos-calendar-wrapper" id="<?php echo esc_attr($calendar_id); ?>">
    
    <!-- HEADER DEL CALENDARIO CON NAVEGACIÓN Y FILTROS -->
    <div class="calendar-header-section">
        
        <!-- Título y navegación principal -->
        <div class="calendar-title-nav d-flex justify-content-between align-items-center mb-4">
            <div class="calendar-title-group">
                <h2 class="calendar-main-title mb-1">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <span id="current-month-name"><?php echo date('F'); ?></span>
                    <span id="current-year"><?php echo date('Y'); ?></span>
                </h2>
                <p class="text-muted mb-0">
                    <span id="events-count">0</span> eventos este mes
                </p>
            </div>
            
            <!-- Navegación con flechas -->
            <?php if ($atts['show_navigation']): ?>
            <div class="calendar-navigation d-flex align-items-center gap-2">
                <button type="button" id="prev-month" class="btn btn-outline-primary">
                    <i class="fas fa-chevron-left"></i>
                    <span class="d-none d-md-inline ms-1">Anterior</span>
                </button>
                
                <button type="button" id="today-btn" class="btn btn-primary">
                    <i class="fas fa-home me-1"></i>
                    <span class="d-none d-md-inline">Hoy</span>
                </button>
                
                <button type="button" id="next-month" class="btn btn-outline-primary">
                    <span class="d-none d-md-inline me-1">Siguiente</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- FILTROS AVANZADOS -->
        <?php if ($atts['show_filters'] || $atts['show_search']): ?>
        <div class="eventos-filters-section">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Filtros de Eventos
                        <button class="btn btn-sm btn-outline-secondary ms-2 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtros-collapse">
                            <i class="fas fa-cog"></i>
                        </button>
                    </h5>
                </div>
                <div class="collapse show" id="filtros-collapse">
                    <div class="card-body">
                        <div class="row">
                            
                            <!-- Búsqueda por texto -->
                            <?php if ($atts['show_search']): ?>
                            <div class="col-md-4">
                                <label for="calendar-search" class="form-label">
                                    <i class="fas fa-search me-1"></i>
                                    Buscar eventos
                                </label>
                                <input type="text" 
                                       id="calendar-search" 
                                       class="form-control" 
                                       placeholder="Título o descripción..."
                                       autocomplete="off">
                            </div>
                            <?php endif; ?>
                            
                            <!-- Filtro por tipo -->
                            <div class="col-md-3">
                                <label for="calendar-type-filter" class="form-label">
                                    <i class="fas fa-tags me-1"></i>
                                    Tipo de evento
                                </label>
                                <select id="calendar-type-filter" class="form-select">
                                    <option value="">Todos los tipos</option>
                                    <option value="cumpleanos">🎂 Cumpleaños</option>
                                    <option value="capacitacion">🎓 Capacitaciones</option>
                                    <option value="reunion_especial">⭐ Reuniones Especiales</option>
                                    <option value="reunion_laboral">💼 Reuniones Laborales</option>
                                </select>
                            </div>
                            
                            <!-- Selectores de navegación manual -->
                            <div class="col-md-2">
                                <label for="month-selector" class="form-label">
                                    <i class="fas fa-calendar me-1"></i>
                                    Mes
                                </label>
                                <select id="month-selector" class="form-select">
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="year-selector" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Año
                                </label>
                                <select id="year-selector" class="form-select">
                                    <?php
                                    $current_year = date('Y');
                                    for ($year = $current_year - 2; $year <= $current_year + 5; $year++): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year == $current_year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <!-- Botón limpiar filtros -->
                            <div class="col-md-1">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="button" 
                                        id="clear-filters" 
                                        class="btn btn-outline-secondary w-100"
                                        data-tooltip="Limpiar todos los filtros">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Filtros de fecha avanzados -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-3">
                                <label for="date-filter-from" class="form-label">
                                    <i class="fas fa-calendar-check me-1"></i>
                                    Desde
                                </label>
                                <input type="date" id="date-filter-from" class="form-control">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date-filter-to" class="form-label">
                                    <i class="fas fa-calendar-times me-1"></i>
                                    Hasta
                                </label>
                                <input type="date" id="date-filter-to" class="form-control">
                            </div>
                            
                            <!-- Filtros rápidos -->
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-bolt me-1"></i>
                                    Filtros rápidos
                                </label>
                                <div class="d-grid d-sm-flex gap-2 gap-sm-2 w-100 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary quick-filter col-6 col-sm-auto" data-filter="today">
                                        Hoy
                                    </button>
                                    <button type="button" class="btn btn-outline-primary quick-filter col-6 col-sm-auto" data-filter="week">
                                        Esta semana
                                    </button>
                                    <button type="button" class="btn btn-outline-primary quick-filter col-6 col-sm-auto" data-filter="month">
                                        Este mes
                                    </button>
                                    <button type="button" class="btn btn-outline-primary quick-filter col-6 col-sm-auto" data-filter="upcoming">
                                        Próximos
                                    </button>
                              </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ESTADO DE CARGA -->
    <div id="calendar-loading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando eventos...</span>
        </div>
        <p class="mt-3 text-muted">Cargando calendario...</p>
    </div>
    
    <!-- CALENDARIO PRINCIPAL -->
    <div id="eventos-calendar" class="calendar-main-content">
        
        <!-- Grid del calendario responsive -->
        <div class="calendar-grid">
            
            <!-- Encabezado con días de la semana -->
            <div class="calendar-weekdays">
                <div class="calendar-weekday">Dom</div>
                <div class="calendar-weekday">Lun</div>
                <div class="calendar-weekday">Mar</div>
                <div class="calendar-weekday">Mié</div>
                <div class="calendar-weekday">Jue</div>
                <div class="calendar-weekday">Vie</div>
                <div class="calendar-weekday">Sáb</div>
            </div>
            
            <!-- Días del calendario -->
            <div class="calendar-days" id="calendar-days-container">
                <!-- Los días se generarán dinámicamente via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- ESTADÍSTICAS Y LEYENDA -->
    <?php if ($atts['show_stats'] || $atts['show_legend']): ?>
    <div class="calendar-footer-section mt-4">
        <div class="row">
            
            <!-- Estadísticas -->
            <?php if ($atts['show_stats']): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Estadísticas del mes
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center" id="calendar-stats">
                            <div class="col-6 col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-primary" id="stat-total">0</div>
                                    <div class="stat-label">Total</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-success" id="stat-today">0</div>
                                    <div class="stat-label">Hoy</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-warning" id="stat-week">0</div>
                                    <div class="stat-label">Esta semana</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number text-info" id="stat-upcoming">0</div>
                                    <div class="stat-label">Próximos</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Leyenda de tipos de eventos -->
            <?php if ($atts['show_legend']): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-palette me-2"></i>
                            Tipos de eventos
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="legend-items">
                            <div class="legend-item d-flex align-items-center mb-2">
                                <div class="legend-color" style="background-color: #FFE082;"></div>
                                <span class="legend-icon me-2">🎂</span>
                                <span class="legend-label">Cumpleaños</span>
                                <span class="badge bg-light text-dark ms-auto" id="count-cumpleanos">0</span>
                            </div>
                            <div class="legend-item d-flex align-items-center mb-2">
                                <div class="legend-color" style="background-color: #A5D6A7;"></div>
                                <span class="legend-icon me-2">🎓</span>
                                <span class="legend-label">Capacitaciones</span>
                                <span class="badge bg-light text-dark ms-auto" id="count-capacitacion">0</span>
                            </div>
                            <div class="legend-item d-flex align-items-center mb-2">
                                <div class="legend-color" style="background-color: #81D4FA;"></div>
                                <span class="legend-icon me-2">⭐</span>
                                <span class="legend-label">Reuniones Especiales</span>
                                <span class="badge bg-light text-dark ms-auto" id="count-reunion_especial">0</span>
                            </div>
                            <div class="legend-item d-flex align-items-center mb-2">
                                <div class="legend-color" style="background-color: #CE93D8;"></div>
                                <span class="legend-icon me-2">💼</span>
                                <span class="legend-label">Reuniones Laborales</span>
                                <span class="badge bg-light text-dark ms-auto" id="count-reunion_laboral">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- MENSAJE CUANDO NO HAY EVENTOS -->
    <div id="no-events-message" class="alert alert-info text-center" style="display: none;">
        <i class="fas fa-calendar-times fa-3x mb-3 text-muted"></i>
        <h5>No hay eventos para mostrar</h5>
        <p class="text-muted mb-0">
            No se encontraron eventos con los filtros aplicados. 
            <button type="button" class="btn btn-link p-0" id="reset-filters-link">
                Limpiar filtros
            </button>
        </p>
    </div>
    
    <!-- VISTA MÓVIL ALTERNATIVA -->
    <div id="mobile-events-list" class="d-lg-none mobile-calendar-view" style="display: none;">
        <div class="mobile-calendar-header">
            <h5>
                <i class="fas fa-mobile-alt me-2"></i>
                <span id="mobile-month-name"></span> <span id="mobile-year"></span>
            </h5>
        </div>
        <div id="mobile-events-container">
            <!-- Los eventos se cargarán aquí en vista móvil -->
        </div>
    </div>
</div>

<!-- TOAST PARA NOTIFICACIONES -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="notification-toast" class="toast" role="alert">
        <div class="toast-header">
            <i id="toast-icon" class="fas fa-info-circle text-info me-2"></i>
            <strong class="me-auto">Eventos</strong>
            <small id="toast-time">ahora</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toast-message">
            Mensaje aquí
        </div>
    </div>
</div>

<!-- ESTILOS ESPECÍFICOS DEL CALENDARIO -->
<style>
/* Estilos específicos para este calendario */
#<?php echo esc_attr($calendar_id); ?> {
    --calendar-primary: #007bff;
    --calendar-success: #28a745;
    --calendar-warning: #ffc107;
    --calendar-danger: #dc3545;
    --calendar-info: #17a2b8;
    --calendar-light: #f8f9fa;
    --calendar-dark: #343a40;
    
    /* Colores de eventos */
    --color-cumpleanos: #FFE082;
    --color-capacitacion: #A5D6A7;
    --color-reunion-especial: #81D4FA;
    --color-reunion-laboral: #CE93D8;
}

/* Calendar Grid */
.calendar-grid {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

/* Weekdays header */
.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid #dee2e6;
}

.calendar-weekday {
    padding: 1rem 0.75rem;
    text-align: center;
    font-weight: 700;
    color: var(--calendar-dark);
    border-right: 1px solid #dee2e6;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.calendar-weekday:last-child {
    border-right: none;
}

/* Calendar days grid */
.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    min-height: 500px;
}

/* Individual day cells */
.calendar-day {
    border: 1px solid #f1f3f4;
    padding: 0.75rem;
    min-height: 120px;
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.calendar-day:hover {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.calendar-day.other-month {
    background: #f8f9fa;
    color: #6c757d;
    opacity: 0.6;
}

.calendar-day.today {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border: 3px solid var(--calendar-primary);
    font-weight: bold;
    position: relative;
}

.calendar-day.today::before {
    content: 'HOY';
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--calendar-primary);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 700;
    z-index: 2;
}

.calendar-day.has-events {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border-left: 4px solid var(--calendar-warning);
}

.calendar-day.has-events.today {
    background: linear-gradient(135deg, #e1f5fe, #b3e5fc);
    border-left: 4px solid var(--calendar-primary);
}

.calendar-day-number {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--calendar-dark);
    position: relative;
    z-index: 1;
}

.calendar-day.other-month .calendar-day-number {
    color: #6c757d;
}

.calendar-day.today .calendar-day-number {
    color: var(--calendar-primary);
    font-size: 1.3rem;
}

/* Events in calendar */
.calendar-events {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 0.25rem;
}

.calendar-event {
    background: var(--calendar-primary);
    color: white;
    padding: 3px 6px;
    border-radius: 6px;
    font-size: 0.7rem;
    margin-bottom: 1px;
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.calendar-event:hover {
    transform: translateY(-1px) scale(1.02);
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    z-index: 10;
}

.calendar-event i {
    font-size: 0.6rem;
    flex-shrink: 0;
}

/* Event types */
.calendar-event.cumpleanos {
    background: linear-gradient(135deg, #FFD54F, #FFCC02);
    color: #8d6e00;
    font-weight: 700;
}

.calendar-event.capacitacion {
    background: linear-gradient(135deg, #81C784, #4CAF50);
    color: white;
}

.calendar-event.reunion-especial {
    background: linear-gradient(135deg, #64B5F6, #2196F3);
    color: white;
}

.calendar-event.reunion-laboral {
    background: linear-gradient(135deg, #BA68C8, #9C27B0);
    color: white;
}

.calendar-event-more {
    background: rgba(108, 117, 125, 0.8);
    color: white;
    padding: 2px 4px;
    border-radius: 4px;
    font-size: 0.6rem;
    text-align: center;
    font-weight: 600;
    margin-top: 2px;
    cursor: pointer;
}

.calendar-event-more:hover {
    background: rgba(108, 117, 125, 1);
}

/* Navigation buttons */
.calendar-navigation .btn {
    min-width: 44px;
    min-height: 44px;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    border: 2px solid transparent;
}

.calendar-navigation .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Filters section */
.eventos-filters-section {
    margin-bottom: 2rem;
}

.eventos-filters-section .card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 12px;
}

/* Stats */
.stat-item {
    padding: 0.5rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Legend */

.legend-item {
    padding: 0.5rem !important;
    border-bottom: 1px solid #f1f3f4;
    overflow-x: auto;
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.legend-item::-webkit-scrollbar {
  display: none;
}

.legend-item:last-child {
    border-bottom: none;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    margin-right: 0.5rem;
    flex-shrink: 0;
    border: 1px solid rgba(0,0,0,0.1);
}

.legend-icon {
    font-size: 1rem;
}

.legend-label {
    font-weight: 500;
}

/* Quick filters */
.quick-filter.active {
    background-color: var(--calendar-primary);
    border-color: var(--calendar-primary);
    color: white;
}

/* Mobile view */
.mobile-calendar-view {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 1rem;
}

.mobile-calendar-header {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}

.calendar-event {
    max-width: 100% !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    min-width: 0 !important;
}

.calendar-event .event-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}

/* FIX: Grid responsivo */
.calendar-days {
    width: 100%;
    overflow-x: auto;
}

.calendar-day {
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
}

@media (max-width: 767px) {
    .calendar-grid { 
        display: none !important; 
    }
    
    #mobile-events-list { 
        display: block !important; 
    }
    
    .mobile-calendar-view {
        width: 100%;
        max-width: 100vw;
    }
    
    .mobile-events-container {
        width: 100%;
        box-sizing: border-box;
        padding: 0.5rem;
    }
    
    .mobile-event-card {
        width: 100%;
        box-sizing: border-box;
        margin-bottom: 0.5rem;
    }
    
    .mobile-event-item{
        overflow: hidden;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    
    .mobile-event-card .card-body {
        padding: 0.75rem;
        overflow: hidden;
        width: max-content;
        min-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .mobile-event-card .d-flex {
        flex-wrap: nowrap !important;
        min-width: max-content;
    }
    
    .evento-date-mobile {
        flex-shrink: 0;
        width: 50px;
        height: 50px;
        margin-right: 0.75rem;
    }
    
    .flex-grow-1 {
        min-width: 200px;
        overflow: visible;
        flex-shrink: 0;
        white-space: nowrap;
    }
    
    .mobile-event-card h6,
    .event-meta {
        white-space: nowrap;
        min-width: max-content;
    }
    
    .mobile-event-card .btn, .mobile-event-item .evento-details-btn{
        flex-shrink: 0;
        margin-left: 0.75rem;
        margin-right: 0.75rem;
    }
    
    .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .mobile-event-card::-webkit-scrollbar {
        height: 4px;
    }
    
    .mobile-event-card::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.2);
        border-radius: 2px;
    }
}

@media (min-width: 768px) {
    .calendar-grid { 
        display: block !important; 
    }
    
    #mobile-events-list { 
        display: none !important; 
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .calendar-days {
        min-height: 350px;
    }
    
    .calendar-day {
        min-height: 80px;
        padding: 0.5rem;
    }
    
    .calendar-event {
        font-size: 0.6rem;
        padding: 2px 4px;
    }
    
    .calendar-day-number {
        font-size: 1rem;
    }
    
    .calendar-navigation .btn {
        min-width: 36px;
        min-height: 36px;
        font-size: 0.85rem;
    }
    
    .calendar-navigation .btn span {
        display: none !important;
    }
    
    .calendar-weekday {
        padding: 0.75rem 0.25rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 576px) {
    .calendar-day {
        min-height: 60px;
        padding: 0.25rem;
    }
    
    .calendar-event {
        font-size: 0.55rem;
        padding: 1px 3px;
    }
    
    .calendar-weekday {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
}

/* Loading state */
#calendar-loading {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Animations */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(10px);
    }
    to { 
        opacity: 1; 
        transform: translateY(0);
    }
}
</style>

<!-- SCRIPT DE INICIALIZACIÓN -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar que EventosCalendar esté disponible
    if (typeof EventosCalendar === 'undefined') {
        console.error('EventosCalendar class not found');
        return;
    }
    
    // Configuración específica para este calendario
    const calendarConfig = {
        containerId: '<?php echo esc_js($calendar_id); ?>',
        year: <?php echo intval($atts['year']); ?>,
        month: <?php echo intval($atts['month']); ?>,
        showFilters: <?php echo $atts['show_filters'] ? 'true' : 'false'; ?>,
        showSearch: <?php echo $atts['show_search'] ? 'true' : 'false'; ?>,
        showNavigation: <?php echo $atts['show_navigation'] ? 'true' : 'false'; ?>,
        showLegend: <?php echo $atts['show_legend'] ? 'true' : 'false'; ?>,
        showStats: <?php echo $atts['show_stats'] ? 'true' : 'false'; ?>,
        autoRefresh: <?php echo $atts['auto_refresh'] ? 'true' : 'false'; ?>,
        compactMode: <?php echo $atts['compact_mode'] ? 'true' : 'false'; ?>,
        height: '<?php echo esc_js($atts['height']); ?>',
        theme: '<?php echo esc_js($atts['theme']); ?>',
        types: '<?php echo esc_js($atts['types']); ?>',
        limit: <?php echo intval($atts['limit']); ?>,
        view: '<?php echo esc_js($atts['view']); ?>'
    };
    
    // Crear instancia del calendario
    const calendarInstance = new EventosCalendar(calendarConfig);
    
    // Guardar referencia global para este calendario
    window['eventosCalendar_<?php echo esc_js($calendar_id); ?>'] = calendarInstance;
    
    // Auto-refresh si está habilitado
    <?php if ($atts['auto_refresh']): ?>
    setInterval(function() {
        if (calendarInstance && typeof calendarInstance.loadCalendar === 'function') {
            calendarInstance.loadCalendar();
        }
    }, 300000); // 5 minutos
    <?php endif; ?>
    
    // Manejo de vista móvil responsiva
    function handleResponsiveView() {
        const isMobile = window.innerWidth < 768; // CORREGIDO: cambiar de 992 a 768
        const calendarGrid = document.querySelector('#<?php echo esc_js($calendar_id); ?> .calendar-grid');
        const mobileView = document.querySelector('#<?php echo esc_js($calendar_id); ?> #mobile-events-list');
        
        if (isMobile && calendarInstance.currentEvents && Object.keys(calendarInstance.currentEvents).length > 0) {
            calendarGrid.style.display = 'none';
            mobileView.style.display = 'block';
            renderMobileView();
        } else {
            calendarGrid.style.display = 'block';
            mobileView.style.display = 'none';
        }
    }
    
    // Renderizar vista móvil
    function renderMobileView() {
        const container = document.querySelector('#<?php echo esc_js($calendar_id); ?> #mobile-events-container');
        const monthName = document.querySelector('#<?php echo esc_js($calendar_id); ?> #current-month-name').textContent;
        const year = document.querySelector('#<?php echo esc_js($calendar_id); ?> #current-year').textContent;
        
        document.querySelector('#<?php echo esc_js($calendar_id); ?> #mobile-month-name').textContent = monthName;
        document.querySelector('#<?php echo esc_js($calendar_id); ?> #mobile-year').textContent = year;
        
        let mobileHTML = '';
        
        if (calendarInstance.currentEvents) {
            // Convertir eventos agrupados por día a lista chronológica
            const allEvents = [];
            Object.keys(calendarInstance.currentEvents).forEach(day => {
                calendarInstance.currentEvents[day].forEach(event => {
                    allEvents.push({
                        ...event,
                        day: parseInt(day)
                    });
                });
            });
            
            // Ordenar por fecha
            allEvents.sort((a, b) => a.day - b.day);
            
            allEvents.forEach(event => {
                const dateObj = new Date(calendarInstance.currentYear, calendarInstance.currentMonth - 1, event.day);
                const formattedDate = dateObj.toLocaleDateString('es-ES', { 
                    weekday: 'long', 
                    day: 'numeric',
                    month: 'long'
                });
                
                mobileHTML += `
                    <div class="mobile-event-item card mb-2 evento-clickable" data-event-id="${event.id}">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="evento-date-mobile me-3">
                                    <div class="date-day">${event.day}</div>
                                    <div class="date-month">${dateObj.toLocaleDateString('es-ES', { month: 'short' })}</div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${event.title}</h6>
                                    <p class="text-muted mb-1">
                                        <span class="badge" style="background-color: ${event.color};">
                                            <i class="${event.icon}"></i> ${event.type_label || event.type}
                                        </span>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>${formattedDate}
                                        ${event.event_time ? `<i class="fas fa-clock ms-2 me-1"></i>${event.formatted_time}` : ''}
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm evento-details-btn" data-event-id="${event.id}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        if (mobileHTML === '') {
            mobileHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-calendar-times me-2"></i>
                    No hay eventos en este mes
                </div>
            `;
        }
        
        container.innerHTML = mobileHTML;
    }
    
    // Event listeners para responsive
    window.addEventListener('resize', handleResponsiveView);
    window.addEventListener('orientationchange', function() {
        setTimeout(handleResponsiveView, 100);
    });
    
    // Manejo de filtros rápidos
    document.querySelectorAll('#<?php echo esc_js($calendar_id); ?> .quick-filter').forEach(button => {
        button.addEventListener('click', function() {
            // Remover clase active de todos los botones
            document.querySelectorAll('#<?php echo esc_js($calendar_id); ?> .quick-filter').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Agregar clase active al botón clickeado
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            const today = new Date();
            const dateFromInput = document.querySelector('#<?php echo esc_js($calendar_id); ?> #date-filter-from');
            const dateToInput = document.querySelector('#<?php echo esc_js($calendar_id); ?> #date-filter-to');
            
            switch (filter) {
                case 'today':
                    const todayStr = today.toISOString().split('T')[0];
                    dateFromInput.value = todayStr;
                    dateToInput.value = todayStr;
                    break;
                    
                case 'week':
                    const startOfWeek = new Date(today);
                    const endOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - today.getDay());
                    endOfWeek.setDate(today.getDate() + (6 - today.getDay()));
                    
                    dateFromInput.value = startOfWeek.toISOString().split('T')[0];
                    dateToInput.value = endOfWeek.toISOString().split('T')[0];
                    break;
                    
                case 'month':
                    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    
                    dateFromInput.value = startOfMonth.toISOString().split('T')[0];
                    dateToInput.value = endOfMonth.toISOString().split('T')[0];
                    break;
                    
                case 'upcoming':
                    const nextMonth = new Date(today);
                    nextMonth.setMonth(today.getMonth() + 1);
                    
                    dateFromInput.value = today.toISOString().split('T')[0];
                    dateToInput.value = nextMonth.toISOString().split('T')[0];
                    break;
            }
            
            // Aplicar filtros
            if (calendarInstance && typeof calendarInstance.applyFilters === 'function') {
                calendarInstance.applyFilters();
            }
        });
    });
    
    // Reset filters link
    document.querySelector('#<?php echo esc_js($calendar_id); ?> #reset-filters-link').addEventListener('click', function() {
        document.querySelector('#<?php echo esc_js($calendar_id); ?> #clear-filters').click();
    });
    
    // Inicializar tooltips de Bootstrap si está disponible
// if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
//         const tooltipElements = document.querySelectorAll('#<?php echo esc_js($calendar_id); ?>  [data-bs-toggle="tooltip"]');
//         tooltipElements.forEach(element => {
//             new bootstrap.Tooltip(element, {
//                 title: element.getAttribute(' [data-bs-toggle="tooltip"]'),
//                 placement: 'top'
//             });
//         });
//     }
    
    // Manejo de colapso de filtros
    const collapseElement = document.querySelector('#<?php echo esc_js($calendar_id); ?> #filtros-collapse');
    if (collapseElement && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
        const collapse = new bootstrap.Collapse(collapseElement, {
            toggle: false
        });
        
        // Guardar estado en localStorage
        collapseElement.addEventListener('shown.bs.collapse', function() {
            localStorage.setItem('eventos_filters_<?php echo esc_js($calendar_id); ?>', 'shown');
        });
        
        collapseElement.addEventListener('hidden.bs.collapse', function() {
            localStorage.setItem('eventos_filters_<?php echo esc_js($calendar_id); ?>', 'hidden');
        });
        
        // Restaurar estado guardado
        const savedState = localStorage.getItem('eventos_filters_<?php echo esc_js($calendar_id); ?>');
        if (savedState === 'hidden') {
            collapse.hide();
        }
    }
    
    // Función de utilidad para mostrar notificaciones
    window.showEventosNotification = function(message, type = 'info') {
        const toast = document.querySelector('#<?php echo esc_js($calendar_id); ?> #notification-toast');
        const toastIcon = document.querySelector('#<?php echo esc_js($calendar_id); ?> #toast-icon');
        const toastMessage = document.querySelector('#<?php echo esc_js($calendar_id); ?> #toast-message');
        const toastTime = document.querySelector('#<?php echo esc_js($calendar_id); ?> #toast-time');
        
        const iconClasses = {
            'success': 'fas fa-check-circle text-success',
            'error': 'fas fa-exclamation-triangle text-danger',
            'warning': 'fas fa-exclamation-circle text-warning',
            'info': 'fas fa-info-circle text-info'
        };
        
        toastIcon.className = iconClasses[type] || iconClasses.info;
        toastMessage.textContent = message;
        toastTime.textContent = 'ahora';
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toastInstance = new bootstrap.Toast(toast);
            toastInstance.show();
        }
    };
    
    // Manejar clicks en eventos para abrir modal
    document.addEventListener('click', function(e) {
        const eventElement = e.target.closest('.evento-clickable, .evento-details-btn');
        if (eventElement && eventElement.closest('#<?php echo esc_js($calendar_id); ?>')) {
            e.preventDefault();
            e.stopPropagation();
            
            const eventId = eventElement.getAttribute('data-event-id');
            if (eventId && calendarInstance && typeof calendarInstance.showEventDetails === 'function') {
                calendarInstance.showEventDetails(eventId);
            }
        }
    });
    
    // Actualizar vista responsiva inicial
    setTimeout(handleResponsiveView, 100);
});
</script>