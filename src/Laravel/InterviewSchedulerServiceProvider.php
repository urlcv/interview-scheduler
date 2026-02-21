<?php

declare(strict_types=1);

namespace URLCV\InterviewScheduler\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use URLCV\InterviewScheduler\Mail\AttendeeConfirmation;
use URLCV\InterviewScheduler\Mail\OrganizerEditLink;
use URLCV\InterviewScheduler\Mail\OrganizerNotification;

/**
 * Laravel service provider for the Meeting & Interview Scheduler package.
 *
 * Loads Blade views and registers two API routes:
 *
 *  POST /tools/interview-scheduler/book        — process a booking, send emails
 *  POST /tools/interview-scheduler/email-edit  — email the organiser their edit link
 */
class InterviewSchedulerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'interview-scheduler');

        $this->registerBookingRoute();
        $this->registerEmailEditRoute();
    }

    // ── Booking endpoint ──────────────────────────────────────────────────────

    private function registerBookingRoute(): void
    {
        Route::post(
            '/tools/interview-scheduler/book',
            function (Request $request) {
                $validated = $request->validate([
                    'booking_data'   => ['required', 'string', 'max:8192'],
                    'slot_index'     => ['required', 'integer', 'min:0', 'max:99'],
                    'attendee_name'  => ['required', 'string', 'max:255'],
                    'attendee_email' => ['required', 'email', 'max:255'],
                ]);

                // Decode the booking payload
                try {
                    $data = json_decode(
                        base64_decode($validated['booking_data'], strict: true),
                        associative: true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                } catch (\Throwable) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid booking link — the data could not be decoded.',
                    ], 422);
                }

                $slots = $data['slots'] ?? [];
                $idx   = (int) $validated['slot_index'];

                if (! isset($slots[$idx])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected time slot is no longer available.',
                    ], 422);
                }

                $slotIso        = (string) $slots[$idx];
                $organizerName  = (string) ($data['organizer'] ?? 'The organiser');
                $organizerEmail = (string) ($data['email'] ?? '');
                $eventTitle     = (string) ($data['title'] ?? 'Meeting');
                $duration       = (int)    ($data['duration'] ?? 30);
                $videoLink      = (string) ($data['link'] ?? '');
                $attendeeName   = $validated['attendee_name'];
                $attendeeEmail  = $validated['attendee_email'];

                if (! filter_var($organizerEmail, FILTER_VALIDATE_EMAIL)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The booking link contains an invalid organiser email address.',
                    ], 422);
                }

                try {
                    Mail::to($attendeeEmail, $attendeeName)->send(
                        new AttendeeConfirmation(
                            attendeeName:   $attendeeName,
                            attendeeEmail:  $attendeeEmail,
                            organizerName:  $organizerName,
                            organizerEmail: $organizerEmail,
                            eventTitle:     $eventTitle,
                            duration:       $duration,
                            slotIso:        $slotIso,
                            videoLink:      $videoLink,
                        )
                    );

                    Mail::to($organizerEmail, $organizerName)->send(
                        new OrganizerNotification(
                            attendeeName:  $attendeeName,
                            attendeeEmail: $attendeeEmail,
                            organizerName: $organizerName,
                            eventTitle:    $eventTitle,
                            duration:      $duration,
                            slotIso:       $slotIso,
                            videoLink:     $videoLink,
                        )
                    );
                } catch (\Throwable $e) {
                    \Log::error('[InterviewScheduler] Booking email send failed', [
                        'error'     => $e->getMessage(),
                        'organizer' => $organizerEmail,
                        'attendee'  => $attendeeEmail,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'The booking was received but confirmation emails could not be sent. Please contact the organiser directly.',
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Booking confirmed! A calendar invite has been sent to your email.',
                ]);
            }
        )
        ->middleware(['web', 'throttle:10,1'])
        ->name('tools.interview-scheduler.book');
    }

    // ── Email-edit endpoint ───────────────────────────────────────────────────

    /**
     * Sends the organiser their personal edit link so they can amend their
     * available slots without needing an account.
     *
     * The edit link is simply the same booking payload but with ?edit= instead
     * of ?book=, which causes the view to pre-fill the organiser form.
     */
    private function registerEmailEditRoute(): void
    {
        Route::post(
            '/tools/interview-scheduler/email-edit',
            function (Request $request) {
                $validated = $request->validate([
                    'booking_data' => ['required', 'string', 'max:8192'],
                    'email'        => ['required', 'email', 'max:255'],
                ]);

                try {
                    $data = json_decode(
                        base64_decode($validated['booking_data'], strict: true),
                        associative: true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                } catch (\Throwable) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid booking data.',
                    ], 422);
                }

                $organizerName = (string) ($data['organizer'] ?? 'there');
                $eventTitle    = (string) ($data['title'] ?? 'your meeting');

                // Build the edit URL — same payload, ?edit= param pre-fills the form
                $editUrl = url('/tools/interview-scheduler') . '?edit=' . $validated['booking_data'];

                try {
                    Mail::to($validated['email'], $organizerName)->send(
                        new OrganizerEditLink(
                            organizerName: $organizerName,
                            editUrl:       $editUrl,
                            eventTitle:    $eventTitle,
                        )
                    );
                } catch (\Throwable $e) {
                    \Log::error('[InterviewScheduler] Edit link email failed', [
                        'error' => $e->getMessage(),
                        'email' => $validated['email'],
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not send the email. Please try again shortly.',
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Edit link sent! Check your inbox.',
                ]);
            }
        )
        ->middleware(['web', 'throttle:5,1'])
        ->name('tools.interview-scheduler.email-edit');
    }
}
