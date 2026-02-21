<?php

declare(strict_types=1);

namespace URLCV\InterviewScheduler\Laravel;

use App\Tools\Contracts\ToolInterface;

/**
 * Meeting & Interview Scheduler tool adapter.
 *
 * Organiser creates a shareable booking link (frontend, no server needed).
 * Attendee picks a slot and submits their details; confirmation emails are
 * sent to both parties via the custom /tools/interview-scheduler/book route.
 */
class InterviewSchedulerTool implements ToolInterface
{
    public function slug(): string
    {
        return 'interview-scheduler';
    }

    public function name(): string
    {
        return 'Meeting & Interview Scheduler';
    }

    public function summary(): string
    {
        return 'Create a shareable booking link with your available time slots — attendees pick a time and both parties get a calendar invite by email.';
    }

    public function descriptionMd(): ?string
    {
        return <<<'MD'
## Free Meeting & Interview Scheduler

Stop the back-and-forth email chains. Create a personalised scheduling link in seconds, share it with candidates or colleagues, and let them pick a time that works.

### How it works

1. **Set your availability** — add the date/time slots you're free, choose a meeting duration (15 – 90 minutes), and optionally attach a video call link (Teams, Zoom, Google Meet, etc.).
2. **Generate your booking link** — click one button to create a shareable URL you can paste into any email or message.
3. **Attendee picks a slot** — they visit the link, see your available times in their own timezone, enter their name and email, and confirm.
4. **Both parties receive a calendar invite** — a confirmation email with an `.ics` calendar file is sent automatically to the attendee, and a notification is sent to you.

### Key features

- **No account required** — works for anyone, completely free
- **Timezone-aware display** — slots are shown in the attendee's local time
- **Calendar invite (.ics)** — one-click "Add to Calendar" for Google Calendar, Outlook, Apple Calendar
- **Video call link** — include a Teams / Zoom / Google Meet link and it appears in the invite
- **Configurable duration** — 15, 30, 45, 60, or 90-minute slots
- **Fully private** — booking data lives in the link; nothing is stored on URLCV servers

### Use cases for recruiters

- Schedule first-stage phone screens and send automatic calendar invites
- Coordinate panel interviews across multiple time zones
- Share availability with hiring managers without calendar integrations
- Send booking links to passive candidates via LinkedIn or email
MD;
    }

    public function categories(): array
    {
        return ['productivity', 'recruiting'];
    }

    public function tags(): array
    {
        return ['scheduling', 'calendar', 'interview', 'meeting', 'booking', 'invite'];
    }

    public function inputSchema(): array
    {
        // Not used — tool has a custom frontendView with its own form
        return [];
    }

    public function run(array $input): array
    {
        // Never called in frontend mode
        return [];
    }

    public function mode(): string
    {
        return 'frontend';
    }

    public function isAsync(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function frontendView(): ?string
    {
        return 'interview-scheduler::interview-scheduler';
    }

    public function rateLimitPerMinute(): int
    {
        return 20;
    }

    public function cacheTtlSeconds(): int
    {
        return 0;
    }

    public function sortWeight(): int
    {
        return 85;
    }
}
