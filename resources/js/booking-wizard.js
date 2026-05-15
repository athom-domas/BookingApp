export function bookingWizard(allServices, allStaff) {
    return {
        // navigation
        step: 1,
        completed: [],

        // data
        allServices,
        allStaff,

        // step 1
        selectedServiceIds: [],

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
        paymentMethod: null,

        // step 5
        notes: '',

        // ── init ──────────────────────────────────────────────────────────
        init() {
            const now = new Date();
            this.calendarMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
            this.$watch('step', (v) => {
                if (v === 3) this.loadAvailableDates();
            });
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
                this.paymentMethod = null;
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
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const d = new Date(year, month - 2, 1);
            const newMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const today = new Date().toISOString().slice(0, 7);
            if (newMonth < today) return;
            this.calendarMonth = newMonth;
            this.loadAvailableDates();
        },

        nextMonth() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const d = new Date(year, month, 1);
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
    };
}
