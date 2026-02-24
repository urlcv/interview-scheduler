# URLCV Meeting & Interview Scheduler

A free, no-login scheduling tool that lets organisers share a booking link with candidates or colleagues, who can then pick a time slot. Both parties receive a calendar invite by email.

## Features

- **No account required** — booking links are short URLs; slots are stored server-side so you can edit without changing the link
- **Three modes** driven by URL parameters:
  - **Organiser** (no params) — fill in details, add availability slots, generate a shareable link
  - **Edit** (`?edit=TOKEN`) — re-open the organiser form pre-filled; organiser receives this link by email
  - **Attendee** (`?book=ID`) — pick a slot, enter name & email, confirm booking
- **Timezone-aware** — slots displayed in attendee's local timezone via the `Intl` API
- **Calendar invite (.ics)** — attached to the attendee confirmation email; works with Google Calendar, Outlook, Apple Calendar
- **Video call link** — include a Teams / Zoom / Google Meet link in the invite
- **Configurable duration** — 15, 30, 45, 60, or 90-minute slots
- **Email me my edit link** — organiser can email themselves a personalised link to amend slots later; the booking link stays the same and updates automatically

## Data storage & API

Schedules are stored in the database. New links use short IDs (`book_id` for the booking URL, `edit_token` for the organiser edit link). Legacy base64 `?book=` and `?edit=` URLs are still supported.

**Table:** `interview_schedules` (in main app migration)

| Column      | Purpose                                            |
|-------------|----------------------------------------------------|
| `book_id`   | Short shareable ID in `?book=` (e.g. 12-char)      |
| `edit_token`| Secret token for `?edit=` (organiser only)         |
| `title`, `organizer`, `email`, `duration`, `video_link`, `tz`, `slots` | Same payload shape as before |

**Endpoints:**

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/tools/interview-scheduler/create` | Create schedule, return `book_id`, `edit_token` |
| `POST` | `/tools/interview-scheduler/update` | Update schedule by `edit_token` |
| `GET`  | `/tools/interview-scheduler/schedule/{book_id}` | Fetch schedule for attendee |
| `GET`  | `/tools/interview-scheduler/schedule/edit/{edit_token}` | Fetch schedule for organiser edit |
| `POST` | `/tools/interview-scheduler/book` | Validate booking, send attendee + organiser emails (accepts `book_id` or legacy `booking_data`) |
| `POST` | `/tools/interview-scheduler/email-edit` | Email the organiser their edit link (accepts `edit_token` or legacy `booking_data`) |

Slots are UTC ISO-8601 strings. The browser converts local times to UTC on generation, and converts back when displaying to attendees.

## Email classes

| Class | Recipient | Contains |
|-------|-----------|----------|
| `AttendeeConfirmation` | Attendee | Booking details + `.ics` calendar invite |
| `OrganizerNotification` | Organiser | Attendee name, email, selected time |
| `OrganizerEditLink` | Organiser | Link to re-open form pre-filled with existing setup |

## Installation

```bash
composer require urlcv/interview-scheduler
```

Ensure the main app has run the migration that creates `interview_schedules` (see the plan or main app migrations).

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
