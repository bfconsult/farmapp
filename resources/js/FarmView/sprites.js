// Placeholder art for the farm view, drawn in code: animals are drawn once
// to a texture and reused; props (gates, tanks, pumps) are drawn in
// metres around the point they stand on, in the angled view.
import { Container, Graphics, Text } from 'pixi.js';
import { iso } from './geo';

// Animal textures are drawn at this many pixels per metre.
export const TEXTURE_PX_PER_METRE = 32;

function animalTexture(renderer, draw) {
    const g = new Graphics();
    draw(g);
    const b = g.getLocalBounds();
    const texture = renderer.generateTexture({ target: g, resolution: 2 });
    g.destroy();
    // Anchored at its feet (y = 0), which is where it stands.
    return { texture, anchor: { x: -b.x / b.width, y: -b.y / b.height } };
}

export function makeAnimalTextures(renderer) {
    const sheep = animalTexture(renderer, (g) => {
        g.ellipse(0, 0, 18, 4).fill({ color: 0x000000, alpha: 0.15 });
        for (const x of [-14, -7, 8, 14]) {
            g.rect(x - 1.5, -12, 3, 12).fill(0x3b3632);
        }
        g.ellipse(0, -20, 21, 12).fill(0xf4f1ea).stroke({ width: 1.5, color: 0xb9b3a7 });
        for (const [x, y, r] of [[-10, -29, 6], [0, -31, 7], [10, -29, 6]]) {
            g.circle(x, y, r).fill(0xf4f1ea);
        }
        g.ellipse(22, -24, 7, 6).fill(0x3b3632);
        g.ellipse(17, -30, 3.5, 1.8).fill(0x3b3632);
    });

    // A black (Angus-style, polled) cow, with a grey muzzle so its head
    // reads against its body.
    const blackCow = animalTexture(renderer, (g) => {
        g.ellipse(0, 0, 30, 6).fill({ color: 0x000000, alpha: 0.15 });
        for (const x of [-25, -16, 16, 25]) {
            g.rect(x - 2.5, -20, 5, 20).fill(0x141414);
        }
        g.roundRect(-30, -44, 60, 26, 10).fill(0x262626).stroke({ width: 1.5, color: 0x0a0a0a });
        g.ellipse(-6, -38, 16, 4).fill({ color: 0xffffff, alpha: 0.12 });
        g.roundRect(26, -52, 14, 17, 5).fill(0x1f1f1f).stroke({ width: 1.5, color: 0x0a0a0a });
        g.roundRect(32, -41, 9, 7, 3).fill(0x6b6b6b);
        g.ellipse(27, -50, 3, 2).fill(0x1f1f1f);
    });

    // Keyed as LIVESTOCK_SPRITES (Utils/livestockSprites.js).
    return { sheep_white: sheep, cow_black: blackCow };
}

function line(g, from, to, width, color) {
    g.moveTo(from.x, from.y).lineTo(to.x, to.y).stroke({ width, color, cap: 'round' });
}

const rotate = ({ x, y }, degrees) => {
    const r = (degrees * Math.PI) / 180;
    return { x: x * Math.cos(r) - y * Math.sin(r), y: x * Math.sin(r) + y * Math.cos(r) };
};

/**
 * A farm gate across the fence `direction` (a unit vector in metres),
 * swung open on its hinge when `open`. Grey when its sensor isn't
 * reporting; plain timber when it has none.
 *
 * Its real width along the fence, so gates don't overlap; `lift` makes it
 * taller to match a fence drawn taller than life, and `pixel` (metres per
 * screen pixel, or 0) keeps its posts and rails a few pixels thick.
 */
export function drawGate(g, { direction, open, active, monitored = true, lift = 1, pixel = 0 }) {
    const width = 4;
    const hinge = { x: -direction.x * width / 2, y: -direction.y * width / 2 };
    const latch = { x: direction.x * width / 2, y: direction.y * width / 2 };
    const swung = rotate(direction, 70);
    const end = open ? { x: hinge.x + swung.x * width, y: hinge.y + swung.y * width } : latch;
    const color = !monitored ? 0x9a7650 : !active ? 0x9ca3af : open ? 0xd97706 : 0x7c8794;
    const at = (p, z) => iso(p.x, p.y, z * lift);
    const thick = (metres, px) => Math.max(metres, px * pixel);

    // Posts.
    for (const post of [hinge, latch]) {
        line(g, at(post, 0), at(post, 1.5), thick(0.18, 3), 0x6b4f35);
    }
    // The gate: rails, ends and a brace.
    for (const z of [0.3, 0.75, 1.2]) {
        line(g, at(hinge, z), at(end, z), thick(0.08, 1.6), color);
    }
    line(g, at(hinge, 0.3), at(hinge, 1.2), thick(0.1, 2), color);
    line(g, at(end, 0.3), at(end, 1.2), thick(0.1, 2), color);
    line(g, at(hinge, 0.3), at(end, 1.2), thick(0.06, 1.2), color);
}

/**
 * A round water tank with a sight glass showing its level (0-100, or null
 * when unknown).
 */
export function drawTank(g, { percent, low, active }) {
    const r = 1.6;
    const h = 2.4;
    const rx = r * Math.SQRT2;
    const ry = rx / 2;
    const shell = active ? 0xb7c4a5 : 0xc4c8cc;

    g.ellipse(0.5, 0.25, rx * 1.15, ry * 1.15).fill({ color: 0x000000, alpha: 0.15 });
    g.ellipse(0, 0, rx, ry).fill(0x8f9c80);
    g.rect(-rx, -h, rx * 2, h).fill(shell);
    // Corrugations down the front.
    for (let i = 1; i < 12; i++) {
        const x = -rx + (i * 2 * rx) / 12;
        const front = ry * Math.sqrt(Math.max(0, 1 - (x / rx) ** 2));
        line(g, { x, y: front - h }, { x, y: front }, 0.03, 0x93a283);
    }
    g.ellipse(0, -h, rx, ry).fill(active ? 0xd3dcc6 : 0xdadde0).stroke({ width: 0.05, color: 0x93a283 });

    // Sight glass on the front.
    const top = ry - h + 0.35;
    const bottom = ry - 0.25;
    g.roundRect(-0.28, top, 0.56, bottom - top, 0.1).fill(0x334155);
    if (percent != null) {
        const water = (bottom - top - 0.1) * Math.max(0, Math.min(100, percent)) / 100;
        g.rect(-0.2, bottom - 0.05 - water, 0.4, water).fill(low ? 0xf59e0b : 0x3b82f6);
    }
}

/**
 * A stock trough, round or long (its size in metres), turned with the
 * view's angle, with water to `percent` full.
 */
export function drawTrough(g, { shape = 'round', length = 1.5, width = 1.5, angle = 0, percent = 100, low = false }) {
    const depth = 0.5;
    const water = low ? 0xf59e0b : 0x4f9bd9;
    const level = depth * 0.85 * Math.max(0.15, Math.min(100, percent ?? 100) / 100);
    if (shape === 'round') {
        // Circles in the angled view are ellipses twice as wide as tall.
        const rx = (length / 2) * Math.SQRT2;
        const ry = rx / 2;
        g.ellipse(0.2, 0.1, rx * 1.15, ry * 1.15).fill({ color: 0x000000, alpha: 0.12 });
        g.ellipse(0, 0, rx, ry).fill(0x9ca3a5);
        g.rect(-rx, -depth, rx * 2, depth).fill(0xb8bdbf);
        g.ellipse(0, -depth, rx, ry).fill(0xcfd3d4);
        g.ellipse(0, -depth, rx * 0.88, ry * 0.88).fill(0x6b7280);
        g.ellipse(0, -level, rx * 0.86, ry * 0.86).fill(water);
        return;
    }

    // A long trough: a box, its corners turned with the view.
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    const corner = (x, y, z) => iso(x * cos - y * sin, x * sin + y * cos, z);
    const l = length / 2;
    const w = width / 2;
    const ring = (inset, z) => [[-l + inset, -w + inset], [l - inset, -w + inset], [l - inset, w - inset], [-l + inset, w - inset]]
        .map(([x, y]) => corner(x, y, z));
    const face = (points, color) => g.poly(points.flatMap((p) => [p.x, p.y])).fill(color);
    // Its shadow: the outline, a little bigger and off to one side.
    face(ring(-0.15, 0).map((p) => ({ x: p.x + 0.15, y: p.y + 0.08 })), { color: 0x000000, alpha: 0.12 });
    const bottom = ring(0, 0);
    const top = ring(0, depth);
    // The sides, back ones first.
    const sides = [0, 1, 2, 3].map((i) => ({ i, depth: bottom[i].y + bottom[(i + 1) % 4].y })).sort((a, b) => a.depth - b.depth);
    for (const { i } of sides) {
        const j = (i + 1) % 4;
        face([bottom[i], bottom[j], top[j], top[i]], 0xb8bdbf);
    }
    face(top, 0xcfd3d4);
    face(ring(0.06, depth), 0x6b7280);
    face(ring(0.07, level), water);
}

/**
 * A pump: a red horizontal cylinder (the pump body) lying on the ground
 * with its motor, a cube, at one end - about two metres long. Deeper red when
 * its sensor isn't reporting (or it has none). Returns the fan on the
 * motor's top, which turns while it runs.
 */
export function drawPump(g, { running, active }) {
    const r = 0.34;              // the body's radius
    const x0 = -0.9;             // the body's far end
    const x1 = 0.3;              // where it meets the motor
    const c = 0.64;              // the motor's size
    const steps = 20;
    const body = active ? 0xd12a1f : 0xa8322a;
    const motor = active ? 0x2f3640 : 0x6b7280;
    const shade = (color, amount) => {
        const k = 1 + amount;
        const ch = (v) => Math.max(0, Math.min(255, Math.round(v * k)));
        return (ch((color >> 16) & 255) << 16) | (ch((color >> 8) & 255) << 8) | ch(color & 255);
    };
    const poly = (points, color) => g.poly(points.flatMap((p) => [p.x, p.y])).fill(color);
    // A point on the body's surface at angle t round its axis, at x.
    const rim = (x, t) => iso(x, r * Math.cos(t), r + r * Math.sin(t));

    g.ellipse(0.1, 0.24, 1.5, 0.64).fill({ color: 0x000000, alpha: 0.14 });

    // The body: strips round it, those facing away first, lit from above.
    const strips = [];
    for (let i = 0; i < steps; i++) {
        const a = (i / steps) * Math.PI * 2;
        const b = ((i + 1) / steps) * Math.PI * 2;
        const mid = (a + b) / 2;
        // Facing the viewer (who looks from +y and above) or away.
        const facing = Math.cos(mid) * 0.7 + Math.sin(mid) * 0.7;
        strips.push({ a, b, facing, light: Math.sin(mid) * 0.25 + Math.cos(mid) * 0.08 });
    }
    strips.sort((p, q) => p.facing - q.facing);
    // The far end's flange, behind everything.
    poly(Array.from({ length: steps }, (_, i) => rim(x0 - 0.04, (i / steps) * Math.PI * 2)), shade(body, -0.35));
    for (const { a, b, light } of strips) {
        poly([rim(x0, a), rim(x1, a), rim(x1, b), rim(x0, b)], shade(body, light));
    }

    // The motor: its three visible faces.
    const corner = (x, y, z) => iso(x, y, z);
    const h = -c / 2;
    const k = c / 2;
    poly([corner(x1, h, c), corner(x1 + c, h, c), corner(x1 + c, k, c), corner(x1, k, c)], shade(motor, 0.35));
    poly([corner(x1 + c, h, 0), corner(x1 + c, k, 0), corner(x1 + c, k, c), corner(x1 + c, h, c)], shade(motor, -0.1));
    poly([corner(x1, k, 0), corner(x1 + c, k, 0), corner(x1 + c, k, c), corner(x1, k, c)], motor);

    // Status light on the motor's side.
    const light = corner(x1 + c * 0.5, k, c * 0.7);
    g.circle(light.x, light.y, 0.09).fill(!active ? 0x9ca3af : running ? 0x22c55e : 0x94a3b8);

    // The cooling fan on top, which turns while it runs.
    const fan = new Graphics();
    const fr = 0.2;
    fan.circle(0, 0, fr).stroke({ width: 0.05, color: 0xe2e8f0 });
    for (let i = 0; i < 3; i++) {
        const a = (i * Math.PI * 2) / 3;
        fan.moveTo(0, 0).lineTo(Math.cos(a) * fr, Math.sin(a) * fr).stroke({ width: 0.05, color: 0xe2e8f0 });
    }
    const top = corner(x1 + c / 2, 0, c);
    fan.position.set(top.x, top.y);
    fan.scale.y = 0.5;
    return fan;
}

const TONES = {
    active: 0x2563eb,
    attention: 0xd97706,
    muted: 0x9ca3af,
};

/**
 * A name tag: a rounded card with a coloured dot, the name and a second
 * line (state, head count), drawn at screen size.
 */
export function makeLabel(title, detail, tone = 'muted') {
    const label = new Container();
    const name = new Text({ text: title, style: { fontFamily: 'Figtree, sans-serif', fontSize: 12, fontWeight: '600', fill: 0x111827 } });
    const sub = new Text({ text: detail ?? '', style: { fontFamily: 'Figtree, sans-serif', fontSize: 11, fill: 0x4b5563 } });
    name.resolution = sub.resolution = 2;
    name.position.set(18, 5);
    sub.position.set(18, 20);
    const width = Math.max(name.width, detail ? sub.width : 0) + 26;
    const height = detail ? 37 : 23;

    const bg = new Graphics()
        .roundRect(0, 0, width, height, 8)
        .fill({ color: 0xffffff, alpha: 0.92 })
        .stroke({ width: 1, color: 0xd1d5db });
    bg.circle(10, 12, 4).fill(TONES[tone] ?? TONES.muted);

    label.addChild(bg, name);
    if (detail) label.addChild(sub);
    // Centred above the point it labels.
    label.pivot.set(width / 2, height + 6);
    label.boxWidth = width;
    label.boxHeight = height;
    return label;
}
