import { Passkeys } from '@laravel/passkeys';

let passkeyComponentsRegistered = false;

function registerPasskeyComponents() {
    if (passkeyComponentsRegistered || !window.Alpine) {
        return;
    }

    passkeyComponentsRegistered = true;
    window.Alpine.data('passkeyVerify', () => ({
        supported: false,
        loading: false,
        error: null,

        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },

        init() {
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },

        async verify() {
            this.loading = true;
            this.error = null;

            try {
                const response = await window.Passkeys.verify({
                    routes: {
                        options: this.$el.dataset.optionsRoute,
                        submit: this.$el.dataset.submitRoute,
                    },
                });
                window.Livewire.navigate(response.redirect || '/');
            } catch (error) {
                if (error.constructor?.name !== 'UserCancelledError') {
                    this.error = error.message;
                }
            } finally {
                this.loading = false;
            }
        },
    }));

    window.Alpine.data('passkeyRegistration', () => ({
        supported: false,
        showForm: false,
        name: '',
        loading: false,
        error: null,

        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },

        getDefaultPasskeyName() {
            const userAgent = navigator.userAgent;
            const browser = [
                { pattern: /Edg|Edge/, name: 'Edge' },
                { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
                { pattern: /Firefox|FxiOS/, name: 'Firefox' },
                { pattern: /Chrome|CriOS/, name: 'Chrome' },
                { pattern: /Safari/, name: 'Safari' },
            ].find(({ pattern }) => pattern.test(userAgent))?.name;
            const operatingSystem = [
                { pattern: /iPhone/, name: 'iPhone' },
                { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
                { pattern: /Android/, name: 'Android' },
                { pattern: /Mac/, name: 'Mac' },
                { pattern: /Windows/, name: 'Windows' },
            ].find(({ pattern }) => pattern.test(userAgent))?.name;

            return [browser, operatingSystem].filter(Boolean).join(' on ') || '';
        },

        init() {
            this.name = this.getDefaultPasskeyName();
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },

        openForm() {
            this.showForm = true;
            this.$nextTick(() => this.$refs.passkeyNameInput?.focus());
        },

        async register() {
            if (!this.name.trim()) {
                return;
            }

            this.loading = true;
            this.error = null;

            try {
                await window.Passkeys.register({ name: this.name });
                this.name = '';
                this.showForm = false;
                await this.$wire.loadPasskeys();
            } catch (error) {
                if (error.constructor?.name !== 'UserCancelledError') {
                    this.error = error.message;
                }
            } finally {
                this.loading = false;
            }
        },

        cancel() {
            this.showForm = false;
            this.name = '';
            this.error = null;
        },
    }));
}

document.addEventListener('alpine:init', registerPasskeyComponents, { once: true });
registerPasskeyComponents();

window.Passkeys = Passkeys;
window.dispatchEvent(new CustomEvent('passkeys:ready'));
