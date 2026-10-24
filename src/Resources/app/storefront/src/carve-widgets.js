export async function copyCoupon(button, clipboard = globalThis.navigator?.clipboard) {
    const code = button.dataset.code;
    if (!code || !clipboard?.writeText) return false;
    try {
        await clipboard.writeText(code);
        button.textContent = 'Copied';
        button.setAttribute('aria-label', 'Copied coupon ' + code);
        return true;
    } catch {
        return false;
    }
}

export function countdownText(date, now = Date.now()) {
    const target = Date.parse(date + 'T00:00:00Z');
    if (!Number.isFinite(target)) return date;
    const minutes = Math.ceil((target - now) / 60000);
    if (minutes <= 0) return 'Ended';
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const rest = minutes % 60;
    return `${days}d ${String(hours).padStart(2, '0')}h ${String(rest).padStart(2, '0')}m`;
}

export function initCarveWidgets(root = document) {
    root.addEventListener('click', (event) => {
        const button = event.target instanceof globalThis.Element ? event.target.closest('.carve-coupon__copy') : null;
        if (button && root.contains(button)) void copyCoupon(button);
    });
    const timers = root.querySelectorAll('.carve-countdown[data-target]');
    if (timers.length === 0) return;
    const update = () => {
        const now = Date.now();
        for (const timer of timers) timer.textContent = countdownText(timer.dataset.target, now);
    };
    update();
    globalThis.setInterval(update, 60000);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initCarveWidgets());
    else initCarveWidgets();
}
