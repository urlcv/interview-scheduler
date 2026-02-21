<?php

declare(strict_types=1);

namespace URLCV\InterviewScheduler\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirmation email sent to the attendee when they book a slot.
 * Includes an .ics calendar invite attachment.
 */
class AttendeeConfirmation extends Mailable
{
    public function __construct(
        public readonly string $attendeeName,
        public readonly string $attendeeEmail,
        public readonly string $organizerName,
        public readonly string $organizerEmail,
        public readonly string $eventTitle,
        public readonly int    $duration,
        public readonly string $slotIso,     // UTC ISO-8601 start time
        public readonly string $videoLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmed: {$this->eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->buildIcs(),
                'invite.ics',
            )->withMime('text/calendar'),
        ];
    }

    private function buildHtml(): string
    {
        $start     = new \DateTimeImmutable($this->slotIso);
        $end       = $start->modify("+{$this->duration} minutes");
        $formatted = $start->format('l, j F Y \a\t g:i A') . ' UTC';
        $endFmt    = $end->format('g:i A') . ' UTC';
        $videoRow  = $this->videoLink
            ? '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Video link</td><td style="padding:8px 0;font-size:14px;"><a href="' . htmlspecialchars($this->videoLink) . '" style="color:#0284c7;">' . htmlspecialchars($this->videoLink) . '</a></td></tr>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Booking Confirmed</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:ui-sans-serif,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;max-width:600px;">
        <!-- Header -->
        <tr><td style="background:#0284c7;padding:32px 40px;">
          <p style="margin:0;color:#bae6fd;font-size:13px;letter-spacing:0.05em;text-transform:uppercase;">URLCV Scheduler</p>
          <h1 style="margin:8px 0 0;color:#ffffff;font-size:24px;font-weight:700;">Your booking is confirmed!</h1>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:32px 40px;">
          <p style="margin:0 0 24px;color:#374151;font-size:16px;">Hi {$this->attendeeName},</p>
          <p style="margin:0 0 24px;color:#374151;font-size:15px;">
            Your meeting with <strong>{$this->organizerName}</strong> has been scheduled. A calendar invite (.ics) is attached — simply open it to add the event to your calendar.
          </p>
          <!-- Details table -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:24px;">
            <tr style="background:#f9fafb;"><td style="padding:12px 16px;" colspan="2"><strong style="font-size:14px;color:#111827;">Meeting details</strong></td></tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;width:130px;">Event</td>
              <td style="padding:12px 16px;font-size:14px;color:#111827;">{$this->eventTitle}</td>
            </tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;">Date &amp; time</td>
              <td style="padding:12px 16px;font-size:14px;color:#111827;">{$formatted} – {$endFmt}</td>
            </tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;">Duration</td>
              <td style="padding:12px 16px;font-size:14px;color:#111827;">{$this->duration} minutes</td>
            </tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;">Organiser</td>
              <td style="padding:12px 16px;font-size:14px;color:#111827;">{$this->organizerName}</td>
            </tr>
            {$videoRow}
          </table>
          <p style="margin:0 0 8px;color:#6b7280;font-size:13px;">Need to make a change? Reply to this email or contact {$this->organizerName} directly.</p>
        </td></tr>
        <!-- Footer -->
        <tr><td style="padding:20px 40px;border-top:1px solid #e5e7eb;background:#f9fafb;">
          <p style="margin:0;color:#9ca3af;font-size:12px;">Scheduled via <a href="https://urlcv.com/tools/interview-scheduler" style="color:#0284c7;">URLCV Meeting &amp; Interview Scheduler</a> — free scheduling for recruiters.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private function buildIcs(): string
    {
        $start   = new \DateTimeImmutable($this->slotIso, new \DateTimeZone('UTC'));
        $end     = $start->modify("+{$this->duration} minutes");
        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $uid     = uniqid('urlcv-scheduler-', true) . '@urlcv.com';
        $dtStart = $start->format('Ymd\THis\Z');
        $dtEnd   = $end->format('Ymd\THis\Z');
        $dtStamp = $now->format('Ymd\THis\Z');

        $description = "Meeting with {$this->organizerName} via URLCV Scheduler.";
        if ($this->videoLink) {
            $description .= "\\nJoin: {$this->videoLink}";
        }

        $locationLine = $this->videoLink
            ? "LOCATION:{$this->videoLink}\r\n"
            : "LOCATION:To be confirmed\r\n";

        $urlLine = $this->videoLink
            ? "URL:{$this->videoLink}\r\n"
            : '';

        return implode('', [
            "BEGIN:VCALENDAR\r\n",
            "VERSION:2.0\r\n",
            "PRODID:-//URLCV//Interview Scheduler//EN\r\n",
            "CALSCALE:GREGORIAN\r\n",
            "METHOD:REQUEST\r\n",
            "BEGIN:VEVENT\r\n",
            "UID:{$uid}\r\n",
            "DTSTAMP:{$dtStamp}\r\n",
            "DTSTART:{$dtStart}\r\n",
            "DTEND:{$dtEnd}\r\n",
            "SUMMARY:{$this->eventTitle}\r\n",
            "DESCRIPTION:{$description}\r\n",
            $locationLine,
            $urlLine,
            "ORGANIZER;CN={$this->organizerName}:mailto:{$this->organizerEmail}\r\n",
            "ATTENDEE;CN={$this->attendeeName};RSVP=TRUE:mailto:{$this->attendeeEmail}\r\n",
            "STATUS:CONFIRMED\r\n",
            "SEQUENCE:0\r\n",
            "END:VEVENT\r\n",
            "END:VCALENDAR\r\n",
        ]);
    }
}
