/**
 * CALENDAR PRO - Advanced FullCalendar JavaScript Module
 * 
 * Features:
 * - Custom day cell content with "+" button
 * - Professional event rendering
 * - Click handlers for events and buttons
 * - Responsive design
 * - Accessibility support
 */

class CalendarPro {
    constructor(config = {}) {
        this.config = {
            calendarElementId: 'calendar',
            dayEventsModal: '#dayEventsModal',
            dayEventsModalLabel: '#dayEventsModalLabel',
            dayEventsDate: '#dayEventsDate',
            dayEventsContent: '#dayEventsContent',
            quickAddButtons: '#quickAddButtons',
            apiEventsUrl: '/calendar/api/events',
            apiDayEventsUrl: '/calendar/api/day-events',
            activiteNewUrl: '/activite/new',
            evenementNewUrl: '/evenement/new',
            activiteShowUrl: '/activite/{id}',
            evenementShowUrl: '/evenement/{id}',
            ...config
        };

        this.calendar = null;
        this.modal = null;
        this.selectedDate = null;
    }

    /**
     * Initialize the calendar
     */
    init() {
        const calendarEl = document.getElementById(this.config.calendarElementId);
        if (!calendarEl) {
            console.error(`Calendar element with ID "${this.config.calendarElementId}" not found`);
            return;
        }

        this.modal = new bootstrap.Modal(document.querySelector(this.config.dayEventsModal));
        this.calendar = new FullCalendar.Calendar(calendarEl, this.getCalendarOptions());
        this.calendar.render();
    }

    /**
     * Get FullCalendar configuration options
     */
    getCalendarOptions() {
        return {
            initialView: 'dayGridMonth',
            initialDate: new Date(),
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            editable: false,
            selectable: true,
            locale: 'fr',
            height: 'auto',
            contentHeight: 'auto',

            // Custom event rendering
            eventContent: this.renderEventContent.bind(this),

            // Custom day cell content
            dayCellContent: this.renderDayCellContent.bind(this),

            // Fetch events from API
            events: this.fetchEvents.bind(this),

            // Event click handler
            eventClick: this.handleEventClick.bind(this),

            // Date click handler
            dateClick: this.handleDateClick.bind(this),

            // Day render hook
            dayCellDidMount: this.onDayCellDidMount.bind(this),
        };
    }

    /**
     * Render custom event content
     */
    renderEventContent(info) {
        const event = info.event;
        const el = document.createElement('div');
        el.className = 'fc-event-custom-content';
        
        const title = event.title;
        const time = event.start ? event.start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        
        el.innerHTML = `
            <div class="fc-event-inner">
                ${time ? `<div class="fc-event-time">${time}</div>` : ''}
                <div class="fc-event-title">${this.escapeHtml(title)}</div>
            </div>
        `;

        return { domNodes: [el] };
    }

    /**
     * Render custom day cell content
     */
    renderDayCellContent(info) {
        // This is handled by dayCellDidMount for more control
        return undefined;
    }

    /**
     * Day cell did mount - add plus button
     */
    onDayCellDidMount(info) {
        const dateStr = info.dateStr;
        const cellElement = info.el;

        // Only show plus button on current month days
        if (info.isOtherMonth) {
            return;
        }

        // Create plus button
        const plusButton = document.createElement('button');
        plusButton.className = 'fc-day-plus-button';
        plusButton.type = 'button';
        plusButton.innerHTML = '<span>+</span>';
        plusButton.setAttribute('aria-label', `Ajouter une activité le ${info.dateStr}`);
        plusButton.setAttribute('title', `Ajouter une activité le ${info.dateStr}`);

        // Add click handler
        plusButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.openQuickAddMenu(dateStr);
        });

        cellElement.appendChild(plusButton);
    }

    /**
     * Fetch events from API - IMPORTANT: Only display start date
     */
    fetchEvents(info, successCallback, failureCallback) {
        fetch(`${this.config.apiEventsUrl}?start=${info.start.toISOString()}&end=${info.end.toISOString()}`)
            .then(response => response.json())
            .then(data => {
                // CONSTRAINT: Only show events on their start date
                // Filter to ensure each event appears only once at start date
                const uniqueEvents = {};
                data.forEach(event => {
                    const key = event.id;
                    if (!uniqueEvents[key]) {
                        uniqueEvents[key] = event;
                    }
                });

                const events = Object.values(uniqueEvents).map(event => ({
                    ...event,
                    classNames: [event.extendedProps.type + '-event']
                }));

                successCallback(events);
            })
            .catch(error => {
                console.error('Error fetching events:', error);
                failureCallback(error);
            });
    }

    /**
     * Handle event click - navigate to details page
     */
    handleEventClick(info) {
        const event = info.event;
        const type = event.extendedProps.type;
        const id = event.id.replace(type + '_', '');
        const url = event.extendedProps.url;

        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Handle date click - open day events modal
     */
    handleDateClick(info) {
        this.selectedDate = info.dateStr;
        this.showDayEventsModal(info.dateStr);
    }

    /**
     * Open quick add menu
     */
    openQuickAddMenu(dateStr) {
        // Option 1: Direct modal (inline)
        // this.showQuickAddModal(dateStr);

        // Option 2: Redirect to form (current implementation)
        this.redirectToForm(dateStr);
    }

    /**
     * Show day events modal
     */
    showDayEventsModal(dateStr) {
        const dateLabel = document.querySelector(this.config.dayEventsModalLabel);
        const dateSubtitle = document.querySelector(this.config.dayEventsDate);
        const contentContainer = document.querySelector(this.config.dayEventsContent);
        const quickAddButtonsContainer = document.querySelector(this.config.quickAddButtons);

        // Update modal title
        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        dateLabel.textContent = `Événements du ${formattedDate}`;
        dateSubtitle.textContent = dateStr;

        // Show loading state
        contentContainer.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;

        // Fetch day events
        fetch(`${this.config.apiDayEventsUrl}?date=${dateStr}`)
            .then(response => response.json())
            .then(data => {
                this.renderDayEventsContent(data, contentContainer);
                this.renderQuickAddButtons(dateStr, quickAddButtonsContainer);
            })
            .catch(error => {
                console.error('Error fetching day events:', error);
                contentContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Erreur lors du chargement des événements.
                    </div>
                `;
            });

        // Show modal
        this.modal.show();
    }

    /**
     * Render day events content
     */
    renderDayEventsContent(data, container) {
        let html = '';

        // Activities section
        if (data.activities && data.activities.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="fw-bold text-success mb-2"><i class="bi bi-lightning-fill"></i> Activités</h6>';
            data.activities.forEach(activity => {
                html += this.renderActivityCard(activity);
            });
            html += '</div>';
        }

        // Events section
        if (data.events && data.events.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar-event"></i> Événements</h6>';
            data.events.forEach(event => {
                html += this.renderEventCard(event);
            });
            html += '</div>';
        }

        // Empty state
        if ((!data.activities || data.activities.length === 0) && (!data.events || data.events.length === 0)) {
            html = `
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h6 class="fw-semibold mb-1">Aucune tâche programmée</h6>
                    <p class="text-muted mb-3">Aucune activité ou événement pour cette date</p>
                </div>
            `;
        }

        container.innerHTML = html;
    }

    /**
     * Render activity card
     */
    renderActivityCard(activity) {
        const timeStr = activity.start ? 
            (activity.end ? `${activity.start} - ${activity.end}` : activity.start) 
            : '';

        return `
            <div class="event-card">
                <div class="event-badge activite">🌾</div>
                <div class="event-content">
                    <div class="event-title">${this.escapeHtml(activity.title)}</div>
                    <div class="event-meta">
                        <span class="badge bg-success-subtle text-success">${this.escapeHtml(activity.type_activite || 'Activité')}</span>
                        ${timeStr ? `<span class="ms-2">${this.escapeHtml(timeStr)}</span>` : ''}
                    </div>
                    ${activity.status ? `<div class="event-meta">Statut: <strong>${this.escapeHtml(activity.status)}</strong></div>` : ''}
                    ${activity.cost ? `<div class="event-meta">Coût: <strong>${this.escapeHtml(activity.cost)} DT</strong></div>` : ''}
                    <div class="event-actions">
                        <a href="${activity.url}" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i> Voir
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render event card
     */
    renderEventCard(event) {
        return `
            <div class="event-card">
                <div class="event-badge evenement">📅</div>
                <div class="event-content">
                    <div class="event-title">${this.escapeHtml(event.title)}</div>
                    <div class="event-meta">
                        <span class="badge bg-primary-subtle text-primary">${this.escapeHtml(event.type_evenement || 'Événement')}</span>
                        ${event.time ? `<span class="ms-2">${this.escapeHtml(event.time)}</span>` : ''}
                    </div>
                    ${event.location ? `<div class="event-meta"><i class="bi bi-geo-alt"></i> ${this.escapeHtml(event.location)}</div>` : ''}
                    ${event.description ? `<div class="event-meta small">${this.escapeHtml(event.description)}</div>` : ''}
                    <div class="event-actions">
                        <a href="${event.url}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Voir
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render quick add buttons
     */
    renderQuickAddButtons(dateStr, container) {
        container.innerHTML = `
            <a href="${this.config.activiteNewUrl}?date=${dateStr}" class="btn btn-sm btn-success">
                <i class="bi bi-plus-circle"></i> Activité
            </a>
            <a href="${this.config.evenementNewUrl}?date=${dateStr}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle"></i> Événement
            </a>
        `;
    }

    /**
     * Redirect to form (for + button click)
     */
    redirectToForm(dateStr) {
        // Open a small menu or directly redirect
        // For now, redirect to quick add menu
        const menu = document.createElement('div');
        menu.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            padding: 16px;
            min-width: 250px;
        `;

        menu.innerHTML = `
            <div style="margin-bottom: 12px; font-weight: 600; color: #1f2937;">Ajouter une activité</div>
            <a href="${this.config.activiteNewUrl}?date=${dateStr}" style="
                display: block;
                padding: 12px;
                background: #22c55e;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                margin-bottom: 8px;
                text-align: center;
                font-weight: 500;
            ">➕ Nouvelle Activité</a>
            <a href="${this.config.evenementNewUrl}?date=${dateStr}" style="
                display: block;
                padding: 12px;
                background: #3b82f6;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                text-align: center;
                font-weight: 500;
            ">➕ Nouvel Événement</a>
        `;

        document.body.appendChild(menu);

        // Close menu on click outside
        setTimeout(() => {
            document.addEventListener('click', (e) => {
                if (!menu.contains(e.target) && e.target.className !== 'fc-day-plus-button') {
                    menu.remove();
                }
            }, { once: true });
        }, 100);
    }

    /**
     * Escape HTML special characters
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Destroy calendar
     */
    destroy() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }
}

// ============================================================================
// AUTO-INITIALIZATION
// ============================================================================

// Wait for ALL dependencies to be loaded
function waitForFullCalendar() {
    // Check if all required libraries are loaded
    if (typeof FullCalendar === 'undefined') {
        console.log('⏳ Waiting for FullCalendar...');
        setTimeout(waitForFullCalendar, 100);
        return;
    }
    
    if (typeof bootstrap === 'undefined') {
        console.log('⏳ Waiting for Bootstrap...');
        setTimeout(waitForFullCalendar, 100);
        return;
    }

    console.log('✅ FullCalendar and Bootstrap loaded, initializing calendar...');
    
    // Ensure DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCalendar);
    } else {
        initializeCalendar();
    }
}

function initializeCalendar() {
    try {
        console.log('✅ Initializing CalendarPro...');
        const calendar = new CalendarPro();
        calendar.init();
        console.log('✅ Calendar initialized successfully!');
    } catch (error) {
        console.error('❌ Error initializing calendar:', error);
    }
}

// Start initialization immediately
waitForFullCalendar();

// Export for use in other modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CalendarPro;
}
