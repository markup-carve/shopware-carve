/**
 * Lazy-loads Mermaid.js and/or Chart.js from jsDelivr CDN only when the page
 * contains diagram markers emitted by carve-php's FencedRenderExtension.
 *
 * Mermaid marker: <pre class="mermaid">...</pre>
 * Chart marker:   <div class="chart"><script type="application/json">...</script></div>
 *
 * Nothing loads on pages without markers (zero cost).
 */

const MERMAID_CDN = 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';
const CHARTJS_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4/auto/+esm';

async function initMermaid(markers) {
    try {
        const m = await import(/* webpackIgnore: true */ MERMAID_CDN);
        m.default.initialize({ startOnLoad: false });
        await m.default.run({ nodes: Array.from(markers) });
    } catch (e) {
        // leave original code block visible on failure
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
            const canvas = document.createElement('canvas');
            marker.appendChild(canvas);
            try {
                new Chart(canvas, config);
            } catch (chartErr) {
                console.error('[carve] Chart render failed', chartErr);
                canvas.remove();
            }
        }
    } catch (e) {
        console.error('[carve] Chart.js init failed', e);
    }
}

export function initCarveDiagrams() {
    const mermaidMarkers = document.querySelectorAll('pre.mermaid');
    const chartMarkers = document.querySelectorAll('div.chart');

    if (mermaidMarkers.length > 0) {
        initMermaid(mermaidMarkers);
    }
    if (chartMarkers.length > 0) {
        initCharts(chartMarkers);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCarveDiagrams);
} else {
    initCarveDiagrams();
}
