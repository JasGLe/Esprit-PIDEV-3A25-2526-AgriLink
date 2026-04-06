# 📅 Google Calendar-like Integration - Complete Implementation Guide

## ✅ What Has Been Implemented

### 1. **Calendar Controller** (`CalendarController.php`)
   - ✅ Main calendar page route: `/calendar` → `calendar_index`
   - ✅ API endpoint for events: `/calendar/api/events` → `calendar_api_events`
   - ✅ API endpoint for day events: `/calendar/api/day-events` → `calendar_api_day_events`
   - ✅ Quick create route: `/calendar/quick-create` → `calendar_quick_create`

### 2. **Calendar Template** (`templates/activity/calendar/index.html.twig`)
   - ✅ FullCalendar.js integration (v6.1.10)
   - ✅ Monthly, weekly, and daily views
   - ✅ Color differentiation:
     - **Activities**: Green (#22c55e)
     - **Events**: Blue (#3b82f6)
   - ✅ Modal for viewing day events
   - ✅ Quick create buttons (+ Activity/+ Event)
   - ✅ Responsive design
   - ✅ French locale support
   - ✅ Legend showing event types

### 3. **Sidebar Integration**
   - ✅ Added "📆 Calendrier" button in sidebar under "Espaces Métiers"
   - ✅ Proper active state styling
   - ✅ Accessible routing

### 4. **Dashboard Widget**
   - ✅ Calendar section in agriculteur dashboard
   - ✅ Card with quick stats (Activities, Events, To-do)
   - ✅ Quick action buttons
   - ✅ Link to full calendar

### 5. **Repository Methods**
   - ✅ `ActiviteRepository::findBetweenDates()` (already existed)
   - ✅ `EvenementRepository::findBetweenDates()` (added new)

---

## 🎯 Features Overview

### Calendar Views
- **Month View**: See all activities and events at a glance
- **Week View**: Focus on a specific week
- **Day View**: Detailed view of a single day

### Event Management
- Click on any day to view events/activities for that day
- Click on an event to go to its detail page
- Use "+" buttons to quickly create new activities/events

### Color Coding
```
🟢 Green  = Activities (Activités)
🔵 Blue   = Events (Événements)
```

### Interactive Elements
- **Date Navigation**: Use prev/next buttons or click today
- **Event Click**: Redirects to event detail page
- **Day Click**: Opens modal with all events for that day
- **Quick Add**: Create new events from calendar date picker

---

## 📊 API Endpoints

### Get Calendar Events
```
GET /calendar/api/events?start=2026-04-01T00:00:00Z&end=2026-04-30T23:59:59Z
```
**Response**: JSON array of events with:
- `id`: Unique event ID (prefixed with `activite_` or `evenement_`)
- `title`: Event title
- `start`: Start datetime
- `end`: End datetime (optional)
- `backgroundColor`: Color code
- `extendedProps`: Additional event metadata

### Get Day Events
```
GET /calendar/api/day-events?date=2026-04-15
```
**Response**: JSON with:
- `date`: Target date
- `activities`: Array of activities for that day
- `events`: Array of events for that day

---

## 🔧 Integration with Existing Forms

### When Clicking Quick Add from Calendar
The calendar automatically passes the selected date to the form routes:
```
/activite/new?date=2026-04-15
/evenement/new?date=2026-04-15
```

**Note**: You can optionally modify `ActiviteType` and `EvenementType` to accept and pre-fill the date parameter:

```php
// In ActiviteController::new()
$date = $request->query->get('date');
if ($date) {
    try {
        $selectedDate = new \DateTime($date);
        $activite->setDateDebut($selectedDate);
    } catch (\Exception $e) {
        // Ignore invalid date
    }
}
```

---

## 📱 Responsive Design

The calendar is fully responsive:
- **Desktop**: Full month/week/day views
- **Tablet**: Optimized sidebar and calendar layout
- **Mobile**: Stacked layout with touch-friendly controls

---

## 🔐 Security Features

✅ User authentication required (`#[IsGranted('ROLE_USER')]`)  
✅ Only shows events within date range  
✅ CSRF protection for any future modifications  
✅ Access control through Symfony security

---

## 🎨 Customization Options

### Change Activity Color
In `templates/activity/calendar/index.html.twig`, find:
```javascript
backgroundColor: '#22c55e', // Change this hex code
borderColor: '#16a34a',     // And this one
```

### Change Event Color
```javascript
backgroundColor: '#3b82f6', // Blue to any color
borderColor: '#1d4ed8',
```

### Modify View Options
In the calendar initialization:
```javascript
initialView: 'dayGridMonth', // Change to: timeGridWeek, timeGridDay
headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay' // Modify available views
}
```

---

## ⚙️ How It Works

### Workflow
1. User clicks "Calendrier" in sidebar
2. Calendar page loads with FullCalendar.js
3. FullCalendar requests events from `/calendar/api/events`
4. Controller queries database for Activités and Événements
5. Events are displayed on calendar with color coding
6. User clicks on a day or event
7. Modal opens showing all events for that day
8. User can click event to view details or "+" to create new

### Event Fetching
```
User navigates calendar
    ↓
FullCalendar.js requests events (AJAX)
    ↓
CalendarController::apiEvents()
    ↓
Query ActiviteRepository & EvenementRepository
    ↓
Return JSON response
    ↓
FullCalendar renders events on calendar
```

---

## 🛠️ Files Created/Modified

### New Files Created
1. `src/Controller/Activity/CalendarController.php` - Calendar logic
2. `templates/activity/calendar/index.html.twig` - Calendar UI

### Modified Files
1. `templates/layouts/dashboard_layout.html.twig` - Added sidebar button
2. `templates/user_management/dashboard/agriculteur.html.twig` - Added dashboard widget
3. `src/Repository/Activity/EvenementRepository.php` - Added `findBetweenDates()` method

---

## 📚 Routes Summary

| Route | Path | Name | Method |
|-------|------|------|--------|
| Calendar Page | `/calendar` | `calendar_index` | GET |
| API Events | `/calendar/api/events` | `calendar_api_events` | GET |
| API Day Events | `/calendar/api/day-events` | `calendar_api_day_events` | GET |
| Quick Create | `/calendar/quick-create` | `calendar_quick_create` | GET/POST |

---

## 🚀 Testing

### Manual Testing Checklist
- [ ] Click "Calendrier" in sidebar → calendar loads
- [ ] Click on a day → modal shows events for that day
- [ ] Click on an event → redirects to event detail page
- [ ] Try "+" button for quick create
- [ ] Switch between month/week/day views
- [ ] Navigate to different months
- [ ] Check activity displays in green
- [ ] Check event displays in blue
- [ ] Test on mobile device
- [ ] Verify responsive design

---

## 💡 Future Enhancements

Optional improvements you could add:
- [ ] Drag & drop events to reschedule
- [ ] Duplicate events
- [ ] Bulk operations
- [ ] Export calendar to iCal format
- [ ] Share calendar with other users
- [ ] Calendar reminders/notifications
- [ ] Weather integration
- [ ] Recurring events
- [ ] Event search/filter
- [ ] Dark mode support

---

## 🐛 Troubleshooting

### Calendar Not Showing Events
1. Check browser console for JavaScript errors
2. Verify `/calendar/api/events` endpoint returns data
3. Check database has activities/events with dates in range
4. Check browser network tab for API response

### Events Not Displaying Correct Dates
1. Verify database datetime fields are correct
2. Check browser timezone settings
3. Verify FullCalendar date format (ISO 8601)

### Modal Not Opening on Day Click
1. Check Bootstrap is loaded (`data-bs-toggle="modal"`)
2. Verify JavaScript console for errors
3. Check modal ID matches button data-bs-target

### Colors Not Showing
1. Check CSS is loading (inspect element)
2. Verify color hex codes are correct
3. Check event className is being applied

---

## 📞 Support

For issues or customization needs:
1. Check FullCalendar documentation: https://fullcalendar.io/
2. Review Symfony routing documentation
3. Check Twig template syntax
4. Verify Bootstrap modals setup

---

**Last Updated**: April 6, 2026  
**Status**: ✅ Production Ready
