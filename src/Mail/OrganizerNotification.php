<?php

declare(strict_types=1);

namespace URLCV\InterviewScheduler\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notification email sent to the organiser when someone books a slot.
 */
class OrganizerNotification extends Mailable
{
    public function __construct(
        public readonly string $attendeeName,
        public readonly string $attendeeEmail,
        public readonly string $organizerName,
        public readonly string $eventTitle,
        public readonly int    $duration,
        public readonly string $slotIso,
        public readonly string $videoLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New booking: {$this->attendeeName} — {$this->eventTitle}",
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
        return [];
    }

    private function buildHtml(): string
    {
        $start     = new \DateTimeImmutable($this->slotIso);
        $end       = $start->modify("+{$this->duration} minutes");
        $formatted = $start->format('l, j F Y \a\t g:i A') . ' UTC';
        $endFmt    = $end->format('g:i A') . ' UTC';
        $videoRow  = $this->videoLink
            ? '<tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px 16px;color:#6b7280;font-size:14px;width:130px;">Video link</td><td style="padding:12px 16px;font-size:14px;color:#111827;"><a href="' . htmlspecialchars($this->videoLink) . '" style="color:#0284c7;">' . htmlspecialchars($this->videoLink) . '</a></td></tr>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>New Booking</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:ui-sans-serif,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;max-width:600px;">
        <!-- Header -->
        <tr><td style="background:#059669;padding:32px 40px;">
          <p style="margin:0;color:#a7f3d0;font-size:13px;letter-spacing:0.05em;text-transform:uppercase;">URLCV Scheduler</p>
          <h1 style="margin:8px 0 0;color:#ffffff;font-size:24px;font-weight:700;">You have a new booking!</h1>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:32px 40px;">
          <p style="margin:0 0 24px;color:#374151;font-size:16px;">Hi {$this->organizerName},</p>
          <p style="margin:0 0 24px;color:#374151;font-size:15px;">
            <strong>{$this->attendeeName}</strong> has booked a slot for <strong>{$this->eventTitle}</strong>. Their confirmation email and calendar invite have been sent automatically.
          </p>
          <!-- Details table -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:24px;">
            <tr style="background:#f9fafb;"><td style="padding:12px 16px;" colspan="2"><strong style="font-size:14px;color:#111827;">Booking details</strong></td></tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;width:130px;">Attendee</td>
              <td style="padding:12px 16px;font-size:14px;color:#111827;">{$this->attendeeName}</td>
            </tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;">Attendee email</td>
              <td style="padding:12px 16px;font-size:14px;color:#111827;"><a href="mailto:{$this->attendeeEmail}" style="color:#0284c7;">{$this->attendeeEmail}</a></td>
            </tr>
            <tr style="border-top:1px solid #e5e7eb;">
              <td style="padding:12px 16px;color:#6b7280;font-size:14px;">Event</td>
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
            {$videoRow}
          </table>
          <p style="margin:0;color:#6b7280;font-size:13px;">The attendee has been sent a calendar invite. Add this time to your own calendar if needed.</p>
        </td></tr>
        <!-- Footer -->
        <tr><td style="padding:20px 40px;border-top:1px solid #e5e7eb;background:#f9fafb;">
          <p style="margin:0;color:#9ca3af;font-size:12px;">Sent via <a href="https://urlcv.com/tools/interview-scheduler" style="color:#0284c7;">URLCV Meeting &amp; Interview Scheduler</a>.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
