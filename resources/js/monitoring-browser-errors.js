/**
 * Opt-in browser error collection for System Monitoring (docs/system-monitoring-prd.md §13.2).
 * Only active when the server renders <meta name="monitoring-browser-errors">. Sends the error
 * message, page path, script file name, and line — never DOM, form input, cookies, or storage.
 */
export function registerMonitoringBrowserErrors() {
    const meta = document.querySelector('meta[name="monitoring-browser-errors"]');
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !csrf || !window.fetch) {
        return;
    }

    const sent = new Set();
    const maxReports = 5;

    const report = (message, source, line) => {
        const text = String(message || 'Unknown error').slice(0, 300);
        const key = `${text}|${source}|${line}`;
        if (sent.has(key) || sent.size >= maxReports) {
            return;
        }
        sent.add(key);

        fetch(meta.content, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf.content },
            body: JSON.stringify({
                message: text,
                page: window.location.pathname,
                source: source ? String(source).split('?')[0].split('/').pop() : null,
                line: Number.isInteger(line) ? line : null,
                browser: navigator.userAgent.slice(0, 120),
            }),
        }).catch(() => {});
    };

    window.addEventListener('error', (event) => report(event.message, event.filename, event.lineno));
    window.addEventListener('unhandledrejection', (event) => report(event.reason?.message ?? event.reason, null, null));
}
