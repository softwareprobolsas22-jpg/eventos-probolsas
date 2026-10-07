<?php
/**
 * Template del modal para mostrar detalles de eventos
 * Soluciona problemas de z-index, layout y múltiples eventos por día
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- MODAL PRINCIPAL DE EVENTOS -->
<div class="modal fade eventos-modal" id="eventoModal" tabindex="-1" role="dialog" 
     aria-labelledby="eventoModalLabel" aria-hidden="true" 
     style="z-index: 999999 !important;">
    
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content eventos-modal-content">
            
            <!-- HEADER DEL MODAL -->
            <div class="modal-header eventos-modal-header">
                <div class="d-flex align-items-center w-100">
                    <div class="evento-modal-icon-container">
                        <i id="evento-modal-icon" class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="modal-title mb-0" id="eventoModalLabel" style="color: #fff">
                            <span id="evento-modal-title">Cargando evento...</span>
                        </h4>
                        <div class="evento-modal-subtitle mt-1">
                            <span id="evento-modal-type-badge" class="badge" style="background-color: #007bff;">
                                <i id="evento-modal-type-icon" class="fas fa-tag me-1"></i>
                                <span id="evento-modal-type-label">Tipo</span>
                            </span>
                            <span class="ms-2 text-light">
                                <i class="fas fa-hashtag me-1"></i>
                                ID: <span id="evento-modal-id">0</span>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>
            
            <!-- BODY DEL MODAL -->
            <div class="modal-body eventos-modal-body p-0">
                
                <!-- LOADING STATE -->
                <div id="modal-loading" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <h5>Cargando detalles del evento</h5>
                    <p class="text-muted">Por favor espere...</p>
                </div>
                
                <!-- ERROR STATE -->
                <div id="modal-error" class="alert alert-danger m-3" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> <span id="modal-error-message">No se pudo cargar el evento.</span>
                </div>
                
                <!-- CONTENIDO PRINCIPAL DEL EVENTO -->
                <div id="modal-content" style="display: none;">
                    
                    <!-- NAVEGACIÓN ENTRE EVENTOS DEL MISMO DÍA -->
                    <div id="evento-navigation" class="eventos-day-navigation" style="display: none;">
                        <div class="navigation-container d-flex justify-content-between align-items-center">
                            <button type="button" id="prev-event-btn" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chevron-left me-1"></i>
                                <span class="d-none d-md-inline">Anterior</span>
                            </button>
                            
                            <div class="navigation-info text-center">
                                <span id="event-position" class="badge bg-secondary">Evento 1 de 1</span>
                                <br>
                                <small class="text-muted">Eventos de este día</small>
                            </div>
                            
                            <button type="button" id="next-event-btn" class="btn btn-outline-primary btn-sm">
                                <span class="d-none d-md-inline me-1">Siguiente</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- INFORMACIÓN PRINCIPAL DEL EVENTO -->
                    <div class="evento-details-section">
                        <div class="row g-0">
                            
                            <!-- COLUMNA PRINCIPAL: DETALLES -->
                            <div class="col-lg-8">
                                <div class="evento-main-details p-4">
                                    
                                    <!-- Fecha y hora prominente -->
                                    <div class="evento-datetime-section mb-4">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="datetime-card">
                                                    <div class="datetime-icon">
                                                        <i class="fas fa-calendar-day"></i>
                                                    </div>
                                                    <div class="datetime-content">
                                                        <label class="datetime-label">Fecha</label>
                                                        <div class="datetime-value" id="evento-modal-date">
                                                            1 de Enero, 2024
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6" id="evento-modal-time-container">
                                                <div class="datetime-card">
                                                    <div class="datetime-icon">
                                                        <i class="fas fa-clock"></i>
                                                    </div>
                                                    <div class="datetime-content">
                                                        <label class="datetime-label">Hora</label>
                                                        <div class="datetime-value" id="evento-modal-time">
                                                            9:00 AM
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Descripción del evento -->
                                    <div id="evento-modal-description-container" class="evento-description-section mb-4">
                                        <h6 class="section-title">
                                            <i class="fas fa-align-left me-2"></i>
                                            Descripción
                                        </h6>
                                        <div class="description-content">
                                            <p id="evento-modal-description" class="mb-0">
                                                Descripción del evento aquí...
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Información adicional -->
                                    <div class="evento-additional-info">
                                        <h6 class="section-title">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Información adicional
                                        </h6>
                                        
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <i class="fas fa-tag info-icon"></i>
                                                <div class="info-content">
                                                    <label>Tipo de evento</label>
                                                    <span id="evento-modal-type-text">Tipo</span>
                                                </div>
                                            </div>
                                            
                                            <div class="info-item">
                                                <i class="fas fa-plus info-icon"></i>
                                                <div class="info-content">
                                                    <label>Creado</label>
                                                    <span id="evento-modal-created">Fecha de creación</span>
                                                </div>
                                            </div>
                                            
                                            <div class="info-item">
                                                <i class="fas fa-edit info-icon"></i>
                                                <div class="info-content">
                                                    <label>Última actualización</label>
                                                    <span id="evento-modal-updated">Fecha de actualización</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- COLUMNA LATERAL: IMAGEN Y ACCIONES -->
                            <div class="col-lg-4">
                                <div class="evento-sidebar p-4">
                                    
                                    <!-- Imagen del evento -->
                                    <div id="evento-modal-image-container" class="evento-image-section mb-4" style="display: none;">
                                        <h6 class="section-title">
                                            <i class="fas fa-image me-2"></i>
                                            Imagen del evento
                                        </h6>
                                        <div class="image-container-new">
                                            <img id="evento-modal-image" 
                                                 src="" 
                                                 alt="Imagen del evento" 
                                                 class="evento-image-clickable">
                                            <div class="image-zoom-overlay">
                                                <div class="zoom-content">
                                                    <i class="fas fa-search-plus zoom-icon"></i>
                                                    <span class="zoom-text">Click para ampliar</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Eventos relacionados -->
                                    <div id="related-events-section" class="related-events-section mt-4" style="display: none;">
                                        <h6 class="section-title">
                                            <i class="fas fa-calendar-week me-2"></i>
                                            Eventos relacionados
                                        </h6>
                                        
                                        <div id="related-events-list" class="related-events-list">
                                            <!-- Los eventos relacionados se cargarán aquí -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FOOTER DEL MODAL -->
            <div class="modal-footer eventos-modal-footer">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="footer-info">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Última actualización: <span id="evento-modal-last-update">Ahora</span>
                        </small>
                    </div>
                    
                    <div class="footer-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA AMPLIAR IMAGEN -->
<div id="imageZoomModal" class="image-zoom-modal" style="display: none;">
    <div class="image-zoom-backdrop"></div>
    <div class="image-zoom-container">
        <div class="image-zoom-header">
            <h5 class="image-zoom-title">Imagen del evento</h5>
            <button type="button" class="image-zoom-close" id="closeImageModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="image-zoom-body">
            <img id="imageZoomImg" src="" alt="Imagen ampliada" class="zoomed-image">
            <div class="image-zoom-controls">
                <button type="button" class="zoom-btn" id="zoomIn">
                    <i class="fas fa-plus"></i>
                </button>
                <button type="button" class="zoom-btn" id="zoomOut">
                    <i class="fas fa-minus"></i>
                </button>
                <button type="button" class="zoom-btn" id="resetZoom">
                    <i class="fas fa-expand-arrows-alt"></i>
                </button>
                <a id="downloadImage" href="" download class="zoom-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA MÚLTIPLES EVENTOS DEL MISMO DÍA -->
<div class="modal fade" id="multipleEventsModal" tabindex="-1" style="z-index: 999998 !important;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-day me-2"></i>
                    Eventos del <span id="multiple-events-date">día</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div id="multiple-events-loading" class="text-center py-4">
                    <div class="spinner-border text-primary mb-3"></div>
                    <p>Cargando eventos del día...</p>
                </div>
                
                <div id="multiple-events-list" style="display: none;">
                    <!-- Lista de eventos se carga aquí -->
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ESTILOS ESPECÍFICOS DEL MODAL -->
<style>
/* Z-INDEX Y OVERLAY FIXES */
.eventos-modal {
    z-index: 999999 !important;
}

.eventos-modal .modal-backdrop {
    z-index: 999998 !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
}

.modal-backdrop.show {
    z-index: 999998 !important;
}

.eventos-modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    width: 100% !important;
    max-width: 100% !important;
}

/* HEADER STYLING */
.eventos-modal-header {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 12px 12px 0 0;
    border-bottom: none;
    padding: 1.5rem;
    width: 100%;
    box-sizing: border-box;
}

.evento-modal-icon-container {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.eventos-modal-header .modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.evento-modal-subtitle {
    opacity: 0.9;
}

.evento-modal-subtitle .badge {
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    color: #000;
}

/* BODY LAYOUT FIXES */
.eventos-modal-body {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
    padding: 0 !important;
}

.evento-details-section {
    width: 100%;
}

.evento-details-section .row {
    margin: 0;
    width: 100%;
}

.evento-details-section .col-lg-8,
.evento-details-section .col-lg-4 {
    padding: 0;
    width: 100%;
    max-width: 100%;
}

/* NAVEGACIÓN ENTRE EVENTOS */
.eventos-day-navigation {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
    width: 100%;
}

.navigation-container {
    width: 100%;
}

.navigation-info {
    flex-grow: 1;
}

.navigation-info .badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

/* SECCIONES PRINCIPALES */
.evento-main-details {
    width: 100% !important;
    box-sizing: border-box;
    border-right: 1px solid #e9ecef;
}

.evento-sidebar {
    width: 100% !important;
    box-sizing: border-box;
    background: #f8f9fa;
}

/* DATETIME CARDS */
.evento-datetime-section {
    width: 100%;
}

.datetime-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
}

.datetime-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.datetime-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.datetime-content {
    flex-grow: 1;
    min-width: 0;
}

.datetime-label {
    display: block;
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.datetime-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #343a40;
    word-break: break-word;
}

/* SECTIONS */
.section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #343a40;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
    display: flex;
    align-items: center;
}

.section-title i {
    color: #007bff;
}

/* DESCRIPTION */
.evento-description-section {
    width: 100%;
}

.description-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #007bff;
    width: 100%;
    box-sizing: border-box;
}

.description-content p {
    margin: 0;
    line-height: 1.6;
    color: #495057;
}

/* INFO GRID */
.info-grid {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.info-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    width: 100%;
    box-sizing: border-box;
}

.info-icon {
    width: 30px;
    height: 30px;
    background: #e9ecef;
    color: #6c757d;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.info-content {
    flex-grow: 1;
    min-width: 0;
}

.info-content label {
    display: block;
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.info-content span {
    font-size: 0.9rem;
    color: #495057;
    font-weight: 500;
    word-break: break-word;
}

/* IMAGE SECTION */
/* ESTILOS PARA LA IMAGEN EN EL MODAL PRINCIPAL */
.evento-image-section {
    width: 100%;
    margin-bottom: 1.5rem;
}

.image-container-new {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.image-container-new:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.evento-image-clickable {
    width: 100% !important;
    height: auto !important;
    object-fit: cover;
    display: block;
    transition: all 0.3s ease;
}

.image-container-new:hover .evento-image-clickable {
    transform: scale(1.05);
}

.image-zoom-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-container-new:hover .image-zoom-overlay {
    opacity: 1;
}

.zoom-content {
    text-align: center;
    color: white;
}

.zoom-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    animation: pulse 2s infinite;
}

.zoom-text {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* ESTILOS DEL MODAL DE AMPLIAR IMAGEN */
.image-zoom-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.image-zoom-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    cursor: pointer;
}

.image-zoom-container {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    display: flex;
    flex-direction: column;
    animation: zoomIn 0.3s ease;
}

.image-zoom-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.image-zoom-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #343a40;
}

.image-zoom-close {
    background: #dc3545;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.image-zoom-close:hover {
    background: #c82333;
    transform: scale(1.1);
}

.image-zoom-body {
    position: relative;
    padding: 1rem;
    flex-grow: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    max-height: 70vh;
    overflow: hidden;
}

.zoomed-image {
    max-width: 50%;
    max-height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
    cursor: grab;
}

.zoomed-image:active {
    cursor: grabbing;
}

.image-zoom-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    background: rgba(0, 0, 0, 0.8);
    padding: 10px;
    border-radius: 25px;
    backdrop-filter: blur(10px);
}

.zoom-btn {
    width: 45px;
    height: 45px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.zoom-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
    color: white;
    text-decoration: none;
}

.download-btn:hover {
    background: #28a745;
}

/* ANIMACIONES */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes zoomIn {
    from { 
        opacity: 0; 
        transform: scale(0.8); 
    }
    to { 
        opacity: 1; 
        transform: scale(1); 
    }
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .image-zoom-container {
        max-width: 95vw;
        max-height: 95vh;
        margin: 1rem;
    }
    
    .image-zoom-header {
        padding: 0.75rem 1rem;
    }
    
    .image-zoom-title {
        font-size: 1rem;
    }
    
    .image-zoom-close {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
    
    .zoom-content .zoom-icon {
        font-size: 2rem;
    }
    
    .zoom-text {
        font-size: 0.9rem;
    }
    
    .zoom-btn {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .image-zoom-controls {
        bottom: 10px;
        padding: 8px;
        gap: 8px;
    }
}

@media (max-width: 480px) {
    .evento-image-clickable {
        max-height: 200px;
    }
    
    .zoom-content .zoom-icon {
        font-size: 1.8rem;
    }
    
    .zoom-text {
        font-size: 0.8rem;
    }
}

/* ESTADOS DE CARGA Y ERROR */
.image-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    background: #f8f9fa;
    border-radius: 12px;
}

.image-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    color: #6c757d;
    text-align: center;
}

.image-error i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #dc3545;
}

/* RELATED EVENTS */
.related-events-section {
    width: 100%;
}

.related-events-list {
    width: 100%;
}

.related-event-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
}

.related-event-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.related-event-icon {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    color: white;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.related-event-content {
    flex-grow: 1;
    min-width: 0;
}

.related-event-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #343a40;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.related-event-date {
    font-size: 0.75rem;
    color: #6c757d;
}

/* FOOTER */
.eventos-modal-footer {
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
    padding: 1rem 1.5rem;
    width: 100%;
    box-sizing: border-box;
}

.footer-info {
    flex-grow: 1;
}

.footer-actions {
    flex-shrink: 0;
}

/* MULTIPLE EVENTS MODAL */
#multipleEventsModal .modal-content {
    border-radius: 12px;
}

.multiple-events-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
}

.multiple-events-item:hover {
    background: #f8f9fa;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.multiple-event-icon {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.multiple-event-content {
    flex-grow: 1;
    min-width: 0;
}

.multiple-event-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #343a40;
    margin-bottom: 0.5rem;
}

.multiple-event-details {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.multiple-event-type {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-weight: 600;
}

.multiple-event-time {
    font-size: 0.85rem;
    color: #6c757d;
    display: flex;
    align-items: center;
}

.multiple-event-description {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 0.5rem;
    line-height: 1.4;
}

/* RESPONSIVE ADJUSTMENTS */
@media (max-width: 992px) {
    .eventos-modal .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100vw - 1rem);
    }
    
    .evento-details-section .row {
        flex-direction: column;
    }
    
    .evento-main-details {
        border-right: none;
        border-bottom: 1px solid #e9ecef;
    }
    
    .evento-sidebar {
        background: white;
    }
    
    .datetime-card {
        padding: 0.75rem;
    }
    
    .datetime-icon {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
    
    .datetime-value {
        font-size: 1rem;
    }
}

@media (max-width: 768px) {
    .eventos-modal-header {
        padding: 1rem;
    }
    
    .evento-modal-icon-container {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .eventos-modal-header .modal-title {
        font-size: 1.2rem;
    }
    
    .evento-main-details,
    .evento-sidebar {
        padding: 1rem;
    }
    
    .datetime-card {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
    }
    
    .datetime-icon {
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
    
    .action-buttons .btn {
        padding: 0.6rem 0.8rem;
        font-size: 0.9rem;
    }
    
    .navigation-container {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .eventos-modal .modal-dialog {
        margin: 0.25rem;
        max-width: calc(100vw - 0.5rem);
    }
    
    .eventos-modal-header {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .evento-modal-icon-container {
        margin: 0;
    }
    
    .datetime-card {
        padding: 0.75rem;
    }
    
    .datetime-icon {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
    }
    
    .multiple-events-item {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .multiple-event-icon {
        margin: 0;
    }
}

/* PRINT STYLES */
@media print {
    .eventos-modal {
        position: static !important;
        z-index: auto !important;
    }
    
    .modal-backdrop {
        display: none !important;
    }
    
    .eventos-modal-header,
    .eventos-modal-footer {
        background: white !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
    }
    
    .btn,
    .navigation-container,
    .action-buttons {
        display: none !important;
    }
    
    .evento-details-section {
        display: block !important;
    }
    
    .evento-main-details,
    .evento-sidebar {
        width: 100% !important;
        float: none !important;
    }
}

/* LOADING STATES */
.loading-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* ACCESSIBILITY IMPROVEMENTS */
.eventos-modal:focus-within {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

.eventos-modal .btn:focus-visible {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* HIGH CONTRAST MODE */
@media (prefers-contrast: high) {
    .eventos-modal-content {
        border: 2px solid #000;
    }
    
    .datetime-card,
    .info-item {
        border: 2px solid #000;
    }
    
    .section-title {
        border-bottom-color: #000;
    }
}

/* REDUCED MOTION */
@media (prefers-reduced-motion: reduce) {
    .eventos-modal *,
    .eventos-modal *::before,
    .eventos-modal *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* DARK MODE PREPARATION */
@media (prefers-color-scheme: dark) {
    .eventos-modal {
        color-scheme: light; /* Force light mode for now */
    }
}
</style>

<!-- JAVASCRIPT ESPECÍFICO DEL MODAL -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Función para mostrar detalles de múltiples eventos del mismo día
    window.showMultipleEventsForDay = function(date, events) {
        const modal = document.getElementById('multipleEventsModal');
        const dateSpan = document.getElementById('multiple-events-date');
        const loadingDiv = document.getElementById('multiple-events-loading');
        const listDiv = document.getElementById('multiple-events-list');
        
        // Formatear fecha
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        dateSpan.textContent = formattedDate;
        
        // Mostrar loading
        loadingDiv.style.display = 'block';
        listDiv.style.display = 'none';
        
        // Simular carga (en implementación real, aquí harías AJAX)
        setTimeout(() => {
            loadingDiv.style.display = 'none';
            listDiv.style.display = 'block';
            
            // Renderizar eventos
            let eventsHTML = '';
            events.forEach(event => {
                eventsHTML += `
                    <div class="multiple-events-item" data-event-id="${event.id}">
                        <div class="multiple-event-icon" style="background-color: ${event.color};">
                            <i class="${event.icon}"></i>
                        </div>
                        <div class="multiple-event-content">
                            <div class="multiple-event-title">${event.title}</div>
                            <div class="multiple-event-details">
                                <span class="multiple-event-type badge" style="background-color: ${event.color};">
                                    ${event.type_label || event.type}
                                </span>
                                ${event.event_time ? `
                                    <span class="multiple-event-time">
                                        <i class="fas fa-clock me-1"></i>
                                        ${event.formatted_time}
                                    </span>
                                ` : ''}
                            </div>
                            ${event.description ? `
                                <div class="multiple-event-description">
                                    ${event.description.length > 100 ? 
                                        event.description.substring(0, 100) + '...' : 
                                        event.description}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            listDiv.innerHTML = eventsHTML;
            
            // Agregar event listeners para clicks en eventos
            listDiv.querySelectorAll('.multiple-events-item').forEach(item => {
                item.addEventListener('click', function() {
                    const eventId = this.getAttribute('data-event-id');
                    
                    // Cerrar modal actual
                    if (typeof bootstrap !== 'undefined') {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    
                    // Mostrar detalles del evento específico
                    setTimeout(() => {
                        if (window.eventosCalendarInstance && 
                            typeof window.eventosCalendarInstance.showEventDetails === 'function') {
                            window.eventosCalendarInstance.showEventDetails(eventId);
                        }
                    }, 300);
                });
            });
            
        }, 500);
        
        // Mostrar modal
        if (typeof bootstrap !== 'undefined') {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }
    };
    
    // Mejorar accesibilidad del modal
    const eventoModal = document.getElementById('eventoModal');
    
    eventoModal.addEventListener('shown.bs.modal', function() {
        // Enfocar el primer elemento interactivo
        const firstFocusable = eventoModal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            firstFocusable.focus();
        }
        
        // Deshabilitar scroll del body
        document.body.style.overflow = 'hidden';
    });
    
    eventoModal.addEventListener('hidden.bs.modal', function() {
        // Restaurar scroll del body
        document.body.style.overflow = '';
    });
    
    // Manejo de teclado para navegación
    eventoModal.addEventListener('keydown', function(e) {
        // Escape para cerrar
        if (e.key === 'Escape') {
            const modalInstance = bootstrap.Modal.getInstance(eventoModal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
        
        // Flechas para navegación entre eventos del mismo día
        if (e.key === 'ArrowLeft') {
            const prevBtn = document.getElementById('prev-event-btn');
            if (prevBtn && prevBtn.style.display !== 'none') {
                prevBtn.click();
            }
        }
        
        if (e.key === 'ArrowRight') {
            const nextBtn = document.getElementById('next-event-btn');
            if (nextBtn && nextBtn.style.display !== 'none') {
                nextBtn.click();
            }
        }
    });
    
    // Gestión de errores de imagen
    document.getElementById('evento-modal-image').addEventListener('error', function() {
        const container = document.getElementById('evento-modal-image-container');
        container.style.display = 'none';
    });
    
    // Lazy loading para eventos relacionados
    const relatedEventsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Cargar eventos relacionados cuando sea visible
                loadRelatedEvents();
                relatedEventsObserver.unobserve(entry.target);
            }
        });
    });
    
    const relatedEventsSection = document.getElementById('related-events-section');
    if (relatedEventsSection) {
        relatedEventsObserver.observe(relatedEventsSection);
    }
    
    function loadRelatedEvents() {
        // Esta función se implementaría para cargar eventos relacionados
        // mediante AJAX cuando sea necesario
    }
    
    // Performance: Debounce resize events
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Ajustar layout del modal si es necesario
            adjustModalLayout();
        }, 250);
    });
    
    function adjustModalLayout() {
        const modal = document.getElementById('eventoModal');
        if (modal.classList.contains('show')) {
            // Recalcular alturas y layouts si es necesario
            const modalBody = modal.querySelector('.modal-body');
            const maxHeight = window.innerHeight * 0.8;
            modalBody.style.maxHeight = maxHeight + 'px';
        }
    }
});

// JAVASCRIPT ESPECÍFICO DEL MODAL DE IMAGEN ZOOM
document.addEventListener('DOMContentLoaded', function() {
    
    // Variables del DOM
    const imageContainer = document.getElementById('evento-modal-image-container');
    const zoomModal = document.getElementById('imageZoomModal');
    const zoomedImage = document.getElementById('imageZoomImg');
    const closeBtn = document.getElementById('closeImageModal');
    const backdrop = document.querySelector('.image-zoom-backdrop');
    const downloadLink = document.getElementById('downloadImage');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const resetZoomBtn = document.getElementById('resetZoom');
    
    // Variables de zoom
    let currentZoom = 1;
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;
    
    // Función para mostrar imagen
    window.showEventImage = function(imageUrl, imageAlt = 'Imagen del evento') {
        
        if (!imageUrl || !imageContainer) {
            if (imageContainer) imageContainer.style.display = 'none';
            return;
        }
        
        // Mostrar estado de carga
        imageContainer.innerHTML = `
            <h6 class="section-title">
                <i class="fas fa-image me-2"></i>
                Imagen del evento
            </h6>
            <div class="image-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando imagen...</span>
                </div>
            </div>
        `;
        imageContainer.style.display = 'block';
        
        // Crear nueva imagen para verificar carga
        const tempImg = new Image();
        tempImg.onload = function() {
            // Imagen cargada correctamente
            imageContainer.innerHTML = `
                <h6 class="section-title">
                    <i class="fas fa-image me-2"></i>
                    Imagen del evento
                </h6>
                <div class="image-container-new">
                    <img id="evento-modal-image" 
                         src="${imageUrl}" 
                         alt="${imageAlt}" 
                         class="evento-image-clickable">
                    <div class="image-zoom-overlay">
                        <div class="zoom-content">
                            <i class="fas fa-search-plus zoom-icon"></i>
                            <span class="zoom-text">Click para ampliar</span>
                        </div>
                    </div>
                </div>
            `;
            
            // Reconfigurar event listeners
            setupImageClickHandler();
        };
        
        tempImg.onerror = function() {
            // Error cargando imagen
            imageContainer.innerHTML = `
                <h6 class="section-title">
                    <i class="fas fa-image me-2"></i>
                    Imagen del evento
                </h6>
                <div class="image-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>No se pudo cargar la imagen</p>
                </div>
            `;
        };
        
        tempImg.src = imageUrl;
    };
    
    // Configurar click en imagen
    function setupImageClickHandler() {
        const currentImage = document.getElementById('evento-modal-image');
        const currentContainer = document.querySelector('.image-container-new');
        
        if (currentImage && currentContainer && zoomModal) {
            // Agregar event listener
            function openZoomModal() {
                const imageSrc = currentImage.src;
                const imageAlt = currentImage.alt;
                
                if (!imageSrc) return;
                
                // Configurar imagen ampliada
                if (zoomedImage) {
                    zoomedImage.src = imageSrc;
                    zoomedImage.alt = imageAlt;
                }
                
                // Configurar título y descarga
                const titleElement = document.querySelector('.image-zoom-title');
                if (titleElement) titleElement.textContent = imageAlt;
                
                if (downloadLink) {
                    downloadLink.href = imageSrc;
                    downloadLink.download = `evento_imagen_${Date.now()}.jpg`;
                }
                
                // Resetear zoom
                resetZoom();
                
                // Mostrar modal
                zoomModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                // Enfocar en cerrar
                setTimeout(() => {
                    if (closeBtn) closeBtn.focus();
                }, 100);
            }
            
            currentImage.addEventListener('click', openZoomModal);
            currentContainer.addEventListener('click', openZoomModal);
        }
    }
    
    // Función para cerrar modal
    function closeZoomModal() {
        if (zoomModal) {
            zoomModal.style.display = 'none';
            document.body.style.overflow = '';
            resetZoom();
        }
    }
    
    // Event listeners para cerrar
    if (closeBtn) {
        closeBtn.addEventListener('click', closeZoomModal);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', closeZoomModal);
    }
    
    // Cerrar con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && zoomModal && zoomModal.style.display === 'flex') {
            closeZoomModal();
        }
    });
    
    // Funciones de zoom
    function resetZoom() {
        currentZoom = 1;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
    }
    
    function zoomIn() {
        if (currentZoom < 3) {
            currentZoom += 0.25;
            updateImageTransform();
        }
    }
    
    function zoomOut() {
        if (currentZoom > 0.5) {
            currentZoom -= 0.25;
            updateImageTransform();
        }
    }
    
    function updateImageTransform() {
        if (zoomedImage) {
            zoomedImage.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentZoom})`;
        }
    }
    
    // Event listeners para controles de zoom
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', zoomIn);
    }
    
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', zoomOut);
    }
    
    if (resetZoomBtn) {
        resetZoomBtn.addEventListener('click', resetZoom);
    }
    
    // Arrastrar imagen cuando está ampliada
    if (zoomedImage) {
        zoomedImage.addEventListener('mousedown', function(e) {
            if (currentZoom > 1) {
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                e.preventDefault();
            }
        });
        
        document.addEventListener('mousemove', function(e) {
            if (isDragging && currentZoom > 1) {
                translateX = e.clientX - startX;
                translateY = e.clientY - startY;
                updateImageTransform();
            }
        });
        
        document.addEventListener('mouseup', function() {
            isDragging = false;
        });
    }
    
    // Zoom con rueda del mouse
    if (zoomModal) {
        zoomModal.addEventListener('wheel', function(e) {
            e.preventDefault();
            
            if (e.deltaY < 0) {
                zoomIn();
            } else {
                zoomOut();
            }
        });
    }
    
    // Configurar imagen inicial si ya existe
    setupImageClickHandler();
    
    // Función para mostrar detalles de múltiples eventos del mismo día
    window.showMultipleEventsForDay = function(date, events) {
        const modal = document.getElementById('multipleEventsModal');
        const dateSpan = document.getElementById('multiple-events-date');
        const loadingDiv = document.getElementById('multiple-events-loading');
        const listDiv = document.getElementById('multiple-events-list');
        
        if (!modal || !dateSpan || !loadingDiv || !listDiv) {
            console.error('Elementos del modal de múltiples eventos no encontrados');
            return;
        }
        
        // Formatear fecha
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        dateSpan.textContent = formattedDate;
        
        // Mostrar loading
        loadingDiv.style.display = 'block';
        listDiv.style.display = 'none';
        
        // Simular carga (en implementación real, aquí harías AJAX)
        setTimeout(() => {
            loadingDiv.style.display = 'none';
            listDiv.style.display = 'block';
            
            // Renderizar eventos
            let eventsHTML = '';
            events.forEach(event => {
                eventsHTML += `
                    <div class="multiple-events-item" data-event-id="${event.id}">
                        <div class="multiple-event-icon" style="background-color: ${event.color};">
                            <i class="${event.icon}"></i>
                        </div>
                        <div class="multiple-event-content">
                            <div class="multiple-event-title">${event.title}</div>
                            <div class="multiple-event-details">
                                <span class="multiple-event-type badge" style="background-color: ${event.color};">
                                    ${event.type_label || event.type}
                                </span>
                                ${event.event_time ? `
                                    <span class="multiple-event-time">
                                        <i class="fas fa-clock me-1"></i>
                                        ${event.formatted_time}
                                    </span>
                                ` : ''}
                            </div>
                            ${event.description ? `
                                <div class="multiple-event-description">
                                    ${event.description.length > 100 ? 
                                        event.description.substring(0, 100) + '...' : 
                                        event.description}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            listDiv.innerHTML = eventsHTML;
            
            // Agregar event listeners para clicks en eventos
            listDiv.querySelectorAll('.multiple-events-item').forEach(item => {
                item.addEventListener('click', function() {
                    const eventId = this.getAttribute('data-event-id');
                    
                    // Cerrar modal actual
                    if (typeof bootstrap !== 'undefined') {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    
                    // Mostrar detalles del evento específico
                    setTimeout(() => {
                        if (window.eventosCalendarInstance && 
                            typeof window.eventosCalendarInstance.showEventDetails === 'function') {
                            window.eventosCalendarInstance.showEventDetails(eventId);
                        }
                    }, 300);
                });
            });
            
        }, 500);
        
        // Mostrar modal
        if (typeof bootstrap !== 'undefined') {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }
    };
    
    // Mejorar accesibilidad del modal
    const eventoModal = document.getElementById('eventoModal');
    
    if (eventoModal) {
        eventoModal.addEventListener('shown.bs.modal', function() {
            // Enfocar el primer elemento interactivo
            const firstFocusable = eventoModal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocusable) {
                firstFocusable.focus();
            }
            
            // Deshabilitar scroll del body
            document.body.style.overflow = 'hidden';
        });
        
        eventoModal.addEventListener('hidden.bs.modal', function() {
            // Restaurar scroll del body
            document.body.style.overflow = '';
        });
        
        // Manejo de teclado para navegación
        eventoModal.addEventListener('keydown', function(e) {
            // Escape para cerrar
            if (e.key === 'Escape') {
                const modalInstance = bootstrap.Modal.getInstance(eventoModal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            
            // Flechas para navegación entre eventos del mismo día
            if (e.key === 'ArrowLeft') {
                const prevBtn = document.getElementById('prev-event-btn');
                if (prevBtn && prevBtn.style.display !== 'none') {
                    prevBtn.click();
                }
            }
            
            if (e.key === 'ArrowRight') {
                const nextBtn = document.getElementById('next-event-btn');
                if (nextBtn && nextBtn.style.display !== 'none') {
                    nextBtn.click();
                }
            }
        });
    }
    
    // Gestión de errores de imagen original
    const originalImage = document.getElementById('evento-modal-image');
    if (originalImage) {
        originalImage.addEventListener('error', function() {
            if (imageContainer) {
                imageContainer.style.display = 'none';
            }
        });
    }
    
    // Lazy loading para eventos relacionados
    const relatedEventsSection = document.getElementById('related-events-section');
    if (relatedEventsSection) {
        const relatedEventsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Cargar eventos relacionados cuando sea visible
                    loadRelatedEvents();
                    relatedEventsObserver.unobserve(entry.target);
                }
            });
        });
        
        relatedEventsObserver.observe(relatedEventsSection);
    }
    
    function loadRelatedEvents() {
        // Esta función se implementaría para cargar eventos relacionados
        // mediante AJAX cuando sea necesario
    }
    
    // Performance: Debounce resize events
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Ajustar layout del modal si es necesario
            adjustModalLayout();
        }, 250);
    });
    
    function adjustModalLayout() {
        const modal = document.getElementById('eventoModal');
        if (modal && modal.classList.contains('show')) {
            // Recalcular alturas y layouts si es necesario
            const modalBody = modal.querySelector('.modal-body');
            if (modalBody) {
                const maxHeight = window.innerHeight * 0.8;
                modalBody.style.maxHeight = maxHeight + 'px';
            }
        }
    }
    
    document.body.appendChild(zoomModal);
});

</script>