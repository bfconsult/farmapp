// Vegetation in the farm view: trees, shrubs and undergrowth from
// vegetation assets - one plant (or clump) at a point, a row along a line,
// or an area filled with them. Placeholder art drawn in code, once per
// kind of plant, and reused as sprites.
import { Graphics } from 'pixi.js';
import { area, bounds, contains, seededRandom } from './geo';

// Plant textures are drawn at this many pixels per metre.
const PX_PER_METRE = 10;

// Each kind of plant: its height (m), how far apart they grow (m), the
// smallest it's drawn on screen in the cartoon style (px), how many make
// a clump at a point, and the tint under an area of it.
export const PLANTS = {
    tree: { height: 9, spacing: 7, minPx: 18, clump: 1, tint: 0x5f8a45 },
    shrubs: { height: 2.5, spacing: 3.5, minPx: 10, clump: 3, tint: 0x7c9f55 },
    undergrowth: { height: 0.9, spacing: 2, minPx: 6, clump: 6, tint: 0x8c9a4f },
};

// At most this many plants in one area or row - further apart beyond that.
const MAX_PER_ASSET = 500;
// In the cartoon style, a plant is drawn at most this much bigger than life.
const MAX_CARTOON_SCALE = 4;

const plantFor = (type) => PLANTS[type] ?? PLANTS.tree;

function plantTexture(renderer, draw) {
    const g = new Graphics();
    draw(g);
    const b = g.getLocalBounds();
    const texture = renderer.generateTexture({ target: g, resolution: 2 });
    g.destroy();
    // Anchored where it meets the ground (y = 0).
    return { texture, anchor: { x: -b.x / b.width, y: -b.y / b.height } };
}

// A few of each, so a wood isn't all one tree.
export function makePlantTextures(renderer) {
    const leaves = [0x5d8a4a, 0x6f9652, 0x7a9a5a];
    const tree = leaves.map((green, i) => plantTexture(renderer, (g) => {
        g.ellipse(6, 2, 30, 9).fill({ color: 0x000000, alpha: 0.15 });
        g.poly([-4, 0, 4, 0, 2, -45, -2, -45]).fill(0x6b4f35);
        // A lumpy, gum-tree-ish crown.
        const lumps = [[-16, -52, 15], [14, -55, 14], [0, -68, 17], [-10, -78, 12], [11, -80, 12], [0 + i * 3, -88, 9]];
        for (const [x, y, r] of lumps) g.circle(x, y, r).fill(green);
        for (const [x, y, r] of lumps.slice(2)) g.circle(x - r * 0.3, y - r * 0.3, r * 0.45).fill({ color: 0xffffff, alpha: 0.12 });
    }));
    const shrubs = leaves.map((green) => plantTexture(renderer, (g) => {
        g.ellipse(2, 1, 15, 4).fill({ color: 0x000000, alpha: 0.15 });
        for (const [x, y, r] of [[-7, -8, 8], [7, -8, 8], [0, -15, 10]]) g.circle(x, y, r).fill(green);
        g.circle(-3, -19, 4).fill({ color: 0xffffff, alpha: 0.12 });
    }));
    const undergrowth = [0x7f9a4d, 0x8fa653].map((green) => plantTexture(renderer, (g) => {
        for (const [x, lean] of [[-5, -3], [-2, -1], [1, 1], [4, 3], [6, 4]]) {
            g.moveTo(x, 0).lineTo(x + lean, -9 + Math.abs(lean)).stroke({ width: 1.6, color: green, cap: 'round' });
        }
    }));
    return { tree, shrubs, undergrowth };
}

/**
 * Where each plant of a vegetation asset stands, in metres:
 * [{x, y, size, variant}]. The same each time for the same asset.
 */
export function plantSpots(item, project) {
    const plant = plantFor(item.vegetation_type);
    const random = seededRandom(item.id * 7919 + 17);
    const spot = (x, y) => ({ x, y, size: 0.8 + random() * 0.4, variant: Math.floor(random() * 3), flip: random() < 0.5 });

    if (item.shape) {
        const polygon = item.shape.map(project);
        const spacing = Math.max(plant.spacing, Math.sqrt(Math.abs(area(polygon)) / MAX_PER_ASSET));
        const b = bounds(polygon);
        const spots = [];
        for (let y = b.minY + spacing / 2; y < b.maxY; y += spacing) {
            for (let x = b.minX + spacing / 2; x < b.maxX; x += spacing) {
                const p = { x: x + (random() - 0.5) * spacing * 0.8, y: y + (random() - 0.5) * spacing * 0.8 };
                if (contains(polygon, p)) spots.push(spot(p.x, p.y));
            }
        }
        return spots;
    }

    if (item.line) {
        const points = item.line.map(project);
        let length = 0;
        for (let i = 1; i < points.length; i++) length += Math.hypot(points[i].x - points[i - 1].x, points[i].y - points[i - 1].y);
        const spacing = Math.max(plant.spacing, length / MAX_PER_ASSET);
        const spots = [];
        for (let i = 1; i < points.length; i++) {
            const a = points[i - 1];
            const b = points[i];
            const steps = Math.max(1, Math.round(Math.hypot(b.x - a.x, b.y - a.y) / spacing));
            for (let s = i === 1 ? 0 : 1; s <= steps; s++) {
                const jitter = spacing * 0.15;
                spots.push(spot(
                    a.x + ((b.x - a.x) * s) / steps + (random() - 0.5) * jitter,
                    a.y + ((b.y - a.y) * s) / steps + (random() - 0.5) * jitter,
                ));
            }
        }
        return spots;
    }

    // A point: one tree, or a small clump of shrubs or undergrowth.
    const centre = project(item.point);
    if (plant.clump === 1) return [spot(centre.x, centre.y)];
    return Array.from({ length: plant.clump }, (_, i) => {
        const angle = (i / plant.clump) * Math.PI * 2 + random();
        const r = i === 0 ? 0 : plant.spacing * (0.4 + random() * 0.4);
        return spot(centre.x + Math.cos(angle) * r, centre.y + Math.sin(angle) * r);
    });
}

/**
 * How big a plant's sprite is drawn at this zoom: its real size, or in the
 * cartoon style at least its type's smallest size on screen.
 */
export function plantScale(type, zoom, cartoon) {
    const plant = plantFor(type);
    const grow = cartoon ? Math.min(MAX_CARTOON_SCALE, Math.max(1, plant.minPx / (zoom * plant.height))) : 1;
    return grow / PX_PER_METRE;
}

export function plantTint(type) {
    return plantFor(type).tint;
}
