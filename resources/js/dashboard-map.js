import jsVectorMap from 'jsvectormap';
import 'jsvectormap/dist/jsvectormap.css';

// Light → dark; the darkest is the admin dashboard's primary blue.
const SHADES = { 1: '#d9e6f8', 2: '#b3cdf0', 3: '#86aee4', 4: '#5c91d8', 5: '#467fcf' };
const OUTLINES = { 1: '#86aee4', 2: '#6b9ae0', 3: '#467fcf', 4: '#3a6fbc', 5: '#2f5ea0' };

/** World map for the dashboard's "Visitors by country" card, shaded by how many users were last seen in each country. */
export async function mountCountryMap(container) {
    // The bundled world map registers itself through a global `jsVectorMap.addMap`, so that global has to exist before it
    // loads. The library keeps its maps private, so the shim also holds on to the world map for its country names.
    let world;
    window.jsVectorMap = { addMap(name, data) { world = data; jsVectorMap.addMap(name, data); } };
    await import('jsvectormap/dist/maps/world.js');
    delete window.jsVectorMap;

    const counts = JSON.parse(container.dataset.countries || '{}');
    const nameOf = code => world.paths[code]?.name ?? code;
    const most = Math.max(1, ...Object.values(counts));

    // The server renders ISO codes (so the list still reads without JS); the map data knows the country names.
    document.querySelectorAll('[data-country-code]').forEach(el => { el.textContent = nameOf(el.dataset.countryCode); });

    // This version only maps discrete values to colours, so bucket each country's count into five shades.
    const buckets = Object.fromEntries(Object.entries(counts).map(([code, count]) => [code, 1 + Math.floor((count / most) * 4)]));

    new jsVectorMap({
        selector: container,
        map: 'world',
        backgroundColor: 'transparent',
        zoomOnScroll: false,
        regionStyle: {
            initial: { fill: '#eef0f5', stroke: '#ffffff', strokeWidth: 0.6 },
            hover: { fillOpacity: 0.8 },
        },
        series: {
            regions: [
                { attribute: 'fill', values: buckets, scale: SHADES },
                // Small countries vanish at world scale, so countries with users also get a darker, thicker outline.
                { attribute: 'stroke', values: buckets, scale: OUTLINES },
                { attribute: 'strokeWidth', values: buckets, scale: Object.fromEntries(Object.keys(SHADES).map(bucket => [bucket, 1.4])) },
            ],
        },
        onRegionTooltipShow(_event, tooltip, code) {
            const count = counts[code] ?? 0;
            tooltip.text(`<b>${nameOf(code)}</b><br>${count.toLocaleString()} ${window.i18n?.[count === 1 ? 'user' : 'users'] ?? (count === 1 ? 'user' : 'users')}`, true);
        },
    });
}
