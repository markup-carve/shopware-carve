/**
 * Lazy-loads Mermaid.js and/or Chart.js from jsDelivr CDN only when the page
 * contains diagram markers emitted by carve-php's FencedRenderExtension, and
 * renders PlantUML markers via the external Kroki service.
 *
 * Mermaid marker:  <pre class="mermaid">...</pre>
 * Chart marker:    <div class="chart"><script type="application/json">...</script></div>
 * PlantUML marker: <pre class="plantuml">...</pre>
 *
 * PlantUML has no in-browser renderer, so each block's source is POSTed to
 * Kroki (https://kroki.io/plantuml/svg) and the returned SVG replaces the
 * <pre> as an inline <img> data URI. Nothing loads/fetches on pages without
 * markers (zero cost).
 */

const MERMAID_CDN = 'https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.esm.min.mjs';
const CHARTJS_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/auto/+esm';
const DEFAULT_KROKI_URL = 'https://kroki.io/plantuml/svg';

async function initMermaid(markers) {
    try {
        const m = await import(/* webpackIgnore: true */ MERMAID_CDN);
        m.default.initialize({ startOnLoad: false });
        await m.default.run({ nodes: Array.from(markers) });
    } catch (e) {
        // leave original code block visible on failure
        for (const marker of markers) delete marker.dataset.carveRendered;
        console.error('[carve] Mermaid init failed', e);
    }
}

async function initCharts(markers) {
    try {
        // chart.js@4 +esm exposes Chart as a named export; .default is the module namespace object.
        const mod = await import(/* webpackIgnore: true */ CHARTJS_CDN);
        const Chart = mod.Chart ?? mod.default;
        for (const marker of markers) {
            const scriptEl = marker.querySelector('script[type="application/json"]');
            if (!scriptEl) continue;
            let config;
            try {
                config = JSON.parse(scriptEl.textContent || '');
            } catch (parseErr) {
                console.error('[carve] Chart config parse failed', parseErr);
                continue;
            }
            if (marker.dataset.carveRendered) continue;
            marker.dataset.carveRendered = '1';
            const fallback = marker.querySelector('.carve-chart-data') ?? document.createElement('pre');
            fallback.className = 'carve-chart-data';
            fallback.textContent = JSON.stringify(config.data ?? {}, null, 2);
            if (!fallback.parentElement) {
                const details = document.createElement('details');
                const summary = document.createElement('summary');
                summary.textContent = 'Chart data';
                details.appendChild(summary);
                details.appendChild(fallback);
                marker.appendChild(details);
            }
            const canvas = document.createElement('canvas');
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', config.options?.plugins?.title?.text || 'Chart; data follows');
            marker.appendChild(canvas);
            try {
                new Chart(canvas, config);
            } catch (chartErr) {
                console.error('[carve] Chart render failed', chartErr);
                canvas.remove();
                delete marker.dataset.carveRendered;
            }
        }
    } catch (e) {
        console.error('[carve] Chart.js init failed', e);
    }
}

async function initPlantuml(markers) {
    for (const marker of markers) {
        // Idempotency: skip blocks already rendered (or in flight).
        if (marker.dataset.carveRendered) continue;
        marker.dataset.carveRendered = '1';

        const source = marker.textContent || '';
        if (source.trim() === '') continue;

        try {
            const configured = document.querySelector('[data-carve-kroki-url]')?.dataset.carveKrokiUrl || DEFAULT_KROKI_URL;
            const endpoint = new URL(configured, window.location.origin);
            if (!['https:', 'http:'].includes(endpoint.protocol)) throw new Error('Unsupported Kroki URL');
            const response = await fetch(endpoint.href, {
                method: 'POST',
                headers: { 'Content-Type': 'text/plain' },
                body: source,
            });
            if (!response.ok) {
                throw new Error('Kroki responded ' + response.status);
            }
            const svg = await response.text();
            const img = document.createElement('img');
            img.className = 'plantuml';
            img.alt = 'PlantUML diagram';
            img.decoding = 'async';
            img.loading = 'lazy';
            // Inline the SVG as a base64 data URI (btoa needs a binary-safe string).
            img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
            marker.replaceWith(img);
        } catch (e) {
            // leave the original code block visible on failure, and clear the
            // guard so a later re-init can retry.
            delete marker.dataset.carveRendered;
            console.error('[carve] PlantUML render failed', e);
        }
    }
}

export function initCarveDiagrams() {
    const mermaidMarkers = document.querySelectorAll('pre.mermaid:not([data-carve-rendered])');
    for (const marker of mermaidMarkers) marker.dataset.carveRendered = '1';
    const chartMarkers = document.querySelectorAll('div.chart');
    const plantumlMarkers = document.querySelectorAll('pre.plantuml');

    if (mermaidMarkers.length > 0) {
        initMermaid(mermaidMarkers);
    }
    if (chartMarkers.length > 0) {
        initCharts(chartMarkers);
    }
    if (plantumlMarkers.length > 0) {
        initPlantuml(plantumlMarkers);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCarveDiagrams);
} else {
    initCarveDiagrams();
}
