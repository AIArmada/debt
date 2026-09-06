window.dirtyForm = (state) => ({
    state,
    original: null,
    initialized: false,

    init() {
        this.original = this.snapshot();
        this.initialized = true;
        this.beforeUnload = this.beforeUnload.bind(this);
        this.confirmNavigation = this.confirmNavigation.bind(this);

        this.$watch('state', () => this.syncWarning(), { deep: true });
        window.addEventListener('beforeunload', this.beforeUnload);
        document.addEventListener('livewire:navigate', this.confirmNavigation);
        this.syncWarning();
    },

    snapshot() {
        return JSON.stringify(Object.fromEntries(
            Object.entries(this.state).map(([key, value]) => [key, value ?? '']),
        ));
    },

    isDirty() {
        return this.initialized && this.snapshot() !== this.original;
    },

    syncWarning() {
        this.$root.dataset.dirty = this.isDirty() ? 'true' : 'false';
    },

    beforeUnload(event) {
        if (this.isDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    },

    confirmNavigation(event) {
        if (this.isDirty() && !window.confirm('You have unsaved changes. Leave this page?')) {
            event.preventDefault();
        }
    },
});
