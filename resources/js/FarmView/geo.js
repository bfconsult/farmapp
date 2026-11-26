// Geometry for the farm view: the property's [lat, lng] points become flat
// metres around its middle (x east, y south), and those become the angled
// 3D ("isometric") screen position, with z the height above the ground.

const METRES_PER_DEGREE_LAT = 110540;

export function makeProjection(points) {
    const lat0 = points.reduce((sum, [lat]) => sum + lat, 0) / points.length;
    const lng0 = points.reduce((sum, [, lng]) => sum + lng, 0) / points.length;
    const metresPerDegreeLng = 111320 * Math.cos((lat0 * Math.PI) / 180);

    return ([lat, lng]) => ({
        x: (lng - lng0) * metresPerDegreeLng,
        y: -(lat - lat0) * METRES_PER_DEGREE_LAT,
    });
}

// The 2:1 view used by most angled 2D games: north is up and to the right.
export function iso(x, y, z = 0) {
    return { x: x - y, y: (x + y) / 2 - z };
}

export function isoPoints(points, z = 0) {
    return points.flatMap(({ x, y }) => {
        const p = iso(x, y, z);
        return [p.x, p.y];
    });
}

export function contains(polygon, { x, y }) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const a = polygon[i];
        const b = polygon[j];
        if ((a.y > y) !== (b.y > y) && x < ((b.x - a.x) * (y - a.y)) / (b.y - a.y) + a.x) {
            inside = !inside;
        }
    }
    return inside;
}

export function bounds(points) {
    const xs = points.map((p) => p.x);
    const ys = points.map((p) => p.y);
    return { minX: Math.min(...xs), maxX: Math.max(...xs), minY: Math.min(...ys), maxY: Math.max(...ys) };
}

export function centroid(polygon) {
    // The area-weighted centre, which stays inside most paddock shapes
    // better than the average of the corners does.
    let area = 0;
    let cx = 0;
    let cy = 0;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const cross = polygon[j].x * polygon[i].y - polygon[i].x * polygon[j].y;
        area += cross;
        cx += (polygon[j].x + polygon[i].x) * cross;
        cy += (polygon[j].y + polygon[i].y) * cross;
    }
    if (Math.abs(area) < 1e-6) {
        const b = bounds(polygon);
        return { x: (b.minX + b.maxX) / 2, y: (b.minY + b.maxY) / 2 };
    }
    return { x: cx / (3 * area), y: cy / (3 * area) };
}

export function area(polygon) {
    let sum = 0;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        sum += polygon[j].x * polygon[i].y - polygon[i].x * polygon[j].y;
    }
    return Math.abs(sum / 2);
}

// A random point inside the polygon, near `around` if given.
export function randomPointIn(polygon, random, around = null, radius = 0) {
    const b = bounds(polygon);
    for (let attempt = 0; attempt < 60; attempt++) {
        const point = around
            ? { x: around.x + (random() * 2 - 1) * radius, y: around.y + (random() * 2 - 1) * radius }
            : { x: b.minX + random() * (b.maxX - b.minX), y: b.minY + random() * (b.maxY - b.minY) };
        if (contains(polygon, point)) {
            return point;
        }
    }
    return around ?? centroid(polygon);
}

// The closest point on any of the polygons' edges, with that edge's
// direction - where a gate sensor's fence is.
export function nearestEdge(point, polygons) {
    let best = null;
    for (const polygon of polygons) {
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const a = polygon[j];
            const b = polygon[i];
            const dx = b.x - a.x;
            const dy = b.y - a.y;
            const length = Math.hypot(dx, dy);
            if (length < 0.01) continue;
            const t = Math.max(0, Math.min(1, ((point.x - a.x) * dx + (point.y - a.y) * dy) / (length * length)));
            const onEdge = { x: a.x + t * dx, y: a.y + t * dy };
            const distance = Math.hypot(point.x - onEdge.x, point.y - onEdge.y);
            if (!best || distance < best.distance) {
                best = { point: onEdge, direction: { x: dx / length, y: dy / length }, distance };
            }
        }
    }
    return best;
}

// A repeatable random sequence, so a mob's sheep start in the same places
// each time the page loads.
export function seededRandom(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}
