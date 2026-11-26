// The lie of the land for the farm view: a regular grid of ground heights
// in metres (from the server's PropertyTerrain), and what's drawn from it -
// how high the ground is anywhere, light and shade on the slopes, and the
// contour lines.
//
// Heights are exaggerated: real farm slopes look flat from this angle.

const EXAGGERATION = 2.5;
// ...but the hills are never taller than this share of the property's width.
const MAX_RELIEF_SHARE = 0.15;
// Sunlight from the north-west (x east, y south, z up), as maps are lit.
const SUN = normalise({ x: -1, y: -1, z: 1.2 });

function normalise(v) {
    const length = Math.hypot(v.x, v.y, v.z);
    return { x: v.x / length, y: v.y / length, z: v.z / length };
}

// From the server's grid (null for none) or, without one, flat ground over
// these bounds in metres.
export function makeTerrain(data, project, flatBounds) {
    let grid;
    if (data?.heights?.length) {
        const topLeft = project([data.north, data.west]);
        const bottomRight = project([data.south, data.east]);
        grid = {
            x0: topLeft.x,
            y0: topLeft.y,
            dx: (bottomRight.x - topLeft.x) / (data.columns - 1),
            dy: (bottomRight.y - topLeft.y) / (data.rows - 1),
            columns: data.columns,
            rows: data.rows,
            heights: Float32Array.from(data.heights),
        };
    } else {
        const { minX, minY, maxX, maxY } = flatBounds;
        grid = { x0: minX - 5, y0: minY - 5, dx: maxX - minX + 10, dy: maxY - minY + 10, columns: 2, rows: 2, heights: new Float32Array(4) };
    }

    let lowest = Infinity;
    let highest = -Infinity;
    for (const h of grid.heights) {
        lowest = Math.min(lowest, h);
        highest = Math.max(highest, h);
    }
    const width = Math.max(grid.dx * (grid.columns - 1), grid.dy * (grid.rows - 1));
    const relief = highest - lowest;
    const exaggeration = relief > 0 ? Math.min(EXAGGERATION, (width * MAX_RELIEF_SHARE) / relief) : 0;

    return { ...grid, lowest, highest, exaggeration, hilly: relief > 0 };
}

// Metres above sea level at a point, blended from the grid around it.
export function elevationAt(t, x, y) {
    const fc = Math.max(0, Math.min(t.columns - 1, (x - t.x0) / t.dx));
    const fr = Math.max(0, Math.min(t.rows - 1, (y - t.y0) / t.dy));
    const c = Math.min(t.columns - 2, Math.floor(fc));
    const r = Math.min(t.rows - 2, Math.floor(fr));
    const u = fc - c;
    const v = fr - r;
    const h = t.heights;
    const i = r * t.columns + c;
    const top = h[i] * (1 - u) + h[i + 1] * u;
    const bottom = h[i + t.columns] * (1 - u) + h[i + t.columns + 1] * u;
    return top * (1 - v) + bottom * v;
}

// How far the ground at a point is drawn above the lowest ground, in metres.
export function groundHeight(t, x, y) {
    return t.exaggeration ? (elevationAt(t, x, y) - t.lowest) * t.exaggeration : 0;
}

// Light on the ground at each grid point, 0-255 (255 is full sun), for
// multiplying over the paddock colours. Flat ground is a little shaded,
// so slopes facing the sun can be brighter than it.
export function shading(t) {
    const out = new Uint8ClampedArray(t.columns * t.rows);
    const z = (c, r) => t.heights[Math.max(0, Math.min(t.rows - 1, r)) * t.columns + Math.max(0, Math.min(t.columns - 1, c))] * t.exaggeration;
    for (let r = 0; r < t.rows; r++) {
        for (let c = 0; c < t.columns; c++) {
            const gx = (z(c + 1, r) - z(c - 1, r)) / (2 * Math.abs(t.dx));
            const gy = (z(c, r + 1) - z(c, r - 1)) / (2 * Math.abs(t.dy));
            const n = normalise({ x: -gx, y: -gy, z: 1 });
            const light = Math.max(0, n.x * SUN.x + n.y * SUN.y + n.z * SUN.z) / SUN.z;
            // Gentle: the heights are only about 30 m detail, so strong
            // shading shows up the data's noise as much as the hills.
            out[r * t.columns + c] = Math.round(255 * Math.max(0.6, Math.min(1, 0.9 + 0.45 * (light - 1))));
        }
    }
    return out;
}

// A round number of metres between contour lines, for about ten across
// the land's range of heights; every fifth is a labelled, darker one.
export function contourInterval(lowest, highest) {
    const target = (highest - lowest) / 10;
    return [1, 2, 5, 10, 20, 25, 50, 100].find((step) => step >= target) ?? 100;
}

// The contour lines as segments in metres ({a, b, level}), traced across
// the grid's squares ("marching squares").
export function contourSegments(t, interval) {
    const segments = [];
    if (!t.hilly) return segments;
    const h = t.heights;
    const point = (c, r) => ({ x: t.x0 + c * t.dx, y: t.y0 + r * t.dy });
    const between = (p, q, hp, hq, level) => {
        const f = (level - hp) / (hq - hp);
        return { x: p.x + (q.x - p.x) * f, y: p.y + (q.y - p.y) * f };
    };

    const first = Math.ceil(t.lowest / interval) * interval;
    for (let r = 0; r < t.rows - 1; r++) {
        for (let c = 0; c < t.columns - 1; c++) {
            const corners = [
                { p: point(c, r), h: h[r * t.columns + c] },
                { p: point(c + 1, r), h: h[r * t.columns + c + 1] },
                { p: point(c + 1, r + 1), h: h[(r + 1) * t.columns + c + 1] },
                { p: point(c, r + 1), h: h[(r + 1) * t.columns + c] },
            ];
            const low = Math.min(...corners.map((k) => k.h));
            const high = Math.max(...corners.map((k) => k.h));
            for (let level = Math.max(first, Math.ceil(low / interval) * interval); level < high; level += interval) {
                // Where the level crosses each side of the square.
                const crossings = [];
                for (let i = 0; i < 4; i++) {
                    const a = corners[i];
                    const b = corners[(i + 1) % 4];
                    if ((a.h < level) !== (b.h < level)) crossings.push(between(a.p, b.p, a.h, b.h, level));
                }
                if (crossings.length === 2) {
                    segments.push({ a: crossings[0], b: crossings[1], level });
                } else if (crossings.length === 4) {
                    // A saddle: pair the sides by the square's middle height.
                    const middle = corners.reduce((sum, k) => sum + k.h, 0) / 4;
                    const [s0, s1, s2, s3] = crossings;
                    if ((middle < level) === (corners[0].h < level)) {
                        segments.push({ a: s0, b: s1, level }, { a: s2, b: s3, level });
                    } else {
                        segments.push({ a: s3, b: s0, level }, { a: s1, b: s2, level });
                    }
                }
            }
        }
    }
    return segments;
}

// A few places along each labelled contour for its height, spread apart.
export function contourLabelSpots(segments, interval, spacing) {
    const spots = [];
    const major = interval * 5;
    for (const segment of segments) {
        if (Math.abs(segment.level / major - Math.round(segment.level / major)) > 1e-6) continue;
        const at = { x: (segment.a.x + segment.b.x) / 2, y: (segment.a.y + segment.b.y) / 2 };
        const sameLevel = spots.filter((spot) => spot.level === segment.level);
        if (sameLevel.length >= 3) continue;
        if (sameLevel.some((spot) => Math.hypot(spot.x - at.x, spot.y - at.y) < spacing)) continue;
        spots.push({ ...at, level: segment.level });
    }
    return spots;
}

// Polygon edges split into short pieces, so fences follow the ground.
export function densify(polygon, step) {
    if (!Number.isFinite(step)) return polygon;
    const out = [];
    for (let i = 0; i < polygon.length; i++) {
        const a = polygon[i];
        const b = polygon[(i + 1) % polygon.length];
        const pieces = Math.max(1, Math.ceil(Math.hypot(b.x - a.x, b.y - a.y) / step));
        for (let s = 0; s < pieces; s++) {
            out.push({ x: a.x + ((b.x - a.x) * s) / pieces, y: a.y + ((b.y - a.y) * s) / pieces });
        }
    }
    return out;
}
