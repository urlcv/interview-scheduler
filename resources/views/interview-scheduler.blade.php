{{--
  Meeting & Interview Scheduler
  ─────────────────────────────
  Three modes driven by Alpine.js:

  ORGANISER MODE     no URL params — build a new scheduling link from scratch
  EDIT MODE          ?edit=BASE64  — organiser form pre-filled with existing data
  ATTENDEE MODE      ?book=BASE64  — attendee picks a slot, emails sent to both

  Booking data format (base64-encoded JSON):
  {
    "title":    "30-min Interview",
    "organizer": "Jane Smith",
    "email":    "jane@company.com",
    "duration": 30,
    "link":     "https://meet.google.com/...",
    "tz":       "Europe/London",
    "slots":    ["2024-01-15T09:00:00.000Z", ...]   // UTC ISO-8601 strings
  }

  Server endpoints (registered by InterviewSchedulerServiceProvider):
    POST /tools/interview-scheduler/book        — validate + send booking emails
    POST /tools/interview-scheduler/email-edit  — email the organiser their edit link
--}}
@php
    $bookParam = request()->query('book', '');
    $editParam = request()->query('edit', '');
@endphp

<div
    x-data="interviewScheduler({{ Js::from($bookParam) }}, {{ Js::from($editParam) }})"
    x-init="init()"
    x-cloak
    class="space-y-6"
>

    {{-- ════════════════════════════════════════════════════════════════════
         ORGANISER / EDIT MODE
         ════════════════════════════════════════════════════════════════════ --}}
    <template x-if="mode === 'organiser'">
        <div class="space-y-6">

            {{-- Intro / how-it-works callout --}}
            <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                <p class="font-semibold mb-1">How to schedule an interview or meeting</p>
                <ol class="list-decimal list-inside space-y-1 text-blue-700">
                    <li>Fill in your meeting details and add the times you're available below.</li>
                    <li>Click <strong>Generate booking link</strong> — a shareable URL is created instantly, no account needed.</li>
                    <li>Paste the link into your email or message. Your candidate or colleague picks a time and <strong>both of you receive a calendar invite automatically</strong>.</li>
                    <li>Use <strong>Email me my edit link</strong> to save a link that lets you amend your slots at any time.</li>
                </ol>
            </div>

            {{-- Pre-fill notice (edit mode) --}}
            <template x-if="isEditMode">
                <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Your scheduling link has been loaded for editing. Update your available slots, then click <strong>Generate booking link</strong> to create a fresh link to share.</span>
                </div>
            </template>

            {{-- ── Event details ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                <h2 class="text-base font-semibold text-gray-900">Meeting details</h2>

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Meeting title <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        x-model="org.title"
                        placeholder="e.g. 30-min Interview, Discovery Call, Technical Screen"
                        maxlength="120"
                        class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    >
                </div>

                {{-- Organiser name + email --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Your name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            x-model="org.name"
                            placeholder="e.g. Sarah Mitchell"
                            maxlength="120"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Your email <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            x-model="org.email"
                            placeholder="you@company.com"
                            maxlength="255"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                        <p class="mt-1 text-xs text-gray-400">Booking notifications are sent here.</p>
                    </div>
                </div>

                {{-- Duration + video link --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Meeting duration
                        </label>
                        <select
                            x-model="org.duration"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white"
                        >
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60">60 minutes (1 hour)</option>
                            <option value="90">90 minutes (1.5 hours)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Video / call link
                            <span class="font-normal text-gray-500">(optional)</span>
                        </label>
                        <input
                            type="url"
                            x-model="org.videoLink"
                            placeholder="Teams, Zoom, Google Meet URL…"
                            maxlength="500"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                        <p class="mt-1 text-xs text-gray-400">Included in the calendar invite sent to attendees.</p>
                    </div>
                </div>
            </div>

            {{-- ── Available time slots ──────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Available time slots</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Enter times in your local timezone. Attendees will see them converted to their own timezone.</p>
                    </div>
                    <button
                        type="button"
                        @click="addSlot()"
                        class="inline-flex items-center gap-1.5 text-sm text-primary-600 hover:text-primary-700 font-medium flex-shrink-0"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add slot
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(slot, idx) in org.slots" :key="idx">
                        <div class="flex flex-wrap items-center gap-3">
                            <input
                                type="date"
                                x-model="slot.date"
                                :min="today"
                                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                            <input
                                type="time"
                                x-model="slot.time"
                                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                            <button
                                type="button"
                                @click="removeSlot(idx)"
                                x-show="org.slots.length > 1"
                                class="text-gray-400 hover:text-red-500 transition-colors"
                                title="Remove this slot"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <p class="text-xs text-gray-500">
                    Add as many slots as you like. Slots with a blank date or time are ignored.
                </p>
            </div>

            {{-- ── Generate button ───────────────────────────────────────────── --}}
            <div>
                <button
                    type="button"
                    @click="generateLink()"
                    :disabled="!isOrgFormValid"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Generate booking link
                </button>
                <p class="mt-2 text-xs text-gray-500" x-show="!isOrgFormValid">
                    Fill in your name, email, meeting title, and at least one complete time slot to generate a link.
                </p>
            </div>

            {{-- ── Generated link + save edit link ──────────────────────────── --}}
            <template x-if="generatedLink">
                <div class="space-y-4">

                    {{-- Shareable booking link --}}
                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-semibold text-green-800">Your booking link is ready — paste this into your email</p>
                        </div>
                        <p class="text-xs text-green-700">
                            Share this link with your candidate or colleague. They pick a time and <strong>both of you receive a calendar invite automatically</strong>.
                        </p>
                        <div class="flex items-stretch gap-2">
                            <input
                                type="text"
                                :value="generatedLink"
                                readonly
                                class="flex-1 border border-green-300 rounded-lg px-3 py-2 text-xs font-mono bg-white focus:outline-none text-gray-800 min-w-0"
                            >
                            <button
                                type="button"
                                @click="copyBookingLink()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border transition-colors flex-shrink-0"
                                :class="bookingLinkCopied
                                    ? 'bg-green-600 text-white border-green-600'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                            >
                                <svg x-show="!bookingLinkCopied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span x-text="bookingLinkCopied ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Email me my edit link --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Save your edit link</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Want to amend your available slots later? Enter your email and we'll send you a personal link that re-opens this form pre-filled with your current setup — no account needed.
                            </p>
                        </div>

                        <template x-if="!editLinkSent">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex-1 min-w-48">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Your email address</label>
                                    <input
                                        type="email"
                                        x-model="editEmailInput"
                                        placeholder="you@company.com"
                                        maxlength="255"
                                        class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    >
                                </div>
                                <button
                                    type="button"
                                    @click="sendEditLink()"
                                    :disabled="!editEmailInput || sendingEditLink"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex-shrink-0"
                                >
                                    <template x-if="sendingEditLink">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                    </template>
                                    <template x-if="!sendingEditLink">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </template>
                                    <span x-text="sendingEditLink ? 'Sending…' : 'Email me my edit link'"></span>
                                </button>
                            </div>
                        </template>

                        <template x-if="editLinkSent">
                            <div class="flex items-center gap-2 text-sm text-green-700">
                                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Edit link sent! Check your inbox — click the link any time to update your available slots.</span>
                            </div>
                        </template>

                        <template x-if="editLinkError">
                            <p class="text-xs text-red-600" x-text="editLinkError"></p>
                        </template>
                    </div>

                </div>
            </template>

        </div>
    </template>

    {{-- ════════════════════════════════════════════════════════════════════
         ATTENDEE MODE — pick a time and confirm booking
         ════════════════════════════════════════════════════════════════════ --}}
    <template x-if="mode === 'attendee'">
        <div class="space-y-6">

            {{-- Init error (bad link) --}}
            <template x-if="initError">
                <div class="rounded-xl bg-red-50 border border-red-200 p-5 text-sm text-red-800">
                    <p class="font-semibold mb-1">Invalid booking link</p>
                    <p x-text="initError"></p>
                    <a href="/tools/interview-scheduler" class="mt-3 inline-block text-primary-600 hover:underline text-sm font-medium">
                        ← Create your own scheduling link
                    </a>
                </div>
            </template>

            <template x-if="bookingData && !initError">
                <div class="space-y-6">

                    {{-- Meeting header --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-medium text-primary-600 uppercase tracking-wide mb-1">Schedule a time with</p>
                                <h2 class="text-xl font-bold text-gray-900" x-text="bookingData.organizer"></h2>
                                <p class="text-base text-gray-600 mt-1" x-text="bookingData.title"></p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary-50 border border-primary-200 text-primary-700 text-xs font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span x-text="bookingData.duration + ' min'"></span>
                                </span>
                                <template x-if="bookingData.link">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-violet-50 border border-violet-200 text-violet-700 text-xs font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        Video call included
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- ── Success state ───────────────────────────────────────── --}}
                    <template x-if="submitted">
                        <div class="bg-green-50 border border-green-200 rounded-xl p-6 space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-green-800 text-base">Booking confirmed!</p>
                                    <p class="text-sm text-green-700 mt-0.5">Check your inbox for a confirmation email with a calendar invite (.ics file) attached.</p>
                                </div>
                            </div>
                            <div class="text-sm text-green-700 space-y-1 pl-13">
                                <p class="font-medium">What happens next:</p>
                                <ul class="list-disc list-inside space-y-0.5 text-green-700">
                                    <li>Open the .ics attachment to add this event to Google Calendar, Outlook, or Apple Calendar.</li>
                                    <template x-if="bookingData.link">
                                        <li>The video call link is in your calendar invite.</li>
                                    </template>
                                    <li x-text="'A notification has also been sent to ' + bookingData.organizer + '.'"></li>
                                </ul>
                            </div>
                        </div>
                    </template>

                    {{-- ── Slot picker + form (hidden after submit) ─────────────── --}}
                    <template x-if="!submitted">
                        <div class="space-y-6">

                            {{-- Step 1: pick a slot --}}
                            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Step 1 — Choose a time
                                    <span class="font-normal text-gray-500 ml-1">(shown in your local timezone)</span>
                                </h3>

                                <template x-if="bookingData.slots && bookingData.slots.length > 0">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <template x-for="(slot, idx) in bookingData.slots" :key="idx">
                                            <button
                                                type="button"
                                                @click="selectSlot(idx)"
                                                class="text-left px-4 py-3 rounded-lg border-2 text-sm transition-all"
                                                :class="selectedSlotIdx === idx
                                                    ? 'border-primary-500 bg-primary-50 text-primary-800'
                                                    : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 hover:bg-primary-50/50'"
                                            >
                                                <div class="font-medium" x-text="formatSlotDate(slot)"></div>
                                                <div class="text-xs mt-0.5 opacity-75" x-text="formatSlotTime(slot, bookingData.duration)"></div>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="!bookingData.slots || bookingData.slots.length === 0">
                                    <p class="text-sm text-gray-500 italic">No time slots were found in this booking link. Please contact the organiser.</p>
                                </template>
                            </div>

                            {{-- Step 2: contact details --}}
                            <div
                                class="bg-white rounded-xl border border-gray-200 p-6 space-y-4 transition-opacity duration-200"
                                :class="selectedSlotIdx === null ? 'opacity-40 pointer-events-none' : 'opacity-100'"
                            >
                                <h3 class="text-sm font-semibold text-gray-900">Step 2 — Your details</h3>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Your full name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            x-model="attendeeName"
                                            placeholder="e.g. James Chen"
                                            maxlength="255"
                                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Your email address <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            x-model="attendeeEmail"
                                            placeholder="you@example.com"
                                            maxlength="255"
                                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- Error --}}
                            <template x-if="errorMsg">
                                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="errorMsg"></div>
                            </template>

                            {{-- Confirm button --}}
                            <div>
                                <button
                                    type="button"
                                    @click="submitBooking()"
                                    :disabled="!isBookingValid || submitting"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                >
                                    <template x-if="submitting">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                    </template>
                                    <template x-if="!submitting">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </template>
                                    <span x-text="submitting ? 'Confirming…' : 'Confirm booking'"></span>
                                </button>
                                <p class="mt-2 text-xs text-gray-500">
                                    By confirming, a calendar invite (.ics) will be sent to your email address.
                                </p>
                            </div>

                        </div>
                    </template>

                </div>
            </template>

        </div>
    </template>

    {{-- Back link for attendees --}}
    <template x-if="mode === 'attendee'">
        <div class="pt-2 border-t border-gray-100">
            <a href="/tools/interview-scheduler" class="text-xs text-gray-400 hover:text-primary-600 transition-colors">
                Need to schedule your own meetings? <span class="underline">Create a free booking link →</span>
            </a>
        </div>
    </template>

</div>

@push('scripts')
<script>
function interviewScheduler(bookParam, editParam) {
    return {
        // ── Mode ─────────────────────────────────────────────────────────────
        // 'organiser' = create / edit form
        // 'attendee'  = booking picker
        mode:       bookParam ? 'attendee' : 'organiser',
        isEditMode: !!editParam,

        // ── Organiser form state ──────────────────────────────────────────────
        org: {
            title:     '',
            name:      '',
            email:     '',
            duration:  '30',
            videoLink: '',
            slots:     [{ date: '', time: '' }],
        },
        today:            new Date().toISOString().split('T')[0],
        generatedLink:    '',
        bookingLinkCopied: false,

        // Edit-link email sender
        editEmailInput:  '',
        sendingEditLink: false,
        editLinkSent:    false,
        editLinkError:   null,

        // ── Attendee state ────────────────────────────────────────────────────
        bookParam:       bookParam,
        bookingData:     null,
        selectedSlotIdx: null,
        attendeeName:    '',
        attendeeEmail:   '',
        submitting:      false,
        submitted:       false,
        errorMsg:        null,
        initError:       null,

        // ── Lifecycle ─────────────────────────────────────────────────────────
        init() {
            if (editParam) {
                // Pre-fill organiser form from ?edit= payload
                try {
                    const data = JSON.parse(atob(editParam));
                    this.org.title     = data.title    || '';
                    this.org.name      = data.organizer || '';
                    this.org.email     = data.email    || '';
                    this.org.duration  = String(data.duration || 30);
                    this.org.videoLink = data.link     || '';
                    this.editEmailInput = data.email   || '';

                    // Convert UTC ISO slots back to local date+time inputs
                    if (Array.isArray(data.slots) && data.slots.length) {
                        this.org.slots = data.slots.map(iso => {
                            const d = new Date(iso);
                            const pad = n => String(n).padStart(2, '0');
                            const date = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
                            const time = pad(d.getHours()) + ':' + pad(d.getMinutes());
                            return { date, time };
                        });
                    }
                } catch (e) {
                    // Silently ignore — form stays blank
                }
            }

            if (bookParam) {
                try {
                    this.bookingData = JSON.parse(atob(bookParam));
                } catch (e) {
                    this.initError = 'This booking link appears to be invalid or corrupted. Please ask the organiser to send a new link.';
                }
            }
        },

        // ── Organiser helpers ─────────────────────────────────────────────────
        addSlot() {
            this.org.slots.push({ date: '', time: '' });
        },

        removeSlot(idx) {
            if (this.org.slots.length > 1) {
                this.org.slots.splice(idx, 1);
            }
        },

        validSlots() {
            return this.org.slots.filter(s => s.date && s.time);
        },

        get isOrgFormValid() {
            return this.org.title.trim()
                && this.org.name.trim()
                && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.org.email)
                && this.validSlots().length > 0;
        },

        generateLink() {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

            const isoSlots = this.validSlots().map(s => {
                return new Date(s.date + 'T' + s.time).toISOString();
            });

            const data = {
                title:    this.org.title.trim(),
                organizer: this.org.name.trim(),
                email:    this.org.email.trim(),
                duration: parseInt(this.org.duration, 10),
                link:     this.org.videoLink.trim(),
                tz:       tz,
                slots:    isoSlots,
            };

            const encoded = btoa(JSON.stringify(data));
            this.generatedLink = window.location.origin + '/tools/interview-scheduler?book=' + encoded;

            // Pre-fill edit email with organiser email
            if (!this.editEmailInput && data.email) {
                this.editEmailInput = data.email;
            }

            // Scroll to result
            this.$nextTick(() => {
                const el = this.$el.querySelector('[x-text*="booking link is ready"]');
                if (el) el.closest('.bg-green-50')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },

        copyBookingLink() {
            const copy = text => {
                if (navigator.clipboard) {
                    return navigator.clipboard.writeText(text);
                }
                // Fallback
                const el = Object.assign(document.createElement('textarea'), { value: text });
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                return Promise.resolve();
            };
            copy(this.generatedLink).then(() => {
                this.bookingLinkCopied = true;
                setTimeout(() => { this.bookingLinkCopied = false; }, 2000);
            });
        },

        async sendEditLink() {
            if (!this.editEmailInput || this.sendingEditLink) return;

            // Build the booking payload from the current form
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const isoSlots = this.validSlots().map(s => new Date(s.date + 'T' + s.time).toISOString());
            const data = {
                title:    this.org.title.trim(),
                organizer: this.org.name.trim(),
                email:    this.org.email.trim(),
                duration: parseInt(this.org.duration, 10),
                link:     this.org.videoLink.trim(),
                tz:       tz,
                slots:    isoSlots,
            };
            const encoded = btoa(JSON.stringify(data));

            this.sendingEditLink = true;
            this.editLinkError   = null;

            try {
                const resp = await fetch('/tools/interview-scheduler/email-edit', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                            ?.getAttribute('content') ?? '',
                    },
                    body: JSON.stringify({
                        booking_data: encoded,
                        email:        this.editEmailInput.trim(),
                    }),
                });

                const json = await resp.json();

                if (json.success) {
                    this.editLinkSent = true;
                } else {
                    this.editLinkError = json.message ?? 'Could not send the email. Please try again.';
                }
            } catch (err) {
                this.editLinkError = 'Network error — please check your connection and try again.';
            } finally {
                this.sendingEditLink = false;
            }
        },

        // ── Attendee helpers ──────────────────────────────────────────────────
        formatSlotDate(isoString) {
            return new Date(isoString).toLocaleDateString([], {
                weekday: 'long',
                year:    'numeric',
                month:   'long',
                day:     'numeric',
            });
        },

        formatSlotTime(isoString, duration) {
            const start = new Date(isoString);
            const end   = new Date(start.getTime() + (duration || 30) * 60000);
            const fmt   = d => d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const tzAbbr = start.toLocaleTimeString([], { timeZoneName: 'short' }).split(' ').pop();
            return fmt(start) + ' – ' + fmt(end) + ' ' + tzAbbr;
        },

        selectSlot(idx) {
            this.selectedSlotIdx = idx;
            this.errorMsg = null;
        },

        get isBookingValid() {
            return this.selectedSlotIdx !== null
                && this.attendeeName.trim().length > 0
                && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.attendeeEmail.trim());
        },

        async submitBooking() {
            if (!this.isBookingValid || this.submitting) return;

            this.submitting = true;
            this.errorMsg   = null;

            try {
                const resp = await fetch('/tools/interview-scheduler/book', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                            ?.getAttribute('content') ?? '',
                    },
                    body: JSON.stringify({
                        booking_data:   this.bookParam,
                        slot_index:     this.selectedSlotIdx,
                        attendee_name:  this.attendeeName.trim(),
                        attendee_email: this.attendeeEmail.trim(),
                    }),
                });

                const json = await resp.json();

                if (json.success) {
                    this.submitted = true;
                    this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    this.errorMsg = json.message ?? 'Something went wrong. Please try again.';
                }
            } catch (err) {
                this.errorMsg = 'Network error — please check your connection and try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
