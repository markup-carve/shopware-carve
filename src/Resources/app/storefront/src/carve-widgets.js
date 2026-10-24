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

export function loadVideo(button) {
    const id = button.dataset.videoId;
    if (!/^[A-Za-z0-9_-]{11}$/.test(id ?? '')) return false;
    const frame = button.ownerDocument.createElement('iframe');
    frame.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1`;
    frame.title = 'YouTube video';
    frame.referrerPolicy = 'no-referrer';
    frame.allow = 'autoplay; encrypted-media; picture-in-picture';
    frame.allowFullscreen = true;
    frame.loading = 'lazy';
    button.replaceWith(frame);
    return true;
}

export function initCarveWidgets(root = document) {
    root.addEventListener('click', (event) => {
        const target = event.target instanceof globalThis.Element ? event.target : null;
        const coupon = target?.closest('.carve-coupon__copy');
        if (coupon && root.contains(coupon)) void copyCoupon(coupon);
        const video = target?.closest('.carve-video__load');
        if (video && root.contains(video)) loadVideo(video);
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
