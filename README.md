# URLCV Meeting & Interview Scheduler

A free, no-login scheduling tool that lets organisers share a booking link with candidates or colleagues, who can then pick a time slot. Both parties receive a calendar invite by email.

## Features

- **No account required** — booking links are self-contained URLs (base64-encoded JSON)
- **Three modes** driven by URL parameters:
  - **Organiser** (no params) — fill in details, add availability slots, generate a shareable link
  - **Edit** (`?edit=BASE64`) — re-open the organiser form pre-filled; organiser receives this link by email
  - **Attendee** (`?book=BASE64`) — pick a slot, enter name & email, confirm booking
- **Timezone-aware** — slots displayed in attendee's local timezone via the `Intl` API
- **Calendar invite (.ics)** — attached to the attendee confirmation email; works with Google Calendar, Outlook, Apple Calendar
- **Video call link** — include a Teams / Zoom / Google Meet link in the invite
- **Configurable duration** — 15, 30, 45, 60, or 90-minute slots
- **Email me my edit link** — organiser can email themselves a personalised link to amend slots later, no login needed

## Booking data format

The `?book=` and `?edit=` parameters are base64-encoded JSON:

```json
{
  "title":     "30-min Interview",
  "organizer": "Jane Smith",
  "email":     "jane@company.com",
  "duration":  30,
  "link":      "https://meet.google.com/abc-xyz",
  "tz":        "Europe/London",
  "slots":     ["2024-01-15T09:00:00.000Z", "2024-01-15T10:30:00.000Z"]
}
```

Slots are stored as UTC ISO-8601 strings. The browser converts local times to UTC on generation, and converts back when displaying to attendees.

## Server endpoints

Registered by `InterviewSchedulerServiceProvider`:

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/tools/interview-scheduler/book` | Validate booking, send attendee + organiser emails |
| `POST` | `/tools/interview-scheduler/email-edit` | Email the organiser their `?edit=` link |

Both endpoints require CSRF (`web` middleware) and are rate-limited.

## Email classes

| Class | Recipient | Contains |
|-------|-----------|---------|
| `AttendeeConfirmation` | Attendee | Booking details + `.ics` calendar invite |
| `OrganizerNotification` | Organiser | Attendee name, email, selected time |
| `OrganizerEditLink` | Organiser | Link to re-open form pre-filled with existing setup |

## Installation

```bash
composer require urlcv/interview-scheduler
```

Then register the tool class:

```php
// config/tools.php
'tools' => [
    \URLCV\InterviewScheduler\Laravel\InterviewSchedulerTool::class,
],
```

Run the sync command:

```bash
php artisan tools:sync
```

## Links

- **Live tool:** https://urlcv.com/tools/interview-scheduler
- **URLCV:** https://urlcv.com — free tools for recruitment agencies
