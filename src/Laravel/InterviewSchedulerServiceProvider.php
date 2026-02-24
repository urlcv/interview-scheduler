<?php

declare(strict_types=1);

namespace URLCV\InterviewScheduler\Laravel;

use App\Models\InterviewSchedule;
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
 * Loads Blade views and registers routes:
 *
 *  POST /tools/interview-scheduler/create             — create schedule, return book_id + edit_token
 *  POST /tools/interview-scheduler/update             — update schedule by edit_token
 *  GET  /tools/interview-scheduler/schedule/{book_id} — fetch schedule for attendee
 *  GET  /tools/interview-scheduler/schedule/edit/{edit_token} — fetch schedule for organiser edit
 *  POST /tools/interview-scheduler/book               — process booking (book_id or legacy booking_data)
 *  POST /tools/interview-scheduler/email-edit         — email edit link (edit_token or legacy booking_data)
 */
class InterviewSchedulerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'interview-scheduler');

        $this->registerScheduleFetchRoutes();
        $this->registerCreateRoute();
        $this->registerUpdateRoute();
        $this->registerBookingRoute();
        $this->registerEmailEditRoute();
    }

    // ── Schedule fetch (for Alpine.js) ────────────────────────────────────────

    private function registerScheduleFetchRoutes(): void
    {
        Route::get(
            '/tools/interview-scheduler/schedule/{book_id}',
            function (string $book_id) {
                $schedule = InterviewSchedule::where('book_id', $book_id)->first();
                if (! $schedule) {
                    return response()->json(['error' => 'Schedule not found'], 404);
                }
                return response()->json($schedule->toBookingPayload());
            }
        )
        ->middleware(['web'])
        ->name('tools.interview-scheduler.schedule');

        Route::get(
            '/tools/interview-scheduler/schedule/edit/{edit_token}',
            function (string $edit_token) {
                $schedule = InterviewSchedule::where('edit_token', $edit_token)->first();
                if (! $schedule) {
                    return response()->json(['error' => 'Schedule not found'], 404);
                }
                return response()->json(array_merge(
                    $schedule->toBookingPayload(),
                    ['book_id' => $schedule->book_id],
                ));
            }
        )
        ->middleware(['web'])
        ->name('tools.interview-scheduler.schedule.edit');
    }

    // ── Create ────────────────────────────────────────────────────────────────

    private function registerCreateRoute(): void
    {
        Route::post(
            '/tools/interview-scheduler/create',
            function (Request $request) {
                $validated = $request->validate([
                    'title'     => ['required', 'string', 'max:255'],
                    'organizer' => ['required', 'string', 'max:255'],
                    'email'     => ['required', 'email', 'max:255'],
                    'duration'  => ['required', 'integer', 'in:15,30,45,60,90'],
                    'link'      => ['nullable', 'string', 'max:2048'],
                    'tz'        => ['required', 'string', 'max:64'],
                    'slots'     => ['required', 'array'],
                    'slots.*'   => ['required', 'string', 'max:64'],
                ]);

                $schedule = InterviewSchedule::create([
                    'book_id'    => InterviewSchedule::generateBookId(),
                    'edit_token' => InterviewSchedule::generateEditToken(),
                    'title'      => $validated['title'],
                    'organizer'  => $validated['organizer'],
                    'email'      => $validated['email'],
                    'duration'   => (int) $validated['duration'],
                    'video_link' => $validated['link'] ?? null,
                    'tz'         => $validated['tz'],
                    'slots'      => $validated['slots'],
                ]);

                return response()->json([
                    'success'     => true,
                    'book_id'     => $schedule->book_id,
                    'edit_token'  => $schedule->edit_token,
                    'booking_url' => url('/tools/interview-scheduler?book=' . $schedule->book_id),
                    'edit_url'    => url('/tools/interview-scheduler?edit=' . $schedule->edit_token),
                ]);
            }
        )
        ->middleware(['web', 'throttle:20,1'])
        ->name('tools.interview-scheduler.create');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    private function registerUpdateRoute(): void
    {
        Route::post(
            '/tools/interview-scheduler/update',
            function (Request $request) {
                $validated = $request->validate([
                    'edit_token' => ['required', 'string', 'max:64'],
                    'title'      => ['required', 'string', 'max:255'],
                    'organizer'  => ['required', 'string', 'max:255'],
                    'email'      => ['required', 'email', 'max:255'],
                    'duration'   => ['required', 'integer', 'in:15,30,45,60,90'],
                    'link'       => ['nullable', 'string', 'max:2048'],
                    'tz'         => ['required', 'string', 'max:64'],
                    'slots'      => ['required', 'array'],
                    'slots.*'    => ['required', 'string', 'max:64'],
                ]);

                $schedule = InterviewSchedule::where('edit_token', $validated['edit_token'])->first();
                if (! $schedule) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid edit link.',
                    ], 404);
                }

                $schedule->update([
                    'title'      => $validated['title'],
                    'organizer'  => $validated['organizer'],
                    'email'      => $validated['email'],
                    'duration'   => (int) $validated['duration'],
                    'video_link' => $validated['link'] ?? null,
                    'tz'         => $validated['tz'],
                    'slots'      => $validated['slots'],
                ]);

                return response()->json([
                    'success'     => true,
                    'book_id'     => $schedule->book_id,
                    'booking_url' => url('/tools/interview-scheduler?book=' . $schedule->book_id),
                ]);
            }
        )
        ->middleware(['web', 'throttle:20,1'])
        ->name('tools.interview-scheduler.update');
    }

    // ── Booking endpoint ──────────────────────────────────────────────────────

    private function registerBookingRoute(): void
    {
        Route::post(
            '/tools/interview-scheduler/book',
            function (Request $request) {
                $validated = $request->validate([
                    'slot_index'     => ['required', 'integer', 'min:0', 'max:999'],
                    'attendee_name'  => ['required', 'string', 'max:255'],
                    'attendee_email' => ['required', 'email', 'max:255'],
                    'book_id'        => ['nullable', 'string', 'max:16'],
                    'booking_data'   => ['nullable', 'string', 'max:8192'],
                ]);

                $bookingData = $validated['booking_data'] ?? null;
                $bookId      = $validated['book_id'] ?? null;
                $slotIndex   = (int) $validated['slot_index'];
                $attendeeName = $validated['attendee_name'];
                $attendeeEmail = $validated['attendee_email'];

                $data = null;

                if ($bookId && is_string($bookId) && strlen($bookId) <= 16) {
                    $schedule = InterviewSchedule::where('book_id', $bookId)->first();
                    if ($schedule) {
                        $data = $schedule->toBookingPayload();
                    }
                }

                if (! $data && $bookingData) {
                    try {
                        $decoded = json_decode(
                            base64_decode($bookingData, true),
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        );
                        if (is_array($decoded)) {
                            $data = $decoded;
                        }
                    } catch (\Throwable) {
                        // Legacy decode failed
                    }
                }

                if (! $data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid booking link.',
                    ], 422);
                }

                $slots = $data['slots'] ?? [];
                if (! isset($slots[$slotIndex])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected time slot is no longer available.',
                    ], 422);
                }

                $slotIso        = (string) $slots[$slotIndex];
                $organizerName  = (string) ($data['organizer'] ?? 'The organiser');
                $organizerEmail = (string) ($data['email'] ?? '');
                $eventTitle     = (string) ($data['title'] ?? 'Meeting');
                $duration       = (int) ($data['duration'] ?? 30);
                $videoLink      = (string) ($data['link'] ?? '');

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

    private function registerEmailEditRoute(): void
    {
        Route::post(
            '/tools/interview-scheduler/email-edit',
            function (Request $request) {
                $validated = $request->validate([
                    'email' => ['required', 'email', 'max:255'],
                ]);

                $editToken   = $request->input('edit_token');
                $bookingData = $request->input('booking_data');

                $editUrl = null;
                $organizerName = 'there';
                $eventTitle = 'your meeting';

                if ($editToken && is_string($editToken) && strlen($editToken) <= 64) {
                    $schedule = InterviewSchedule::where('edit_token', $editToken)->first();
                    if ($schedule) {
                        $editUrl       = url('/tools/interview-scheduler?edit=' . $editToken);
                        $organizerName = $schedule->organizer;
                        $eventTitle    = $schedule->title;
                    }
                }

                if (! $editUrl && $bookingData) {
                    try {
                        $data = json_decode(
                            base64_decode($bookingData, true),
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        );
                        if (is_array($data)) {
                            $organizerName = (string) ($data['organizer'] ?? 'there');
                            $eventTitle    = (string) ($data['title'] ?? 'your meeting');
                            $editUrl       = url('/tools/interview-scheduler') . '?edit=' . $bookingData;
                        }
                    } catch (\Throwable) {
                        // Legacy decode failed
                    }
                }

                if (! $editUrl) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid edit link.',
                    ], 422);
                }

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
