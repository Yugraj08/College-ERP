document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const originalFetch = window.fetch;
    window.fetch = function (url, options = {}) {
        options.headers = options.headers || {};
        if (!(options.headers instanceof Headers)) {
            options.headers = new Headers(options.headers);
        }

        if (csrfToken && !options.headers.has('X-CSRF-TOKEN') && !options.headers.has('x-csrf-token')) {
            options.headers.set('X-CSRF-TOKEN', csrfToken);
        }

        if (!options.headers.has('X-Requested-With')) {
            options.headers.set('X-Requested-With', 'XMLHttpRequest');
        }

        return originalFetch(url, options);
    };
});
