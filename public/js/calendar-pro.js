/**
 * CalendarPro - FullCalendar configuration with a clean, modern UI.
 * Focus: layout quality, readability and responsive behavior.
 */
class CalendarPro {
    constructor(config = {}) {
        this.config = {
            calendarElementId: 'calendar',
            dayEventsModal: '#dayEventsModal',
            dayEventsModalLabel: '#dayEventsModalLabel',
            dayEventsDate: '#dayEventsDate',
            dayEventsContent: '#dayEventsContent',
            apiEventsUrl: '/calendar/api/events',
            apiDayEventsUrl: '/calendar/api/day-events',
            ...config
        };

        this.calendar = null;
        this.modal = null;
        this.selectedDate = null;
    }

    init() {
        const calendarEl = document.getElementById(this.config.calendarElementId);
        if (!calendarEl) {
            console.error(`Calendar element with ID "${this.config.calendarElementId}" not found`);
            return;
        }

        const modalElement = document.querySelector(this.config.dayEventsModal);
        if (typeof bootstrap !== 'undefined' && modalElement) {
            this.modal = new bootstrap.Modal(modalElement);
        }

        this.calendar = new FullCalendar.Calendar(calendarEl, this.getCalendarOptions());
        this.calendar.render();
    }

    getCalendarOptions() {
        return {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Aujourd\'hui',
                month: 'Mois',
                week: 'Semaine',
                day: 'Jour'
            },
            locale: 'fr',
            firstDay: 1,
            navLinks: true,
            editable: false,
            selectable: true,
            dayMaxEvents: true,
            expandRows: true,
            stickyHeaderDates: true,
            height: 'auto',
            contentHeight: 'auto',
            nowIndicator: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: false
            },
            views: {
                dayGridMonth: {
                    dayMaxEventRows: 4
                },
                timeGridWeek: {
                    allDaySlot: true,
                    slotMinTime: '06:00:00',
                    slotMaxTime: '22:00:00'
                },
                timeGridDay: {
                    allDaySlot: true,
                    slotMinTime: '06:00:00',
                    slotMaxTime: '22:00:00'
                }
            },
            events: this.fetchEvents.bind(this),
            eventContent: this.renderEventContent.bind(this),
            eventClick: this.handleEventClick.bind(this),
            dateClick: this.handleDateClick.bind(this),
            dayCellDidMount: this.handleDayCellMount.bind(this)
        };
    }

    fetchEvents(info, successCallback, failureCallback) {
        fetch(`${this.config.apiEventsUrl}?start=${info.start.toISOString()}&end=${info.end.toISOString()}`)
            .then((response) => response.json())
            .then((data) => {
                const uniqueEvents = {};
                data.forEach((event) => {
                    if (!uniqueEvents[event.id]) {
                        uniqueEvents[event.id] = event;
                    }
                });

                const events = Object.values(uniqueEvents).map((event) => ({
                    ...event,
                    classNames: [event.extendedProps?.type ? `${event.extendedProps.type}-event` : 'default-event']
                }));

                successCallback(events);
            })
            .catch((error) => {
                console.error('Error fetching events:', error);
                failureCallback(error);
            });
    }

    renderEventContent(info) {
        const wrapper = document.createElement('div');
        wrapper.className = 'fc-event-custom-content';

        const eventTime = info.timeText ? `<span class="fc-event-time-chip">${this.escapeHtml(info.timeText)}</span>` : '';

        wrapper.innerHTML = `
            <div class="fc-event-inner">
                ${eventTime}
                <span class="fc-event-title">${this.escapeHtml(info.event.title)}</span>
            </div>
        `;

        return { domNodes: [wrapper] };
    }

    handleDayCellMount(info) {
        if (!info.isOther) {
            info.el.classList.add('fc-day-current-month');
        }
    }

    handleEventClick(info) {
        const event = info.event;
        const url = event.extendedProps?.url;
        if (url) {
            window.location.href = url;
        }
    }

    handleDateClick(info) {
        this.selectedDate = info.dateStr;
        this.showDayEventsModal(info.dateStr);
    }

    showDayEventsModal(dateStr) {
        const dateLabel = document.querySelector(this.config.dayEventsModalLabel);
        const dateSubtitle = document.querySelector(this.config.dayEventsDate);
        const contentContainer = document.querySelector(this.config.dayEventsContent);

        if (!dateLabel || !dateSubtitle || !contentContainer) {
            return;
        }

        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        dateLabel.textContent = `Agenda du ${formattedDate}`;
        dateSubtitle.textContent = dateStr;

        contentContainer.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;

        fetch(`${this.config.apiDayEventsUrl}?date=${dateStr}`)
            .then((response) => response.json())
            .then((data) => {
                this.renderDayEventsContent(data, contentContainer);
            })
            .catch((error) => {
                console.error('Error fetching day events:', error);
                contentContainer.innerHTML = `
                    <div class="alert alert-danger mb-0" role="alert">
                        Erreur lors du chargement des evenements.
                    </div>
                `;
            });

        if (this.modal) {
            this.modal.show();
        }
    }

    renderDayEventsContent(data, container) {
        let html = '';

        if (data.activities && data.activities.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="fw-semibold text-success mb-2">Activites</h6>';
            data.activities.forEach((activity) => {
                html += this.renderActivityCard(activity);
            });
            html += '</div>';
        }

        if (data.events && data.events.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="fw-semibold text-primary mb-2">Evenements</h6>';
            data.events.forEach((event) => {
                html += this.renderEventCard(event);
            });
            html += '</div>';
        }

        if ((!data.activities || data.activities.length === 0) && (!data.events || data.events.length === 0)) {
            html = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-calendar2-x"></i></div>
                    <h6 class="fw-semibold mb-1">Aucune tache planifiee</h6>
                    <p class="text-muted mb-0">Aucune activite ou evenement pour cette date.</p>
                </div>
            `;
        }

        container.innerHTML = html;
    }

    renderActivityCard(activity) {
        const timeStr = activity.start ? (activity.end ? `${activity.start} - ${activity.end}` : activity.start) : '';

        return `
            <div class="event-card">
                <div class="event-badge activite">A</div>
                <div class="event-content">
                    <div class="event-title">${this.escapeHtml(activity.title)}</div>
                    <div class="event-meta">
                        <span class="badge rounded-pill text-bg-success">${this.escapeHtml(activity.type_activite || 'Activite')}</span>
                        ${timeStr ? `<span>${this.escapeHtml(timeStr)}</span>` : ''}
                    </div>
                    ${activity.status ? `<div class="event-meta">Statut: <strong>${this.escapeHtml(activity.status)}</strong></div>` : ''}
                    ${activity.cost ? `<div class="event-meta">Cout: <strong>${this.escapeHtml(activity.cost)} DT</strong></div>` : ''}
                    <div class="event-actions">
                        <a href="${activity.url}" class="btn btn-sm btn-outline-success">Voir</a>
                    </div>
                </div>
            </div>
        `;
    }

    renderEventCard(event) {
        return `
            <div class="event-card">
                <div class="event-badge evenement">E</div>
                <div class="event-content">
                    <div class="event-title">${this.escapeHtml(event.title)}</div>
                    <div class="event-meta">
                        <span class="badge rounded-pill text-bg-primary">${this.escapeHtml(event.type_evenement || 'Evenement')}</span>
                        ${event.time ? `<span>${this.escapeHtml(event.time)}</span>` : ''}
                    </div>
                    ${event.location ? `<div class="event-meta">Lieu: ${this.escapeHtml(event.location)}</div>` : ''}
                    ${event.description ? `<div class="event-meta small">${this.escapeHtml(event.description)}</div>` : ''}
                    <div class="event-actions">
                        <a href="${event.url}" class="btn btn-sm btn-outline-primary">Voir</a>
                    </div>
                </div>
            </div>
        `;
    }

    escapeHtml(text) {
        if (!text) {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    destroy() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }
}

function initializeCalendar() {
    if (typeof FullCalendar === 'undefined') {
        console.error('FullCalendar is not loaded.');
        return;
    }

    try {
        const calendar = new CalendarPro();
        calendar.init();
    } catch (error) {
        console.error('Error initializing calendar:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCalendar);
} else {
    initializeCalendar();
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = CalendarPro;
}
