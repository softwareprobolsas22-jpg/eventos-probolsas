<?php
/**
 * Template para listado de eventos en admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap eventos-admin-wrap">
    <h1 class="wp-heading-inline">
        <i class="fas fa-calendar-alt me-2"></i>
        <?php _e('Eventos Probolsas', 'eventos-probolsas'); ?>
        <span class="badge bg-primary ms-2"><?php echo count($events); ?></span>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=eventos-probolsas-add'); ?>" class="page-title-action btn-pastel">
        <i class="fas fa-plus"></i> <?php _e('Agregar nuevo', 'eventos-probolsas'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Filtros y búsqueda mejorados -->
    <div class="eventos-filters mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    <?php _e('Filtros de búsqueda', 'eventos-probolsas'); ?>
                    <?php if (!empty($_GET['search']) || !empty($_GET['type']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                    <span class="badge bg-info ms-2">
                        <i class="fas fa-check me-1"></i>
                        <?php _e('Activos', 'eventos-probolsas'); ?>
                    </span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="get" action="" id="eventos-filter-form">
                    <input type="hidden" name="page" value="eventos-probolsas">
                    
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label for="search" class="form-label">
                                <i class="fas fa-search me-1"></i>
                                <?php _e('Buscar eventos', 'eventos-probolsas'); ?>
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                        id="search" 
                                        name="search" 
                                        class="form-control" 
                                        value="<?php echo esc_attr(isset($_GET['search']) ? $_GET['search'] : ''); ?>"
                                        placeholder="<?php _e('Título o descripción...', 'eventos-probolsas'); ?>">
                                <?php if (!empty($_GET['search'])): ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('search').value=''; document.getElementById('eventos-filter-form').submit();">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-6">
                            <label for="type" class="form-label">
                                <i class="fas fa-tags me-1"></i>
                                <?php _e('Tipo', 'eventos-probolsas'); ?>
                            </label>
                            <select id="type" name="type" class="form-select">
                                <option value=""><?php _e('Todos los tipos', 'eventos-probolsas'); ?></option>
                                <?php foreach ($event_types as $type => $config): ?>
                                <option value="<?php echo esc_attr($type); ?>" 
                                        <?php selected(isset($_GET['type']) ? $_GET['type'] : '', $type); ?>
                                        data-color="<?php echo esc_attr($config['color']); ?>">
                                    <?php echo esc_html($config['label']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-2 col-md-6">
                            <label for="date_from" class="form-label">
                                <i class="fas fa-calendar-plus me-1"></i>
                                <?php _e('Desde', 'eventos-probolsas'); ?>
                            </label>
                            <input type="date" 
                                    id="date_from" 
                                    name="date_from" 
                                    class="form-control"
                                    value="<?php echo esc_attr(isset($_GET['date_from']) ? $_GET['date_from'] : ''); ?>">
                        </div>
                        
                        <div class="col-lg-2 col-md-6">
                            <label for="date_to" class="form-label">
                                <i class="fas fa-calendar-minus me-1"></i>
                                <?php _e('Hasta', 'eventos-probolsas'); ?>
                            </label>
                            <input type="date" 
                                    id="date_to" 
                                    name="date_to" 
                                    class="form-control"
                                    value="<?php echo esc_attr(isset($_GET['date_to']) ? $_GET['date_to'] : ''); ?>">
                        </div>
                        
                        <div class="col-lg-2 col-md-12">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-eventos-primary flex-fill">
                                    <i class="fas fa-search me-1"></i>
                                    <?php _e('Buscar', 'eventos-probolsas'); ?>
                                </button>
                                
                                <a href="<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>" 
                                    class="btn btn-outline-secondary clear-filters-btn"
                                    data-tooltip="<?php _e('Limpiar filtros', 'eventos-probolsas'); ?>">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros rápidos -->
                    <div class="row mt-3">
                      <div class="col-12">
                        <span class="text-muted me-3">
                          <i class="fas fa-bolt me-1"></i>
                          <?php _e('Filtros rápidos:', 'eventos-probolsas'); ?>
                        </span>
                        <div class="d-flex flex-wrap justify-content-center gap-2 quick-filters">
                          <button type="button" class="btn btn-outline-info btn-sm quick-filter-btn" data-filter="today">
                            <i class="fas fa-calendar-day me-1"></i> <?php _e('Hoy', 'eventos-probolsas'); ?>
                          </button>
                          <button type="button" class="btn btn-outline-success btn-sm quick-filter-btn" data-filter="upcoming">
                            <i class="fas fa-arrow-up me-1"></i> <?php _e('Próximos', 'eventos-probolsas'); ?>
                          </button>
                          <button type="button" class="btn btn-outline-warning btn-sm quick-filter-btn" data-filter="this-month">
                            <i class="fas fa-calendar-alt me-1"></i> <?php _e('Este mes', 'eventos-probolsas'); ?>
                          </button>
                          <button type="button" class="btn btn-outline-dark btn-sm quick-filter-btn" data-filter="past">
                            <i class="fas fa-history me-1"></i> <?php _e('Pasados', 'eventos-probolsas'); ?>
                          </button>
                        </div>
                      </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas mejoradas -->
    <?php
    $stats = (new Eventos_Probolsas_DB())->get_stats();
    ?>
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stats-card border-left-eventos">
                <div class="card-body">
                    <div class="card-title text-eventos-primary">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <h5 class="text-eventos-primary"><?php echo esc_html($stats['total']); ?></h5>
                    <p class="card-text text-muted"><?php _e('Total de eventos', 'eventos-probolsas'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stats-card" style="border-left: 4px solid #28a745;">
                <div class="card-body">
                    <div class="card-title text-success">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                    <h5 class="text-success"><?php echo esc_html($stats['today']); ?></h5>
                    <p class="card-text text-muted"><?php _e('Eventos hoy', 'eventos-probolsas'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stats-card" style="border-left: 4px solid #17a2b8;">
                <div class="card-body">
                    <div class="card-title text-info">
                        <i class="fas fa-arrow-up fa-2x"></i>
                    </div>
                    <h5 class="text-info"><?php echo esc_html($stats['upcoming']); ?></h5>
                    <p class="card-text text-muted"><?php _e('Próximos 30 días', 'eventos-probolsas'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stats-card" style="border-left: 4px solid #ffc107;">
                <div class="card-body">
                    <div class="card-title text-warning">
                        <i class="fas fa-image fa-2x"></i>
                    </div>
                    <h5 class="text-warning"><?php echo esc_html($stats['with_image']); ?></h5>
                    <p class="card-text text-muted"><?php _e('Con imagen', 'eventos-probolsas'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas por tipo -->
    <?php if (!empty($stats['by_type'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        <?php _e('Eventos por tipo', 'eventos-probolsas'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($event_types as $type => $config): ?>
                        <?php if (isset($stats['by_type'][$type]) && $stats['by_type'][$type] > 0): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3" style="width: 40px; height: 40px; background-color: <?php echo esc_attr($config['color']); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: var(--eventos-admin-shadow);">
                                    <i class="<?php echo esc_attr($config['icon']); ?> text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1"><?php echo esc_html($stats['by_type'][$type]); ?></h6>
                                    <small class="text-muted"><?php echo esc_html($config['label']); ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Lista de eventos -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?php _e('Lista de eventos', 'eventos-probolsas'); ?>
                    <span class="badge bg-primary ms-2"><?php echo count($events); ?></span>
                </h5>
                
                <div class="header-actions">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-info btn-sm" id="export-events-btn" data-tooltip="<?php _e('Exportar eventos', 'eventos-probolsas'); ?>">
                            <i class="fas fa-download"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="import-events-btn" data-tooltip="<?php _e('Importar eventos', 'eventos-probolsas'); ?>">
                            <i class="fas fa-upload"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()" data-tooltip="<?php _e('Actualizar lista', 'eventos-probolsas'); ?>">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($events)): ?>
            <div class="text-center p-5">
                <div class="empty-state">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted"><?php _e('No hay eventos para mostrar', 'eventos-probolsas'); ?></h4>
                    <p class="text-muted mb-4">
                        <?php if (!empty($_GET['search']) || !empty($_GET['type']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                            <?php _e('No se encontraron eventos con los filtros aplicados.', 'eventos-probolsas'); ?>
                        <?php else: ?>
                            <?php _e('Aún no has creado ningún evento. ¡Crea tu primer evento ahora!', 'eventos-probolsas'); ?>
                        <?php endif; ?>
                    </p>
                    <div class="empty-state-actions">
                        <a href="<?php echo admin_url('admin.php?page=eventos-probolsas-add'); ?>" class="btn btn-eventos-primary me-2">
                            <i class="fas fa-plus me-1"></i>
                            <?php _e('Crear primer evento', 'eventos-probolsas'); ?>
                        </a>
                        <?php if (!empty($_GET['search']) || !empty($_GET['type']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                        <a href="<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            <?php _e('Limpiar filtros', 'eventos-probolsas'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">
                                <i class="fas fa-heading me-1"></i>
                                <?php _e('Evento', 'eventos-probolsas'); ?>
                            </th>
                            <th style="width: 15%;">
                                <i class="fas fa-tags me-1"></i>
                                <?php _e('Tipo', 'eventos-probolsas'); ?>
                            </th>
                            <th style="width: 12%;">
                                <i class="fas fa-calendar me-1"></i>
                                <?php _e('Fecha', 'eventos-probolsas'); ?>
                            </th>
                            <th style="width: 10%;">
                                <i class="fas fa-clock me-1"></i>
                                <?php _e('Hora', 'eventos-probolsas'); ?>
                            </th>
                            <th style="width: 8%;">
                                <i class="fas fa-image me-1"></i>
                                <?php _e('Imagen', 'eventos-probolsas'); ?>
                            </th>
                            <th style="width: 15%;">
                                <i class="fas fa-cogs me-1"></i>
                                <?php _e('Acciones', 'eventos-probolsas'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                        <?php 
                        $type_config = Eventos_Probolsas_Helpers::get_event_type_config($event['type']);
                        $is_today = Eventos_Probolsas_Helpers::is_today($event['event_date']);
                        $is_past = Eventos_Probolsas_Helpers::is_past_event($event['event_date']);
                        $days_until = Eventos_Probolsas_Helpers::days_until_event($event['event_date']);
                        ?>
                        <tr class="<?php echo $is_today ? 'table-warning' : ($is_past ? 'table-light' : ''); ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3" style="width: 4px; height: 50px; background-color: <?php echo esc_attr($event['color']); ?>; border-radius: 2px;"></div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center">
                                            <strong class="evento-title"><?php echo esc_html($event['title']); ?></strong>
                                            <?php if ($is_today): ?>
                                            <span class="badge bg-warning text-dark ms-2">
                                                <i class="fas fa-calendar-day me-1"></i>
                                                <?php _e('HOY', 'eventos-probolsas'); ?>
                                            </span>
                                            <?php elseif (!$is_past && $days_until <= 7): ?>
                                            <span class="badge bg-info ms-2">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php printf(__('En %d días', 'eventos-probolsas'), $days_until); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($event['description'])): ?>
                                        <small class="text-muted d-block mt-1">
                                            <?php echo esc_html(wp_trim_words($event['description'], 12)); ?>
                                        </small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php printf(__('ID: %s', 'eventos-probolsas'), $event['id']); ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php printf(__('Creado: %s', 'eventos-probolsas'), date_i18n(get_option('date_format'), strtotime($event['created_at']))); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge fs-6" style="background-color: <?php echo esc_attr($event['color']); ?>;">
                                    <i class="<?php echo esc_attr($event['icon']); ?> me-1"></i>
                                    <?php echo esc_html($type_config['label']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <strong><?php echo esc_html(Eventos_Probolsas_Helpers::format_date($event['event_date'])); ?></strong>
                                    <small class="text-muted">
                                        <?php echo esc_html(date_i18n('l', strtotime($event['event_date']))); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($event['event_time'])): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo esc_html(Eventos_Probolsas_Helpers::format_time($event['event_time'])); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php _e('Todo el día', 'eventos-probolsas'); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($event['image_url'])): ?>
                                <img src="<?php echo esc_url($event['image_url']); ?>" 
                                    alt="<?php echo esc_attr($event['title']); ?>"
                                    class="evento-thumbnail shadow-eventos"
                                    width="40" height="40"
                                    style="border-radius: 6px; object-fit: cover; cursor: pointer;"
                                    data-tooltip="<?php _e('Haz clic para ver imagen completa', 'eventos-probolsas'); ?>">
                                <?php else: ?>
                                <div class="text-muted" data-tooltip="<?php _e('Sin imagen', 'eventos-probolsas'); ?>">
                                    <i class="fas fa-image fa-lg"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?php echo admin_url('admin.php?page=eventos-probolsas&action=view&event_id=' . $event['id']); ?>" 
                                        class="btn btn-outline-info btn-sm"
                                        data-tooltip="<?php _e('Ver detalles', 'eventos-probolsas'); ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="<?php echo admin_url('admin.php?page=eventos-probolsas&action=edit&event_id=' . $event['id']); ?>" 
                                        class="btn btn-outline-primary btn-sm"
                                        data-tooltip="<?php _e('Editar evento', 'eventos-probolsas'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm delete-event-btn"
                                            data-event-id="<?php echo esc_attr($event['id']); ?>"
                                            data-event-title="<?php echo esc_attr($event['title']); ?>"
                                            data-tooltip="<?php _e('Eliminar evento', 'eventos-probolsas'); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($events)): ?>
        <div class="card-footer bg-eventos-light">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    <?php printf(__('Mostrando %d eventos', 'eventos-probolsas'), count($events)); ?>
                </small>
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    <?php printf(__('Actualizado: %s', 'eventos-probolsas'), current_time('H:i')); ?>
                </small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmación para eliminar -->
<div class="modal fade modal-delete-Event" id="deleteEventModal" tabindex="-1" role="dialog" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    <?php _e('Confirmar eliminación', 'eventos-probolsas'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?php _e('¡Atención!', 'eventos-probolsas'); ?></strong>
                    <?php _e('Esta acción no se puede deshacer.', 'eventos-probolsas'); ?>
                </div>
                <p><?php _e('¿Estás seguro de que deseas eliminar el evento?', 'eventos-probolsas'); ?></p>
                <div class="event-details">
                    <strong id="event-title-to-delete" class="text-danger"></strong>
                </div>
                <p class="text-muted mt-3">
                    <?php _e('Se eliminará permanentemente el evento y su imagen asociada (si tiene).', 'eventos-probolsas'); ?>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    <?php _e('Cancelar', 'eventos-probolsas'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                    <i class="fas fa-trash me-1"></i>
                    <?php _e('Sí, eliminar evento', 'eventos-probolsas'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Input oculto para importar -->
<input type="file" id="import-events-input" accept=".csv" style="display: none;">

<!-- Scripts adicionales -->
<script>
jQuery(document).ready(function($) {
    // Filtros rápidos
    $('.quick-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        const today = new Date().toISOString().split('T')[0];
        const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
        const lastDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0];
        
        // Limpiar filtros actuales
        $('#search, #type').val('');
        $('#date_from, #date_to').val('');
        
        switch(filter) {
            case 'today':
                $('#date_from, #date_to').val(today);
                break;
            case 'upcoming':
                $('#date_from').val(today);
                $('#date_to').val('');
                break;
            case 'this-month':
                $('#date_from').val(firstDayOfMonth);
                $('#date_to').val(lastDayOfMonth);
                break;
            case 'past':
                $('#date_to').val(today);
                $('#date_from').val('');
                break;
        }
        
        $('#eventos-filter-form').submit();
    });
    
    // Filtro por tipo desde select
    $('#type').on('change', function() {
        const selectedType = $(this).val();
        if (selectedType) {
            $('.quick-filter-btn').removeClass('active');
        }
    });
    
    // Exportar eventos
    $('#export-events-btn').on('click', function() {
        const currentUrl = new URL(window.location);
        const params = new URLSearchParams(currentUrl.search);
        params.set('action', 'eventos_export_csv');
        params.set('nonce', eventosAjax.nonce);
        
        window.open(eventosAjax.ajax_url + '?' + params.toString());
    });
    
    // Importar eventos
    $('#import-events-btn').on('click', function() {
        $('#import-events-input').click();
    });
    
    $('#import-events-input').on('change', function(e) {
        const file = e.target.files[0];
        if (file && window.eventosAdmin) {
            window.eventosAdmin.importEvents(file);
        }
    });
    
    // Auto-submit en filtros de fecha
    $('#date_from, #date_to').on('change', function() {
        // Pequeño delay para mejor UX
        setTimeout(function() {
            $('#eventos-filter-form').submit();
        }, 300);
    });
    
    // Resaltar filas al hacer hover
    $('tbody tr').hover(
        function() {
            $(this).addClass('shadow-eventos-hover');
        },
        function() {
            $(this).removeClass('shadow-eventos-hover');
        }
    );
    
    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // Ctrl + N para nuevo evento
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.location.href = '<?php echo admin_url('admin.php?page=eventos-probolsas-add'); ?>';
        }
        
        // F3 para enfocar búsqueda
        if (e.key === 'F3') {
            e.preventDefault();
            $('#search').focus();
        }
        
        // Escape para limpiar filtros
        if (e.key === 'Escape') {
            $('#search').val('');
            $('#type').val('');
            $('#date_from').val('');
            $('#date_to').val('');
        }
    });
    
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').each(function() {
        const $element = $(this);
        const title = $element.attr('data-bs-toggle="tooltip"');
        
        $element.attr('title', title);
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip($element[0]);
        }
    });
});
</script>

<!-- Estilos adicionales -->
<style>

#imagePreviewModal {
    background: transparent !important;
    -webkit-box-shadow: none !important;
    box-shadow: none !important;
    max-width: 100% !important;
}

.modal-delete-Event {
    background: transparent !important;
    box-shadow: none !important;
    max-width: 100% !important;
}
.empty-state {
    padding: 3rem 2rem;
}

.empty-state i {
    opacity: 0.5;
}

.evento-title {
    font-size: 1.1em;
    color: var(--eventos-admin-dark);
}

.quick-filters {
    padding: 1rem;
    background: var(--eventos-admin-light);
    border-radius: var(--eventos-admin-border-radius);
}

.quick-filter-btn.active {
    background-color: var(--eventos-admin-primary);
    border-color: var(--eventos-admin-primary);
    color: white;
}

.table tbody tr {
    transition: var(--eventos-admin-transition);
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
}

.table tbody tr.table-warning {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}

.header-actions .btn-group .btn {
    min-width: 40px;
}

.event-details {
    padding: 1rem;
    background: var(--eventos-admin-light);
    border-radius: var(--eventos-admin-border-radius);
    margin: 1rem 0;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-group {
        display: flex;
        flex-direction: column;
    }
    
    .btn-group .btn {
        border-radius: var(--eventos-admin-border-radius) !important;
        margin-bottom: 2px;
    }
    
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .quick-filters {
        text-align: center;
    }
    
    .quick-filter-btn {
        margin-bottom: 0.5rem;
    }
}

/* Animaciones */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>