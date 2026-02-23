{{--
  Meeting & Interview Scheduler
  ─────────────────────────────
  Three modes driven by URL params:

  ORGANISER  (no params)   — weekly calendar grid, click/drag multi-select
  EDIT       (?edit=B64)   — organiser form pre-filled from encoded payload
  ATTENDEE   (?book=B64)   — slot picker shown in browser's local timezone

  Booking payload (base64-encoded JSON):
  {
    "title":     "30-min Interview",
    "organizer": "Jane Smith",
    "email":     "jane@company.com",
    "duration":  30,
    "link":      "https://...",
    "tz":        "Europe/London",
    "slots":     ["2024-01-15T09:00:00.000Z", ...]   // UTC ISO-8601
  }

  selectedSlots is stored as a plain JS object (hash) for reliable Alpine reactivity:
    { "2024-01-15T09:00": true, ... }   ← local-time keys
  Every mutation replaces the object reference (spread) so Alpine re-renders bindings.

  Server routes registered by InterviewSchedulerServiceProvider:
    POST /tools/interview-scheduler/book        → emails + .ics
    POST /tools/interview-scheduler/email-edit  → emails organiser their ?edit= link
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
                    <li>Fill in your details, then <strong>click or drag on the calendar grid</strong> to mark when you're available.</li>
                    <li>Click <strong>Generate booking link</strong> — a shareable URL is created instantly, no account needed.</li>
                    <li>Paste the link into your email. Your candidate picks a slot and <strong>both parties receive a calendar invite automatically</strong>.</li>
                    <li>Use <strong>Email me my edit link</strong> to save a link that re-opens this form pre-filled whenever you want to change your slots.</li>
                </ol>
            </div>

            {{-- Edit-mode banner --}}
            <template x-if="isEditMode">
                <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Your scheduling link has been loaded for editing. Adjust your slots on the calendar, then click <strong>Generate booking link</strong> to create a fresh URL.</span>
                </div>
            </template>

            {{-- ── Meeting details ──────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900">Meeting details</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Meeting title <span class="text-red-400">*</span>
                    </label>
                    <input type="text" x-model="org.title"
                        placeholder="e.g. 30-min Interview, Discovery Call, Technical Screen"
                        maxlength="120"
                        class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Your name <span class="text-red-400">*</span></label>
                        <input type="text" x-model="org.name" placeholder="e.g. Sarah Mitchell" maxlength="120"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Your email <span class="text-red-400">*</span>
                            <span class="font-normal text-gray-400">(you'll be notified here)</span>
                        </label>
                        <input type="email" x-model="org.email" placeholder="you@company.com" maxlength="255"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Meeting duration</label>
                        <select x-model="org.duration"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white">
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60">60 minutes (1 hr)</option>
                            <option value="90">90 minutes (1.5 hr)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Video / call link <span class="font-normal text-gray-400">(Teams, Zoom, Meet…)</span>
                        </label>
                        <input type="url" x-model="org.videoLink"
                            placeholder="https://meet.google.com/abc-xyz" maxlength="500"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>
            </div>

            {{-- ── Weekly availability calendar ─────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

                {{-- Toolbar --}}
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50">
                    {{-- Week nav --}}
                    <div class="flex items-center gap-1">
                        <button type="button" @click="prevWeek()"
                            class="p-1.5 rounded-lg hover:bg-gray-200 text-gray-500 transition-colors" title="Previous week">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-gray-800 w-52 text-center" x-text="weekLabel"></span>
                        <button type="button" @click="nextWeek()"
                            class="p-1.5 rounded-lg hover:bg-gray-200 text-gray-500 transition-colors" title="Next week">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Count badge --}}
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full transition-colors"
                            :class="slotCount > 0
                                ? 'bg-primary-100 text-primary-700'
                                : 'bg-gray-100 text-gray-500'"
                            x-text="slotCount > 0 ? slotCount + ' slot' + (slotCount === 1 ? '' : 's') + ' selected' : 'No slots selected'">
                        </span>
                        {{-- Weekends toggle --}}
                        <button type="button" @click="showWeekends = !showWeekends"
                            class="text-xs font-medium px-2.5 py-1 rounded-full border transition-colors"
                            :class="showWeekends ? 'bg-gray-700 text-white border-gray-700' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                            Weekends
                        </button>
                        {{-- Copy from previous week --}}
                        <button type="button" @click="copyFromPrevWeek()" x-show="hasPrevWeekSlots"
                            class="text-xs font-medium px-2.5 py-1 rounded-full border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                            title="Copy same weekday/time pattern from last week">
                            Copy prev week
                        </button>
                        {{-- Copy to next week --}}
                        <button type="button" @click="copyToNextWeek()" x-show="hasWeekSlots"
                            class="text-xs font-medium px-2.5 py-1 rounded-full border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                            title="Copy this week's slots to next week">
                            Copy to next week
                        </button>
                        {{-- Clear this week --}}
                        <button type="button" @click="clearWeek()" x-show="hasWeekSlots"
                            class="text-xs font-medium text-red-500 hover:text-red-700 transition-colors">
                            Clear week
                        </button>
                    </div>
                </div>

                {{-- Instruction strip --}}
                <div class="px-4 py-2 bg-blue-50 border-b border-blue-100 text-xs text-blue-700 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span><strong>Click</strong> a cell to mark yourself available. <strong>Click and drag</strong> to select multiple slots at once. Click again to deselect.</span>
                </div>

                {{-- Grid --}}
                <div class="overflow-x-auto" style="user-select: none;" @mouseleave="endDrag()">
                    <table class="border-collapse w-full" style="min-width: 420px;">

                        {{-- Day header row --}}
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 w-14 bg-gray-50 border-b border-r border-gray-200 py-2 px-2 text-right">
                                    <span class="text-xs text-gray-400 font-mono" x-text="tzShort"></span>
                                </th>
                                <template x-for="day in visibleDays" :key="day.iso">
                                    <th class="border-b border-r border-gray-200 last:border-r-0 py-2 px-1 text-center"
                                        :class="day.isToday ? 'bg-primary-50' : day.isPast ? 'bg-gray-50' : 'bg-white'">
                                        <div class="text-xs font-medium text-gray-400 uppercase" x-text="day.dayName"></div>
                                        <div class="text-sm font-bold mt-0.5"
                                            :class="day.isToday ? 'text-primary-600' : day.isPast ? 'text-gray-300' : 'text-gray-800'"
                                            x-text="day.dayNum"></div>
                                    </th>
                                </template>
                            </tr>
                        </thead>

                        {{-- Time slot rows — each <td> IS the clickable cell --}}
                        <tbody>
                            <template x-for="slot in timeSlots" :key="slot.key">
                                <tr>
                                    {{-- Time label --}}
                                    <td class="sticky left-0 z-10 bg-white border-b border-r border-gray-100 text-right pr-2 pl-1 text-xs text-gray-400 font-mono align-middle whitespace-nowrap" style="height:32px; width:56px;">
                                        <span x-text="slot.label"></span>
                                    </td>

                                    {{-- Availability cells — direct <td> click/drag --}}
                                    <template x-for="day in visibleDays" :key="day.iso">
                                        <td
                                            class="border-b border-r border-gray-100 last:border-r-0 transition-colors duration-75"
                                            style="height:32px; min-width:70px;"
                                            :class="tdClass(day, slot)"
                                            @mousedown.prevent="cellMousedown(day, slot)"
                                            @mouseenter="cellMouseenter(day, slot)"
                                            @touchstart.prevent="cellTouch(day, slot)"
                                            :title="!cellIsPast(day, slot) ? (day.dayName + ' ' + day.dayNum + ' at ' + slot.label) : ''"
                                        >
                                            {{-- Checkmark shown inside selected cells --}}
                                            <div x-show="selectedSlots[slotKey(day.iso, slot.key)]" class="flex items-center justify-center h-full">
                                                <svg class="w-3 h-3 text-white opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Legend --}}
                <div class="flex flex-wrap items-center gap-4 px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 rounded-sm bg-primary-500 inline-flex items-center justify-center">
                            <svg class="w-2 h-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        You're available
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 rounded-sm bg-white border border-gray-300 inline-block"></span>
                        Not available
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 rounded-sm bg-gray-100 inline-block border border-gray-200"></span>
                        Past / unavailable
                    </span>
                    <span class="ml-auto hidden sm:block text-gray-400">Drag across multiple cells to select quickly</span>
                </div>
            </div>

            {{-- ── Generate button ───────────────────────────────────────────── --}}
            <div>
                <button type="button" @click="generateLink()" :disabled="!isOrgFormValid"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Generate booking link
                </button>
                <p x-show="!isOrgFormValid" class="mt-2 text-xs text-gray-400">
                    Complete your name, email, meeting title, and select at least one available slot on the calendar above.
                </p>
            </div>

            {{-- ── Generated link + email edit ──────────────────────────────── --}}
            <template x-if="generatedLink">
                <div class="space-y-4">

                    {{-- Shareable booking link --}}
                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-semibold text-green-800">Booking link ready — paste into your email or message</p>
                        </div>
                        <p class="text-xs text-green-700">
                            Your candidate sees your available times in <strong>their own timezone</strong>. When they confirm, both of you receive a calendar invite (.ics) automatically.
                        </p>
                        <div class="flex items-stretch gap-2">
                            <input type="text" :value="generatedLink" readonly @click="$el.select()"
                                class="flex-1 border border-green-300 rounded-lg px-3 py-2 text-xs font-mono bg-white focus:outline-none text-gray-800 min-w-0 cursor-text">
                            <button type="button" @click="copyBookingLink()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border transition-all flex-shrink-0"
                                :class="bookingLinkCopied ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
                                <svg x-show="!bookingLinkCopied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span x-text="bookingLinkCopied ? '✓ Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Email edit link --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Save your edit link</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Need to change your slots later? Enter your email and we'll send you a personal link that re-opens this form with everything pre-filled — no account or password needed.
                            </p>
                        </div>
                        <template x-if="!editLinkSent">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex-1 min-w-48">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Your email address</label>
                                    <input type="email" x-model="editEmailInput" placeholder="you@company.com" maxlength="255"
                                        class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                </div>
                                <button type="button" @click="sendEditLink()" :disabled="!editEmailInput.trim() || sendingEditLink"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex-shrink-0">
                                    <svg x-show="sendingEditLink" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!sendingEditLink" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
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
                                Edit link sent! Check your inbox to save it for later.
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
         ATTENDEE MODE
         ════════════════════════════════════════════════════════════════════ --}}
    <template x-if="mode === 'attendee'">
        <div class="space-y-6">

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
                    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold text-primary-600 uppercase tracking-wide mb-1">Schedule a time with</p>
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

                        {{-- Detected timezone --}}
                        <div class="flex items-center gap-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                            </svg>
                            <span>
                                All times are shown in your timezone:
                                <strong x-text="detectedTz"></strong>
                                <span class="text-gray-400" x-text="'(' + detectedTzAbbr + ')'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Success state --}}
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
                            <div class="bg-white rounded-lg border border-green-200 p-4 space-y-2 text-sm text-gray-600">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Open the .ics attachment to add to Google Calendar, Outlook, or Apple Calendar.
                                </div>
                                <template x-if="bookingData.link">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
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

                    {{-- Slot picker (hidden after submit) --}}
                    <template x-if="!submitted">
                        <div class="space-y-6">

                            {{-- Step 1 --}}
                            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                                <h3 class="text-sm font-semibold text-gray-900">Step 1 — Choose a time</h3>

                                <template x-if="!bookingData.slots || bookingData.slots.length === 0">
                                    <p class="text-sm text-gray-400 italic">No time slots in this booking link. Please contact the organiser.</p>
                                </template>

                                {{-- Slots grouped by local date --}}
                                <template x-for="[dateLabel, daySlots] in slotsByDay" :key="dateLabel">
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2" x-text="dateLabel"></h4>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                            <template x-for="slot in daySlots" :key="slot.idx">
                                                <button type="button" @click="selectSlot(slot.idx)"
                                                    class="px-3 py-2.5 rounded-lg border-2 text-sm text-left transition-all"
                                                    :class="selectedSlotIdx === slot.idx
                                                        ? 'border-primary-500 bg-primary-50 text-primary-800 font-semibold'
                                                        : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 hover:bg-primary-50'">
                                                    <div class="text-xs font-medium leading-snug" x-text="formatAttendeeTime(slot.iso, bookingData.duration)"></div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Step 2 --}}
                            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4 transition-opacity duration-200"
                                :class="selectedSlotIdx === null ? 'opacity-40 pointer-events-none' : 'opacity-100'">
                                <h3 class="text-sm font-semibold text-gray-900">Step 2 — Your details</h3>

                                <template x-if="selectedSlotIdx !== null">
                                    <div class="rounded-lg bg-primary-50 border border-primary-200 px-4 py-2.5 text-sm text-primary-800 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span>Selected: <strong x-text="selectedSlotIdx !== null ? formatAttendeeDateTime(bookingData.slots[selectedSlotIdx], bookingData.duration) : ''"></strong></span>
                                    </div>
                                </template>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Your full name <span class="text-red-400">*</span></label>
                                        <input type="text" x-model="attendeeName" placeholder="e.g. James Chen" maxlength="255"
                                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Your email address <span class="text-red-400">*</span></label>
                                        <input type="email" x-model="attendeeEmail" placeholder="you@example.com" maxlength="255"
                                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    </div>
                                </div>
                            </div>

                            <template x-if="errorMsg">
                                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="errorMsg"></div>
                            </template>

                            <div>
                                <button type="button" @click="submitBooking()" :disabled="!isBookingValid || submitting"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                    <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span x-text="submitting ? 'Confirming…' : 'Confirm booking'"></span>
                                </button>
                                <p class="mt-2 text-xs text-gray-400">By confirming, a calendar invite (.ics) will be sent to your email address.</p>
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
            title: '', name: '', email: '', duration: '30', videoLink: '',
        },

        // ── Calendar ──────────────────────────────────────────────────────────
        // selectedSlots is a PLAIN OBJECT (hash) — keys are "YYYY-MM-DDTHH:MM" (local time).
        // We always REPLACE the reference (object spread) so Alpine detects every change.
        selectedSlots: {},
        weekStartISO:  '',
        isDragging:    false,
        dragAction:    null,      // 'select' | 'deselect'
        showWeekends:  false,

        // ── Generate / copy ───────────────────────────────────────────────────
        generatedLink:     '',
        bookingLinkCopied: false,

        // ── Edit-link email ───────────────────────────────────────────────────
        editEmailInput:  '',
        sendingEditLink: false,
        editLinkSent:    false,
        editLinkError:   null,

        // ── Attendee ──────────────────────────────────────────────────────────
        bookParam,
        bookingData:     null,
        selectedSlotIdx: null,
        attendeeName:    '',
        attendeeEmail:   '',
        submitting:      false,
        submitted:       false,
        errorMsg:        null,
        initError:       null,
        detectedTz:      '',
        detectedTzAbbr:  '',
        tzShort:         '',

        // ────────────────────────────────────────────────────────────────────
        // Lifecycle
        // ────────────────────────────────────────────────────────────────────
        init() {
            this.detectedTz     = Intl.DateTimeFormat().resolvedOptions().timeZone;
            this.detectedTzAbbr = new Date().toLocaleTimeString('en-US', { timeZoneName: 'short' })
                                            .split(' ').pop();
            this.tzShort        = this.detectedTzAbbr;

            // Start calendar on next Monday if today is a weekend
            const now = new Date();
            const dow = now.getDay(); // 0=Sun, 6=Sat
            if (dow === 0 || dow === 6) {
                const daysUntilMonday = dow === 0 ? 1 : 2;
                const m = new Date(now);
                m.setDate(m.getDate() + daysUntilMonday);
                this.weekStartISO = this._getMondayISO(m);
            } else {
                this.weekStartISO = this._getMondayISO(now);
            }

            // Clear all selected slots when duration changes — keys are no longer valid
            this.$watch('org.duration', () => { this.selectedSlots = {}; });

            if (editParam) this._loadEditParam(editParam);

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
                    const hash = {};
                    data.slots.forEach(iso => {
                        hash[this._dateToLocalKey(new Date(iso))] = true;
                    });
                    this.selectedSlots = hash;
                    this.weekStartISO  = this._getMondayISO(new Date(data.slots[0]));
                }
            } catch (e) { /* silently ignore — form stays blank */ }
        },

        // ────────────────────────────────────────────────────────────────────
        // Utility
        // ────────────────────────────────────────────────────────────────────
        _p(n) { return String(n).padStart(2, '0'); },

        _dateToLocalKey(d) {
            return `${d.getFullYear()}-${this._p(d.getMonth()+1)}-${this._p(d.getDate())}T${this._p(d.getHours())}:${this._p(d.getMinutes())}`;
        },

        _getMondayISO(date) {
            const d = new Date(date);
            d.setHours(0, 0, 0, 0);
            const dow = d.getDay();
            d.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));
            return `${d.getFullYear()}-${this._p(d.getMonth()+1)}-${this._p(d.getDate())}`;
        },

        get todayISO() {
            const d = new Date();
            return `${d.getFullYear()}-${this._p(d.getMonth()+1)}-${this._p(d.getDate())}`;
        },

        // ────────────────────────────────────────────────────────────────────
        // Calendar week navigation
        // ────────────────────────────────────────────────────────────────────
        prevWeek() {
            const d = new Date(this.weekStartISO + 'T00:00');
            d.setDate(d.getDate() - 7);
            this.weekStartISO = this._getMondayISO(d);
        },

        nextWeek() {
            const d = new Date(this.weekStartISO + 'T00:00');
            d.setDate(d.getDate() + 7);
            this.weekStartISO = this._getMondayISO(d);
        },

        get weekLabel() {
            const start = new Date(this.weekStartISO + 'T00:00');
            const end   = new Date(start);
            end.setDate(end.getDate() + (this.showWeekends ? 6 : 4));
            return start.toLocaleDateString([], { month: 'short', day: 'numeric' })
                + ' – ' + end.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
        },

        get visibleDays() {
            const count = this.showWeekends ? 7 : 5;
            const today = this.todayISO;
            return Array.from({ length: count }, (_, i) => {
                const d = new Date(this.weekStartISO + 'T00:00');
                d.setDate(d.getDate() + i);
                const iso = `${d.getFullYear()}-${this._p(d.getMonth()+1)}-${this._p(d.getDate())}`;
                return {
                    iso,
                    dayName: d.toLocaleDateString([], { weekday: 'short' }),
                    dayNum:  d.getDate(),
                    isToday: iso === today,
                    isPast:  iso < today,
                };
            });
        },

        get timeSlots() {
            const step = parseInt(this.org.duration || 30, 10);
            const startHour = 8;
            const endHour = 23;
            return Array.from(
                { length: Math.floor((endHour - startHour) * 60 / step) },
                (_, i) => {
                    const m   = startHour * 60 + i * step;
                    const h   = Math.floor(m / 60);
                    const min = m % 60;
                    return { key: `${this._p(h)}:${this._p(min)}`, label: `${this._p(h)}:${this._p(min)}`, h, min };
                }
            );
        },

        slotKey(dayISO, timeKey) { return `${dayISO}T${timeKey}`; },

        // isPastSlot — component method, called from template
        cellIsPast(day, slot) {
            if (day.isPast) return true;
            if (day.isToday) {
                const now = new Date();
                return now.getHours() * 60 + now.getMinutes() >= slot.h * 60 + slot.min;
            }
            return false;
        },

        // The :class for each calendar <td>
        tdClass(day, slot) {
            const past = this.cellIsPast(day, slot);
            const key  = this.slotKey(day.iso, slot.key);
            const sel  = !!this.selectedSlots[key];
            if (past) return 'bg-gray-100 opacity-40 cursor-not-allowed';
            if (sel)  return 'bg-primary-500 hover:bg-primary-600 cursor-pointer';
            return 'bg-white hover:bg-primary-100 cursor-pointer border-gray-100';
        },

        // ────────────────────────────────────────────────────────────────────
        // Drag selection
        // ────────────────────────────────────────────────────────────────────
        cellMousedown(day, slot) {
            if (this.cellIsPast(day, slot)) return;
            const key  = this.slotKey(day.iso, slot.key);
            this.isDragging = true;
            this.dragAction = this.selectedSlots[key] ? 'deselect' : 'select';
            this._toggle(key, this.dragAction);
        },

        cellMouseenter(day, slot) {
            if (!this.isDragging || this.cellIsPast(day, slot)) return;
            this._toggle(this.slotKey(day.iso, slot.key), this.dragAction);
        },

        cellTouch(day, slot) {
            if (this.cellIsPast(day, slot)) return;
            const key = this.slotKey(day.iso, slot.key);
            this._toggle(key, this.selectedSlots[key] ? 'deselect' : 'select');
        },

        endDrag() { this.isDragging = false; this.dragAction = null; },

        // Replace the object reference so Alpine always sees the change
        _toggle(key, action) {
            if (action === 'select' && !this.selectedSlots[key]) {
                this.selectedSlots = { ...this.selectedSlots, [key]: true };
            } else if (action === 'deselect' && this.selectedSlots[key]) {
                const copy = { ...this.selectedSlots };
                delete copy[key];
                this.selectedSlots = copy;
            }
        },

        // ────────────────────────────────────────────────────────────────────
        // Slot count helpers
        // ────────────────────────────────────────────────────────────────────
        get slotCount() { return Object.keys(this.selectedSlots).length; },

        get hasWeekSlots() {
            const days = new Set(this.visibleDays.map(d => d.iso));
            return Object.keys(this.selectedSlots).some(k => days.has(k.split('T')[0]));
        },

        get hasPrevWeekSlots() {
            const prevWeekDays = this.visibleDays.map(d => this._addDays(d.iso, -7));
            return Object.keys(this.selectedSlots).some(k => prevWeekDays.includes(k.split('T')[0]));
        },

        _addDays(iso, days) {
            const d = new Date(iso + 'T00:00');
            d.setDate(d.getDate() + days);
            return `${d.getFullYear()}-${this._p(d.getMonth() + 1)}-${this._p(d.getDate())}`;
        },

        copyFromPrevWeek() {
            const added = {};
            for (const day of this.visibleDays) {
                const prevDate = this._addDays(day.iso, -7);
                for (const slot of this.timeSlots) {
                    const srcKey = `${prevDate}T${slot.key}`;
                    if (this.selectedSlots[srcKey] && !this.cellIsPast(day, slot)) {
                        added[`${day.iso}T${slot.key}`] = true;
                    }
                }
            }
            if (Object.keys(added).length > 0) {
                this.selectedSlots = { ...this.selectedSlots, ...added };
            }
        },

        copyToNextWeek() {
            const added = {};
            for (const day of this.visibleDays) {
                const nextDate = this._addDays(day.iso, 7);
                for (const slot of this.timeSlots) {
                    const srcKey = `${day.iso}T${slot.key}`;
                    if (this.selectedSlots[srcKey]) {
                        added[`${nextDate}T${slot.key}`] = true;
                    }
                }
            }
            if (Object.keys(added).length > 0) {
                this.selectedSlots = { ...this.selectedSlots, ...added };
            }
        },

        clearWeek() {
            const days = new Set(this.visibleDays.map(d => d.iso));
            const copy = { ...this.selectedSlots };
            Object.keys(copy).forEach(k => { if (days.has(k.split('T')[0])) delete copy[k]; });
            this.selectedSlots = copy;
        },

        // ────────────────────────────────────────────────────────────────────
        // Form validation + link generation
        // ────────────────────────────────────────────────────────────────────
        get isOrgFormValid() {
            return this.org.title.trim()
                && this.org.name.trim()
                && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.org.email)
                && this.slotCount > 0;
        },

        generateLink() {
            const tz       = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const isoSlots = Object.keys(this.selectedSlots).sort().map(k => new Date(k).toISOString());
            const encoded  = btoa(JSON.stringify({
                title:    this.org.title.trim(),
                organizer: this.org.name.trim(),
                email:    this.org.email.trim(),
                duration: parseInt(this.org.duration, 10),
                link:     this.org.videoLink.trim(),
                tz, slots: isoSlots,
            }));
            this.generatedLink = window.location.origin + '/tools/interview-scheduler?book=' + encoded;
            if (!this.editEmailInput && this.org.email) this.editEmailInput = this.org.email;
        },

        copyBookingLink() {
            const write = t => navigator.clipboard
                ? navigator.clipboard.writeText(t)
                : Promise.resolve((() => {
                    const el = Object.assign(document.createElement('textarea'), { value: t });
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
            const isoSlots = Object.keys(this.selectedSlots).sort().map(k => new Date(k).toISOString());
            const encoded  = btoa(JSON.stringify({
                title: this.org.title.trim(), organizer: this.org.name.trim(),
                email: this.org.email.trim(), duration: parseInt(this.org.duration, 10),
                link: this.org.videoLink.trim(), tz, slots: isoSlots,
            }));
            this.sendingEditLink = true; this.editLinkError = null;
            try {
                const r    = await fetch('/tools/interview-scheduler/email-edit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' },
                    body: JSON.stringify({ booking_data: encoded, email: this.editEmailInput.trim() }),
                });
                const json = await r.json();
                json.success ? (this.editLinkSent = true) : (this.editLinkError = json.message ?? 'Could not send email.');
            } catch { this.editLinkError = 'Network error — please try again.'; }
            finally  { this.sendingEditLink = false; }
        },

        // ────────────────────────────────────────────────────────────────────
        // Attendee helpers
        // ────────────────────────────────────────────────────────────────────
        get slotsByDay() {
            const groups = {};
            (this.bookingData?.slots || []).forEach((iso, idx) => {
                const label = new Date(iso).toLocaleDateString([], {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                });
                (groups[label] = groups[label] || []).push({ iso, idx });
            });
            return Object.entries(groups);
        },

        formatAttendeeTime(iso, dur) {
            const s = new Date(iso), e = new Date(s.getTime() + (dur || 30) * 60000);
            const f = d => d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const z = s.toLocaleTimeString([], { timeZoneName: 'short' }).split(' ').pop();
            return f(s) + ' – ' + f(e) + ' ' + z;
        },

        formatAttendeeDateTime(iso, dur) {
            if (!iso) return '';
            const d = new Date(iso).toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
            return d + ', ' + this.formatAttendeeTime(iso, dur);
        },

        selectSlot(idx) { this.selectedSlotIdx = idx; this.errorMsg = null; },

        get isBookingValid() {
            return this.selectedSlotIdx !== null
                && this.attendeeName.trim().length > 0
                && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.attendeeEmail.trim());
        },

        async submitBooking() {
            if (!this.isBookingValid || this.submitting) return;
            this.submitting = true; this.errorMsg = null;
            try {
                const r = await fetch('/tools/interview-scheduler/book', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' },
                    body: JSON.stringify({
                        booking_data: this.bookParam, slot_index: this.selectedSlotIdx,
                        attendee_name: this.attendeeName.trim(), attendee_email: this.attendeeEmail.trim(),
                    }),
                });
                const json = await r.json();
                if (json.success) { this.submitted = true; this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                else              { this.errorMsg = json.message ?? 'Something went wrong. Please try again.'; }
            } catch { this.errorMsg = 'Network error — please check your connection.'; }
            finally { this.submitting = false; }
        },
    };
}
</script>
@endpush
