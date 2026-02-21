{{--
  Meeting & Interview Scheduler
  ─────────────────────────────
  Three modes driven by Alpine.js URL params:

  ORGANISER  (no params)   — weekly calendar grid, click/drag multi-select slots
  EDIT       (?edit=B64)   — same form, pre-filled from encoded payload
  ATTENDEE   (?book=B64)   — slot picker in browser local-timezone, grouped by date

  Booking payload (base64-encoded JSON in both ?book= and ?edit=):
  {
    "title":     "30-min Interview",
    "organizer": "Jane Smith",
    "email":     "jane@company.com",
    "duration":  30,              // minutes
    "link":      "https://...",   // optional video call URL
    "tz":        "Europe/London", // organiser's IANA timezone (informational)
    "slots":     ["2024-01-15T09:00:00.000Z", ...]  // UTC ISO-8601
  }

  Server routes (InterviewSchedulerServiceProvider):
    POST /tools/interview-scheduler/book        → validate, send emails + .ics
    POST /tools/interview-scheduler/email-edit  → email organiser their ?edit= link
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
    @mouseup.window="endDrag()"
>

    {{-- ════════════════════════════════════════════════════════════════════
         ORGANISER / EDIT MODE
         ════════════════════════════════════════════════════════════════════ --}}
    <template x-if="mode === 'organiser'">
        <div class="space-y-6">

            {{-- Info callout --}}
            <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                <p class="font-semibold mb-1">How to schedule an interview or meeting</p>
                <ol class="list-decimal list-inside space-y-1 text-blue-700">
                    <li>Fill in your meeting details below, then <strong>click or drag on the calendar</strong> to mark your available times.</li>
                    <li>Click <strong>Generate booking link</strong> to get a shareable URL — no account needed.</li>
                    <li>Paste the link into your email. Your candidate picks a time and <strong>both of you get a calendar invite automatically</strong>.</li>
                    <li>Use <strong>Email me my edit link</strong> to save a link that re-opens this form pre-filled so you can change slots any time.</li>
                </ol>
            </div>

            {{-- Edit mode notice --}}
            <template x-if="isEditMode">
                <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Your scheduling link has been loaded for editing. Adjust your available slots on the calendar, then click <strong>Generate booking link</strong> to create a fresh shareable URL.</span>
                </div>
            </template>

            {{-- ── Event details ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900">Meeting details</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
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
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Your email <span class="text-red-500">*</span>
                            <span class="font-normal text-gray-400">(booking notifications)</span>
                        </label>
                        <input
                            type="email"
                            x-model="org.email"
                            placeholder="you@company.com"
                            maxlength="255"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Meeting duration</label>
                        <select
                            x-model="org.duration"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white"
                        >
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60">60 minutes (1 hr)</option>
                            <option value="90">90 minutes (1.5 hr)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Video / call link
                            <span class="font-normal text-gray-400">(Teams, Zoom, Meet…)</span>
                        </label>
                        <input
                            type="url"
                            x-model="org.videoLink"
                            placeholder="https://meet.google.com/abc-xyz"
                            maxlength="500"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                    </div>
                </div>
            </div>

            {{-- ── Weekly calendar grid ──────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

                {{-- Calendar toolbar --}}
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="prevWeek()"
                            class="p-1.5 rounded-lg hover:bg-gray-200 text-gray-600 transition-colors"
                            title="Previous week"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-gray-800 min-w-48 text-center" x-text="weekLabel"></span>
                        <button
                            type="button"
                            @click="nextWeek()"
                            class="p-1.5 rounded-lg hover:bg-gray-200 text-gray-600 transition-colors"
                            title="Next week"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Slot count badge --}}
                        <span
                            class="text-xs font-medium px-2.5 py-1 rounded-full"
                            :class="selectedSlots.length > 0 ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'"
                            x-text="selectedSlots.length > 0 ? selectedSlots.length + ' slot' + (selectedSlots.length === 1 ? '' : 's') + ' selected' : 'No slots selected'"
                        ></span>

                        {{-- Weekend toggle --}}
                        <button
                            type="button"
                            @click="showWeekends = !showWeekends"
                            class="text-xs font-medium px-2.5 py-1 rounded-full border transition-colors"
                            :class="showWeekends ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                        >
                            Weekends
                        </button>

                        {{-- Clear week --}}
                        <button
                            type="button"
                            @click="clearWeek()"
                            x-show="hasWeekSlots"
                            class="text-xs font-medium text-red-500 hover:text-red-700 transition-colors"
                        >
                            Clear week
                        </button>
                    </div>
                </div>

                {{-- Scrollable grid --}}
                <div class="overflow-x-auto select-none" @mouseleave="endDrag()">
                    <table class="w-full border-collapse" style="min-width: 480px;">

                        {{-- Day headers --}}
                        <thead>
                            <tr>
                                {{-- Time column header --}}
                                <th class="w-14 bg-gray-50 border-b border-r border-gray-200 py-2 px-1 text-xs font-medium text-gray-400 text-right pr-2" style="width:56px;">
                                    <span class="text-gray-400 text-xs" x-text="localTz.length > 12 ? '' : localTz"></span>
                                </th>
                                <template x-for="day in visibleDays" :key="day.iso">
                                    <th
                                        class="border-b border-r border-gray-200 py-2 px-1 text-center last:border-r-0"
                                        :class="day.isToday ? 'bg-primary-50' : 'bg-gray-50'"
                                    >
                                        <div class="text-xs font-medium text-gray-500" x-text="day.dayName"></div>
                                        <div
                                            class="text-sm font-bold mt-0.5"
                                            :class="day.isToday ? 'text-primary-600' : 'text-gray-800'"
                                            x-text="day.dayNum"
                                        ></div>
                                    </th>
                                </template>
                            </tr>
                        </thead>

                        {{-- Time slot rows --}}
                        <tbody>
                            <template x-for="slot in timeSlots" :key="slot.key">
                                <tr class="group">
                                    {{-- Time label --}}
                                    <td class="border-b border-r border-gray-100 bg-gray-50 text-right pr-2 text-xs text-gray-400 font-mono align-top pt-0.5" style="width:56px; height:28px;">
                                        <span x-text="slot.label"></span>
                                    </td>

                                    {{-- Day cells --}}
                                    <template x-for="day in visibleDays" :key="day.iso">
                                        <td
                                            class="border-b border-r border-gray-100 last:border-r-0 p-0"
                                            style="height:28px;"
                                        >
                                            <button
                                                type="button"
                                                class="w-full h-full block transition-colors duration-75 rounded-sm"
                                                :class="cellClass(day.iso, slot.key, day.isPast || slot.isPast(day.iso))"
                                                :disabled="day.isPast || slot.isPast(day.iso)"
                                                @mousedown.prevent="startDrag(day.iso, slot.key)"
                                                @mouseenter="applyDrag(day.iso, slot.key)"
                                                @touchstart.prevent="touchToggle(day.iso, slot.key)"
                                                :title="day.dayName + ' ' + day.dayNum + ' at ' + slot.label"
                                            ></button>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>

                    </table>
                </div>

                {{-- Legend --}}
                <div class="flex items-center gap-4 px-5 py-2.5 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-primary-500 inline-block"></span>
                        Selected (available)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-gray-100 border border-gray-200 inline-block"></span>
                        Not available
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-gray-50 inline-block opacity-40"></span>
                        Past
                    </span>
                    <span class="ml-auto text-gray-400 hidden sm:block">Click or drag to select multiple slots</span>
                </div>
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
                <template x-if="!isOrgFormValid">
                    <p class="mt-2 text-xs text-gray-400">
                        Fill in your name, email, meeting title, and select at least one time slot on the calendar.
                    </p>
                </template>
            </div>

            {{-- ── Generated link ────────────────────────────────────────────── --}}
            <template x-if="generatedLink">
                <div class="space-y-4">

                    {{-- Shareable booking link --}}
                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-semibold text-green-800">
                                Booking link ready — paste this into your email or message
                            </p>
                        </div>
                        <p class="text-xs text-green-700">
                            Your candidate sees available times in <strong>their own timezone</strong>. When they confirm, both of you receive a calendar invite automatically.
                        </p>
                        <div class="flex items-stretch gap-2">
                            <input
                                type="text"
                                :value="generatedLink"
                                readonly
                                @click="$el.select()"
                                class="flex-1 border border-green-300 rounded-lg px-3 py-2 text-xs font-mono bg-white focus:outline-none text-gray-800 min-w-0 cursor-text"
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
                                <span x-text="bookingLinkCopied ? '✓ Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Email me edit link --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Save your edit link</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Want to change your available slots later? Get a personal link emailed to you — click it any time to reopen this form with your current setup pre-filled. No account or password needed.
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
                                    :disabled="!editEmailInput.trim() || sendingEditLink"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex-shrink-0"
                                >
                                    <svg x-show="!sendingEditLink" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <svg x-show="sendingEditLink" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span x-text="sendingEditLink ? 'Sending…' : 'Email me my edit link'"></span>
                                </button>
                            </div>
                        </template>

                        <template x-if="editLinkSent">
                            <div class="flex items-center gap-2 text-sm text-green-700">
                                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Edit link sent! Check your inbox — click it any time to update your slots.
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
         ATTENDEE MODE — pick a slot, confirmation emails sent on submit
         ════════════════════════════════════════════════════════════════════ --}}
    <template x-if="mode === 'attendee'">
        <div class="space-y-6">

            {{-- Init error --}}
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
                                <p class="text-xs font-semibold text-primary-600 uppercase tracking-wide mb-1">Schedule a time with</p>
                                <h2 class="text-xl font-bold text-gray-900" x-text="bookingData.organizer"></h2>
                                <p class="text-base text-gray-600 mt-1" x-text="bookingData.title"></p>
                            </div>
                            <div class="flex flex-wrap gap-2 items-start">
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

                        {{-- Detected timezone notice --}}
                        <div class="mt-4 flex items-center gap-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                            </svg>
                            <span>
                                All times shown in your timezone:
                                <strong x-text="detectedTz"></strong>
                                <span class="text-gray-400 ml-0.5" x-text="'(' + detectedTzAbbr + ')'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- ── Success state ────────────────────────────────────────── --}}
                    <template x-if="submitted">
                        <div class="bg-green-50 border border-green-200 rounded-xl p-6 space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-green-800 text-base">Booking confirmed!</p>
                                    <p class="text-sm text-green-700 mt-0.5">A calendar invite (.ics) has been sent to your email address.</p>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg border border-green-200 p-4 space-y-1.5 text-sm text-gray-700">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Open the .ics file to add this to Google Calendar, Outlook, or Apple Calendar.
                                </div>
                                <template x-if="bookingData.link">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        The video call link is inside your calendar invite.
                                    </div>
                                </template>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span x-text="bookingData.organizer + ' has been notified of your booking.'"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- ── Slot picker (hidden after booking) ───────────────────── --}}
                    <template x-if="!submitted">
                        <div class="space-y-6">

                            {{-- Step 1: choose a time --}}
                            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                                <h3 class="text-sm font-semibold text-gray-900">Step 1 — Choose a time</h3>

                                <template x-if="!bookingData.slots || bookingData.slots.length === 0">
                                    <p class="text-sm text-gray-400 italic">No time slots found in this booking link. Please contact the organiser.</p>
                                </template>

                                {{-- Slots grouped by local date --}}
                                <template x-if="bookingData.slots && bookingData.slots.length > 0">
                                    <div class="space-y-5">
                                        <template x-for="[dateLabel, daySlots] in slotsByDay" :key="dateLabel">
                                            <div>
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2" x-text="dateLabel"></h4>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                                    <template x-for="slot in daySlots" :key="slot.idx">
                                                        <button
                                                            type="button"
                                                            @click="selectSlot(slot.idx)"
                                                            class="px-3 py-2.5 rounded-lg border-2 text-sm text-left transition-all"
                                                            :class="selectedSlotIdx === slot.idx
                                                                ? 'border-primary-500 bg-primary-50 text-primary-800 font-semibold'
                                                                : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 hover:bg-primary-50/50'"
                                                        >
                                                            <div class="font-medium text-xs" x-text="formatAttendeeTime(slot.iso, bookingData.duration)"></div>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- Step 2: your details --}}
                            <div
                                class="bg-white rounded-xl border border-gray-200 p-6 space-y-4 transition-opacity duration-200"
                                :class="selectedSlotIdx === null ? 'opacity-40 pointer-events-none' : 'opacity-100'"
                            >
                                <h3 class="text-sm font-semibold text-gray-900">Step 2 — Your details</h3>

                                {{-- Selected slot confirmation --}}
                                <template x-if="selectedSlotIdx !== null">
                                    <div class="rounded-lg bg-primary-50 border border-primary-200 px-4 py-2.5 text-sm text-primary-800 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span>
                                            Selected:
                                            <strong x-text="selectedSlotIdx !== null ? formatAttendeeDateTime(bookingData.slots[selectedSlotIdx], bookingData.duration) : ''"></strong>
                                        </span>
                                    </div>
                                </template>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
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
                                        <label class="block text-xs font-medium text-gray-600 mb-1">
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
                                    <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span x-text="submitting ? 'Confirming…' : 'Confirm booking'"></span>
                                </button>
                                <p class="mt-2 text-xs text-gray-400">
                                    By confirming, a calendar invite (.ics) will be sent to your email address.
                                </p>
                            </div>

                        </div>
                    </template>

                </div>
            </template>

        </div>
    </template>

    {{-- Attendee footer --}}
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
        // ── Mode ──────────────────────────────────────────────────────────────
        mode:       bookParam ? 'attendee' : 'organiser',
        isEditMode: !!editParam,

        // ── Organiser form ────────────────────────────────────────────────────
        org: {
            title:     '',
            name:      '',
            email:     '',
            duration:  '30',
            videoLink: '',
        },

        // ── Calendar state ────────────────────────────────────────────────────
        weekStartISO:  '',          // "YYYY-MM-DD" of the Monday shown
        selectedSlots: [],          // ["YYYY-MM-DDTHH:MM", ...] local-time keys
        isDragging:    false,
        dragAction:    null,        // 'select' | 'deselect'
        showWeekends:  false,

        // ── Generate / copy state ─────────────────────────────────────────────
        generatedLink:    '',
        bookingLinkCopied: false,

        // ── Edit-link email ───────────────────────────────────────────────────
        editEmailInput:  '',
        sendingEditLink: false,
        editLinkSent:    false,
        editLinkError:   null,

        // ── Attendee state ────────────────────────────────────────────────────
        bookParam,
        bookingData:     null,
        selectedSlotIdx: null,
        attendeeName:    '',
        attendeeEmail:   '',
        submitting:      false,
        submitted:       false,
        errorMsg:        null,
        initError:       null,

        // Timezone detection
        detectedTz:     '',
        detectedTzAbbr: '',

        // ── Init ──────────────────────────────────────────────────────────────
        init() {
            // Detect local timezone
            this.detectedTz     = Intl.DateTimeFormat().resolvedOptions().timeZone;
            this.detectedTzAbbr = new Date().toLocaleTimeString('en-US', { timeZoneName: 'short' })
                                            .split(' ').pop();

            // Initialise calendar to current week
            this.weekStartISO = this.getMondayISO(new Date());

            if (editParam) {
                this._loadEditParam(editParam);
            }

            if (bookParam) {
                try {
                    this.bookingData = JSON.parse(atob(bookParam));
                } catch (e) {
                    this.initError = 'This booking link appears to be invalid or corrupted. Please ask the organiser to send a new link.';
                }
            }
        },

        _loadEditParam(param) {
            try {
                const data = JSON.parse(atob(param));
                this.org.title     = data.title     || '';
                this.org.name      = data.organizer || '';
                this.org.email     = data.email     || '';
                this.org.duration  = String(data.duration || 30);
                this.org.videoLink = data.link      || '';
                this.editEmailInput = data.email    || '';

                if (Array.isArray(data.slots) && data.slots.length) {
                    // Convert UTC ISO strings → local "YYYY-MM-DDTHH:MM" keys
                    this.selectedSlots = data.slots.map(iso => {
                        const d = new Date(iso);
                        return this._dateToLocalKey(d);
                    });
                    // Navigate calendar to first slot's week
                    this.weekStartISO = this.getMondayISO(new Date(data.slots[0]));
                }
            } catch (e) {
                // Silently ignore — form stays blank
            }
        },

        // ── Calendar helpers ──────────────────────────────────────────────────

        _pad(n) { return String(n).padStart(2, '0'); },

        _dateToLocalKey(d) {
            return `${d.getFullYear()}-${this._pad(d.getMonth()+1)}-${this._pad(d.getDate())}T${this._pad(d.getHours())}:${this._pad(d.getMinutes())}`;
        },

        getMondayISO(date) {
            const d = new Date(date);
            d.setHours(0, 0, 0, 0);
            const day = d.getDay(); // 0=Sun
            d.setDate(d.getDate() - (day === 0 ? 6 : day - 1));
            return `${d.getFullYear()}-${this._pad(d.getMonth()+1)}-${this._pad(d.getDate())}`;
        },

        get todayISO() {
            const d = new Date();
            return `${d.getFullYear()}-${this._pad(d.getMonth()+1)}-${this._pad(d.getDate())}`;
        },

        get weekLabel() {
            const start = new Date(this.weekStartISO + 'T00:00');
            const end   = new Date(start);
            end.setDate(end.getDate() + (this.showWeekends ? 6 : 4));
            return start.toLocaleDateString([], { month: 'short', day: 'numeric' })
                + ' – ' + end.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
        },

        prevWeek() {
            const d = new Date(this.weekStartISO + 'T00:00');
            d.setDate(d.getDate() - 7);
            this.weekStartISO = this.getMondayISO(d);
        },

        nextWeek() {
            const d = new Date(this.weekStartISO + 'T00:00');
            d.setDate(d.getDate() + 7);
            this.weekStartISO = this.getMondayISO(d);
        },

        get localTz() {
            return this.detectedTz || '';
        },

        get visibleDays() {
            const count = this.showWeekends ? 7 : 5;
            const today = this.todayISO;
            const days  = [];
            for (let i = 0; i < count; i++) {
                const d = new Date(this.weekStartISO + 'T00:00');
                d.setDate(d.getDate() + i);
                const iso = `${d.getFullYear()}-${this._pad(d.getMonth()+1)}-${this._pad(d.getDate())}`;
                days.push({
                    iso,
                    dayName: d.toLocaleDateString([], { weekday: 'short' }),
                    dayNum:  d.getDate(),
                    isToday: iso === today,
                    isPast:  iso < today,
                });
            }
            return days;
        },

        get timeSlots() {
            const step   = parseInt(this.org.duration || 30, 10);
            const startM = 8 * 60;   // 08:00
            const endM   = 19 * 60;  // 19:00
            const slots  = [];
            for (let m = startM; m < endM; m += step) {
                const h   = Math.floor(m / 60);
                const min = m % 60;
                const key = `${this._pad(h)}:${this._pad(min)}`;
                slots.push({
                    key,
                    label: key,
                    h,
                    min,
                    isPast: (dayISO) => {
                        if (dayISO < this.todayISO) return true;
                        if (dayISO === this.todayISO) {
                            const now = new Date();
                            return now.getHours() * 60 + now.getMinutes() >= h * 60 + min;
                        }
                        return false;
                    },
                });
            }
            return slots;
        },

        slotKey(dayISO, timeKey) {
            return `${dayISO}T${timeKey}`;
        },

        isSelected(dayISO, timeKey) {
            return this.selectedSlots.includes(this.slotKey(dayISO, timeKey));
        },

        cellClass(dayISO, timeKey, isPast) {
            if (isPast) return 'bg-gray-50 opacity-30 cursor-not-allowed';
            if (this.isSelected(dayISO, timeKey)) return 'bg-primary-500 hover:bg-primary-600 cursor-pointer';
            return 'bg-white hover:bg-primary-100 cursor-pointer';
        },

        get hasWeekSlots() {
            const weekDayISOs = new Set(this.visibleDays.map(d => d.iso));
            return this.selectedSlots.some(key => weekDayISOs.has(key.split('T')[0]));
        },

        clearWeek() {
            const weekDayISOs = new Set(this.visibleDays.map(d => d.iso));
            this.selectedSlots = this.selectedSlots.filter(key => !weekDayISOs.has(key.split('T')[0]));
        },

        // ── Drag selection ────────────────────────────────────────────────────

        startDrag(dayISO, timeKey) {
            const key = this.slotKey(dayISO, timeKey);
            this.isDragging = true;
            this.dragAction = this.selectedSlots.includes(key) ? 'deselect' : 'select';
            this._applyToKey(key);
        },

        applyDrag(dayISO, timeKey) {
            if (!this.isDragging) return;
            this._applyToKey(this.slotKey(dayISO, timeKey));
        },

        endDrag() {
            this.isDragging = false;
            this.dragAction = null;
        },

        touchToggle(dayISO, timeKey) {
            // Simple tap toggle for mobile (no drag)
            const key = this.slotKey(dayISO, timeKey);
            this._applyToKey(key, this.selectedSlots.includes(key) ? 'deselect' : 'select');
        },

        _applyToKey(key, action) {
            const act = action ?? this.dragAction;
            const idx = this.selectedSlots.indexOf(key);
            if (act === 'select' && idx < 0) {
                this.selectedSlots.push(key);
            } else if (act === 'deselect' && idx >= 0) {
                this.selectedSlots.splice(idx, 1);
            }
        },

        // ── Form validation & link generation ────────────────────────────────

        get isOrgFormValid() {
            return this.org.title.trim()
                && this.org.name.trim()
                && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.org.email)
                && this.selectedSlots.length > 0;
        },

        generateLink() {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

            // Sort keys and convert local times → UTC ISO strings
            const isoSlots = [...this.selectedSlots]
                .sort()
                .map(key => new Date(key).toISOString());

            const data = {
                title:    this.org.title.trim(),
                organizer: this.org.name.trim(),
                email:    this.org.email.trim(),
                duration: parseInt(this.org.duration, 10),
                link:     this.org.videoLink.trim(),
                tz,
                slots:    isoSlots,
            };

            const encoded = btoa(JSON.stringify(data));
            this.generatedLink = window.location.origin + '/tools/interview-scheduler?book=' + encoded;

            if (!this.editEmailInput && data.email) {
                this.editEmailInput = data.email;
            }
        },

        copyBookingLink() {
            const write = txt => navigator.clipboard
                ? navigator.clipboard.writeText(txt)
                : Promise.resolve((() => {
                    const el = Object.assign(document.createElement('textarea'), { value: txt });
                    document.body.appendChild(el); el.select(); document.execCommand('copy'); el.remove();
                })());

            write(this.generatedLink).then(() => {
                this.bookingLinkCopied = true;
                setTimeout(() => { this.bookingLinkCopied = false; }, 2000);
            });
        },

        async sendEditLink() {
            if (!this.editEmailInput.trim() || this.sendingEditLink) return;

            const tz       = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const isoSlots = [...this.selectedSlots].sort().map(k => new Date(k).toISOString());
            const encoded  = btoa(JSON.stringify({
                title:    this.org.title.trim(),
                organizer: this.org.name.trim(),
                email:    this.org.email.trim(),
                duration: parseInt(this.org.duration, 10),
                link:     this.org.videoLink.trim(),
                tz,
                slots:    isoSlots,
            }));

            this.sendingEditLink = true;
            this.editLinkError   = null;

            try {
                const resp = await fetch('/tools/interview-scheduler/email-edit', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    },
                    body: JSON.stringify({ booking_data: encoded, email: this.editEmailInput.trim() }),
                });
                const json = await resp.json();
                json.success ? (this.editLinkSent = true)
                             : (this.editLinkError = json.message ?? 'Could not send the email. Please try again.');
            } catch {
                this.editLinkError = 'Network error — please check your connection and try again.';
            } finally {
                this.sendingEditLink = false;
            }
        },

        // ── Attendee helpers ──────────────────────────────────────────────────

        // Slots grouped by local date label → [[dateLabel, [{iso, idx}]], ...]
        get slotsByDay() {
            const groups = {};
            (this.bookingData?.slots || []).forEach((iso, idx) => {
                const d = new Date(iso);
                const dateLabel = d.toLocaleDateString([], {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                });
                if (!groups[dateLabel]) groups[dateLabel] = [];
                groups[dateLabel].push({ iso, idx });
            });
            return Object.entries(groups);
        },

        formatAttendeeTime(isoString, duration) {
            const start  = new Date(isoString);
            const end    = new Date(start.getTime() + (duration || 30) * 60000);
            const fmt    = d => d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const tzAbbr = start.toLocaleTimeString([], { timeZoneName: 'short' }).split(' ').pop();
            return fmt(start) + ' – ' + fmt(end) + ' ' + tzAbbr;
        },

        formatAttendeeDateTime(isoString, duration) {
            if (!isoString) return '';
            const d = new Date(isoString);
            const dateStr = d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
            return dateStr + ', ' + this.formatAttendeeTime(isoString, duration);
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
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
            } catch {
                this.errorMsg = 'Network error — please check your connection and try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
