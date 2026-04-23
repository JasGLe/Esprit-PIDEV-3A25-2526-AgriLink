/**
 * CalendarPro - FullCalendar configuration with a clean, modern UI.
 * Focus: layout quality, readability and responsive behavior.
 */
if (typeof window.CalendarPro === 'undefined') {
    window.CalendarPro = class CalendarPro {
    constructor(config = {}) {
        this.config = {
            calendarElementId: 'calendar',
            dayEventsModal: '#dayEventsModal',
            dayEventsModalLabel: '#dayEventsModalLabel',
            dayEventsDate: '#dayEventsDate',
            dayEventsContent: '#dayEventsContent',
            quickAddModal: '#quickAddModal',
            quickAddSelection: '#quickAddSelection',
            quickAddEventLink: '#quickAddEventLink',
            quickAddActivityLink: '#quickAddActivityLink',
            apiEventsUrl: '/calendar/api/events',
            apiDayEventsUrl: '/calendar/api/day-events',
            activiteNewUrl: '/activite/new',
            evenementNewUrl: '/evenement/new',
            currentUrl: window.location.pathname + window.location.search,
            ...config
        };

        this.calendar = null;
        this.dayModal = null;
        this.quickAddModal = null;
    }

    init() {
        const calendarEl = document.getElementById(this.config.calendarElementId);
        if (!calendarEl) {
            console.error(`Calendar element with ID "${this.config.calendarElementId}" not found`);
            return;
        }

        if (typeof bootstrap !== 'undefined') {
            const dayModalElement = document.querySelector(this.config.dayEventsModal);
            const quickAddModalElement = document.querySelector(this.config.quickAddModal);

            if (dayModalElement) {
                this.dayModal = new bootstrap.Modal(dayModalElement);
            }

            if (quickAddModalElement) {
                this.quickAddModal = new bootstrap.Modal(quickAddModalElement);
            }
        }

        if (calendarEl.dataset.currentUrl) {
            this.config.currentUrl = calendarEl.dataset.currentUrl;
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
                    slotMaxTime: '22:00:00',
                    slotDuration: '01:00:00',
                    slotLabelInterval: '01:00:00'
                },
                timeGridDay: {
                    allDaySlot: true,
                    slotMinTime: '06:00:00',
                    slotMaxTime: '22:00:00',
                    slotDuration: '01:00:00',
                    slotLabelInterval: '01:00:00'
                }
            },
            events: this.fetchEvents.bind(this),
            eventContent: this.renderEventContent.bind(this),
            eventClick: this.handleEventClick.bind(this),
            dateClick: this.handleDateClick.bind(this),
            datesSet: this.handleDatesSet.bind(this),
            dayCellDidMount: this.handleDayCellMount.bind(this),
            dayHeaderDidMount: this.handleDayHeaderMount.bind(this),
            slotLaneDidMount: this.handleSlotLaneMount.bind(this)
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
        const activeView = this.getViewType(info);
        if (activeView === 'dayGridMonth' && !info.isOther && info.el) {
            this.injectGoToDayButton(info.el, info.date);
            return;
        }

        if (activeView !== 'timeGridDay') {
            return;
        }
    }

    handleDayHeaderMount(info) {
        if (!info.el || !info.date) {
            return;
        }

        const activeView = this.getViewType(info);
        if (activeView !== 'timeGridDay') {
            return;
        }
    }

    handleSlotLaneMount(info) {
        if (!info.el || !info.date) {
            return;
        }

        const activeView = this.getViewType(info);
        if (activeView !== 'timeGridDay') {
            return;
        }

        this.injectAddButton(info.el, info.date, 'slot');
    }

    injectAddButton(container, dateValue, mode) {
        if (!container || container.querySelector(`.fc-add-entry-btn-${mode}`)) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `fc-add-entry-btn fc-add-entry-btn-${mode}`;
        button.setAttribute('aria-label', 'Ajouter une entree');
        button.innerHTML = '+';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.openQuickAddModal(dateValue);
        });

        container.appendChild(button);
    }

    handleEventClick(info) {
        const url = info.event.extendedProps?.url;
        if (url) {
            window.location.href = url;
        }
    }

    handleDateClick(info) {
        const activeView = this.getViewType(info);
        if (activeView === 'timeGridDay') {
            this.openQuickAddModal(info.date);
            return;
        }

        this.showDayEventsModal(info.dateStr);
    }

    handleDatesSet(info) {
        const activeView = this.getViewType(info);
        this.removeAllAddButtons();
        this.removeAllGoToDayButtons();

        if (activeView === 'dayGridMonth') {
            window.requestAnimationFrame(() => {
                this.ensureMonthViewGoToDayButtons();
            });
            return;
        }

        if (activeView !== 'timeGridDay') {
            return;
        }

        window.requestAnimationFrame(() => {
            this.ensureDayViewAddButtons();
        });
    }

    injectGoToDayButton(container, dateValue) {
        if (!container || container.querySelector('.fc-go-day-btn')) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'fc-go-day-btn';
        button.setAttribute('aria-label', 'Voir en vue jour');
        button.textContent = '+';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (!this.calendar) {
                return;
            }
            this.calendar.changeView('timeGridDay', dateValue);
        });

        container.appendChild(button);
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

        if (this.dayModal) {
            this.dayModal.show();
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

    openQuickAddModal(dateValue) {
        const payload = this.buildPrefillPayload(dateValue);

        const selectionEl = document.querySelector(this.config.quickAddSelection);
        const eventLinkEl = document.querySelector(this.config.quickAddEventLink);
        const activityLinkEl = document.querySelector(this.config.quickAddActivityLink);

        if (!selectionEl || !eventLinkEl || !activityLinkEl) {
            return;
        }

        selectionEl.textContent = payload.label;
        eventLinkEl.href = this.buildCreationUrl(this.config.evenementNewUrl, payload);
        activityLinkEl.href = this.buildCreationUrl(this.config.activiteNewUrl, payload);

        if (this.quickAddModal) {
            this.quickAddModal.show();
        }
    }

    buildPrefillPayload(dateValue) {
        const dateObj = dateValue instanceof Date ? dateValue : new Date(dateValue);

        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        const hours = String(dateObj.getHours()).padStart(2, '0');
        const minutes = String(dateObj.getMinutes()).padStart(2, '0');

        const date = `${year}-${month}-${day}`;
        const time = `${hours}:${minutes}`;
        const hasTime = !(hours === '00' && minutes === '00');

        const humanDate = dateObj.toLocaleDateString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        return {
            date,
            time: hasTime ? time : '',
            start: hasTime ? `${date}T${time}:00` : `${date}T00:00:00`,
            label: hasTime ? `${humanDate} a ${time}` : humanDate
        };
    }

    buildCreationUrl(baseUrl, payload) {
        const params = new URLSearchParams();
        params.set('date', payload.date);
        params.set('start', payload.start);
        params.set('redirect', this.config.currentUrl);

        if (payload.time) {
            params.set('time', payload.time);
            params.set('datetime', `${payload.date} ${payload.time}`);
        }

        return `${baseUrl}?${params.toString()}`;
    }

    getViewType(info) {
        return info?.view?.type || (this.calendar ? this.calendar.view.type : '');
    }

    removeAllAddButtons() {
        if (!this.calendar || !this.calendar.el) {
            return;
        }

        this.calendar.el.querySelectorAll('.fc-add-entry-btn').forEach((button) => {
            button.remove();
        });
    }

    removeAllGoToDayButtons() {
        if (!this.calendar || !this.calendar.el) {
            return;
        }

        this.calendar.el.querySelectorAll('.fc-go-day-btn').forEach((button) => {
            button.remove();
        });
    }

    ensureDayViewAddButtons() {
        if (!this.calendar || !this.calendar.el || this.calendar.view.type !== 'timeGridDay') {
            return;
        }

        const viewDate = new Date(this.calendar.view.currentStart);
        viewDate.setHours(0, 0, 0, 0);

        this.calendar.el.querySelectorAll('.fc-timegrid-slots tr[data-time]').forEach((row) => {
            const lane = row.querySelector('.fc-timegrid-slot-lane');
            if (!lane) {
                return;
            }

            const time = (row.getAttribute('data-time') || '00:00:00').split(':');
            const slotDate = new Date(viewDate);
            slotDate.setHours(
                Number.parseInt(time[0] || '0', 10),
                Number.parseInt(time[1] || '0', 10),
                0,
                0
            );

            this.injectAddButton(lane, slotDate, 'slot');
        });
    }

    ensureMonthViewGoToDayButtons() {
        if (!this.calendar || !this.calendar.el || this.calendar.view.type !== 'dayGridMonth') {
            return;
        }

        this.calendar.el.querySelectorAll('.fc-daygrid-day[data-date]').forEach((dayCell) => {
            const dateRaw = dayCell.getAttribute('data-date');
            if (!dateRaw) {
                return;
            }

            const dayFrame = dayCell.querySelector('.fc-daygrid-day-frame');
            if (!dayFrame) {
                return;
            }

            const date = new Date(`${dateRaw}T00:00:00`);
            if (Number.isNaN(date.getTime())) {
                return;
            }

            this.injectGoToDayButton(dayFrame, date);
        });
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
}

function initializeCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) {
        return;
    }

    if (calendarEl.dataset.calendarProInitialized === 'true') {
        return;
    }

    if (typeof FullCalendar === 'undefined') {
        console.error('FullCalendar is not loaded.');
        return;
    }

    try {
        const CalendarProClass = window.CalendarPro;
        if (typeof CalendarProClass === 'undefined') {
            console.error('CalendarPro is not loaded.');
            return;
        }

        const calendar = new CalendarProClass();
        calendar.init();
        calendarEl.dataset.calendarProInitialized = 'true';
    } catch (error) {
        console.error('Error initializing calendar:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCalendar);
} else {
    initializeCalendar();
}

document.addEventListener('turbo:load', initializeCalendar);

if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.CalendarPro;
}
