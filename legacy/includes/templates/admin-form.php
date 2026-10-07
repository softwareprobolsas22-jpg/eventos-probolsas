<?php
/**
 * Template para formulario de eventos en admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($event);
$page_title = $is_edit ? __('Editar Evento', 'eventos-probolsas') : __('Agregar Nuevo Evento', 'eventos-probolsas');
?>

<div class="wrap eventos-admin-wrap">
    <h1 class="wp-heading-inline">
        <i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus'; ?> me-2"></i>
        <?php echo $page_title; ?>
        
        <?php if ($is_edit): ?>
        <span class="badge bg-secondary ms-2">ID: <?php echo esc_html($event['id']); ?></span>
        <?php endif; ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>" class="page-title-action">
        <i class="fas fa-arrow-left me-1"></i>
        <?php _e('Volver a la lista', 'eventos-probolsas'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Breadcrumb mejorado -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>">
                    <i class="fas fa-home"></i> <?php _e('Eventos', 'eventos-probolsas'); ?>
                </a>
            </li>
            <?php if ($is_edit): ?>
            <li class="breadcrumb-item">
                <a href="<?php echo admin_url('admin.php?page=eventos-probolsas&action=view&event_id=' . $event['id']); ?>">
                    <?php echo esc_html($event['title']); ?>
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php _e('Editar', 'eventos-probolsas'); ?>
            </li>
            <?php else: ?>
            <li class="breadcrumb-item active" aria-current="page">
                <?php _e('Nuevo Evento', 'eventos-probolsas'); ?>
            </li>
            <?php endif; ?>
        </ol>
    </nav>
    
    <!-- Alertas de estado -->
    <?php if ($is_edit): ?>
    <div class="alert alert-info fade show" style="display: flex; justify-content: space-between" role="alert">
        <div>
            <i class="fas fa-info-circle me-2"></i>
            <strong><?php _e('Modo edición:', 'eventos-probolsas'); ?></strong>
            <?php _e('Estás editando un evento existente. Los cambios se guardarán inmediatamente.', 'eventos-probolsas'); ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Formulario principal -->
        <div class="col-lg-8">
            <div class="card eventos-form-card fade-in">
                <div class="card-header form-header">
                    <h5 class="mb-0">
                        <i class="fas fa-form me-2"></i>
                        <?php _e('Información del Evento', 'eventos-probolsas'); ?>
                        <?php if ($is_edit): ?>
                        <span class="badge bg-white text-dark ms-2">
                            <?php printf(__('Creado: %s', 'eventos-probolsas'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event['created_at']))); ?>
                        </span>
                        <?php endif; ?>
                    </h5>
                </div>
                
                <div class="card-body">
                    <form id="evento-form" method="post" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?php wp_nonce_field('eventos_save_event', 'eventos_nonce'); ?>
                        
                        <?php if ($is_edit): ?>
                        <input type="hidden" name="event_id" value="<?php echo esc_attr($event['id']); ?>">
                        <?php endif; ?>
                        
                        <!-- Título del evento -->
                        <div class="mb-4">
                            <label for="event-title" class="form-label required">
                                <i class="fas fa-heading me-1"></i>
                                <?php _e('Título del Evento', 'eventos-probolsas'); ?>
                            </label>
                            <input type="text" 
                                   id="event-title" 
                                   name="title" 
                                   class="form-control form-control-lg" 
                                   value="<?php echo $is_edit ? esc_attr($event['title']) : ''; ?>"
                                   placeholder="<?php _e('Ingresa un título descriptivo...', 'eventos-probolsas'); ?>"
                                   maxlength="255"
                                   required>
                            <div class="invalid-feedback">
                                <?php _e('El título es requerido y debe tener al menos 3 caracteres.', 'eventos-probolsas'); ?>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php _e('Máximo 255 caracteres. Sé descriptivo y claro.', 'eventos-probolsas'); ?>
                                <span class="char-counter float-end">
                                    <span id="title-char-count">0</span>/255
                                </span>
                            </div>
                        </div>
                        
                        <!-- Tipo de evento mejorado -->
                        <div class="mb-4">
                            <label for="event-type" class="form-label required">
                                <i class="fas fa-tags me-1"></i>
                                <?php _e('Tipo de Evento', 'eventos-probolsas'); ?>
                            </label>
                            <select id="event-type" name="type" class="form-select form-select-lg" required>
                                <option value=""><?php _e('Selecciona un tipo...', 'eventos-probolsas'); ?></option>
                                <?php foreach ($event_types as $type => $config): ?>
                                <option value="<?php echo esc_attr($type); ?>" 
                                        <?php selected($is_edit ? $event['type'] : '', $type); ?>
                                        data-color="<?php echo esc_attr($config['color']); ?>"
                                        data-icon="<?php echo esc_attr($config['icon']); ?>"
                                        data-requires-image="<?php echo $config['requires_image'] ? 'true' : 'false'; ?>">
                                    <?php echo esc_html($config['label']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                <?php _e('Debes seleccionar un tipo de evento.', 'eventos-probolsas'); ?>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-palette me-1"></i>
                                <?php _e('El tipo determina el color, icono y si requiere imagen.', 'eventos-probolsas'); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Fecha del evento -->
                            <div class="col-md-6 mb-4">
                                <label for="event-date" class="form-label required">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php _e('Fecha del Evento', 'eventos-probolsas'); ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-calendar-alt"></i>
                                    </span>
                                    <input type="date" 
                                           id="event-date" 
                                           name="event_date" 
                                           class="form-control" 
                                           value="<?php echo $is_edit ? esc_attr($event['event_date']) : ''; ?>"
                                           min="<?php echo date('Y-m-d', strtotime('-1 year')); ?>"
                                           max="<?php echo date('Y-m-d', strtotime('+5 years')); ?>"
                                           required>
                                </div>
                                <div class="invalid-feedback">
                                    <?php _e('La fecha es requerida y debe ser válida.', 'eventos-probolsas'); ?>
                                </div>
                                <div class="form-text" id="date-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php _e('Selecciona la fecha del evento', 'eventos-probolsas'); ?>
                                </div>
                            </div>
                            
                            <!-- Hora del evento -->
                            <div class="col-md-6 mb-4">
                                <label for="event-time" class="form-label">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php _e('Hora del Evento', 'eventos-probolsas'); ?>
                                    <small class="text-muted">(<?php _e('Opcional', 'eventos-probolsas'); ?>)</small>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                    <input type="time" 
                                           id="event-time" 
                                           name="event_time" 
                                           class="form-control" 
                                           value="<?php echo $is_edit && !empty($event['event_time']) ? esc_attr(substr($event['event_time'], 0, 5)) : ''; ?>">
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <?php _e('Deja vacío si es un evento de todo el día', 'eventos-probolsas'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descripción del evento -->
                        <div class="mb-4">
                            <label for="event-description" class="form-label">
                                <i class="fas fa-align-left me-1"></i>
                                <?php _e('Descripción', 'eventos-probolsas'); ?>
                                <small class="text-muted">(<?php _e('Opcional', 'eventos-probolsas'); ?>)</small>
                            </label>
                            <textarea id="event-description" 
                                      name="description" 
                                      class="form-control" 
                                      rows="4" 
                                      placeholder="<?php _e('Describe los detalles del evento, agenda, ubicación, etc...', 'eventos-probolsas'); ?>"
                                      maxlength="1000"><?php echo $is_edit ? esc_textarea($event['description']) : ''; ?></textarea>
                            <div class="form-text">
                                <div class="d-flex justify-content-between">
                                    <span>
                                        <i class="fas fa-info-circle me-1"></i>
                                        <?php _e('Información adicional sobre el evento', 'eventos-probolsas'); ?>
                                    </span>
                                    <span class="char-counter">
                                        <span id="description-char-count">0</span>/1000 <?php _e('caracteres', 'eventos-probolsas'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sección de imagen  -->
                        <div id="image-section" class="mb-4" style="display: none;">
                            <label class="form-label required">
                                <i class="fas fa-image me-1"></i>
                                <?php _e('Imagen del Evento', 'eventos-probolsas'); ?>
                                <span class="badge bg-info ms-2"><?php _e('Requerida', 'eventos-probolsas'); ?></span>
                            </label>
                            
                            <?php if ($is_edit && (!empty($event['image_url']) || !empty($event['image_attachment_id']))): ?>
                            <div id="existing-image" class="mb-3">
                                <div class="card">
                                    <div class="card-header d-flex g-1 align-items-center justify-content-center">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-check-circle text-success me-1"></i>
                                            <?php _e('Imagen actual', 'eventos-probolsas'); ?>
                                        </h6>
                                        <?php if (!empty($event['image_attachment_id'])): ?>
                                            <span class="badge bg-primary ms-2">
                                                <i class="fas fa-photo-video me-1"></i>
                                                <?php _e('En Biblioteca de Medios', 'eventos-probolsas'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex imagen-event-thumbail-content">
                                            <div class=" d-flex justify-content-center" style="margin-right: 5px;">
                                                <img src="<?php echo esc_url($event['image_url']); ?>" 
                                                     alt="<?php echo esc_attr($event['title']); ?>"
                                                     class="img-thumbnail evento-thumbnail"
                                                     style="max-width: 150px; cursor: pointer;"
                                                     data-full-url="<?php echo esc_url($event['image_url']); ?>">
                                            </div>
                                            <div class="d-flex align-items-center contenedor-detalles-imagen">
                                                <?php if (!empty($event['image_attachment_id'])): ?>
                                                <div class="image-details">
                                                    <p class="mb-1">
                                                        <strong><?php _e('ID de Attachment:', 'eventos-probolsas'); ?></strong> 
                                                        <span class="text-muted"><?php echo esc_html($event['image_attachment_id']); ?></span>
                                                    </p>
                                                    <?php if (!empty($event['image_title'])): ?>
                                                    <p class="mb-1">
                                                        <strong><?php _e('Título:', 'eventos-probolsas'); ?></strong> 
                                                        <span class="text-muted"><?php echo esc_html($event['image_title']); ?></span>
                                                    </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($event['image_file_size'])): ?>
                                                    <p class="mb-1">
                                                        <strong><?php _e('Tamaño:', 'eventos-probolsas'); ?></strong> 
                                                        <span class="text-muted"><?php echo esc_html($event['image_file_size']); ?></span>
                                                    </p>
                                                    <?php endif; ?>
                                                    <div class="mt-3">
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if (!empty($event['image_attachment_id'])): ?>
                                                            <a href="<?php echo admin_url('post.php?post=' . $event['image_attachment_id'] . '&action=edit'); ?>" 
                                                               target="_blank" 
                                                               class="btn btn-outline-primary">
                                                                <i class="fas fa-edit me-1"></i>
                                                                <?php _e('Editar en Medios', 'eventos-probolsas'); ?>
                                                            </a>
                                                            <?php endif; ?>
                                                            <a href="<?php echo esc_url($event['image_url']); ?>" 
                                                               target="_blank" 
                                                               class="btn btn-outline-info">
                                                                <i class="fas fa-external-link-alt me-1"></i>
                                                                <?php _e('Ver original', 'eventos-probolsas'); ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-warning mb-0">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <?php _e('Esta imagen no está en la biblioteca de medios. Se recomienda reemplazarla.', 'eventos-probolsas'); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Campo oculto para attachment_id existente -->
                                <?php if (!empty($event['image_attachment_id'])): ?>
                                <input type="hidden" id="existing-attachment-id" value="<?php echo esc_attr($event['image_attachment_id']); ?>">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div id="image-upload-area" class="image-upload-area">
                                <!-- Selector de imagen mejorado -->
                                <div id="image-selector-area">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="card image-option-card h-100" style="cursor: pointer;" id="media-library-option">
                                                <div class="card-body text-center py-4">
                                                    <div class="option-icon mb-3">
                                                        <i class="fas fa-photo-video fa-4x text-primary"></i>
                                                    </div>
                                                    <h6 class="mb-2"><?php _e('Biblioteca de Medios', 'eventos-probolsas'); ?></h6>
                                                    <p class="text-muted mb-0">
                                                        <?php _e('Seleccionar imagen existente', 'eventos-probolsas'); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="card image-option-card h-100" style="cursor: pointer;" id="upload-new-option">
                                                <div class="card-body text-center py-4">
                                                    <div class="option-icon mb-3">
                                                        <i class="fas fa-cloud-upload-alt fa-4x text-success"></i>
                                                    </div>
                                                    <h6 class="mb-2"><?php _e('Subir Nueva', 'eventos-probolsas'); ?></h6>
                                                    <p class="text-muted mb-0">
                                                        <?php _e('Desde tu dispositivo', 'eventos-probolsas'); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php _e('Las imágenes se guardan en tu biblioteca de medios de WordPress', 'eventos-probolsas'); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Upload tradicional (oculto inicialmente) -->
                                <div id="traditional-upload" class="upload-zone border border-2 border-dashed rounded p-4 text-center" style="display: none;">
                                    <div class="upload-icon mb-3">
                                        <i class="fas fa-cloud-upload-alt fa-4x text-muted"></i>
                                    </div>
                                    <h6 class="mb-2">
                                        <?php _e('Subir nueva imagen', 'eventos-probolsas'); ?>
                                    </h6>
                                    <p class="text-muted mb-3">
                                        <?php _e('Arrastra una imagen aquí o haz clic para seleccionar', 'eventos-probolsas'); ?>
                                    </p>
                                    <button type="button" id="upload-image-btn" class="btn btn-outline-primary">
                                        <i class="fas fa-upload me-1"></i>
                                        <?php _e('Seleccionar Imagen', 'eventos-probolsas'); ?>
                                    </button>
                                    <input type="file" 
                                           id="event-image-input" 
                                           name="event_image" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif" 
                                           style="display: none;">
                                </div>
                                
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php _e('Formatos: JPG, PNG, GIF. Tamaño máximo: 5MB', 'eventos-probolsas'); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <small class="text-muted">
                                                <i class="fas fa-images me-1"></i>
                                                <?php _e('Dimensiones recomendadas: 800x600px', 'eventos-probolsas'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones de acción mejorados -->
                        <div class="form-actions mt-5 pt-4 border-top">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex gap-2">
                                        <button type="submit" id="save-event-btn" class="btn btn-eventos-primary">
                                            <i class="fas fa-save me-1"></i>
                                            <?php echo $is_edit ? __('Actualizar Evento', 'eventos-probolsas') : __('Crear Evento', 'eventos-probolsas'); ?>
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                                            <i class="fas fa-times me-1"></i>
                                            <?php _e('Cancelar', 'eventos-probolsas'); ?>
                                        </button>
                                        
                                        <?php if ($is_edit): ?>
                                        <button type="button" id="save-and-new-btn" class="btn btn-outline-info">
                                            <i class="fas fa-plus me-1"></i>
                                            <?php _e('Guardar y Nuevo', 'eventos-probolsas'); ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 text-end">
                                    <?php if ($is_edit): ?>
                                    <div class="btn-group">
                                        <a href="<?php echo admin_url('admin.php?page=eventos-probolsas&action=view&event_id=' . $event['id']); ?>" 
                                           class="btn btn-outline-info">
                                            <i class="fas fa-eye me-1"></i>
                                            <?php _e('Vista previa', 'eventos-probolsas'); ?>
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-danger delete-event-btn"
                                                data-event-id="<?php echo esc_attr($event['id']); ?>"
                                                data-event-title="<?php echo esc_attr($event['title']); ?>">
                                            <i class="fas fa-trash me-1"></i>
                                            <?php _e('Eliminar', 'eventos-probolsas'); ?>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Información de guardado automático -->
                            <div class="mt-3">
                                <div id="autosave-status" class="text-muted small" style="display: none;">
                                    <i class="fas fa-clock me-1"></i>
                                    <span id="autosave-text"><?php _e('Guardado automáticamente', 'eventos-probolsas'); ?></span>
                                    <span id="autosave-time"></span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Panel lateral mejorado -->
        <div class="col-lg-4">
            <!-- Información del tipo de evento -->
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        <?php _e('Información del Tipo', 'eventos-probolsas'); ?>
                    </h6>
                </div>
                <div class="card-body" id="event-type-info">
                    <div class="text-center text-muted">
                        <i class="fas fa-arrow-left fa-3x mb-3"></i>
                        <h6><?php _e('Selecciona un tipo', 'eventos-probolsas'); ?></h6>
                        <p class="small"><?php _e('Elige un tipo de evento para ver su configuración', 'eventos-probolsas'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Preview del evento -->
            <div class="card mb-4 fade-in" id="event-preview" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-eye me-1"></i>
                        <?php _e('Vista Previa', 'eventos-probolsas'); ?>
                        <span class="badge bg-success ms-2 updating-badge" style="display: none;">
                            <?php _e('Actualizando...', 'eventos-probolsas'); ?>
                        </span>
                    </h6>
                </div>
                <div class="card-body">
                    <div id="preview-content">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>
            </div>
            
            <!-- Ayuda y consejos mejorados -->
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-1"></i>
                        <?php _e('Consejos Útiles', 'eventos-probolsas'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="tips-list">
                        <div class="tip-item mb-3">
                            <div class="tip-icon">
                                <i class="fas fa-heading text-primary"></i>
                            </div>
                            <div class="tip-content">
                                <strong><?php _e('Título descriptivo', 'eventos-probolsas'); ?></strong>
                                <p class="small text-muted mb-0">
                                    <?php _e('Usa un título claro que describa exactamente el evento', 'eventos-probolsas'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="tip-item mb-3">
                            <div class="tip-icon">
                                <i class="fas fa-image text-success"></i>
                            </div>
                            <div class="tip-content">
                                <strong><?php _e('Imágenes de calidad', 'eventos-probolsas'); ?></strong>
                                <p class="small text-muted mb-0">
                                    <?php _e('Las imágenes mejoran la presentación y engagement', 'eventos-probolsas'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="tip-item mb-3">
                            <div class="tip-icon">
                                <i class="fas fa-clock text-warning"></i>
                            </div>
                            <div class="tip-content">
                                <strong><?php _e('Hora específica', 'eventos-probolsas'); ?></strong>
                                <p class="small text-muted mb-0">
                                    <?php _e('Incluye la hora si el evento tiene horario específico', 'eventos-probolsas'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="tip-item mb-3">
                            <div class="tip-icon">
                                <i class="fas fa-align-left text-info"></i>
                            </div>
                            <div class="tip-content">
                                <strong><?php _e('Descripción completa', 'eventos-probolsas'); ?></strong>
                                <p class="small text-muted mb-0">
                                    <?php _e('Incluye ubicación, agenda y detalles importantes', 'eventos-probolsas'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="fas fa-users text-purple"></i>
                            </div>
                            <div class="tip-content">
                                <strong><?php _e('Audiencia objetivo', 'eventos-probolsas'); ?></strong>
                                <p class="small text-muted mb-0">
                                    <?php _e('Considera quién participará en el evento', 'eventos-probolsas'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Atajos de teclado -->
            <div class="card fade-in">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-keyboard me-1"></i>
                        <?php _e('Atajos de Teclado', 'eventos-probolsas'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="shortcuts-list">
                        <div class="shortcut-item d-flex justify-content-between mb-2">
                            <span class="shortcut-action"><?php _e('Guardar', 'eventos-probolsas'); ?></span>
                            <kbd>Ctrl + S</kbd>
                        </div>
                        <div class="shortcut-item d-flex justify-content-between mb-2">
                            <span class="shortcut-action"><?php _e('Cancelar', 'eventos-probolsas'); ?></span>
                            <kbd>Esc</kbd>
                        </div>
                        <div class="shortcut-item d-flex justify-content-between mb-2">
                            <span class="shortcut-action"><?php _e('Nuevo evento', 'eventos-probolsas'); ?></span>
                            <kbd>Ctrl + N</kbd>
                        </div>
                        <div class="shortcut-item d-flex justify-content-between">
                            <span class="shortcut-action"><?php _e('Volver', 'eventos-probolsas'); ?></span>
                            <kbd>Alt + ←</kbd>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información adicional para edición -->
            <?php if ($is_edit): ?>
            <div class="card mt-4 fade-in">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line me-1"></i>
                        <?php _e('Información del Evento', 'eventos-probolsas'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="event-meta">
                        <div class="meta-item mb-3">
                            <i class="fas fa-hashtag text-muted me-2"></i>
                            <strong><?php _e('ID:', 'eventos-probolsas'); ?></strong>
                            <span class="text-muted"><?php echo esc_html($event['id']); ?></span>
                        </div>
                        <div class="meta-item mb-3">
                            <i class="fas fa-plus text-success me-2"></i>
                            <strong><?php _e('Creado:', 'eventos-probolsas'); ?></strong>
                            <span class="text-muted">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($event['created_at']))); ?>
                            </span>
                        </div>
                        <?php if ($event['updated_at'] !== $event['created_at']): ?>
                        <div class="meta-item mb-3">
                            <i class="fas fa-edit text-warning me-2"></i>
                            <strong><?php _e('Última modificación:', 'eventos-probolsas'); ?></strong>
                            <span class="text-muted">
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event['updated_at']))); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <i class="fas fa-calendar-check text-info me-2"></i>
                            <strong><?php _e('Estado:', 'eventos-probolsas'); ?></strong>
                            <?php
                            $is_today = Eventos_Probolsas_Helpers::is_today($event['event_date']);
                            $is_future = Eventos_Probolsas_Helpers::is_future_event($event['event_date']);
                            $is_past = Eventos_Probolsas_Helpers::is_past_event($event['event_date']);
                            
                            if ($is_today): ?>
                                <span class="badge bg-warning text-dark"><?php _e('HOY', 'eventos-probolsas'); ?></span>
                            <?php elseif ($is_future): ?>
                                <span class="badge bg-success"><?php _e('Próximo', 'eventos-probolsas'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php _e('Pasado', 'eventos-probolsas'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar -->
<?php if ($is_edit): ?>
<div class="modal fade" id="deleteEventModal" tabindex="-1">
    <div class="modal-dialog">
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
                <p><?php _e('¿Estás seguro de que deseas eliminar este evento?', 'eventos-probolsas'); ?></p>
                <div class="event-to-delete">
                    <div class="card">
                        <div class="card-body">
                            <h6 id="event-title-to-delete" class="text-danger"></h6>
                            <small class="text-muted">
                                <?php printf(__('ID: %s', 'eventos-probolsas'), $event['id']); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <p class="text-muted mt-3">
                    <?php _e('Se eliminará permanentemente el evento, su imagen asociada (si tiene) y no podrá ser recuperado.', 'eventos-probolsas'); ?>
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

<?php endif; ?>

<!-- Scripts mejorados -->
<script>
jQuery(document).ready(function($) {
    let isFormDirty = false;
    let autosaveTimer;
    
    // Contadores de caracteres
    function updateCharCounters() {
        const titleCount = $('#event-title').val().length;
        const descCount = $('#event-description').val().length;
        
        $('#title-char-count').text(titleCount);
        $('#description-char-count').text(descCount);
        
        // Cambiar color según el límite
        if (titleCount > 230) {
            $('#title-char-count').addClass('text-warning');
        } else {
            $('#title-char-count').removeClass('text-warning');
        }
        
        if (descCount > 900) {
            $('#description-char-count').addClass('text-warning');
        } else {
            $('#description-char-count').removeClass('text-warning');
        }
    }
    
    $('#event-title, #event-description').on('input', updateCharCounters);
    updateCharCounters(); // Inicializar
    
    // Marcar formulario como sucio
    $('#evento-form input, #evento-form textarea, #evento-form select').on('change input', function() {
        isFormDirty = true;
        showAutosaveStatus();
    });
    
    // Actualizar información del tipo de evento
    $('#event-type').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const color = selectedOption.data('color');
        const icon = selectedOption.data('icon');
        const requiresImage = selectedOption.data('requires-image');
        const label = selectedOption.text();
        
        if ($(this).val()) {
            const infoHtml = `
                <div class="text-center">
                    <div class="event-type-preview p-4 rounded mb-3" style="background-color: ${color}; color: ${getContrastColor(color)};">
                        <i class="${icon} fa-3x mb-3"></i>
                        <h5 class="mb-0">${label}</h5>
                    </div>
                    <div class="event-type-details">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="detail-item">
                                    <i class="fas fa-palette text-muted"></i>
                                    <small class="d-block"><strong>Color:</strong></small>
                                    <small class="text-muted">${color}</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="detail-item">
                                    <i class="fas fa-icons text-muted"></i>
                                    <small class="d-block"><strong>Icono:</strong></small>
                                    <small class="text-muted"><i class="${icon}"></i> ${icon.split(' ')[1]}</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="detail-item mt-2">
                                    <i class="fas fa-image text-muted"></i>
                                    <small class="d-block"><strong>Imagen:</strong></small>
                                    <small class="text-muted">
                                        ${requiresImage ? 
                                            '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Requerida</span>' : 
                                            '<span class="text-success"><i class="fas fa-check-circle"></i> Opcional</span>'
                                        }
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#event-type-info').html(infoHtml);
            
            // Mostrar/ocultar sección de imagen
            if (requiresImage) {
                $('#image-section').show().addClass('required');
                $('#event-image-input').prop('required', true);
            } else {
                $('#image-section').hide().removeClass('required');
                $('#event-image-input').prop('required', false);
            }
        } else {
            $('#event-type-info').html(`
                <div class="text-center text-muted">
                    <i class="fas fa-arrow-left fa-3x mb-3"></i>
                    <h6>Selecciona un tipo</h6>
                    <p class="small">Elige un tipo de evento para ver su configuración</p>
                </div>
            `);
            $('#image-section').hide();
        }
        
        updateEventPreview();
    }).trigger('change');
    
    // Función auxiliar para obtener color de contraste
    function getContrastColor(hexColor) {
        const rgb = hexToRgb(hexColor);
        const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
        return luminance > 0.5 ? '#000000' : '#ffffff';
    }
    
    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : {r: 0, g: 0, b: 0};
    }
    
    // Actualizar preview del evento
    function updateEventPreview() {
        const title = $('#event-title').val();
        const type = $('#event-type').val();
        const date = $('#event-date').val();
        const time = $('#event-time').val();
        const description = $('#event-description').val();
        
        if (title && type && date) {
            $('.updating-badge').show();
            
            setTimeout(function() {
                const selectedOption = $('#event-type option:selected');
                const color = selectedOption.data('color');
                const icon = selectedOption.data('icon');
                const label = selectedOption.text();
                
                const [year, month, day] = date.split('-');
                const formattedDate = new Date(year, month - 1, day).toLocaleDateString('es-ES', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                const previewHtml = `
                    <div class="event-preview-item">
                        <div class="d-flex align-items-start mb-3">
                            <div class="event-preview-icon me-3" style="background-color: ${color}; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                <i class="${icon} text-white fa-lg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-2">${title}</h6>
                                <div class="event-meta">
                                    <p class="small text-muted mb-1">
                                        <i class="fas fa-tag me-1"></i> ${label}
                                    </p>
                                    <p class="small text-muted mb-1">
                                        <i class="fas fa-calendar me-1"></i> ${formattedDate}
                                    </p>
                                    ${time ? `<p class="small text-muted mb-1"><i class="fas fa-clock me-1"></i> ${time}</p>` : ''}
                                    ${description ? `<p class="small text-muted mb-0 mt-2">${description.substring(0, 120)}${description.length > 120 ? '...' : ''}</p>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="preview-footer">
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                Vista previa actualizada
                            </small>
                        </div>
                    </div>
                `;
                
                $('#preview-content').html(previewHtml);
                $('#event-preview').show();
                $('.updating-badge').hide();
            }, 500);
        } else {
            $('#event-preview').hide();
        }
    }
    
    // Actualizar preview en tiempo real con debounce
    let previewTimeout;
    $('#event-title, #event-date, #event-time, #event-description').on('input change', function() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(updateEventPreview, 300);
    });
    
    // Drag and drop mejorado para upload de imagen
    const uploadZone = $('.upload-zone');
    
    uploadZone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    uploadZone.on('dragleave dragend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!$(this).is(':hover')) {
            $(this).removeClass('drag-over');
        }
    });
    
    uploadZone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#event-image-input')[0].files = files;
            $('#event-image-input').trigger('change');
        }
    });
    
    // Validación en tiempo real mejorada
    function validateField($field) {
        const fieldName = $field.attr('name') || $field.attr('id');
        const value = $field.val().trim();
        let isValid = true;
        let message = '';
        
        $field.removeClass('is-valid is-invalid');
        $field.siblings('.invalid-feedback').remove();
        
        switch (fieldName) {
            case 'title':
                if (!value) {
                    isValid = false;
                    message = 'El título es requerido';
                } else if (value.length < 3) {
                    isValid = false;
                    message = 'El título debe tener al menos 3 caracteres';
                } else if (value.length > 255) {
                    isValid = false;
                    message = 'El título no puede exceder 255 caracteres';
                }
                break;
                
            case 'type':
                if (!value) {
                    isValid = false;
                    message = 'Debes seleccionar un tipo de evento';
                }
                break;
                
            case 'event_date':
                if (!value) {
                    isValid = false;
                    message = 'La fecha es requerida';
                } else {
                    const selectedDate = new Date(value);
                    const oneYearAgo = new Date();
                    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
                    
                    if (selectedDate < oneYearAgo) {
                        isValid = false;
                        message = 'La fecha no puede ser anterior a un año';
                    } else {
                        // Mostrar información adicional de la fecha
                        const dayOfWeek = selectedDate.toLocaleDateString('es-ES', { weekday: 'long' });
                        const daysUntil = Math.ceil((selectedDate - new Date()) / (1000 * 60 * 60 * 24));
                        
                        let dateInfo = `Será un ${dayOfWeek}`;
                        if (daysUntil === 0) {
                            dateInfo += ' (hoy)';
                        } else if (daysUntil === 1) {
                            dateInfo += ' (mañana)';
                        } else if (daysUntil > 1) {
                            dateInfo += ` (en ${daysUntil} días)`;
                        } else {
                            dateInfo += ` (hace ${Math.abs(daysUntil)} días)`;
                        }
                        
                        $('#date-info').html(`<i class="fas fa-info-circle me-1"></i>${dateInfo}`);
                    }
                }
                break;
                
            case 'event_time':
                if (value && !/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(value)) {
                    isValid = false;
                    message = 'Formato de hora no válido (HH:MM)';
                }
                break;
                
            case 'description':
                if (value.length > 1000) {
                    isValid = false;
                    message = 'La descripción no puede exceder 1000 caracteres';
                }
                break;
        }
        
        if (isValid) {
            $field.addClass('is-valid');
        } else {
            $field.addClass('is-invalid');
            if (message) {
                $field.after(`<div class="invalid-feedback">${message}</div>`);
            }
        }
        
        return isValid;
    }
    
    // Aplicar validación en tiempo real
    $('#event-title, #event-type, #event-date, #event-time, #event-description').on('blur input', function() {
        validateField($(this));
    });
    
    // Auto-resize para textarea
    $('#event-description').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Guardar y nuevo
    $('#save-and-new-btn').on('click', function() {
        $('#evento-form').append('<input type="hidden" name="save_and_new" value="1">');
        $('#evento-form').submit();
    });
    
    // Auto-save (cada 30 segundos)
    function showAutosaveStatus() {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            if (isFormDirty) {
                $('#autosave-status').show();
                $('#autosave-time').text(new Date().toLocaleTimeString());
                
                // Simular auto-save (en una implementación real, harías AJAX aquí)
                setTimeout(function() {
                    $('#autosave-status').fadeOut();
                }, 3000);
            }
        }, 30000);
    }
    
    // Confirmación antes de salir con cambios sin guardar
    $(window).on('beforeunload', function(e) {
        if (isFormDirty) {
            const message = '¿Estás seguro de que deseas salir? Los cambios no guardados se perderán.';
            e.returnValue = message;
            return message;
        }
    });
    
    // Marcar como guardado al enviar
    $('#evento-form').on('submit', function() {
        isFormDirty = false;
    });
    
    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // Ctrl + S para guardar
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#evento-form').submit();
        }
        
        // Ctrl + N para nuevo evento
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.location.href = '<?php echo admin_url('admin.php?page=eventos-probolsas-add'); ?>';
        }
        
        // Escape para cancelar
        if (e.key === 'Escape') {
            if (confirm('¿Deseas cancelar y volver a la lista?')) {
                window.location.href = '<?php echo admin_url('admin.php?page=eventos-probolsas'); ?>';
            }
        }
        
        // Alt + izquierda para volver
        if (e.altKey && e.key === 'ArrowLeft') {
            e.preventDefault();
            history.back();
        }
    });
    
    // Inicializar preview si estamos editando
    <?php if ($is_edit): ?>
    updateEventPreview();
    <?php endif; ?>
    
    // Trigger inicial para el contador de caracteres
    updateCharCounters();
});

jQuery(document).ready(function($) {
    // Eventos para las opciones de imagen
    $('#media-library-option').on('click', function() {
        $(this).addClass('selected');
        $('#upload-new-option').removeClass('selected');
        
        if (window.eventosAdmin) {
            window.eventosAdmin.openMediaLibrary();
        }
    });
    
    $('#upload-new-option').on('click', function() {
        $(this).addClass('selected');
        $('#media-library-option').removeClass('selected');
        $('#traditional-upload').slideDown();
    });
    
    // Efectos hover para las tarjetas
    $('.image-option-card').hover(
        function() {
            $(this).addClass('border-primary shadow-sm');
        },
        function() {
            if (!$(this).hasClass('selected')) {
                $(this).removeClass('border-primary shadow-sm');
            }
        }
    );
    
    // Enqueue media uploader si no está cargado
    if (typeof wp === 'undefined' || !wp.media) {
        // Cargar scripts de media uploader
        $.getScript(eventosAjax.media_uploader_url, function() {
            console.log('Media uploader cargado');
        }).fail(function() {
            console.log('Error al cargar media uploader');
        });
    }
});
</script>

<!-- Estilos adicionales -->
<style>
.eventos-form-card {
    border: none;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    border-radius: 12px;
    overflow: hidden;
}

#event-description{
    resize: ;: none !important;
}

.form-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 1px solid #dee2e6;
}

.required::after {
    content: " *";
    color: #dc3545;
    font-weight: bold;
}

.char-counter {
    font-weight: 600;
    font-size: 0.875rem;
}

.char-counter.text-warning {
    color: #ffc107 !important;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    padding: 0.75rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.tip-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.tip-icon {
    width: 30px;
    flex-shrink: 0;
    text-align: center;
}

.tip-content {
    flex-grow: 1;
    margin-left: 10px;
}

.upload-zone {
    transition: all 0.3s ease;
    cursor: pointer;
    border-radius: 12px;
}

.upload-zone:hover,
.upload-zone.drag-over {
    background-color: #f8f9fa;
    border-color: #007bff !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,123,255,0.15);
}

.upload-zone.drag-over {
    background-color: #e3f2fd;
    border-color: #1976d2 !important;
}

.shortcut-item kbd {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 0.75rem;
    font-family: monospace;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.event-preview-icon {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.event-type-preview {
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.event-type-preview:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}

.detail-item {
    text-align: center;
    padding: 0.5rem;
    background: rgba(255,255,255,0.1);
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.event-meta p {
    margin-bottom: 0.25rem;
}

.meta-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.meta-item:last-child {
    border-bottom: none;
}

.updating-badge {
    animation: pulse 1.5s infinite;
}

#event-description{
    resize: none !important;
}

.imagen-event-thumbail-content{
    margin-right: 5px !important;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.fade-in {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(20px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

.form-actions {
    background: rgba(248, 249, 250, 0.5);
    border-radius: 8px;
    padding: 1.5rem;
}

.event-to-delete .card {
    border-left: 4px solid #dc3545;
}

#autosave-status {
    background: #d4edda;
    color: #155724;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #c3e6cb;
}

@media (max-width: 768px) {
    .form-actions .row {
        flex-direction: column;
    }
    
    .form-actions .col-md-4 {
        margin-top: 1rem;
        text-align: center !important;
    }
    
    .btn-group {
        justify-content: center;
    }
    
    .tip-item {
        flex-direction: column;
        text-align: center;
    }
    
    .tip-icon {
        margin-bottom: 0.5rem;
    }
    
    .tip-content {
        margin-left: 0;
    }
    
    .shortcut-item {
        justify-content: space-between;
    }
}

/* Mejoras de accesibilidad */
.btn:focus,
.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #80bdff;
}

/* Estados de validación mejorados */
.was-validated .form-control:valid,
.form-control.is-valid {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.5.5 4-4-.5-.5L3 5.23l-1.5-1.5-.5.5z'/%3e%3c/svg%3e");
}

.was-validated .form-control:invalid,
.form-control.is-invalid {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3e%3cpath fill='%23dc3545' d='M6 0C2.7 0 0 2.7 0 6s2.7 6 6 6 6-2.7 6-6-2.7-6-6-6zm2.5 7.5L7.5 9 6 7.5 4.5 9 3.5 8 5 6.5 3.5 5 4.5 4 6 5.5 7.5 4l1 1L7 6.5 8.5 8z'/%3e%3c/svg%3e");
}

.image-option-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.image-option-card:hover,
.image-option-card.selected {
    transform: translateY(-5px);
    border-color: #007bff !important;
    box-shadow: 0 8px 25px rgba(0,123,255,0.15) !important;
}

.option-icon {
    transition: transform 0.3s ease;
}

.image-option-card:hover .option-icon {
    transform: scale(1.1);
}

.upload-zone {
    transition: all 0.3s ease;
}

.upload-zone:hover,
.upload-zone.drag-over {
    background-color: #f8f9fa;
    border-color: #007bff !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,123,255,0.15);
}

.upload-zone.drag-over {
    background-color: #e3f2fd;
    border-color: #1976d2 !important;
}

.image-details p {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

#existing-image .card {
    border-left: 4px solid #28a745;
}

.evento-thumbnail {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.evento-thumbnail:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

@media (max-width: 550px){
    .imagen-event-thumbail-content{
        flex-direction: column !important;
    }
    
    .contenedor-detalles-imagen{
        justify-content: center !important;
        text-align: center;
    }
}
</style>