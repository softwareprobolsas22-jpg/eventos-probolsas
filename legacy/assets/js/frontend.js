/**
 * Frontend JavaScript para Eventos Probolsas - VERSIÓN COMPLETAMENTE ACTUALIZADA Y CORREGIDA
 * Compatible con calendario reestructurado y modales corregidos
 */

// Verificar que jQuery esté disponible
if (typeof jQuery === 'undefined') {
    console.error('Eventos Probolsas: jQuery no está disponible');
}

(function($) {
    'use strict';
    
    // Clase principal para el calendario - COMPLETAMENTE REESCRITA
    class EventosCalendar {
        constructor(options = {}) {
            // Configuración por defecto
            this.config = {
                containerId: options.containerId || this.findAvailableContainer(),
                year: options.year || new Date().getFullYear(),
                month: options.month || (new Date().getMonth() + 1),
                showFilters: options.showFilters !== false,
                showSearch: options.showSearch !== false,
                showNavigation: options.showNavigation !== false,
                showLegend: options.showLegend !== false,
                showStats: options.showStats !== false,
                autoRefresh: options.autoRefresh === true,
                compactMode: options.compactMode === true,
                height: options.height || 'auto',
                theme: options.theme || 'default',
                types: options.types || '',
                limit: parseInt(options.limit) || 0,
                view: options.view || 'month'
            };
            
            // Estado del calendario
            this.currentYear = this.config.year;
            this.currentMonth = this.config.month;
            this.events = {};
            this.allEvents = {};
            this.isLoading = false;
            this.searchTimeout = null;
            
            // Filtros actuales
            this.currentFilters = {
                search: '',
                type: '',
                date_from: '',
                date_to: ''
            };
            
            // Referencias DOM
            this.container = null;
            this.calendarGrid = null;
            this.calendarDays = null;
            
            // Inicializar
            this.init();
        }
        
        findAvailableContainer() {
        const possibleContainers = document.querySelectorAll('.eventos-calendar-wrapper');
        
        for (let container of possibleContainers) {
            if (container.id && !container.dataset.eventosInitialized) {
                // Marcar como inicializado para evitar duplicados
                container.dataset.eventosInitialized = 'true';
                return container.id;
            }
        }
        
        // 2. Si no hay contenedores sin inicializar, buscar por el ID por defecto
        const defaultContainer = document.getElementById('eventos-calendar-wrapper');
        if (defaultContainer && !defaultContainer.dataset.eventosInitialized) {
            defaultContainer.dataset.eventosInitialized = 'true';
            return 'eventos-calendar-wrapper';
        }
        
        // 3. Si no encuentra nada, retornar null
        console.warn('No se encontraron contenedores de calendario disponibles para inicializar');
        return null;
    }

        init() {
            
            // Verificar que el contenedor existe
            this.container = document.getElementById(this.config.containerId);
            if (!this.container) {
                console.error('Contenedor del calendario no encontrado:', this.config.containerId);
                return;
            }
            
            // Obtener referencias DOM
            this.calendarGrid = this.container.querySelector('.calendar-grid');
            this.calendarDays = this.container.querySelector('#calendar-days-container');
            
            // Configurar selectores iniciales
            this.updateSelectors();
            
            // Bind events
            this.bindEvents();
            
            // Cargar calendario inicial
            this.loadCalendar();
            
            // Guardar referencia global
            window.eventosCalendarInstance = this;
        }

        bindEvents() {
            const self = this;
            const containerId = this.config.containerId;

            // Navegación con flechas
            $(document).on('click', `#${containerId} #prev-month`, function(e) {
                e.preventDefault();
                self.navigateMonth(-1);
            });

            $(document).on('click', `#${containerId} #next-month`, function(e) {
                e.preventDefault();
                self.navigateMonth(1);
            });

            $(document).on('click', `#${containerId} #today-btn`, function(e) {
                e.preventDefault();
                self.goToToday();
            });

            // Selectores de mes y año
            $(document).on('change', `#${containerId} #month-selector`, function() {
                const newMonth = parseInt($(this).val());
                self.currentMonth = newMonth;
                self.loadCalendar();
            });

            $(document).on('change', `#${containerId} #year-selector`, function() {
                const newYear = parseInt($(this).val());
                self.currentYear = newYear;
                self.loadCalendar();
            });

            // Filtros mejorados con debounce
            $(document).on('input', `#${containerId} #calendar-search`, function() {
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(() => {
                    self.currentFilters.search = $(this).val().trim();
                    self.applyFilters();
                }, 300);
            });

            $(document).on('change', `#${containerId} #calendar-type-filter`, function() {
                self.currentFilters.type = $(this).val();
                self.applyFilters();
            });

            $(document).on('change', `#${containerId} #date-filter-from`, function() {
                self.currentFilters.date_from = $(this).val();
                self.applyFilters();
            });

            $(document).on('change', `#${containerId} #date-filter-to`, function() {
                self.currentFilters.date_to = $(this).val();
                self.applyFilters();
            });

            // Limpiar filtros
            $(document).on('click', `#${containerId} #clear-filters`, function() {
                self.clearFilters();
            });

            // Filtros rápidos
            $(document).on('click', `#${containerId} .quick-filter`, function() {
                const filter = $(this).data('filter');
                self.applyQuickFilter(filter);
            });

            // Clicks en días del calendario - MEJORADO PARA MÚLTIPLES EVENTOS
            $(document).on('click', `#${containerId} .calendar-day`, function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const date = $(this).data('date');
                const dayEvents = self.getEventsForDate(date);
                
                if (dayEvents.length === 0) {
                    return; // No hay eventos
                }
                
                if (dayEvents.length === 1) {
                    // Un solo evento: abrir modal directo
                    self.showEventDetails(dayEvents[0].id);
                } else {
                    // Múltiples eventos: mostrar lista
                    self.showMultipleEventsModal(date, dayEvents);
                }
            });

            // Clicks en eventos específicos
            $(document).on('click', `#${containerId} .calendar-event`, function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const eventId = $(this).data('event-id');
                if (eventId) {
                    self.showEventDetails(eventId);
                }
            });

            // Clicks en "más eventos"
            $(document).on('click', `#${containerId} .calendar-event-more`, function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $day = $(this).closest('.calendar-day');
                const date = $day.data('date');
                const dayEvents = self.getEventsForDate(date);
                
                if (dayEvents.length > 0) {
                    self.showMultipleEventsModal(date, dayEvents);
                }
            });

            // Cerrar modal con Escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('#eventoModal').modal('hide');
                    $('#multipleEventsModal').modal('hide');
                }
            });

            // Auto-refresh si está habilitado
            if (this.config.autoRefresh) {
                setInterval(() => {
                    if (!this.isLoading) {
                        this.loadCalendar();
                    }
                }, 300000); // 5 minutos
            }
        }

        navigateMonth(direction) {
            if (direction > 0) {
                if (this.currentMonth === 12) {
                    this.currentMonth = 1;
                    this.currentYear++;
                } else {
                    this.currentMonth++;
                }
            } else {
                if (this.currentMonth === 1) {
                    this.currentMonth = 12;
                    this.currentYear--;
                } else {
                    this.currentMonth--;
                }
            }

            this.updateSelectors();
            this.loadCalendar();
        }

        goToToday() {
            const today = new Date();
            this.currentYear = today.getFullYear();
            this.currentMonth = today.getMonth() + 1;
            
            this.updateSelectors();
            this.loadCalendar();
        }

        updateSelectors() {
            const containerId = this.config.containerId;
            $(`#${containerId} #month-selector`).val(this.currentMonth);
            $(`#${containerId} #year-selector`).val(this.currentYear);
        }

        loadCalendar() {
            if (this.isLoading) {
                return;
            }
            
            // Verificar que eventosAjax esté disponible
            if (typeof eventosAjax === 'undefined') {
                this.showError('Error de configuración: eventosAjax no disponible');
                return;
            }
            
            this.isLoading = true;
            this.showLoading();
            
            const self = this;
            
            $.ajax({
                url: eventosAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'eventos_get_calendar_data',
                    year: this.currentYear,
                    month: this.currentMonth,
                    nonce: eventosAjax.nonce
                },
                timeout: 15000,
                success: function(response) {
                    if (response.success) {
                        self.allEvents = response.data.events || {};
                        self.events = { ...self.allEvents };
                        self.renderCalendar(response.data);
                        self.updateStats(response.data.stats);
                        self.updateMonthDisplay();
                        self.applyFilters(); // Aplicar filtros después de cargar
                    } else {
                        self.showError(response.data || 'Error al cargar eventos');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Error de conexión';
                    if (xhr.status === 403) {
                        errorMessage = 'Sin permisos';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Error del servidor';
                    } else if (status === 'timeout') {
                        errorMessage = 'Tiempo de espera agotado';
                    }
                    self.showError(errorMessage);
                },
                complete: function() {
                    self.hideLoading();
                    self.isLoading = false;
                }
            });
        }

        renderCalendar(data) {
            if (!this.calendarDays) {
                console.error('Contenedor de días no encontrado');
                return;
            }

            const calendarHtml = this.generateCalendarHTML(data);
            this.calendarDays.innerHTML = calendarHtml;
            
            // Actualizar eventos actuales para acceso externo
            this.currentEvents = this.events;
        }

        generateCalendarHTML(data) {
            let html = '';
            
            const firstDay = data.first_day_of_week || 0;
            const daysInMonth = data.days_in_month || this.getDaysInMonth(this.currentYear, this.currentMonth);
            
            // Días del mes anterior
            const prevMonth = this.currentMonth === 1 ? 12 : this.currentMonth - 1;
            const prevYear = this.currentMonth === 1 ? this.currentYear - 1 : this.currentYear;
            const daysInPrevMonth = this.getDaysInMonth(prevYear, prevMonth);
            
            for (let i = firstDay - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                const date = `${prevYear}-${String(prevMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                html += `<div class="calendar-day other-month" data-date="${date}">`;
                html += `<div class="calendar-day-number">${day}</div>`;
                html += `</div>`;
            }

            // Días del mes actual
            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = `${this.currentYear}-${String(this.currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayEvents = this.events[day] || [];
                
                let classes = 'calendar-day';
                if (date === todayStr) classes += ' today';
                if (dayEvents.length > 0) classes += ' has-events';
                
                html += `<div class="${classes}" data-date="${date}">`;
                html += `<div class="calendar-day-number">${day}</div>`;
                
                if (dayEvents.length > 0) {
                    html += '<div class="calendar-events">';
                    
                    // Mostrar hasta 3 eventos + indicador de más
                    const visibleEvents = dayEvents.slice(0, 3);
                    visibleEvents.forEach(event => {
                        html += `<div class="calendar-event ${event.type}" 
                                      data-event-id="${event.id}" 
                                      title="${this.escapeHtml(event.title)}">`;
                        html += `<i class="${event.icon}"></i> ${this.truncateText(event.title, 12)}`;
                        html += `</div>`;
                    });
                    
                    if (dayEvents.length > 3) {
                        html += `<div class="calendar-event-more" data-date="${date}">+${dayEvents.length - 3} más</div>`;
                    }
                    html += '</div>';
                }
                
                html += `</div>`;
            }

            // Días del siguiente mes
            const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            const remainingCells = totalCells - (firstDay + daysInMonth);
            const nextMonth = this.currentMonth === 12 ? 1 : this.currentMonth + 1;
            const nextYear = this.currentMonth === 12 ? this.currentYear + 1 : this.currentYear;
            
            for (let day = 1; day <= remainingCells; day++) {
                const date = `${nextYear}-${String(nextMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                html += `<div class="calendar-day other-month" data-date="${date}">`;
                html += `<div class="calendar-day-number">${day}</div>`;
                html += `</div>`;
            }

            return html;
        }

        // NUEVA FUNCIÓN: Aplicar filtros al calendario
        applyFilters() {
            // Restaurar eventos originales
            this.events = { ...this.allEvents };
            
            // Aplicar filtros si existen
            if (this.hasActiveFilters()) {
                const filteredEvents = {};
                
                Object.keys(this.events).forEach(day => {
                    const dayEvents = this.events[day].filter(event => {
                        return this.eventMatchesFilters(event);
                    });
                    
                    if (dayEvents.length > 0) {
                        filteredEvents[day] = dayEvents;
                    }
                });
                
                this.events = filteredEvents;
            }
            
            // Regenerar calendario con eventos filtrados
            this.renderCalendar({
                first_day_of_week: new Date(this.currentYear, this.currentMonth - 1, 1).getDay(),
                days_in_month: this.getDaysInMonth(this.currentYear, this.currentMonth)
            });
            
            // Actualizar estadísticas
            this.updateStatsFromFiltered();
            
            // Mostrar/ocultar mensaje de sin resultados
            this.toggleNoEventsMessage();
        }

        hasActiveFilters() {
            return this.currentFilters.search || 
                   this.currentFilters.type || 
                   this.currentFilters.date_from || 
                   this.currentFilters.date_to;
        }

        eventMatchesFilters(event) {
            // Filtro de búsqueda
            if (this.currentFilters.search) {
                const searchTerm = this.currentFilters.search.toLowerCase();
                const matchesSearch = event.title.toLowerCase().includes(searchTerm) ||
                                    (event.description && event.description.toLowerCase().includes(searchTerm));
                if (!matchesSearch) return false;
            }
            
            // Filtro de tipo
            if (this.currentFilters.type && event.type !== this.currentFilters.type) {
                return false;
            }
            
            // Filtro de fecha desde
            if (this.currentFilters.date_from && event.event_date < this.currentFilters.date_from) {
                return false;
            }
            
            // Filtro de fecha hasta
            if (this.currentFilters.date_to && event.event_date > this.currentFilters.date_to) {
                return false;
            }
            
            return true;
        }

        // NUEVA FUNCIÓN: Limpiar filtros
        clearFilters() {
            this.currentFilters = {
                search: '',
                type: '',
                date_from: '',
                date_to: ''
            };
            
            const containerId = this.config.containerId;
            $(`#${containerId} #calendar-search`).val('');
            $(`#${containerId} #calendar-type-filter`).val('');
            $(`#${containerId} #date-filter-from`).val('');
            $(`#${containerId} #date-filter-to`).val('');
            
            // Remover clases active de filtros rápidos
            $(`#${containerId} .quick-filter`).removeClass('active');
            
            this.applyFilters();
        }

        // NUEVA FUNCIÓN: Aplicar filtros rápidos
        applyQuickFilter(filter) {
            const today = new Date();
            const containerId = this.config.containerId;
            
            // Limpiar filtros actuales
            this.clearFilters();
            
            switch (filter) {
                case 'today':
                    const todayStr = today.toISOString().split('T')[0];
                    this.currentFilters.date_from = todayStr;
                    this.currentFilters.date_to = todayStr;
                    $(`#${containerId} #date-filter-from`).val(todayStr);
                    $(`#${containerId} #date-filter-to`).val(todayStr);
                    break;
                    
                case 'week':
                    const startOfWeek = new Date(today);
                    const endOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - today.getDay());
                    endOfWeek.setDate(today.getDate() + (6 - today.getDay()));
                    
                    const weekStart = startOfWeek.toISOString().split('T')[0];
                    const weekEnd = endOfWeek.toISOString().split('T')[0];
                    
                    this.currentFilters.date_from = weekStart;
                    this.currentFilters.date_to = weekEnd;
                    $(`#${containerId} #date-filter-from`).val(weekStart);
                    $(`#${containerId} #date-filter-to`).val(weekEnd);
                    break;
                    
                case 'month':
                    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    
                    const monthStart = startOfMonth.toISOString().split('T')[0];
                    const monthEnd = endOfMonth.toISOString().split('T')[0];
                    
                    this.currentFilters.date_from = monthStart;
                    this.currentFilters.date_to = monthEnd;
                    $(`#${containerId} #date-filter-from`).val(monthStart);
                    $(`#${containerId} #date-filter-to`).val(monthEnd);
                    break;
                    
                case 'upcoming':
                    const nextMonth = new Date(today);
                    nextMonth.setMonth(today.getMonth() + 1);
                    
                    const upcomingStart = today.toISOString().split('T')[0];
                    const upcomingEnd = nextMonth.toISOString().split('T')[0];
                    
                    this.currentFilters.date_from = upcomingStart;
                    this.currentFilters.date_to = upcomingEnd;
                    $(`#${containerId} #date-filter-from`).val(upcomingStart);
                    $(`#${containerId} #date-filter-to`).val(upcomingEnd);
                    break;
            }
            
            this.applyFilters();
        }

        // NUEVA FUNCIÓN: Obtener eventos para una fecha específica
        getEventsForDate(date) {
            const dateObj = new Date(date);
            const day = dateObj.getDate();
            return this.events[day] || [];
        }

        // NUEVA FUNCIÓN: Mostrar modal de múltiples eventos
        showMultipleEventsModal(date, events) {
            if (typeof window.showMultipleEventsForDay === 'function') {
                window.showMultipleEventsForDay(date, events);
            } else {
                // Fallback: crear modal dinámicamente
                this.createMultipleEventsModal(date, events);
            }
        }

        // NUEVA FUNCIÓN: Crear modal de múltiples eventos dinámicamente
        createMultipleEventsModal(date, events) {
            const dateObj = new Date(date);
            const formattedDate = dateObj.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Remover modal anterior si existe
            $('#multipleEventsModal').remove();
            
            let eventsHtml = '';
            events.forEach(event => {
                eventsHtml += `
                    <div class="list-group-item list-group-item-action evento-list-item" data-event-id="${event.id}">
                        <div class="d-flex align-items-center">
                            <div class="evento-icon me-3" style="background-color: ${event.color};">
                                <i class="${event.icon}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${this.escapeHtml(event.title)}</h6>
                                ${event.event_time ? `<small class="text-muted"><i class="fas fa-clock me-1"></i>${event.formatted_time}</small>` : ''}
                                ${event.description ? `<p class="mb-1 text-muted">${this.truncateText(event.description, 80)}</p>` : ''}
                            </div>
                            <div>
                                <span class="badge" style="background-color: ${event.color}; color: ${event.type === 'cumpleanos' ? '#8d6e00' : 'white'};">
                                    ${event.type_label}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            const modalHtml = `
                <div class="modal fade eventos-modal" id="multipleEventsModal" tabindex="-1" aria-labelledby="multipleEventsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="multipleEventsModalLabel">
                                    <i class="fas fa-calendar-day me-2"></i>
                                    Eventos del ${formattedDate}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div class="list-group list-group-flush">
                                    ${eventsHtml}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <small class="text-muted me-auto">
                                    <i class="fas fa-info-circle me-1"></i>
                                    ${events.length} evento${events.length !== 1 ? 's' : ''} programado${events.length !== 1 ? 's' : ''}
                                </small>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Agregar modal al body
            $('body').append(modalHtml);
            
            const $modal = $('#multipleEventsModal');
            
            // Configurar z-index
            $modal.css('z-index', '999999');
            
            // Bind click en eventos
            $modal.on('click', '.evento-list-item', (e) => {
                e.preventDefault();
                const eventId = $(e.currentTarget).data('event-id');
                $modal.modal('hide');
                
                // Esperar a que se cierre el modal antes de abrir el siguiente
                setTimeout(() => {
                    this.showEventDetails(eventId);
                }, 300);
            });
            
            // Mostrar modal
            $modal.modal('show');
            
            // Asegurar posicionamiento
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css('z-index', '999999');
            }, 100);
            
            // Remover modal después de cerrarlo
            $modal.on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        // MEJORADA: Mostrar detalles del evento en modal
        showEventDetails(eventId) {
            const self = this;
            
            // Verificar que el modal existe
            let $modal = $('#eventoModal');
            if ($modal.length === 0) {
                console.error('Modal #eventoModal no encontrado. Asegúrate de incluir frontend-modal.php');
                return;
            }
            
            // Configurar z-index antes de mostrar
            $modal.css('z-index', '999999');
            
            // Mostrar modal inmediatamente
            $modal.modal({
                backdrop: 'static',
                keyboard: true,
                focus: true
            }).modal('show');
            
            // Asegurar posicionamiento correcto
            setTimeout(() => {
                $('.modal-backdrop').css('z-index', '999998');
                $modal.css({
                    'z-index': '999999',
                    'position': 'fixed',
                    'top': '0',
                    'left': '0',
                    'width': '100%',
                    'height': '100%',
                    'display': 'block'
                });
                
                // Centrar el dialog
                const $dialog = $modal.find('.modal-dialog');
                $dialog.css({
                    'position': 'relative',
                    'margin': '1rem auto',
                    'transform': 'translate(0, 0)'
                });
            }, 100);
            
            // Mostrar loading
            $('#modal-loading').show();
            $('#modal-content').hide();
            $('#modal-error').hide();
            
            // Cargar datos del evento
            $.ajax({
                url: eventosAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'eventos_get_event_details',
                    event_id: eventId,
                    nonce: eventosAjax.nonce
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        self.populateModal(response.data);
                    } else {
                        self.showModalError(response.data || 'Error al cargar el evento');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', status, error);
                    let errorMessage = 'Error de conexión al cargar el evento';
                    
                    if (xhr.status === 403) {
                        errorMessage = 'Sin permisos para ver este evento';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Evento no encontrado';
                    } else if (status === 'timeout') {
                        errorMessage = 'Tiempo de espera agotado. Intenta nuevamente.';
                    }
                    
                    self.showModalError(errorMessage);
                },
                complete: function() {
                    $('#modal-loading').hide();
                }
            });
        }

        // FUNCIÓN CORREGIDA PARA POPULAR EL MODAL
        populateModal(event) {
            try {
                // Título y tipo
                $('#evento-modal-title').text(event.title || 'Sin título');
                
                // Icono del título si existe
                const $titleIcon = $('#evento-modal-icon');
                if ($titleIcon.length && event.icon) {
                    $titleIcon.attr('class', event.icon + ' me-2');
                }
                
                // Badge de tipo
                const $typeBadge = $('#evento-modal-type-badge');
                if ($typeBadge.length) {
                    $typeBadge.attr('style', `background-color: ${event.color || '#007bff'};`);
                }
                
                const $typeIcon = $('#evento-modal-type-icon');
                if ($typeIcon.length && event.icon) {
                    $typeIcon.attr('class', event.icon);
                }
                
                const $typeLabel = $('#evento-modal-type-label');
                if ($typeLabel.length) {
                    $typeLabel.text(event.type_label || 'Sin categoría');
                }
                
                const $typeText = $('#evento-modal-type-text');
                if ($typeText.length) {
                    $typeText.text(event.type_label || 'Sin categoría');
                }
                
                // Fecha
                const $modalDate = $('#evento-modal-date');
                if ($modalDate.length) {
                    $modalDate.text(event.formatted_date || event.event_date || 'Sin fecha');
                }
                
                // ID del evento
                const $modalId = $('#evento-modal-id');
                if ($modalId.length) {
                    $modalId.text(event.id || '');
                }
                
                // Hora
                const $timeContainer = $('#evento-modal-time-container');
                const $modalTime = $('#evento-modal-time');
                if (event.event_time && event.formatted_time) {
                    if ($modalTime.length) {
                        $modalTime.text(event.formatted_time);
                    }
                    if ($timeContainer.length) {
                        $timeContainer.show();
                    }
                } else {
                    if ($timeContainer.length) {
                        $timeContainer.hide();
                    }
                }
                
                // Descripción
                const $descContainer = $('#evento-modal-description-container');
                const $modalDesc = $('#evento-modal-description');
                if (event.description) {
                    if ($modalDesc.length) {
                        $modalDesc.text(event.description);
                    }
                    if ($descContainer.length) {
                        $descContainer.show();
                    }
                } else {
                    if ($descContainer.length) {
                        $descContainer.hide();
                    }
                }
                
                // Imagen
                const $imageContainer = $('#evento-modal-image-container');
                const $modalImage = $('#evento-modal-image');
                if (event.image_url) {
                    if ($modalImage.length) {
                        $modalImage.attr('src', event.image_url).attr('alt', event.title || 'Imagen del evento');
                    }
                    if ($imageContainer.length) {
                        $imageContainer.show();
                    }
                    
                    // Configurar modal de imagen ampliada
                    $modalImage.off('click').on('click', function() {
                        const $imageModal = $('#image-modal-title');
                        if ($imageModal.length) {
                            $imageModal.text(event.title || 'Imagen del evento');
                        }
                        
                        const $imageModalImg = $('#image-modal-img');
                        if ($imageModalImg.length) {
                            $imageModalImg.attr('src', event.image_url).attr('alt', event.title || 'Imagen del evento');
                        }
                        
                        const $downloadLink = $('#image-download-link');
                        if ($downloadLink.length) {
                            $downloadLink.attr('href', event.image_url);
                        }
                    });
                } else {
                    if ($imageContainer.length) {
                        $imageContainer.hide();
                    }
                }
                
                // Navegación entre eventos (si existe)
                if (event.navigation) {
                    this.setupEventNavigation(event.navigation);
                }
                
                // Fechas de creación y actualización
                if (event.created_at) {
                    const $created = $('#evento-modal-created');
                    if ($created.length) {
                        $created.text(this.formatDateTime(event.created_at));
                    }
                }
                
                if (event.updated_at) {
                    const $updated = $('#evento-modal-updated, #evento-modal-last-update');
                    if ($updated.length) {
                        $updated.text(this.formatDateTime(event.updated_at));
                    }
                }
                
                // Eventos relacionados
                if (event.related_events && event.related_events.length > 0) {
                    this.renderRelatedEvents(event.related_events);
                } else {
                    const $relatedSection = $('#related-events-section');
                    if ($relatedSection.length) {
                        $relatedSection.hide();
                    }
                }
                
                // Mostrar contenido y ocultar error
                $('#modal-content').show();
                $('#modal-error').hide();
                
            } catch (error) {
                console.error('Error al popular modal:', error);
                this.showModalError('Error al mostrar los datos del evento');
            }
        }

        setupEventNavigation(navigation) {
            if (navigation.total > 1) {
                $('#event-position').text(`Evento ${navigation.position} de ${navigation.total}`);
                
                if (navigation.has_prev) {
                    $('#prev-event-btn').show().off('click').on('click', () => {
                        this.showEventDetails(navigation.prev_id);
                    });
                } else {
                    $('#prev-event-btn').hide();
                }
                
                if (navigation.has_next) {
                    $('#next-event-btn').show().off('click').on('click', () => {
                        this.showEventDetails(navigation.next_id);
                    });
                } else {
                    $('#next-event-btn').hide();
                }
                
                $('#evento-navigation').show();
            } else {
                $('#evento-navigation').hide();
            }
        }

        renderRelatedEvents(relatedEvents) {
            const container = $('#related-events-list');
            let html = '';
            
            relatedEvents.forEach(event => {
                html += `
                    <div class="related-event-item" data-event-id="${event.id}">
                        <div class="related-event-icon" style="background-color: ${event.color};">
                            <i class="${event.icon}"></i>
                        </div>
                        <div class="related-event-content">
                            <div class="related-event-title">${event.title}</div>
                            <div class="related-event-date">${event.formatted_date}</div>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
            $('#related-events-section').show();
            
            // Bind clicks en eventos relacionados
            container.off('click').on('click', '.related-event-item', (e) => {
                const eventId = $(e.currentTarget).data('event-id');
                this.showEventDetails(eventId);
            });
        }

        // FUNCIÓN CORREGIDA PARA MOSTRAR ERROR EN MODAL
        showModalError(message) {
            const $errorMessage = $('#modal-error-message');
            if ($errorMessage.length) {
                $errorMessage.text(message);
            }
            
            $('#modal-error').show();
            $('#modal-content').hide();
            
            // Agregar botón de reintento si no existe
            if ($('#modal-retry-btn').length === 0) {
                const retryButton = `
                    <button type="button" class="btn btn-primary mt-3" id="modal-retry-btn">
                        <i class="fas fa-redo me-1"></i> Reintentar
                    </button>
                `;
                $('#modal-error').append(retryButton);
                
                // Bind retry
                $('#modal-retry-btn').on('click', () => {
                    const eventId = $('#evento-modal-id').text();
                    if (eventId) {
                        this.showEventDetails(eventId);
                    }
                });
            }
        }

        updateStats(stats) {
            if (!this.config.showStats) return;
            
            const containerId = this.config.containerId;
            
            if (stats) {
                $(`#${containerId} #stat-total`).text(stats.total_month || 0);
                $(`#${containerId} #stat-today`).text(stats.today || 0);
                $(`#${containerId} #stat-week`).text(stats.next_week || 0);
                $(`#${containerId} #stat-upcoming`).text(stats.next_week || 0);
                
                // Actualizar contadores de leyenda
                if (stats.by_type) {
                    Object.keys(stats.by_type).forEach(type => {
                        $(`#${containerId} #count-${type}`).text(stats.by_type[type]);
                    });
                }
            }
            
            // Actualizar contador de eventos del mes
            $(`#${containerId} #events-count`).text(this.getTotalVisibleEvents());
        }

        updateStatsFromFiltered() {
            const totalEvents = this.getTotalVisibleEvents();
            const containerId = this.config.containerId;
            
            $(`#${containerId} #events-count`).text(totalEvents);
            $(`#${containerId} #stat-total`).text(totalEvents);
            
            // Calcular estadísticas de eventos filtrados
            const today = new Date().toISOString().split('T')[0];
            const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            let todayCount = 0;
            let weekCount = 0;
            const typeCounts = {};
            
            Object.values(this.events).forEach(dayEvents => {
                dayEvents.forEach(event => {
                    if (event.event_date === today) {
                        todayCount++;
                    }
                    if (event.event_date >= today && event.event_date <= nextWeek) {
                        weekCount++;
                    }
                    
                    typeCounts[event.type] = (typeCounts[event.type] || 0) + 1;
                });
            });
            
            $(`#${containerId} #stat-today`).text(todayCount);
            $(`#${containerId} #stat-week`).text(weekCount);
            $(`#${containerId} #stat-upcoming`).text(weekCount);
            
            // Actualizar contadores de tipos
            ['cumpleanos', 'capacitacion', 'reunion_especial', 'reunion_laboral'].forEach(type => {
                $(`#${containerId} #count-${type}`).text(typeCounts[type] || 0);
            });
        }

        getTotalVisibleEvents() {
            let total = 0;
            Object.values(this.events).forEach(dayEvents => {
                total += dayEvents.length;
            });
            return total;
        }

        updateMonthDisplay() {
            const containerId = this.config.containerId;
            const monthNames = eventosAjax.months || {
                1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
                5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
                9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
            };
            
            $(`#${containerId} #current-month-name`).text(monthNames[this.currentMonth]);
            $(`#${containerId} #current-year`).text(this.currentYear);
        }

        toggleNoEventsMessage() {
            const containerId = this.config.containerId;
            const hasEvents = this.getTotalVisibleEvents() > 0;
            const hasFilters = this.hasActiveFilters();
            
            if (!hasEvents && hasFilters) {
                $(`#${containerId} #no-events-message`).show();
                $(`#${containerId} .calendar-grid`).hide();
            } else {
                $(`#${containerId} #no-events-message`).hide();
                $(`#${containerId} .calendar-grid`).show();
            }
        }

        getDaysInMonth(year, month) {
            return new Date(year, month, 0).getDate();
        }

        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        formatDateTime(dateTimeString) {
            try {
                const date = new Date(dateTimeString);
                return date.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return dateTimeString;
            }
        }

        showLoading() {
            const containerId = this.config.containerId;
            $(`#${containerId} #calendar-loading`).show();
            $(`#${containerId} #eventos-calendar`).hide();
        }

        hideLoading() {
            const containerId = this.config.containerId;
            $(`#${containerId} #calendar-loading`).hide();
            $(`#${containerId} #eventos-calendar`).show();
        }

        showError(message) {
            const containerId = this.config.containerId;
            const errorHtml = `
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h5>Error al cargar el calendario</h5>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-redo"></i> Recargar
                    </button>
                </div>
            `;
            $(`#${containerId} #eventos-calendar`).html(errorHtml);
        }

        // FUNCIÓN PARA LIMPIAR MODALES AL CAMBIAR DE CALENDARIO
        cleanupEventModals() {
            // Cerrar cualquier modal abierto
            $('.modal.show').modal('hide');
            
            // Remover modales dinámicos
            $('#multipleEventsModal').remove();
            
            // Limpiar backdrops huérfanos
            setTimeout(() => {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
            }, 300);
        }

        // Método público para refrescar el calendario
        refresh() {
            this.loadCalendar();
        }

        // Método público para ir a una fecha específica
        goToDate(year, month) {
            this.currentYear = parseInt(year);
            this.currentMonth = parseInt(month);
            this.updateSelectors();
            this.loadCalendar();
        }

        // Método público para establecer filtros
        setFilters(filters) {
            this.currentFilters = { ...this.currentFilters, ...filters };
            this.applyFilters();
        }

        // Método público para obtener eventos actuales
        getCurrentEvents() {
            return this.events;
        }

        // Método público para obtener configuración
        getConfig() {
            return this.config;
        }
    }

    // EXPORTAR LA CLASE GLOBALMENTE
    window.EventosCalendar = EventosCalendar;

    // FUNCIÓN GLOBAL PARA MOSTRAR MÚLTIPLES EVENTOS (COMPATIBLE CON TEMPLATES EXTERNOS)
    window.showMultipleEventsForDay = function(date, events) {
        if (window.eventosCalendarInstance) {
            window.eventosCalendarInstance.createMultipleEventsModal(date, events);
        }
    };

    // FUNCIÓN GLOBAL PARA MOSTRAR EVENTO ESPECÍFICO
    window.showEventosEvent = function(eventId) {
        if (window.eventosCalendarInstance) {
            window.eventosCalendarInstance.showEventDetails(eventId);
        }
    };

    // Inicialización cuando el documento esté listo
    $(document).ready(function() {
        
        // Configurar botones de compartir y añadir a calendario en el modal
        $(document).on('click', '#share-event-btn', function() {
            const title = $('#evento-modal-title').text();
            const url = window.location.href;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Evento: ' + title,
                    text: 'Te invito a este evento: ' + title,
                    url: url
                }).catch(console.error);
            } else {
                // Fallback: copiar al portapapeles
                const textToCopy = `Evento: ${title}\n${url}`;
                
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showToast('Enlace copiado al portapapeles', 'success');
                }).catch(() => {
                    // Fallback del fallback
                    prompt('Copia este enlace:', textToCopy);
                });
            }
        });

        $(document).on('click', '#add-to-calendar-btn', function() {
            const eventId = $('#evento-modal-id').text();
            if (eventId && eventosAjax) {
                const downloadUrl = eventosAjax.ajax_url + 
                    '?action=eventos_download_ical&event_id=' + eventId + 
                    '&nonce=' + eventosAjax.nonce;
                window.open(downloadUrl, '_blank');
            }
        });

        // Manejo mejorado de clicks en eventos para listas móviles
        $(document).on('click', '.evento-clickable, .evento-details-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const eventId = $(this).data('event-id');
            if (eventId && window.eventosCalendarInstance) {
                window.eventosCalendarInstance.showEventDetails(eventId);
            }
        });

        // Manejo de responsive automático
        function handleResponsiveCalendar() {
            const isMobile = window.innerWidth < 768;
            
            $('.eventos-calendar-container').each(function() {
                const $container = $(this);
                
                if (isMobile) {
                    // En móvil, colapsar filtros por defecto
                    const $collapse = $container.find('#filtros-collapse');
                    if ($collapse.length && $collapse.hasClass('show')) {
                        $collapse.removeClass('show');
                    }
                    
                    // Ajustar tamaño de botones
                    $container.find('.calendar-navigation .btn').addClass('btn-sm');
                    
                } else {
                    // En desktop, expandir filtros
                    const $collapse = $container.find('#filtros-collapse');
                    if ($collapse.length && !$collapse.hasClass('show')) {
                        $collapse.addClass('show');
                    }
                    
                    // Tamaño normal de botones
                    $container.find('.calendar-navigation .btn').removeClass('btn-sm');
                }
            });
        }

        // Ejecutar al cargar y al cambiar tamaño
        handleResponsiveCalendar();
        $(window).on('resize', debounce(handleResponsiveCalendar, 250));

        // Manejo mejorado de filtros rápidos globales
        $(document).on('click', '.quick-filter', function() {
            const $this = $(this);
            const $container = $this.closest('.eventos-calendar-container');
            
            // Remover active de hermanos
            $container.find('.quick-filter').removeClass('active');
            
            // Agregar active al clickeado
            $this.addClass('active');
        });

        // Reset de filtros mejorado
        $(document).on('click', '#reset-filters-link, .clear-filters-btn', function() {
            const $container = $(this).closest('.eventos-calendar-container');
            $container.find('#clear-filters').trigger('click');
        });

        // Inicialización de tooltips mejorada
        function initializeTooltips() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                // Destruir tooltips existentes
                $('[data-bs-toggle="tooltip"]').each(function() {
                    const tooltip = bootstrap.Tooltip.getInstance(this);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                });
                
                // Crear nuevos tooltips
                $('[data-tooltip]').each(function() {
                    new bootstrap.Tooltip(this, {
                        title: $(this).attr('data-tooltip'),
                        placement: 'top',
                        trigger: 'hover focus'
                    });
                });
            }
        }

        // Inicializar tooltips al cargar
        // initializeTooltips();

        // Reinicializar tooltips cuando se actualice el calendario
        $(document).on('calendar:updated', initializeTooltips);

        // Manejo de errores globales para AJAX
        $(document).ajaxError(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('eventos_')) {
                console.error('Error AJAX en Eventos:', xhr.responseText);
                
                // Mostrar error amigable al usuario
                if (typeof showToast === 'function') {
                    showToast('Error de conexión. Intenta nuevamente.', 'error');
                }
            }
        });

        // Funciones de utilidad globales
        window.showEventosToast = function(message, type = 'info') {
            showToast(message, type);
        };

        window.refreshAllCalendars = function() {
            $('.eventos-calendar-container').each(function() {
                const containerId = $(this).attr('id');
                const instance = window['eventosCalendar_' + containerId];
                if (instance && typeof instance.refresh === 'function') {
                    instance.refresh();
                }
            });
        };

        // Precargar modals para mejor UX
        function preloadModals() {
            // Precargar modal de eventos si no existe
            if ($('#eventoModal').length === 0) {
                console.warn('Modal de eventos no encontrado. Asegúrate de incluir frontend-modal.php');
            }
            
            // Precargar modal de múltiples eventos si no existe
            if ($('#multipleEventsModal').length === 0) {
                console.warn('Modal de múltiples eventos no encontrado.');
            }
        }

        preloadModals();

        // Auto-refresh global cada 10 minutos para todos los calendarios
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                window.refreshAllCalendars();
            }
        }, 600000); // 10 minutos

        // Pausa auto-refresh cuando la pestaña no está visible
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Refrescar calendarios al volver a la pestaña
                setTimeout(window.refreshAllCalendars, 1000);
            }
        });

        // Limpiar memory leaks al salir de la página
        $(window).on('beforeunload', function() {
            // Limpiar tooltips
            $('[data-bs-toggle="tooltip"]').each(function() {
                const tooltip = bootstrap.Tooltip.getInstance(this);
                if (tooltip) {
                    tooltip.dispose();
                }
            });
            
            // Limpiar timers
            clearTimeout(window.eventosCalendarTimeout);
        });
    });

    // Función auxiliar para mostrar toast notifications - MEJORADA
    function showToast(message, type = 'info') {
        // Buscar contenedor de toast en cualquier calendario
        let $toast = $('#notification-toast');
        
        if ($toast.length === 0) {
            // Crear toast dinámicamente si no existe
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999999;">
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
            `;
            $('body').append(toastHtml);
            $toast = $('#notification-toast');
        }

        const iconClasses = {
            'success': 'fas fa-check-circle text-success',
            'error': 'fas fa-exclamation-triangle text-danger',
            'warning': 'fas fa-exclamation-circle text-warning',
            'info': 'fas fa-info-circle text-info'
        };

        $('#toast-icon').attr('class', iconClasses[type] || iconClasses.info);
        $('#toast-message').text(message);
        $('#toast-time').text('ahora');
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toastInstance = new bootstrap.Toast($toast[0], {
                autohide: true,
                delay: 5000
            });
            toastInstance.show();
        } else {
            // Fallback sin Bootstrap
            $toast.fadeIn().delay(5000).fadeOut();
        }
    }

    // Función auxiliar debounce
    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    // Función auxiliar throttle
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Exportar utilidades globalmente
    window.eventosUtils = {
        showToast,
        debounce,
        throttle
    };

    // Funciones globales adicionales para compatibilidad
    window.showEventModal = function(eventId) {
        if (window.eventosCalendarInstance) {
            window.eventosCalendarInstance.showEventDetails(eventId);
        }
    };

    window.hideEventModal = function() {
        $('#eventoModal').modal('hide');
        $('#multipleEventsModal').modal('hide');
    };

    window.refreshEventCalendar = function() {
        if (window.eventosCalendarInstance) {
            window.eventosCalendarInstance.refresh();
        }
    };

    // Manejo global de modales para asegurar z-index correcto
    $(document).on('show.bs.modal', '.modal', function() {
        const $modal = $(this);
        
        // Forzar z-index correcto
        setTimeout(() => {
            $modal.css('z-index', '999999');
            $('.modal-backdrop').css('z-index', '999998');
            
            // Asegurar posicionamiento
            $modal.css({
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%'
            });
        }, 100);
    });

    // Limpiar modales al ocultarlos
    $(document).on('hidden.bs.modal', '.modal', function() {
        // Restaurar body scroll
        $('body').removeClass('modal-open').css('padding-right', '');
        
        // Remover modales dinámicos
        if ($(this).attr('id') === 'multipleEventsModal') {
            $(this).remove();
        }
        
        // Limpiar backdrops huérfanos si no hay modales activos
        if ($('.modal.show').length === 0) {
            $('.modal-backdrop').remove();
        }
    });

    // Funciones de accesibilidad
    $(document).keydown(function(e) {
        // Escape para cerrar modales
        if (e.key === 'Escape') {
            $('.modal.show').modal('hide');
        }
        
        // Enter para abrir evento seleccionado
        if (e.key === 'Enter') {
            const $focused = $('.calendar-day:focus');
            if ($focused.length) {
                $focused.trigger('click');
            }
        }
    });

    // Soporte para navegación por teclado
    $(document).on('keydown', '.calendar-day', function(e) {
        const $current = $(this);
        let $target = null;
        
        switch(e.key) {
            case 'ArrowRight':
                $target = $current.next('.calendar-day');
                break;
            case 'ArrowLeft':
                $target = $current.prev('.calendar-day');
                break;
            case 'ArrowDown':
                $target = $current.closest('.calendar-days').find('.calendar-day').eq($current.index() + 7);
                break;
            case 'ArrowUp':
                $target = $current.closest('.calendar-days').find('.calendar-day').eq($current.index() - 7);
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                $current.trigger('click');
                return;
        }
        
        if ($target && $target.length) {
            e.preventDefault();
            $target.focus();
        }
    });

    // Soporte para gestos táctiles (swipe) en móviles
    let touchStartX = 0;
    let touchEndX = 0;
    
    $(document).on('touchstart', '.eventos-calendar-container', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    $(document).on('touchend', '.eventos-calendar-container', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (window.eventosCalendarInstance) {
                if (diff > 0) {
                    // Swipe left - next month
                    window.eventosCalendarInstance.navigateMonth(1);
                } else {
                    // Swipe right - previous month
                    window.eventosCalendarInstance.navigateMonth(-1);
                }
            }
        }
    }

    // Lazy loading para imágenes en modales
    $(document).on('shown.bs.modal', '#eventoModal', function() {
        const $images = $(this).find('img[data-src]');
        $images.each(function() {
            const $img = $(this);
            const src = $img.data('src');
            if (src) {
                $img.attr('src', src).removeAttr('data-src');
            }
        });
    });

    // Performance monitoring (opcional)
    if (window.performance && window.performance.mark) {
        window.performance.mark('eventos-frontend-loaded');
        
        // Marcar cuando se carga el primer calendario
        $(document).one('calendar:loaded', function() {
            window.performance.mark('eventos-calendar-first-load');
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Mover modal al body si existe
        const modal = document.getElementById('eventoModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

})(jQuery);