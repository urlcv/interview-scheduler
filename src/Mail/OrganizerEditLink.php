<?php

declare(strict_types=1);

namespace URLCV\InterviewScheduler\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email sent to the organiser containing their personal edit link.
 * Allows them to re-open their scheduling form pre-filled with existing data.
 */
class OrganizerEditLink extends Mailable
{
    public function __construct(
        public readonly string $organizerName,
        public readonly string $editUrl,
        public readonly string $eventTitle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your booking edit link — {$this->eventTitle}",
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
        $safeUrl   = htmlspecialchars($this->editUrl);
        $safeTitle = htmlspecialchars($this->eventTitle);
        $safeName  = htmlspecialchars($this->organizerName);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Your Edit Link</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:ui-sans-serif,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;max-width:600px;">
        <!-- Header -->
        <tr><td style="background:#0369a1;padding:32px 40px;">
          <p style="margin:0;color:#bae6fd;font-size:13px;letter-spacing:0.05em;text-transform:uppercase;">URLCV Scheduler</p>
          <h1 style="margin:8px 0 0;color:#ffffff;font-size:24px;font-weight:700;">Your scheduling edit link</h1>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:32px 40px;">
          <p style="margin:0 0 16px;color:#374151;font-size:16px;">Hi {$safeName},</p>
          <p style="margin:0 0 24px;color:#374151;font-size:15px;">
            Here is your personal edit link for <strong>{$safeTitle}</strong>.
            Click the button below to re-open your scheduling form with all your current slots pre-filled —
            update them, then generate a new booking link to share.
          </p>
          <!-- CTA button -->
          <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td style="background:#0284c7;border-radius:8px;padding:12px 24px;">
                <a href="{$safeUrl}" style="color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">
                  Edit my scheduling link →
                </a>
              </td>
            </tr>
          </table>
          <!-- Fallback URL -->
          <p style="margin:0 0 8px;color:#6b7280;font-size:13px;">Or copy this URL into your browser:</p>
          <p style="margin:0 0 24px;word-break:break-all;font-size:12px;color:#374151;font-family:monospace;background:#f3f4f6;padding:10px 12px;border-radius:6px;">
            {$safeUrl}
          </p>
          <p style="margin:0;color:#9ca3af;font-size:12px;">
            This link does not expire. Keep it safe — anyone with this link can edit your scheduling form.
          </p>
        </td></tr>
        <!-- Footer -->
        <tr><td style="padding:20px 40px;border-top:1px solid #e5e7eb;background:#f9fafb;">
          <p style="margin:0;color:#9ca3af;font-size:12px;">
            Sent via <a href="https://urlcv.com/tools/interview-scheduler" style="color:#0284c7;">URLCV Meeting &amp; Interview Scheduler</a> — free scheduling for recruiters.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
