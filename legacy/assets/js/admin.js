/**
 * Admin JavaScript para Eventos Probolsas - VERSIÓN COMPLETA CORREGIDA
 * Maneja formularios, validaciones, upload de imágenes y CRUD con modales corregidos
 */

(function($) {
    'use strict';

    // Clase principal para administración de eventos
    class EventosAdmin {
        constructor() {
            this.currentEventId = null;
            this.isSubmitting = false;
            this.uploadedImageUrl = null;
            this.currentAttachment = null;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeComponents();
            this.setupValidation();
            this.initializeModals();
        }

        bindEvents() {
            const self = this;

            // Formulario de evento
            
            $(document).on('click', '#save-event-btn', function(e) {
                e.preventDefault();
                self.saveEvent();
            });

            // Cambio de tipo de evento
            $(document).on('change', '#event-type', function() {
                self.handleEventTypeChange();
            });

            // Upload de imagen
            $(document).on('change', '#event-image-input', function(e) {
                self.handleImageUpload(e.target.files[0]);
            });
            
            $(document).on('click', '#upload-new-option', function() {
                $('#traditional-upload').slideDown();
                $('#event-image-input').click();
            });
            

            $(document).on('click', '#upload-image-btn', function() {
                $('#event-image-input').click();
            });

            $(document).on('click', '#remove-image-btn', function() {
                self.removeImage();
            });

            // Filtros en tiempo real
            $(document).on('input', '#search', function() {
                self.debounce(self.applyFilters.bind(self), 300)();
            });

            $(document).on('change', '#type, #date_from, #date_to', function() {
                self.applyFilters();
            });

            // Limpiar filtros
            $(document).on('click', '.clear-filters-btn', function() {
                self.clearFilters();
            });

            // Prevención de doble clic en botones
            $(document).on('click', '.btn[type="submit"], .btn-primary', function() {
                const $btn = $(this);
                if ($btn.hasClass('disabled') || $btn.prop('disabled')) {
                    return false;
                }
                
                self.disableButton($btn, 2000);
            });

            // Validación en tiempo real
            $(document).on('blur', '#event-title, #event-date', function() {
                self.validateField($(this));
            });

            // Auto-resize para textarea
            $(document).on('input', '#event-description', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Teclas de acceso rápido
            $(document).on('keydown', function(e) {
                // Ctrl + S para guardar
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    if ($('#evento-form').length) {
                        $('#evento-form').submit();
                    }
                }
                
                // Escape para cerrar modales
                if (e.key === 'Escape') {
                    $('.modal.show').modal('hide');
                }
            });
        }

        initializeComponents() {
            // Inicializar fecha por defecto
            const today = new Date().toISOString().split('T')[0];
            const $dateInput = $('#event-date');
            
            if ($dateInput.length && !$dateInput.val()) {
                $dateInput.val(today);
            }

            // Inicializar componentes de Bootstrap
            this.initializePopovers();
            
            // Configurar tipo de evento inicial
            this.handleEventTypeChange();
            
            // Auto-resize inicial para textareas
            $('#event-description').trigger('input');
        }

        initializePopovers() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
                popovers.forEach(function(popover) {
                    new bootstrap.Popover(popover);
                });
            }
        }

        setupValidation() {
            // Configurar validación personalizada
            const form = document.getElementById('evento-form');
            if (form) {
                form.noValidate = true;
            }
        }

        handleEventTypeChange() {
            const eventType = $('#event-type').val();
            const $imageSection = $('#image-section');
            const $imageInput = $('#event-image-input');
            
            // Tipos que requieren imagen
            const requiresImage = ['capacitacion','cumpleanos', 'reunion_especial'];
            
            if (requiresImage.includes(eventType)) {
                $imageSection.show().addClass('required');
                $imageInput.prop('required', true);
                this.showImageUploadArea();
            } else {
                $imageSection.hide().removeClass('required');
                $imageInput.prop('required', false);
                this.hideImageUploadArea();
            }
            
            // Actualizar estilos según el tipo
            this.updateFormStyling(eventType);
        }

        updateFormStyling(eventType) {
            const colors = {
                'cumpleanos': '#FFF3CD',
                'capacitacion': '#D4EDDA',
                'reunion_especial': '#D1ECF1',
                'reunion_laboral': '#E2D9F3'
            };
            
            const color = colors[eventType] || '#f8f9fa';
            
            $('.form-header, .form-footer').css({
                'background': `linear-gradient(135deg, ${color}, ${this.lightenColor(color, 20)})`,
                'transition': 'background 0.3s ease'
            });
        }

        lightenColor(color, percent) {
            const num = parseInt(color.replace("#", ""), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) + amt;
            const B = (num >> 8 & 0x00FF) + amt;
            const G = (num & 0x0000FF) + amt;
            
            return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 + 
                (B < 255 ? B < 1 ? 0 : B : 255) * 0x100 + 
                (G < 255 ? G < 1 ? 0 : G : 255)).toString(16).slice(1);
        }

        showImageUploadArea() {
            $('.image-upload-area').fadeIn();
        }

        hideImageUploadArea() {
            $('.image-upload-area').fadeOut();
        }

        handleImageUpload(file) {
            if (!file) return;
            
            // Validar tipo de archivo
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                this.showAlert(eventosAjax.strings.invalid_file, 'error');
                return;
            }
            
            // Validar tamaño (max 5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showAlert('El archivo es demasiado grande. Máximo 5MB.', 'error');
                return;
            }
            
            this.uploadImage(file);
        }

        uploadImage(file) {
            const self = this;
            const formData = new FormData();
            
            formData.append('image', file);
            formData.append('action', 'eventos_upload_image');
            formData.append('nonce', eventosAjax.nonce);
            
            // Mostrar progreso
            this.showUploadProgress();
            
            $.ajax({
                url: eventosAjax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            self.updateUploadProgress(percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        // Guardar datos completos del attachment
                        self.currentAttachment = {
                            id: response.data.attachment_id,
                            url: response.data.url,
                            medium_url: response.data.medium_url,
                            thumbnail_url: response.data.thumbnail_url,
                            title: response.data.title,
                            alt_text: response.data.alt_text,
                            filename: response.data.filename,
                            file_size: response.data.file_size,
                            mime_type: response.data.mime_type
                        };
                        
                        self.showImagePreviewEnhanced(self.currentAttachment);
                        self.showAlert('Imagen subida exitosamente a la biblioteca de medios', 'success');
                    } else {
                        self.showAlert(response.data || 'Error al subir imagen', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al subir imagen:', error);
                    self.showAlert('Error al subir imagen', 'error');
                },
                complete: function() {
                    self.hideUploadProgress();
                }
            });
        }

        showUploadProgress() {
            const progressHtml = `
                <div id="upload-progress" class="mt-3">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%">
                            <span class="sr-only">0% Completado</span>
                        </div>
                    </div>
                    <small class="text-muted">${eventosAjax.strings.uploading}</small>
                </div>
            `;
            
            $('#image-upload-area').append(progressHtml);
        }

        updateUploadProgress(percent) {
            const $progress = $('.progress-bar');
            $progress.css('width', percent + '%').attr('aria-valuenow', percent);
            $progress.find('.sr-only').text(Math.round(percent) + '% Completado');
        }

        hideUploadProgress() {
            $('#upload-progress').fadeOut(function() {
                $(this).remove();
            });
        }

        showImagePreview(url) {
            const previewHtml = `
                <div id="image-preview-container" class="mt-3">
                    <img id="image-preview" src="${url}" alt="Vista previa" class="img-thumbnail" style="max-width: 200px; cursor: pointer;">
                    <div class="mt-2">
                        <button type="button" id="remove-image-btn" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash"></i> Eliminar imagen
                        </button>
                    </div>
                </div>
            `;
            
            $('#image-preview-container').remove();
            $('#image-upload-area').append(previewHtml);
        }

        removeImage() {
            this.uploadedImageUrl = null;
            $('#image-preview-container').fadeOut(function() {
                $(this).remove();
            });
        }

        validateField($field) {
            const fieldName = $field.attr('name') || $field.attr('id');
            const value = $field.val().trim();
            let isValid = true;
            let message = '';
            
            // Remover clases de validación anteriores
            $field.removeClass('is-valid is-invalid');
            $field.next('.invalid-feedback').remove();
            
            switch (fieldName) {
                case 'title':
                case 'event-title':
                    if (!value) {
                        isValid = false;
                        message = 'El título es requerido';
                    } else if (value.length < 3) {
                        isValid = false;
                        message = 'El título debe tener al menos 3 caracteres';
                    }
                    break;
                    
                case 'event_date':
                case 'event-date':
                    if (!value) {
                        isValid = false;
                        message = 'La fecha es requerida';
                    } else {
                        const selectedDate = new Date(value);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        
                        if (selectedDate < today) {
                            isValid = false;
                            message = 'La fecha no puede ser anterior a hoy';
                        }
                    }
                    break;
            }
            
            // Aplicar clases de validación
            if (isValid) {
                $field.addClass('is-valid');
            } else {
                $field.addClass('is-invalid');
                $field.after(`<div class="invalid-feedback">${message}</div>`);
            }
            
            return isValid;
        }
        
        showImagePreviewEnhanced(attachment) {
            const previewHtml = `
                <div id="image-preview-container" class="mt-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <img id="image-preview" 
                                         src="${attachment.medium_url || attachment.url}" 
                                         alt="${attachment.alt_text || attachment.title}" 
                                         class="img-thumbnail" 
                                         style="max-width: 150px; cursor: pointer;"
                                         data-full-url="${attachment.url}">
                                </div>
                                <div class="col-md-8">
                                    <h6 class="mb-2">
                                        <i class="fas fa-image text-success me-1"></i>
                                        Imagen en Biblioteca de Medios
                                    </h6>
                                    <div class="image-details">
                                        <p class="mb-1">
                                            <strong>Archivo:</strong> <span class="text-muted">${attachment.filename}</span>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Tamaño:</strong> <span class="text-muted">${attachment.file_size}</span>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Tipo:</strong> <span class="text-muted">${attachment.mime_type}</span>
                                        </p>
                                        <p class="mb-2">
                                            <strong>ID:</strong> <span class="text-muted">${attachment.id}</span>
                                        </p>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info" id="view-full-image-btn">
                                                <i class="fas fa-search-plus me-1"></i> Ver completa
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" id="remove-image-btn">
                                                <i class="fas fa-trash me-1"></i> Quitar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="attachment-id-field" name="image_attachment_id" value="${attachment.id}">
                </div>
            `;
            
            $('#image-preview-container').remove();
            $('#image-upload-area').append(previewHtml);
            
            // Bind events para los nuevos botones
            this.bindImagePreviewEvents(attachment);
        }
        
        /**
         * Eventos para el preview mejorado
         */
        bindImagePreviewEvents(attachment) {
            const self = this;
            
            // Ver imagen completa
            $('#view-full-image-btn').on('click', function() {
                self.showImagePreview(attachment.url);
            });
            
            // Quitar imagen (pero no eliminarla de medios)
            $('#remove-image-btn').on('click', function() {
                if (confirm('¿Deseas quitar esta imagen del evento? La imagen permanecerá en tu biblioteca de medios.')) {
                    self.removeImageFromEvent();
                }
            });
        }
        
        /**
         * Quitar imagen del evento sin eliminarla de medios
         */
        removeImageFromEvent() {
            this.currentAttachment = null;
            $('#image-preview-container').fadeOut(function() {
                $(this).remove();
            });
            $('#attachment-id-field').remove();
        }
        
        /**
         * Abrir selector de biblioteca de medios
         */
        openMediaLibrary() {
            const self = this;
            
            // Verificar si wp.media está disponible
            if (typeof wp !== 'undefined' && wp.media) {
                const mediaUploader = wp.media({
                    title: 'Seleccionar Imagen para Evento',
                    button: {
                        text: 'Usar esta imagen'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    
                    // Validar que sea una imagen
                    if (!attachment.mime.startsWith('image/')) {
                        self.showAlert('Por favor selecciona solo imágenes.', 'error');
                        return;
                    }
                    
                    // Preparar datos del attachment
                    self.currentAttachment = {
                        id: attachment.id,
                        url: attachment.url,
                        medium_url: attachment.sizes?.medium?.url || attachment.url,
                        thumbnail_url: attachment.sizes?.thumbnail?.url || attachment.url,
                        title: attachment.title,
                        alt_text: attachment.alt,
                        filename: attachment.filename,
                        file_size: self.formatBytes(attachment.filesizeInBytes || 0),
                        mime_type: attachment.mime
                    };
                    
                    self.showImagePreviewEnhanced(self.currentAttachment);
                    self.showAlert('Imagen seleccionada de la biblioteca de medios', 'success');
                });
                
                mediaUploader.open();
            } else {
                this.showAlert('El selector de medios no está disponible. Usa la opción de subir archivo.', 'warning');
            }
        }
        
        /**
         * Formatear bytes
         */
        formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }


        validateForm() {
            let isValid = true;
            const requiredFields = ['#event-title', '#event-type', '#event-date'];
            
            requiredFields.forEach(selector => {
                const $field = $(selector);
                if ($field.length && !this.validateField($field)) {
                    isValid = false;
                }
            });
            
            // Validar imagen si es requerida
            const eventType = $('#event-type').val();
            const requiresImage = ['capacitacion','cumpleanos', 'reunion_especial'];
            
             if (requiresImage.includes(eventType)) {
                const hasExistingImage = $('#existing-image').length > 0;
                const hasCurrentAttachment = this.currentAttachment && this.currentAttachment.id;
                const hasAttachmentField = $('#attachment-id-field').length > 0;
                
                // Verificar si hay alguna imagen disponible
                if (!hasExistingImage && !hasCurrentAttachment && !hasAttachmentField) {
                    isValid = false;
                    this.showAlert('Este tipo de evento requiere una imagen', 'error');
                    
                    // Hacer scroll hasta la sección de imagen
                    $('#image-section')[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            return isValid;
        }

    saveEvent() {
        if (this.isSubmitting) return;
        
        setTimeout(() => {
            this.performSave();
        }, 100);
    }

    performSave() {
        
        if (!this.validateForm()) {
            this.showAlert('Por favor corrige los errores en el formulario', 'error');
            return;
        }
        
        this.isSubmitting = true;
        const $submitBtn = $('#save-event-btn');
        const originalText = $submitBtn.html();
        
        // Deshabilitar botón y mostrar loading
        $submitBtn.prop('disabled', true).html(`
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Guardando...
        `);
        
        const formData = this.getFormData();
        
        const action = this.currentEventId ? 'eventos_update' : 'eventos_create';
        
        if (this.currentEventId) {
            formData.event_id = this.currentEventId;
        }
        
        const self = this;
        
        $.ajax({
            url: eventosAjax.ajax_url,
            type: 'POST',
            data: formData,
            timeout: 30000,
            success: function(response) {
                
                if (response && response.success) {
                    self.showAlert(response.data.message, 'success');
                    
                    // Redireccionar después de crear
                    if (!self.currentEventId && response.data.event_id) {
                        setTimeout(function() {
                            window.location.href = self.getEditUrl(response.data.event_id);
                        }, 1500);
                    }
                    
                    // Actualizar lista si estamos en la página principal
                    if (window.location.href.includes('eventos-probolsas&') === false) {
                        self.refreshEventsList();
                    }
                    
                } else {
                    const errorMessage = (response && response.data) ? response.data : eventosAjax.strings.error;
                    console.error('Error en respuesta:', errorMessage);
                    self.showAlert(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                let errorMessage = eventosAjax.strings.error;
                
                if (xhr.status === 0) {
                    errorMessage = 'Error de conexión. Verifica tu conexión a internet.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Sin permisos para realizar esta acción.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Endpoint no encontrado.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Error del servidor. Intenta nuevamente.';
                }
                
                self.showAlert(errorMessage, 'error');
            },
            complete: function() {
                self.isSubmitting = false;
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }

        getFormData() {
            const formData = {
                action: this.currentEventId ? 'eventos_update' : 'eventos_create',
                nonce: eventosAjax.nonce,
                title: $('#event-title').val().trim(),
                type: $('#event-type').val(),
                description: $('#event-description').val().trim(),
                event_date: $('#event-date').val(),
                event_time: $('#event-time').val() || null
            };
            
            // Agregar attachment_id si hay una imagen seleccionada
            if (this.currentAttachment && this.currentAttachment.id) {
                formData.image_attachment_id = this.currentAttachment.id;
                formData.image_url = this.currentAttachment.url;
            }
            
            return formData;
        }

        getEditUrl(eventId) {
            const baseUrl = window.location.href.split('?')[0];
            return `${baseUrl}?page=eventos-probolsas&action=edit&event_id=${eventId}`;
        }

        // ===== FUNCIONES DE MODALES CORREGIDAS =====

        // Función mejorada para confirmar eliminación de evento
        confirmDeleteEvent(eventId, eventTitle) {
            this.currentEventId = eventId;
            
            // Actualizar contenido del modal
            $('#event-title-to-delete').text(eventTitle);
            
            // Asegurar que el modal tenga el z-index correcto
            const $modal = $('#deleteEventModal');
            $modal.css('z-index', '999999');
            
            // Crear modal dinámicamente si no existe
            if ($modal.length === 0) {
                this.createDeleteModal();
                $('#event-title-to-delete').text(eventTitle);
            }
            
            // Mostrar modal con configuración específica
            $modal.modal({
                backdrop: 'static',
                keyboard: false,
                focus: true
            }).modal('show');
            
            // Asegurar que el backdrop tenga z-index menor
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css('z-index', '999999');
                
                // Forzar posicionamiento correcto
                $modal.addClass('show').css({
                    'display': 'block',
                    'position': 'fixed',
                    'top': '0',
                    'left': '0',
                    'width': '100%',
                    'height': '100%',
                    'z-index': '999999'
                });
                
                // Centrar el dialog
                const $dialog = $modal.find('.modal-dialog');
                $dialog.css({
                    'position': 'relative',
                    'margin': '1.75rem auto',
                    'transform': 'translate(0, 0)'
                });
            }, 100);
        }

        // Crear modal de eliminación dinámicamente
        createDeleteModal() {
            const modalHtml = `
                <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteEventModalLabel">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Confirmar eliminación
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fas fa-exclamation-triangle warning-icon"></i>
                                    <p class="mb-3">
                                        <strong>¡Atención!</strong> Esta acción no se puede deshacer.
                                    </p>
                                    <p class="mb-3">
                                        ¿Estás seguro de que deseas eliminar el evento?
                                    </p>
                                    <p class="mb-0">
                                        <strong class="text-danger" id="event-title-to-delete">Ejemplo Cumpleaños</strong>
                                    </p>
                                    <small class="text-muted">
                                        Se eliminará permanentemente el evento y su imagen asociada (si tiene).
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                                    <i class="fas fa-trash me-1"></i> Sí, eliminar evento
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal anterior si existe
            $('#deleteEventModal').remove();
            
            // Agregar nuevo modal al body
            $('body').append(modalHtml);
        }

        // Función mejorada para eliminar evento
        deleteEvent() {
            if (!this.currentEventId) return;
            
            const self = this;
            const $confirmBtn = $('#confirm-delete-btn');
            const originalText = $confirmBtn.html();
            
            // Deshabilitar botón y mostrar loading
            $confirmBtn.prop('disabled', true).html(`
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Eliminando...
            `);
            
            $.ajax({
                url: eventosAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'eventos_delete',
                    event_id: this.currentEventId,
                    nonce: eventosAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showAlert(response.data.message, 'success');
                        
                        // Cerrar modal
                        $('#deleteEventModal').modal('hide');
                        
                        // Remover fila de la tabla con animación
                        $(`.delete-event-btn[data-event-id="${self.currentEventId}"]`)
                            .closest('tr')
                            .addClass('table-danger')
                            .fadeOut(1000, function() {
                                $(this).remove();
                                self.updateEventCount();
                            });
                        
                    } else {
                        self.showAlert(response.data || eventosAjax.strings.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al eliminar evento:', error);
                    self.showAlert('Error de conexión al eliminar el evento', 'error');
                },
                complete: function() {
                    $confirmBtn.prop('disabled', false).html(originalText);
                    self.currentEventId = null;
                }
            });
        }

        // Función mejorada para mostrar imagen preview
        showImagePreview(src) {
            // Remover modal anterior si existe
            $('#imagePreviewModal').remove();
            
            const modalHtml = `
                <div class="modal fade" id="imagePreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-image me-2"></i>
                                    Vista previa de imagen
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center p-3">
                                <img src="${src}" alt="Vista previa" class="img-fluid" style="max-height: 70vh;">
                            </div>
                            <div class="modal-footer">
                                <a href="${src}" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    Abrir en nueva ventana
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Agregar y mostrar modal
            $('body').append(modalHtml);
            const $modal = $('#imagePreviewModal');
            
            // Configurar z-index
            $modal.css('z-index', '999999');
            
            // Mostrar modal
            $modal.modal('show');
            
            // Asegurar posicionamiento correcto
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css('z-index', '999999');
            }, 100);
            
            // Remover modal después de cerrarlo
            $modal.on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        // Función para forzar el correcto posicionamiento de modales
        forceModalPosition() {
            // Aplicar a todos los modales existentes
            $('.modal').each(function() {
                const $modal = $(this);
                
                if ($modal.hasClass('show')) {
                    $modal.css({
                        'z-index': '999999',
                        'position': 'fixed',
                        'top': '0',
                        'left': '0',
                        'width': '100%',
                        'height': '100%'
                    });
                    
                    const $dialog = $modal.find('.modal-dialog');
                    $dialog.css({
                        'position': 'relative',
                        'margin': '1.75rem auto',
                        'transform': 'translate(0, 0)'
                    });
                }
            });
            
            // Asegurar que el backdrop esté detrás
            $('.modal-backdrop').css('z-index', '999998');
        }

        // Función para limpiar modales huérfanos
        cleanupModals() {
            // Remover modales sin contenido o duplicados
            $('.modal').each(function() {
                const $modal = $(this);
                
                // Si el modal no tiene contenido válido, removerlo
                if ($modal.find('.modal-content').length === 0) {
                    $modal.remove();
                }
                
                // Si hay múltiples modales con el mismo ID, mantener solo el último
                const modalId = $modal.attr('id');
                if (modalId) {
                    const $duplicates = $(`#${modalId}`);
                    if ($duplicates.length > 1) {
                        $duplicates.not(':last').remove();
                    }
                }
            });
            
            // Remover backdrops huérfanos
            $('.modal-backdrop').each(function() {
                const $backdrop = $(this);
                if ($('.modal.show').length === 0) {
                    $backdrop.remove();
                }
            });
        }

        // Función de inicialización mejorada para modales
        initializeModals() {
            const self = this;
            
            // Limpiar modales al inicializar
            this.cleanupModals();
            
            // Event listener mejorado para botones de eliminar
            $(document).off('click', '.delete-event-btn').on('click', '.delete-event-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const eventId = $(this).data('event-id');
                const eventTitle = $(this).data('event-title') || $(this).closest('tr').find('td:first').text().trim();
                
                if (eventId) {
                    self.confirmDeleteEvent(eventId, eventTitle);
                }
            });
            
            // Event listener para confirmar eliminación
            $(document).off('click', '#confirm-delete-btn').on('click', '#confirm-delete-btn', function(e) {
                e.preventDefault();
                self.deleteEvent();
            });
            
            // Event listener para preview de imágenes
            $(document).off('click', '.evento-thumbnail, #image-preview').on('click', '.evento-thumbnail, #image-preview', function(e) {
                e.preventDefault();
                const src = $(this).attr('src');
                if (src) {
                    self.showImagePreview(src);
                }
            });
            
            // Forzar posicionamiento correcto cuando se muestre cualquier modal
            $(document).on('shown.bs.modal', '.modal', function() {
                self.forceModalPosition();
            });
            
            // Limpiar cuando se oculte el modal
            $(document).on('hidden.bs.modal', '.modal', function() {
                self.cleanupModals();
                
                // Restaurar scroll del body
                $('body').removeClass('modal-open').css('padding-right', '');
            });
            
            // Manejar el escape para cerrar modales
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.modal.show').modal('hide');
                }
            });
        }

        // ===== RESTO DE FUNCIONES =====

        applyFilters() {
            const filters = {
                search: $('#search').val().trim(),
                type: $('#type').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val()
            };
            
            // Filtrar tabla de eventos
            this.filterEventsTable(filters);
        }

        filterEventsTable(filters) {
            const $rows = $('tbody tr');
            let visibleCount = 0;
            
            $rows.each(function() {
                const $row = $(this);
                const title = $row.find('td:first').text().toLowerCase();
                const type = $row.find('.badge').text().toLowerCase();
                const dateCell = $row.find('td:nth-child(3)').text();
                
                let show = true;
                
                // Filtro de búsqueda
                if (filters.search && !title.includes(filters.search.toLowerCase())) {
                    show = false;
                }
                
                // Filtro de tipo
                if (filters.type && !type.includes(filters.type.toLowerCase())) {
                    show = false;
                }
                
                // Filtros de fecha (implementar logica de fechas)
                // if (filters.date_from || filters.date_to) { ... }
                
                if (show) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });
            
            // Actualizar contador
            $('.badge.bg-primary').text(visibleCount);
            
            // Mostrar mensaje si no hay resultados
            if (visibleCount === 0 && $rows.length > 0) {
                this.showNoResultsMessage();
            } else {
                this.hideNoResultsMessage();
            }
        }

        clearFilters() {
            $('#search').val('');
            $('#type').val('');
            $('#date_from').val('');
            $('#date_to').val('');
            
            this.applyFilters();
        }

        showNoResultsMessage() {
            if ($('#no-results-message').length === 0) {
                const message = `
                    <tr id="no-results-message">
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-search fa-2x text-muted mb-3"></i>
                            <h6>No se encontraron eventos</h6>
                            <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                        </td>
                    </tr>
                `;
                $('tbody').append(message);
            }
        }

        hideNoResultsMessage() {
            $('#no-results-message').remove();
        }

        refreshEventsList() {
            // Recargar la lista de eventos sin refrescar toda la página
            const currentUrl = new URL(window.location);
            const params = new URLSearchParams(currentUrl.search);
            
            if (params.get('page') === 'eventos-probolsas' && !params.get('action')) {
                location.reload();
            }
        }

        updateEventCount() {
            const visibleRows = $('tbody tr:visible:not(#no-results-message)').length;
            $('.badge.bg-primary').text(visibleRows);
            
            // Actualizar estadísticas si existen
            if ($('.card .card-title').length > 0) {
                // Logica para actualizar contadores de estadísticas
            }
        }

        disableButton($btn, duration = 2000) {
            $btn.addClass('disabled').prop('disabled', true);
            
            setTimeout(function() {
                $btn.removeClass('disabled').prop('disabled', false);
            }, duration);
        }

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Función mejorada para mostrar alertas
        showAlert(message, type = 'info') {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type] || 'alert-info';
            
            const iconClass = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-triangle',
                'warning': 'fas fa-exclamation-circle',
                'info': 'fas fa-info-circle'
            }[type] || 'fas fa-info-circle';
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show eventos-alert" role="alert" style="z-index: 999999; position: relative;">
                    <i class="${iconClass} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Remover alertas anteriores
            $('.eventos-alert').remove();
            
            // Insertar nueva alerta
            $('.wrap').prepend(alertHtml);
            
            // Auto-hide después de 5 segundos
            setTimeout(function() {
                $('.eventos-alert').fadeOut();
            }, 5000);
            
            // Scroll hasta la alerta
            $('html, body').animate({
                scrollTop: $('.eventos-alert').offset().top - 20
            }, 500);
        }

        // Método para exportar eventos
        exportEvents() {
            const filters = {
                search: $('#search').val().trim(),
                type: $('#type').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val()
            };
            
            // Crear URL para descarga
            const params = new URLSearchParams({
                action: 'eventos_export',
                nonce: eventosAjax.nonce,
                ...filters
            });
            
            window.open(`${eventosAjax.ajax_url}?${params.toString()}`);
        }

        // Método para importar eventos
        importEvents(file) {
            if (!file) return;
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'eventos_import');
            formData.append('nonce', eventosAjax.nonce);
            
            const self = this;
            
            $.ajax({
                url: eventosAjax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showAlert(`${response.data.imported} eventos importados exitosamente`, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        self.showAlert(response.data || 'Error al importar eventos', 'error');
                    }
                },
                error: function() {
                    self.showAlert('Error al importar eventos', 'error');
                }
            });
        }
    }

    // Inicialización
    $(document).ready(function() {
        // Crear instancia global
        window.eventosAdmin = new EventosAdmin();
        
        // Detectar si estamos editando un evento existente
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'edit') {
            window.eventosAdmin.currentEventId = urlParams.get('event_id');
        }
        
        // Mejorar UX con animaciones
        $('.card, .table, .btn').addClass('fade-in');
        
        // Auto-save para formularios largos (cada 30 segundos)
        if ($('#evento-form').length) {
            setInterval(function() {
                const formData = window.eventosAdmin.getFormData();
                
                // Solo auto-guardar si hay cambios significativos
                if (formData.title && formData.event_date) {
                    localStorage.setItem('eventos_draft', JSON.stringify(formData));
                }
            }, 30000);
        }
        
        // Recuperar draft al cargar formulario
        if ($('#evento-form').length && !window.eventosAdmin.currentEventId) {
            const draft = localStorage.getItem('eventos_draft');
            if (draft) {
                try {
                    const data = JSON.parse(draft);
                    if (confirm('Se encontró un borrador guardado. ¿Deseas recuperarlo?')) {
                        $('#event-title').val(data.title || '');
                        $('#event-type').val(data.type || '');
                        $('#event-description').val(data.description || '');
                        $('#event-date').val(data.event_date || '');
                        $('#event-time').val(data.event_time || '');
                    }
                    localStorage.removeItem('eventos_draft');
                } catch (e) {
                    console.log('Error al recuperar borrador:', e);
                }
            }
        }
        
        // Confirmación antes de salir con cambios sin guardar
        let hasUnsavedChanges = false;
        
        $('#evento-form input, #evento-form textarea, #evento-form select').on('input change', function() {
            hasUnsavedChanges = true;
        });
        
        $('#evento-form').on('submit', function() {
            hasUnsavedChanges = false;
        });
        
        $(window).on('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                const message = '¿Estás seguro de que deseas salir? Los cambios no guardados se perderán.';
                e.returnValue = message;
                return message;
            }
        });
        
        // Atajos de teclado adicionales
        $(document).keydown(function(e) {
            // Ctrl + E para nuevo evento
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'admin.php?page=eventos-probolsas-add';
            }
            
            // F3 para buscar
            if (e.key === 'F3') {
                e.preventDefault();
                $('#search').focus();
            }
        });

        // Funciones globales de utilidad para modales
        window.showLoadingModal = function(message = 'Procesando...') {
            const modalHtml = `
                <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-body text-center py-4">
                                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <h6 class="mb-2">Procesando...</h6>
                                <p class="text-muted mb-0">${message}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#loadingModal').remove();
            $('body').append(modalHtml);
            
            const $modal = $('#loadingModal');
            $modal.css('z-index', '999999');
            $modal.modal('show');
            
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css('z-index', '999999');
            }, 100);
        };

        window.hideLoadingModal = function() {
            $('#loadingModal').modal('hide').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        };

        window.showConfirmModal = function(options = {}) {
            const defaults = {
                title: '¿Estás seguro?',
                message: '¿Deseas continuar con esta acción?',
                confirmText: 'Confirmar',
                cancelText: 'Cancelar',
                confirmClass: 'btn-primary',
                onConfirm: function() {}
            };
            
            const settings = { ...defaults, ...options };
            
            const modalHtml = `
                <div class="modal fade" id="confirmModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-question-circle me-2"></i>
                                    ${settings.title}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <div class="mb-4">
                                        <i class="fas fa-question-circle text-info" style="font-size: 4rem;"></i>
                                    </div>
                                    <p class="text-muted">${settings.message}</p>
                                </div>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> ${settings.cancelText}
                                </button>
                                <button type="button" class="btn ${settings.confirmClass}" id="confirm-action-btn">
                                    <i class="fas fa-check me-1"></i> ${settings.confirmText}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#confirmModal').remove();
            $('body').append(modalHtml);
            
            const $modal = $('#confirmModal');
            $modal.css('z-index', '999999');
            
            // Bind confirm action
            $modal.find('#confirm-action-btn').on('click', function() {
                $modal.modal('hide');
                if (typeof settings.onConfirm === 'function') {
                    settings.onConfirm();
                }
            });
            
            $modal.modal('show');
            
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css('z-index', '999999');
            }, 100);
            
            $modal.on('hidden.bs.modal', function() {
                $(this).remove();
            });
        };

        // Funciones adicionales de UI
        window.showHelpModal = function() {
            const helpHtml = `
                <div class="modal fade" id="helpModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Ayuda - Gestión de Eventos
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="mb-4">
                                        <h6><i class="fas fa-calendar-plus text-primary me-2"></i>Crear Eventos</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><strong>Título:</strong> Nombre descriptivo del evento</li>
                                            <li class="mb-2"><strong>Tipo:</strong> Categoría del evento
                                                <ul class="mt-1">
                                                    <li>Cumpleaños - Requiere imagen</li>
                                                    <li>Capacitación - Solo texto</li>
                                                    <li>Reunión Especial - Requiere imagen</li>
                                                    <li>Reunión Laboral - Solo texto</li>
                                                </ul>
                                            </li>
                                            <li class="mb-2"><strong>Fecha:</strong> Día del evento</li>
                                            <li class="mb-2"><strong>Hora:</strong> Opcional</li>
                                        </ul>
                                    </div>
                                    <div class="mb-4">
                                        <h6><i class="fas fa-search text-success me-2"></i>Filtrar Eventos</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><strong>Búsqueda:</strong> Por título o descripción</li>
                                            <li class="mb-2"><strong>Tipo:</strong> Filtrar por categoría</li>
                                            <li class="mb-2"><strong>Fechas:</strong> Rango de fechas</li>
                                        </ul>
                                        
                                        <h6 class="mt-4"><i class="fas fa-keyboard text-warning me-2"></i>Atajos</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1"><kbd>Ctrl + S</kbd> Guardar evento</li>
                                            <li class="mb-1"><kbd>Ctrl + E</kbd> Nuevo evento</li>
                                            <li class="mb-1"><kbd>F3</kbd> Enfocar búsqueda</li>
                                            <li class="mb-1"><kbd>Esc</kbd> Cerrar modales</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h6><i class="fas fa-lightbulb text-info me-2"></i>Consejos</h6>
                                        <div class="alert alert-info">
                                            <ul class="mb-0">
                                                <li>Los eventos de cumpleaños y reuniones especiales requieren una imagen</li>
                                                <li>Las imágenes deben ser JPG, PNG o GIF (máximo 5MB)</li>
                                                <li>Los eventos se muestran automáticamente en el calendario frontend</li>
                                                <li>Puedes filtrar eventos en tiempo real mientras escribes</li>
                                                <li>Los cambios se guardan automáticamente como borrador cada 30 segundos</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                    <i class="fas fa-check me-1"></i> Entendido
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#helpModal').remove();
            $('body').append(helpHtml);
            
            const $modal = $('#helpModal');
            $modal.css('z-index', '999999');
            $modal.modal('show');
            
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css('z-index', '999999');
            }, 100);
            
            $modal.on('hidden.bs.modal', function() {
                $(this).remove();
            });
        };

        // Agregar botón de ayuda si no existe
        if ($('.page-title-action').length && $('#help-btn').length === 0) {
            $('.page-title-action').after(`
                <button type="button" id="help-btn" class="btn btn-outline-info btn-sm ms-2" onclick="showHelpModal()" title="Ayuda">
                    <i class="fas fa-question-circle"></i> Ayuda
                </button>
            `);
        }

        // Inicialización final de tooltips
        function initTooltips() {
            $('[data-bs-toggle="tooltip"]').each(function() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const existingTooltip = bootstrap.Tooltip.getInstance(this);
                    if (existingTooltip) {
                        existingTooltip.dispose();
                    }
                    
                    new bootstrap.Tooltip(this, {
                        title: $(this).attr('tooltip'),
                        placement: 'top',
                        trigger: 'hover focus'
                    });
                }
            });
        }

        // Inicializar tooltips
        // setTimeout(initTooltips, 500);

        // Manejo mejorado de errores AJAX
        $(document).ajaxError(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('eventos_')) {
                console.error('Error AJAX en Eventos Admin:', xhr.status, xhr.responseText);
                
                // Mostrar error específico basado en código de estado
                let errorMessage = 'Error de conexión';
                if (xhr.status === 403) {
                    errorMessage = 'Sin permisos para realizar esta acción';
                } else if (xhr.status === 404) {
                    errorMessage = 'Recurso no encontrado';
                } else if (xhr.status === 500) {
                    errorMessage = 'Error interno del servidor';
                } else if (xhr.status === 0) {
                    errorMessage = 'Sin conexión a internet';
                }
                
                if (window.eventosAdmin) {
                    window.eventosAdmin.showAlert(errorMessage, 'error');
                }
            }
        });

        // Limpieza al salir de la página
        $(window).on('beforeunload', function() {
            // Limpiar tooltips
            $('[data-bs-toggle="tooltip"]').each(function() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const tooltip = bootstrap.Tooltip.getInstance(this);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                }
            });
            
            // Limpiar modales
            $('.modal').each(function() {
                $(this).modal('hide').remove();
            });
            
            // Limpiar timers
            clearTimeout(window.eventosAdminTimeout);
        });

        // Performance optimizations
        $(window).on('resize', window.eventosAdmin.debounce(function() {
            // Reajustar modales en resize
            $('.modal.show').each(function() {
                const $modal = $(this);
                const $dialog = $modal.find('.modal-dialog');
                
                if (window.innerWidth < 768) {
                    $dialog.css('margin', '0.25rem auto');
                } else {
                    $dialog.css('margin', '1.75rem auto');
                }
            });
        }, 250));
    });

})(jQuery);