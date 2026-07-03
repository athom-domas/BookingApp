export function bookingWizard(allServices, allStaff, bookingPreferences = null, paymentMode = 'both', categories = []) {
    return {
        // navigation
        step: 1,
        completed: [],

        // data
        allServices,
        allStaff,
        paymentMode,

        // step 1
        selectedServiceIds: [],
        showAllServices: false,
        categories,
        selectedCategory: null,

        // step 2
        staffId: null,

        // step 3
        date: null,
        slot: null,
        calendarMonth: '',
        availableDates: [],
        loadingDates: false,
        availableSlots: [],
        loadingSlots: false,

        // step 4
        submitting: false,
        paymentMethod: null,
        notes: '',

        // waitlist offer source (null when booking directly)
        waitlistEntryId: null,

        // preferences & suggestions
        preferences: bookingPreferences,
        suggestedSlots: [],
        suggestedSlotsLoaded: false,
        loadingSuggested: false,
        showSuggestions: true,

        // ── init ──────────────────────────────────────────────────────────
        init() {
            const now = new Date();
            this.calendarMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

            const saved = sessionStorage.getItem('bookingWizardState');
            if (saved) {
                sessionStorage.removeItem('bookingWizardState');
                try {
                    const s = JSON.parse(saved);
                    this.selectedServiceIds = Array.isArray(s.selectedServiceIds) ? s.selectedServiceIds : [];
                    this.staffId            = (s.staffId === null || Number.isInteger(s.staffId)) ? s.staffId : null;
                    this.date               = (typeof s.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s.date)) ? s.date : null;
                    this.slot               = (typeof s.slot === 'string' && /^\d{2}:\d{2}$/.test(s.slot)) ? s.slot : null;
                    this.calendarMonth      = s.calendarMonth ?? this.calendarMonth;
                    this.paymentMethod      = s.paymentMethod ?? null;
                    this.notes              = (typeof s.notes === 'string') ? s.notes.slice(0, 1000) : '';
                    this.completed          = Array.isArray(s.completed) ? s.completed : [];
                    this.step               = (Number.isInteger(s.step) && s.step >= 1 && s.step <= 4) ? s.step : 1;
                    this.waitlistEntryId    = Number.isInteger(s.waitlistEntryId) ? s.waitlistEntryId : null;
                } catch (_) {}

                if (this.step === 3 || (this.completed.includes(3) && this.date)) {
                    this.loadAvailableDates();
                    if (this.date) this.loadAvailableSlots();
                }
            }

            if (this.paymentMode !== 'both' && !this.paymentMethod) {
                this.paymentMethod = this.paymentMode;
            }

            this.$watch('step', (v) => {
                if (v === 3) this.loadAvailableDates();
            });
        },

        saveForAuth(returnPath) {
            sessionStorage.setItem('bookingWizardState', JSON.stringify({
                selectedServiceIds: this.selectedServiceIds,
                staffId:            this.staffId,
                date:               this.date,
                slot:               this.slot,
                calendarMonth:      this.calendarMonth,
                paymentMethod:      this.paymentMethod,
                notes:              this.notes,
                step:               this.step,
                completed:          this.completed,
                waitlistEntryId:    this.waitlistEntryId,
            }));
            window.location.href = returnPath;
        },

        // ── navigation ────────────────────────────────────────────────────
        isOpen(n) {
            return this.step === n;
        },

        isCompleted(n) {
            return this.completed.includes(n);
        },

        completeStep(n) {
            if (! this.completed.includes(n)) {
                this.completed.push(n);
            }
            this.step = n + 1;
            if (n === 2 && this.preferences) this.loadSuggestedSlots();
            if (n === 3 && this.paymentMode !== 'both') this.paymentMethod = this.paymentMode;
        },

        goTo(n) {
            if (n <= 1) {
                this.staffId = null;
            }
            if (n <= 2) {
                this.date = null;
                this.slot = null;
                this.availableSlots = [];
                this.availableDates = [];
            }
            if (n <= 3) {
                this.paymentMethod = this.paymentMode !== 'both' ? this.paymentMode : null;
            }
            this.completed = this.completed.filter(s => s < n);
            this.step = n;
        },

        // ── computed ──────────────────────────────────────────────────────
        get totalDuration() {
            return this.selectedServiceIds.reduce((sum, id) => {
                const s = this.allServices.find(s => s.id === id);
                return sum + (s ? s.duration : 0);
            }, 0);
        },

        get totalPrice() {
            return this.selectedServiceIds.reduce((sum, id) => {
                const s = this.allServices.find(s => s.id === id);
                return sum + (s ? s.price : 0);
            }, 0);
        },

        get visibleServices() {
            if (this.selectedCategory !== null) {
                if (this.selectedCategory === 'altri') {
                    return this.allServices.filter(s => s.category_id === null);
                }
                return this.allServices.filter(s => s.category_id === this.selectedCategory);
            }
            if (this.showAllServices) return this.allServices;
            const featured = this.allServices.filter(s => s.featured);
            return featured.length > 0 ? featured : this.allServices;
        },

        get hasMoreServices() {
            if (this.selectedCategory !== null) return false;
            const featured = this.allServices.filter(s => s.featured);
            return featured.length > 0 && featured.length < this.allServices.length;
        },

        get hasUncategorized() {
            return this.categories.length > 0 && this.allServices.some(s => s.category_id === null);
        },

        get filteredStaff() {
            if (this.selectedServiceIds.length === 0) return this.allStaff;
            return this.allStaff.filter(member =>
                this.selectedServiceIds.every(sid => member.service_ids.includes(sid))
            );
        },

        get scheduledDateTime() {
            if (! this.date || ! this.slot) return '';
            return `${this.date} ${this.slot}:00`;
        },

        get servicesSummary() {
            if (this.selectedServiceIds.length === 0) return '';
            const names = this.selectedServiceIds.map(id => this.serviceById(id)?.name).filter(Boolean);
            return `${names.join(', ')} · ${this.totalDuration} min · € ${this.totalPrice.toFixed(2).replace('.', ',')}`;
        },

        get staffSummary() {
            if (this.staffId === null) return 'Qualsiasi operatore';
            return this.allStaff.find(s => s.id === this.staffId)?.name ?? '';
        },

        get dateSummary() {
            if (! this.date || ! this.slot) return '';
            const [y, m, d] = this.date.split('-');
            return `${d}/${m}/${y} alle ${this.slot}`;
        },

        get paymentSummary() {
            if (this.paymentMethod === 'online') return 'Paga ora (online)';
            if (this.paymentMethod === 'in_salon') return 'Paga in salone';
            return '';
        },

        // ── service selection ─────────────────────────────────────────────
        serviceById(id) {
            return this.allServices.find(s => s.id === id) ?? null;
        },

        isSelectedService(id) {
            return this.selectedServiceIds.includes(id);
        },

        toggleService(id) {
            const idx = this.selectedServiceIds.indexOf(id);
            if (idx === -1) {
                this.selectedServiceIds.push(id);
            } else {
                this.selectedServiceIds.splice(idx, 1);
            }
        },

        // ── calendar ──────────────────────────────────────────────────────
        get monthLabel() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            return new Date(year, month - 1, 1).toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
        },

        get calendarGrid() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const firstDay = new Date(year, month - 1, 1);
            const lastDay  = new Date(year, month, 0);
            const startPad = (firstDay.getDay() + 6) % 7; // Monday = 0

            const cells = [];
            for (let i = 0; i < startPad; i++) cells.push(null);
            for (let d = 1; d <= lastDay.getDate(); d++) {
                cells.push(`${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
            }
            return cells;
        },

        isAvailableDate(dateStr) {
            return this.availableDates.includes(dateStr);
        },

        prevMonth() {
            if (this.loadingDates) return;
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const d = new Date(year, month - 2, 1);
            const newMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const today = new Date().toISOString().slice(0, 7);
            if (newMonth < today) return;
            this.calendarMonth = newMonth;
            this.loadAvailableDates();
        },

        nextMonth() {
            if (this.loadingDates) return;
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const d = new Date(year, month, 1);
            const limit = new Date();
            limit.setFullYear(limit.getFullYear() + 1);
            if (d > limit) return;
            this.calendarMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            this.loadAvailableDates();
        },

        async loadAvailableDates() {
            if (this.selectedServiceIds.length === 0) return;
            this.loadingDates = true;
            this.availableDates = [];

            const params = new URLSearchParams();
            this.selectedServiceIds.forEach(id => params.append('serviceIds[]', id));
            if (this.staffId) params.append('staffId', this.staffId);
            params.append('month', this.calendarMonth);

            try {
                const res  = await fetch(`/api/booking/available-dates?${params}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.availableDates = data.data ?? [];
            } catch (_) {
                this.availableDates = [];
            } finally {
                this.loadingDates = false;
            }
        },

        async selectDate(dateStr) {
            this.date = dateStr;
            this.slot = null;
            this.availableSlots = [];
            await this.loadAvailableSlots();
        },

        async loadAvailableSlots() {
            if (! this.date || this.selectedServiceIds.length === 0) return;
            this.loadingSlots = true;

            const params = new URLSearchParams();
            this.selectedServiceIds.forEach(id => params.append('serviceIds[]', id));
            if (this.staffId) params.append('staffId', this.staffId);
            params.append('staffPreference', this.staffId ? 'specific' : 'any');
            params.append('date', this.date);

            try {
                const res  = await fetch(`/api/booking/slots?${params}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.availableSlots = (data.data ?? []).map(s => ({ start: s.start, end: s.end }));
            } catch (_) {
                this.availableSlots = [];
            } finally {
                this.loadingSlots = false;
            }
        },

        // ── suggestions ───────────────────────────────────────────────────
        toLocalIsoDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        isPreferredDay(iso) {
            if (!this.preferences?.days?.length) return false;
            const dow = new Date(iso + 'T00:00:00').getDay();
            return this.preferences.days.includes(dow);
        },

        async loadSuggestedSlots() {
            if (!this.preferences || !this.selectedServiceIds.length) return;
            this.suggestedSlotsLoaded = false;
            this.loadingSuggested = true;
            this.suggestedSlots = [];
            this.showSuggestions = true;

            const params = new URLSearchParams();
            this.selectedServiceIds.forEach(id => params.append('serviceIds[]', id));
            if (this.staffId) params.append('staffId', this.staffId);
            if (this.preferences.days?.length) {
                this.preferences.days.forEach(d => params.append('preferredDays[]', d));
            }
            if (this.preferences.timeFrom) params.append('timeFrom', this.preferences.timeFrom);
            if (this.preferences.timeTo)   params.append('timeTo',   this.preferences.timeTo);
            params.append('limit', '6');

            try {
                const res  = await fetch('/api/booking/suggested-slots?' + params.toString(), { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.suggestedSlots = data.data ?? [];
            } catch (_) {}

            this.loadingSuggested = false;
            this.suggestedSlotsLoaded = true;
        },

        get groupedSuggested() {
            const map = {};
            for (const s of this.suggestedSlots) {
                if (!map[s.date]) map[s.date] = { date: s.date, slots: [] };
                map[s.date].slots.push(s);
            }
            return Object.values(map).slice(0, 3);
        },

        submitBooking() {
            if (this.submitting) return;
            this.submitting = true;
            this.$refs.bookingForm.submit();
        },

        selectSuggestedSlot(dateVal, timeVal) {
            this.date          = dateVal;
            this.slot          = timeVal;
            this.calendarMonth = dateVal.slice(0, 7);
            this.loadAvailableSlots();
        },

        formatSuggestedDate(iso) {
            const s = new Date(iso + 'T00:00:00').toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
            return s.charAt(0).toUpperCase() + s.slice(1);
        },
    };
}
