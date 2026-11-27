// The farm view's scene: the property as an angled 3D diorama, drawn with
// PixiJS. The world is in metres (geo.js) and the camera zooms it, so a
// sheep is a sheep's size - with more detail shown the closer you get:
//
//   zoomed out:  paddocks, fence lines, trees, a name tag for each mob,
//                gate, tank and pump
//   zoomed in:   fence posts, grass, and each animal wandering its paddock
//
// In the cartoon style (the default), props (gates, tanks, pumps) and
// fences are drawn bigger than life when zoomed out so they stay visible -
// as in farm games - and at their real size once you're close enough.
// "True to scale" draws them their real size whatever the zoom.
//
// The view can be turned around the property (this.angle): everything in
// metres is rotated by it before going into the angled view (this.view).
//
// The ground has hills when the property's heights have been fetched
// (terrain.js): this.view puts everything at the ground's height there, and
// the paddocks are a picture painted onto a mesh of the ground.
import { Application, Container, Graphics, MeshSimple, Rectangle, Sprite, Text, Texture } from 'pixi.js';
import { area, bounds, centroid, contains, iso, makeProjection, nearestEdge, randomPointIn, seededRandom } from './geo';
import { contourInterval, contourLabelSpots, contourSegments, densify, groundHeight, makeTerrain, shading } from './terrain';
import { TEXTURE_PX_PER_METRE, animalFor, drawGate, drawPump, drawTank, drawTrough, makeAnimalTextures, makeLabel } from './sprites';
import { makePlantTextures, plantScale, plantSpots, plantTint } from './vegetation';

// Pixels per metre at which more detail appears.
const SHOW_POSTS_AT = 3;
const SHOW_ANIMALS_AT = 2.5;
const SHOW_GRASS_AT = 2;
// A prop is drawn at least this many pixels across.
const MIN_PROP_PX = 34;
// ...but a trough only this big: it's small beside a tank or shed.
const MIN_TROUGH_PX = 16;
// In the cartoon style, a fence is at least this tall on screen, and its
// posts at least this far apart.
const MIN_FENCE_PX = 12;
const MIN_POST_GAP_PX = 16;
// The most animals drawn for one mob; its tag still says how many.
const MAX_ANIMALS = 80;
const GATE_SNAP_METRES = 40;

const GRASS = [0x9ccc74, 0x8cc06a, 0xa8d17f, 0x95c56f, 0xa2c977, 0x91bd66];
// The paddock picture's resolution, at most.
const GROUND_PX_PER_METRE = 2;
const GROUND_MAX_PX = 2048;

// How each type of fence is drawn close up: its wires' (or rails')
// heights in metres, their colour and width on screen, and post spacing.
const FENCE_STYLES = {
    plain: { wires: 4, color: 0x8a8f94, width: 1, posts: 4 },
    barbed: { wires: 4, color: 0x7a7f84, width: 1, posts: 4 },
    electric: { wires: 2, color: 0xf4f1e4, width: 1.8, posts: 8 },
    netting: { wires: 1, color: 0x8a8f94, width: 1, posts: 4, mesh: true },
    post_and_rail: { wires: 3, color: 0x9a7650, width: 3, posts: 2.5 },
    other: { wires: 2, color: 0x8a8f94, width: 1, posts: 4 },
};
const LOWEST_WIRE = 0.3;
const TOP_WIRE = 1.05;

function wireHeights(run) {
    const style = FENCE_STYLES[run.type] ?? FENCE_STYLES.plain;
    const count = Math.max(1, Math.min(8, run.wires || style.wires));
    if (count === 1) return [TOP_WIRE];
    return Array.from({ length: count }, (_, i) => LOWEST_WIRE + ((TOP_WIRE - LOWEST_WIRE) * i) / (count - 1));
}

// A path's segments: an open fence's between its points, a closed
// paddock edge's back round to the start too.
function* segments(run) {
    const { points, closed } = run;
    for (let i = closed ? 0 : 1, j = closed ? points.length - 1 : 0; i < points.length; j = i++) {
        yield [points[j], points[i]];
    }
}

const css = (color) => `#${color.toString(16).padStart(6, '0')}`;

function propTone(prop) {
    if (!prop.monitored) return 'muted';
    const key = prop.state?.key;
    if (!key || key === 'not_reporting' || key === 'unknown') return 'muted';
    if (key === 'open' || key === 'low') return 'attention';
    if (key === 'stopped') return 'muted';
    return 'active';
}

export class FarmScene {
    constructor(element, { onOpen, onRotate, angle = 0, cartoon = true }) {
        this.cartoon = cartoon;
        this.element = element;
        this.onOpen = onOpen;
        this.onRotate = onRotate;
        this.showContours = true;
        this.zoom = 1;
        this.angle = (angle * Math.PI) / 180;
        this.herds = new Map();
        this.props = [];
        this.plants = [];
        this.zoneLabels = [];
        this.destroyed = false;
        this.dragged = false;
    }

    async init() {
        const app = new Application();
        await app.init({
            resizeTo: this.element,
            background: 0xe7eee1,
            antialias: true,
            autoDensity: true,
            resolution: window.devicePixelRatio || 1,
        });
        if (this.destroyed) {
            app.destroy(true, { children: true });
            return;
        }
        this.app = app;
        this.element.appendChild(app.canvas);
        this.textures = makeAnimalTextures(app.renderer);
        this.plantTextures = makePlantTextures(app.renderer);

        this.world = new Container();
        this.skirt = new Graphics();
        this.ground = new Container();
        this.contours = new Graphics();
        this.contourLabels = new Container();
        this.grass = new Graphics();
        this.zoneNames = new Container();
        this.posts = new Graphics();
        this.fences = new Graphics();
        this.objects = new Container();
        this.objects.sortableChildren = true;
        this.labels = new Container();
        this.world.addChild(this.skirt, this.ground, this.contours, this.contourLabels, this.grass, this.zoneNames, this.posts, this.fences, this.objects, this.labels);
        app.stage.addChild(this.world);

        this.listen(app.canvas);
        app.ticker.add((ticker) => this.tick(Math.min(ticker.deltaMS, 100) / 1000));

        if (this.pending) {
            this.setData(this.pending);
            this.pending = null;
        }
    }

    destroy() {
        this.destroyed = true;
        this.unlisten?.();
        this.groundTexture?.destroy(true);
        this.app?.destroy(true, { children: true });
    }

    // A point in metres to the screen, turned by the view's angle, z metres
    // above the ground there.
    view(x, y, z = 0) {
        return this.viewAt(x, y, z + (this.terrain ? groundHeight(this.terrain, x, y) : 0));
    }

    // ...and z metres above the lowest ground, whatever the ground's height.
    viewAt(x, y, z) {
        const cos = Math.cos(this.angle);
        const sin = Math.sin(this.angle);
        return iso(x * cos - y * sin, x * sin + y * cos, z);
    }

    viewPoints(points, z = 0) {
        return points.flatMap(({ x, y }) => {
            const p = this.view(x, y, z);
            return [p.x, p.y];
        });
    }

    // The point in metres under a point on the screen - found by looking
    // at the lowest ground, then again at the height of what's there.
    groundAt(sx, sy) {
        const wx = (sx - this.world.x) / this.zoom;
        const wy = (sy - this.world.y) / this.zoom;
        const cos = Math.cos(-this.angle);
        const sin = Math.sin(-this.angle);
        let z = 0;
        let point = null;
        for (let i = 0; i < 4; i++) {
            const rx = wy + z + wx / 2;
            const ry = wy + z - wx / 2;
            point = { x: rx * cos - ry * sin, y: rx * sin + ry * cos };
            z = this.terrain ? groundHeight(this.terrain, point.x, point.y) : 0;
        }
        return point;
    }

    // Cartoon (props and fences big enough to see from afar) or true to scale.
    setCartoon(on) {
        this.cartoon = on;
        if (this.app && this.project) this.updateCamera(true);
    }

    // How much bigger than life fences are drawn at this zoom.
    get fenceScale() {
        return this.cartoon ? Math.max(1, MIN_FENCE_PX / (this.zoom * TOP_WIRE)) : 1;
    }

    setContours(on) {
        this.showContours = on;
        if (this.contours) {
            this.contours.visible = on;
            this.contourLabels.visible = on;
        }
    }

    get degrees() {
        return Math.round(((this.angle * 180) / Math.PI % 360 + 360) % 360);
    }

    // Turn the view to this many degrees, keeping what's in the middle of
    // the screen there. Redraws the land, so it's done once a frame at most.
    rotateTo(degrees) {
        if (!this.app || !this.project) return;
        const cx = this.app.screen.width / 2;
        const cy = this.app.screen.height / 2;
        const middle = this.groundAt(cx, cy);

        this.angle = (((degrees % 360) + 360) % 360) * Math.PI / 180;
        const p = this.view(middle.x, middle.y);
        this.world.position.set(cx - p.x * this.zoom, cy - p.y * this.zoom);

        if (!this.redrawQueued) {
            this.redrawQueued = true;
            requestAnimationFrame(() => {
                this.redrawQueued = false;
                if (this.destroyed) return;
                this.drawLand();
                this.buildProps(this.propData ?? []);
                this.placePlants();
                this.updateCamera(true);
            });
        }
        this.onRotate?.(this.degrees);
    }

    rotateBy(degrees) {
        this.rotateTo(this.degrees + degrees);
    }

    setData(data) {
        if (!this.app) {
            this.pending = data;
            return;
        }

        // Fitted to the land when it first shows, and again when hills
        // arrive, as they make it taller.
        const refit = !this.zones || (Boolean(data.terrain) && !this.hadTerrain);
        this.hadTerrain = Boolean(data.terrain);
        const staticKey = JSON.stringify([data.boundary, data.zones, data.fences, data.vegetation, data.terrain?.heights?.length, data.terrain?.north]);
        if (staticKey !== this.staticKey) {
            this.staticKey = staticKey;
            this.buildLand(data);
        }
        if (!this.project) return;

        this.propData = data.props ?? [];
        this.buildProps(this.propData);
        this.buildHerds(data.mobs ?? []);
        if (refit && this.terrain) this.fit();
        this.updateCamera(true);
    }

    // The ground, paddocks, fences and paddock names.
    buildLand({ boundary, zones, fences, vegetation, terrain }) {
        const all = [...(boundary ?? []), ...(zones ?? []).flatMap((zone) => zone.coordinates ?? [])];
        if (all.length === 0) {
            this.project = null;
            return;
        }
        // Kept for later refreshes, so nothing moves.
        this.project ??= makeProjection(all);

        this.boundary = boundary?.length > 2 ? boundary.map(this.project) : null;
        this.zones = (zones ?? [])
            .filter((zone) => zone.coordinates?.length > 2)
            .map((zone) => ({ ...zone, polygon: zone.coordinates.map(this.project) }));
        // The boundary's and paddocks' edges.
        this.fenceLines = [...(this.boundary ? [this.boundary] : []), ...this.zones.map((zone) => zone.polygon)];
        this.land = this.boundary ? [this.boundary] : this.zones.map((zone) => zone.polygon);

        // The fences: where fence assets have been drawn, those - with the
        // paddock edges faint, as a paddock edge isn't always fenced. Until
        // there are any, every paddock edge is drawn as a fence.
        const drawn = (fences ?? [])
            .filter((fence) => fence.line?.length > 1)
            .map((fence) => ({ id: fence.id, type: fence.fence_type, wires: fence.wires, points: fence.line.map(this.project), closed: false }));
        this.runs = drawn.length
            ? drawn
            : this.fenceLines.map((points) => ({ type: null, points, closed: true }));
        this.edges = drawn.length ? this.fenceLines : [];

        const everything = [...this.fenceLines.flat(), ...drawn.flatMap((run) => run.points)];
        this.terrain = makeTerrain(terrain, this.project, bounds(everything));
        const step = this.terrain.hilly ? Math.min(Math.abs(this.terrain.dx), Math.abs(this.terrain.dy)) / 2 : Infinity;
        // Fences and the land's edge in short pieces, so they follow the ground.
        this.fenceRuns = this.runs.map((run) => ({ ...run, points: densify(run.points, step, run.closed) }));
        this.edgeLines = this.edges.map((polygon) => densify(polygon, step));
        this.edgeRuns = this.land.map((polygon) => densify(polygon, step));

        // Vegetation: areas tinted on the ground, and each plant.
        this.vegetation = (vegetation ?? []).map((item) => ({
            type: item.vegetation_type,
            area: item.shape ? item.shape.map(this.project) : null,
            // Only on the property - an area drawn past its edge stops there.
            spots: plantSpots(item, this.project).filter((spot) => this.land.some((polygon) => contains(polygon, spot))),
        }));

        this.buildGround();
        this.buildContours();
        this.buildPlants();
        this.drawLand();
    }

    // The paddocks painted, with light and shade from the hills, onto a
    // picture that's laid over a mesh of the ground.
    buildGround() {
        const t = this.terrain;
        const width = t.dx * (t.columns - 1);
        const height = t.dy * (t.rows - 1);
        const scale = Math.min(GROUND_PX_PER_METRE, GROUND_MAX_PX / Math.abs(width), GROUND_MAX_PX / Math.abs(height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(2, Math.round(Math.abs(width) * scale));
        canvas.height = Math.max(2, Math.round(Math.abs(height) * scale));
        const ctx = canvas.getContext('2d');
        const toPx = (p) => [((p.x - t.x0) / width) * canvas.width, ((p.y - t.y0) / height) * canvas.height];
        const trace = (polygon) => {
            polygon.forEach((p, i) => (i ? ctx.lineTo(...toPx(p)) : ctx.moveTo(...toPx(p))));
            ctx.closePath();
        };

        if (this.boundary) {
            ctx.fillStyle = css(0x86b862);
            ctx.beginPath();
            trace(this.boundary);
            ctx.fill();
        }
        this.zones.forEach((zone, i) => {
            ctx.fillStyle = css(GRASS[i % GRASS.length]);
            ctx.beginPath();
            trace(zone.polygon);
            ctx.fill();
        });
        // Under an area of trees, shrubs or undergrowth.
        for (const item of this.vegetation.filter((v) => v.area)) {
            ctx.fillStyle = css(plantTint(item.type));
            ctx.globalAlpha = 0.6;
            ctx.beginPath();
            trace(item.area);
            ctx.fill();
            ctx.globalAlpha = 1;
        }

        if (t.hilly) {
            // The light, one pixel a grid point, stretched smoothly over it all.
            const light = shading(t);
            const lit = document.createElement('canvas');
            lit.width = t.columns;
            lit.height = t.rows;
            const image = lit.getContext('2d').createImageData(t.columns, t.rows);
            light.forEach((v, i) => image.data.set([v, v, v, 255], i * 4));
            lit.getContext('2d').putImageData(image, 0, 0);
            ctx.globalCompositeOperation = 'multiply';
            ctx.imageSmoothingEnabled = true;
            // Grid points are pixel centres, so the image overhangs by half a pixel.
            const px = canvas.width / (t.columns - 1);
            const py = canvas.height / (t.rows - 1);
            ctx.drawImage(lit, -px / 2, -py / 2, canvas.width + px, canvas.height + py);
            // ...then only where there's land.
            ctx.globalCompositeOperation = 'destination-in';
            ctx.beginPath();
            this.land.forEach(trace);
            ctx.fill();
            ctx.globalCompositeOperation = 'source-over';
        }

        this.groundTexture?.destroy(true);
        this.groundTexture = Texture.from(canvas);

        const uvs = new Float32Array(t.columns * t.rows * 2);
        for (let r = 0; r < t.rows; r++) {
            for (let c = 0; c < t.columns; c++) {
                uvs[(r * t.columns + c) * 2] = c / (t.columns - 1);
                uvs[(r * t.columns + c) * 2 + 1] = r / (t.rows - 1);
            }
        }
        this.ground.removeChildren().forEach((child) => child.destroy());
        this.groundMesh = new MeshSimple({
            texture: this.groundTexture,
            vertices: new Float32Array(t.columns * t.rows * 2),
            uvs,
            indices: new Uint32Array((t.columns - 1) * (t.rows - 1) * 6),
        });
        this.ground.addChild(this.groundMesh);
    }

    // The ground mesh as seen from this angle: each grid point on screen,
    // and its squares drawn from the back to the front, so a hill hides
    // what's behind it.
    drawGround() {
        const t = this.terrain;
        const vertices = new Float32Array(t.columns * t.rows * 2);
        for (let r = 0; r < t.rows; r++) {
            for (let c = 0; c < t.columns; c++) {
                const i = r * t.columns + c;
                const p = this.viewAt(t.x0 + c * t.dx, t.y0 + r * t.dy, t.exaggeration ? (t.heights[i] - t.lowest) * t.exaggeration : 0);
                vertices[i * 2] = p.x;
                vertices[i * 2 + 1] = p.y;
            }
        }

        const cos = Math.cos(this.angle);
        const sin = Math.sin(this.angle);
        const squares = [];
        for (let r = 0; r < t.rows - 1; r++) {
            for (let c = 0; c < t.columns - 1; c++) {
                const x = t.x0 + (c + 0.5) * t.dx;
                const y = t.y0 + (r + 0.5) * t.dy;
                // Further down the screen is nearer.
                squares.push({ depth: (x * cos - y * sin) + (x * sin + y * cos), i: r * t.columns + c });
            }
        }
        squares.sort((a, b) => a.depth - b.depth);
        const indices = new Uint32Array(squares.length * 6);
        squares.forEach(({ i }, k) => {
            indices.set([i, i + 1, i + t.columns + 1, i, i + t.columns + 1, i + t.columns], k * 6);
        });

        this.groundMesh.geometry.positions = vertices;
        this.groundMesh.geometry.indices = indices;
    }

    // Contour lines over the land, and where their heights are labelled.
    buildContours() {
        const t = this.terrain;
        this.contourLabels.removeChildren().forEach((child) => child.destroy());
        if (!t.hilly) {
            this.contourLines = [];
            return;
        }
        this.contourStep = contourInterval(t.lowest, t.highest);
        const onLand = (p) => this.land.some((polygon) => contains(polygon, p));
        this.contourLines = contourSegments(t, this.contourStep)
            .filter(({ a, b }) => onLand({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }));

        const extent = bounds(this.fenceLines.flat());
        const spacing = Math.max(extent.maxX - extent.minX, extent.maxY - extent.minY) / 3;
        this.contourSpots = contourLabelSpots(this.contourLines, this.contourStep, spacing).map((spot) => {
            const text = new Text({ text: `${spot.level} m`, style: { fontFamily: 'Figtree, sans-serif', fontSize: 11, fontWeight: '600', fill: 0x5b4a2e } });
            text.resolution = 2;
            text.alpha = 0.8;
            text.anchor.set(0.5);
            this.contourLabels.addChild(text);
            return { ...spot, text };
        });
        this.contours.visible = this.showContours;
        this.contourLabels.visible = this.showContours;
    }

    // Contour lines a constant width on screen; every fifth one darker.
    drawContours() {
        this.contours.clear();
        const t = this.terrain;
        if (!t?.hilly) return;
        const major = this.contourStep * 5;
        const isMajor = (level) => Math.abs(level / major - Math.round(level / major)) < 1e-6;
        for (const majorLines of [false, true]) {
            let any = false;
            for (const { a, b, level } of this.contourLines) {
                if (isMajor(level) !== majorLines) continue;
                const z = (level - t.lowest) * t.exaggeration + 0.05;
                const p = this.viewAt(a.x, a.y, z);
                const q = this.viewAt(b.x, b.y, z);
                this.contours.moveTo(p.x, p.y).lineTo(q.x, q.y);
                any = true;
            }
            if (any) {
                this.contours.stroke({ width: (majorLines ? 1.4 : 0.8) / this.zoom, color: 0x5b4a2e, alpha: majorLines ? 0.55 : 0.3 });
            }
        }
        for (const spot of this.contourSpots ?? []) {
            const p = this.viewAt(spot.x, spot.y, (spot.level - t.lowest) * t.exaggeration);
            spot.text.position.set(p.x, p.y);
        }
    }

    // The ground, grass, posts and paddock names, as seen from this angle.
    drawLand() {
        this.drawGround();

        // Tufts of grass, seen close up.
        this.grass.clear();
        for (const zone of this.zones) {
            const random = seededRandom(zone.id * 7919);
            const count = Math.min(600, Math.round(area(zone.polygon) / 250));
            for (let i = 0; i < count; i++) {
                const p = randomPointIn(zone.polygon, random);
                const base = this.view(p.x, p.y);
                this.grass
                    .moveTo(base.x - 0.25, base.y - 0.35).lineTo(base.x, base.y).lineTo(base.x + 0.25, base.y - 0.4)
                    .stroke({ width: 0.07, color: 0x5f8f3e, alpha: 0.7 });
            }
        }

        for (const label of this.zoneLabels) label.destroy();
        this.zoneLabels = this.zones.map((zone) => {
            const text = new Text({ text: zone.name, style: { fontFamily: 'Figtree, sans-serif', fontSize: 13, fontWeight: '600', fill: 0x3f5f2a } });
            text.resolution = 2;
            text.alpha = 0.75;
            text.anchor.set(0.5);
            const middle = centroid(zone.polygon);
            const c = this.view(middle.x, middle.y);
            text.position.set(c.x, c.y);
            this.zoneNames.addChild(text);
            return text;
        });

        this.lastFenceZoom = null;
    }

    // A sprite for each plant, among the animals and props so they hide
    // each other properly.
    buildPlants() {
        for (const plant of this.plants) plant.sprite.destroy();
        this.plants = this.vegetation.flatMap((item) => item.spots.map((spot) => {
            const variants = this.plantTextures[item.type] ?? this.plantTextures.tree;
            const { texture, anchor } = variants[spot.variant % variants.length];
            const sprite = new Sprite(texture);
            sprite.anchor.set(anchor.x, anchor.y);
            this.objects.addChild(sprite);
            return { sprite, ...spot, type: item.type };
        }));
        this.placePlants();
    }

    // Where each plant stands at this angle, and its size at this zoom.
    placePlants() {
        for (const plant of this.plants) {
            const p = this.view(plant.x, plant.y);
            const scale = plant.size * plantScale(plant.type, this.zoom, this.cartoon);
            plant.sprite.position.set(p.x, p.y);
            plant.sprite.scale.set(plant.flip ? -scale : scale, scale);
            plant.sprite.zIndex = p.y;
        }
    }

    // Gates, tanks, pumps and troughs - the assets, or sensors on their own
    // - each with a name tag. A gate with no sensor is plain timber; a
    // trough with none just has its name.
    buildProps(props) {
        for (const prop of this.props) {
            prop.container.destroy({ children: true });
            prop.label.destroy({ children: true });
        }
        this.props = props
            .filter((item) => ['gate', 'tank', 'pump', 'trough'].includes(item.kind))
            .map((item) => {
                let at = this.project([item.latitude, item.longitude]);
                const active = item.monitored && item.state && !['not_reporting', 'unknown'].includes(item.state.key);
                const container = new Container();
                const g = new Graphics();
                container.addChild(g);
                let size = 4;
                let height = 1.5;
                let wheel = null;
                let minPx = MIN_PROP_PX;
                let gate = null;

                if (item.kind === 'gate') {
                    // On the nearest fence, across it. Drawn by drawGates,
                    // as it's redrawn with the fence at each zoom.
                    const edge = nearestEdge(at, this.runs);
                    const onFence = edge && edge.distance <= GATE_SNAP_METRES;
                    if (onFence) at = edge.point;
                    const direction = onFence ? edge.direction : { x: 1, y: 0 };
                    const cos = Math.cos(this.angle);
                    const sin = Math.sin(this.angle);
                    gate = {
                        direction: { x: direction.x * cos - direction.y * sin, y: direction.x * sin + direction.y * cos },
                        open: item.state?.key === 'open',
                        active,
                        monitored: item.monitored,
                    };
                } else if (item.kind === 'trough') {
                    drawTrough(g, { ...item.trough, angle: this.angle, percent: item.monitored ? item.percent : 100, low: item.state?.key === 'low' });
                    size = Math.max(1.5, item.trough?.length ?? 1.5);
                    height = 1;
                    minPx = MIN_TROUGH_PX;
                } else if (item.kind === 'tank') {
                    drawTank(g, { percent: item.percent, low: item.state?.key === 'low', active });
                    height = 2.8;
                } else {
                    wheel = drawPump(g, { running: item.state?.key === 'running', active });
                    container.addChild(wheel);
                    size = 2;
                    height = 2;
                }

                const p = this.view(at.x, at.y);
                container.position.set(p.x, p.y);
                container.zIndex = p.y;
                this.objects.addChild(container);

                const detail = !item.monitored ? (item.kind === 'trough' ? null : 'No sensor') : item.state?.label ?? 'No readings yet';
                const label = makeLabel(item.name, detail, propTone(item));
                this.labels.addChild(label);

                // Its tag shows only while the pointer is on it (or the tag),
                // so tags don't cover the map.
                label.visible = false;
                let hovering = 0;
                const hover = (change) => {
                    hovering = Math.max(0, hovering + change);
                    label.visible = hovering > 0;
                    if (label.visible) this.placeLabels();
                };

                const url = item.url;
                for (const target of [container, label]) {
                    target.eventMode = 'static';
                    target.cursor = 'pointer';
                    target.on('pointertap', (e) => e.button === 0 && !this.dragged && this.onOpen(url));
                    target.on('pointerover', () => hover(1));
                    target.on('pointerout', () => hover(-1));
                }

                const prop = { container, g, label, at: p, size, height, minPx, wheel, gate, spinning: item.state?.key === 'running' };
                if (!gate) this.fitHitArea(prop);
                return prop;
            });
        this.drawGates();
    }

    // Gates at their real width, as tall as the fence they're in - in the
    // cartoon style that's taller than life, but never wider, so gates
    // close together don't overlap.
    drawGates() {
        const lift = this.fenceScale;
        const pixel = this.cartoon ? 1 / this.zoom : 0;
        for (const prop of this.props ?? []) {
            if (!prop.gate) continue;
            prop.g.clear();
            drawGate(prop.g, { ...prop.gate, lift, pixel });
            prop.height = 1.5 * lift;
            this.fitHitArea(prop);
        }
    }

    // Somewhere comfortable to point at: the prop's drawing, plus a few
    // pixels round it, as a gate's thin rails are hard to hit.
    fitHitArea(prop) {
        const box = prop.g.getLocalBounds();
        const pad = 6 / (this.zoom * (prop.container.scale.x || 1));
        prop.container.hitArea = new Rectangle(box.x - pad, box.y - pad, box.width + pad * 2, box.height + pad * 2);
    }

    // Each mob in its paddock; a herd keeps its animals where they are when
    // the data refreshes, unless it has moved or changed size.
    buildHerds(mobs) {
        const seen = new Set();
        for (const mob of mobs) {
            const zone = this.zones.find((z) => z.id === mob.zone_id);
            if (!zone) continue;
            const kind = animalFor(mob.type);
            const key = `${mob.id}:${zone.id}:${mob.count}:${kind}`;
            seen.add(key);
            if (this.herds.has(key)) {
                this.herds.get(key).mob = mob;
                continue;
            }
            this.herds.set(key, this.makeHerd(mob, zone, kind));
        }
        for (const [key, herd] of this.herds) {
            if (!seen.has(key)) {
                herd.animals.forEach((animal) => animal.sprite.destroy());
                herd.label.destroy({ children: true });
                this.herds.delete(key);
            }
        }
    }

    makeHerd(mob, zone, kind) {
        const random = seededRandom(mob.id * 104729 + zone.id);
        const count = Math.min(mob.count, MAX_ANIMALS);
        const spread = Math.max(6, Math.sqrt(count) * (kind === 'cattle' ? 3.5 : 2.4));
        const centre = randomPointIn(zone.polygon, random, centroid(zone.polygon), spread * 2);
        const { texture, anchor } = this.textures[kind];

        const animals = Array.from({ length: count }, () => {
            const at = randomPointIn(zone.polygon, random, centre, spread);
            const sprite = new Sprite(texture);
            sprite.anchor.set(anchor.x, anchor.y);
            sprite.scale.set((random() < 0.5 ? -1 : 1) / TEXTURE_PX_PER_METRE, 1 / TEXTURE_PX_PER_METRE);
            this.objects.addChild(sprite);
            return { sprite, x: at.x, y: at.y, target: null, pause: random() * 8, speed: 0.35 + random() * 0.3 };
        });

        const noun = kind === 'cattle' ? 'cattle' : 'sheep';
        const label = makeLabel(mob.name, `${mob.count} ${mob.type ? mob.type.toLowerCase() : noun}`, 'active');
        label.eventMode = 'static';
        label.cursor = 'pointer';
        label.on('pointertap', (e) => e.button === 0 && !this.dragged && this.onOpen(route('mobs.show', mob.id)));
        this.labels.addChild(label);

        return { mob, zone, kind, animals, centre, wanderTo: null, nextWander: 20 + random() * 40, spread, random, label };
    }

    tick(seconds) {
        for (const herd of this.herds.values()) {
            // The mob as a whole drifts slowly around its paddock.
            herd.nextWander -= seconds;
            if (herd.nextWander <= 0) {
                herd.wanderTo = randomPointIn(herd.zone.polygon, herd.random, herd.centre, herd.spread * 4);
                herd.nextWander = 40 + herd.random() * 60;
            }
            if (herd.wanderTo) {
                const dx = herd.wanderTo.x - herd.centre.x;
                const dy = herd.wanderTo.y - herd.centre.y;
                const d = Math.hypot(dx, dy);
                const step = 0.12 * seconds;
                if (d <= step) herd.wanderTo = null;
                else herd.centre = { x: herd.centre.x + (dx / d) * step, y: herd.centre.y + (dy / d) * step };
            }

            // Each animal grazes, then walks a few metres, staying near the mob.
            for (const animal of herd.animals) {
                if (animal.pause > 0) {
                    animal.pause -= seconds;
                } else {
                    animal.target ??= randomPointIn(herd.zone.polygon, herd.random, herd.centre, herd.spread);
                    const dx = animal.target.x - animal.x;
                    const dy = animal.target.y - animal.y;
                    const d = Math.hypot(dx, dy);
                    const step = animal.speed * seconds;
                    if (d <= step) {
                        animal.target = null;
                        animal.pause = 2 + herd.random() * 10;
                    } else {
                        animal.x += (dx / d) * step;
                        animal.y += (dy / d) * step;
                        // Face the way it's walking on screen.
                        const ahead = this.view(animal.target.x, animal.target.y);
                        const here = this.view(animal.x, animal.y);
                        animal.sprite.scale.x = (Math.sign(ahead.x - here.x) || 1) / TEXTURE_PX_PER_METRE;
                    }
                }
                const p = this.view(animal.x, animal.y);
                animal.sprite.position.set(p.x, p.y);
                animal.sprite.zIndex = p.y;
            }
        }

        for (const prop of this.props) {
            if (prop.wheel && prop.spinning) prop.wheel.rotation += seconds * 4;
        }

        this.placeLabels();
    }

    // Name tags stay the same size on screen, above what they label.
    placeLabels() {
        const scale = 1 / this.zoom;
        for (const prop of this.props) {
            const lift = prop.height * prop.container.scale.y;
            prop.label.scale.set(scale);
            prop.label.position.set(prop.at.x, prop.at.y - lift);
        }
        for (const herd of this.herds.values()) {
            const p = this.view(herd.centre.x, herd.centre.y);
            const close = this.zoom >= SHOW_ANIMALS_AT;
            herd.label.scale.set(scale);
            herd.label.position.set(p.x, p.y - (close ? herd.spread * 0.6 + 2 : 0));
        }
        for (const label of this.zoneLabels) {
            label.scale.set(scale);
        }
        for (const spot of this.contourSpots ?? []) {
            spot.text.scale.set(scale);
        }
        this.separateLabels();
    }

    // Tags that would overlap are stacked upwards, nearest the viewer first.
    separateLabels() {
        const tags = [...this.props.map((prop) => prop.label), ...[...this.herds.values()].map((herd) => herd.label)]
            .filter((tag) => tag.visible)
            .sort((a, b) => b.y - a.y);
        const placed = [];
        for (const tag of tags) {
            const width = tag.boxWidth / this.zoom;
            const height = (tag.boxHeight + 4) / this.zoom;
            for (let moved = true, tries = 0; moved && tries < 10; tries++) {
                moved = false;
                for (const other of placed) {
                    const overlaps = Math.abs(tag.x - other.x) < (width + other.width) / 2
                        && tag.y > other.y - other.height && tag.y - height < other.y;
                    if (overlaps) {
                        tag.y = other.y - other.height;
                        moved = true;
                    }
                }
            }
            placed.push({ x: tag.x, y: tag.y, width, height });
        }
    }

    // After a pan or zoom: what's shown at this distance, and the lines
    // and props that are drawn at screen size.
    updateCamera(force = false) {
        this.world.scale.set(this.zoom);

        this.posts.visible = this.cartoon || this.zoom >= SHOW_POSTS_AT;
        this.grass.visible = this.zoom >= SHOW_GRASS_AT;
        for (const herd of this.herds.values()) {
            for (const animal of herd.animals) animal.sprite.visible = this.zoom >= SHOW_ANIMALS_AT;
        }
        for (const prop of this.props) {
            // Gates aren't scaled: drawGates makes them taller instead.
            if (prop.gate) continue;
            prop.container.scale.set(this.cartoon ? Math.max(1, prop.minPx / (this.zoom * prop.size)) : 1);
            this.fitHitArea(prop);
        }
        for (const plant of this.plants) {
            const scale = plant.size * plantScale(plant.type, this.zoom, this.cartoon);
            plant.sprite.scale.set(plant.flip ? -scale : scale, scale);
        }

        // Fence wire and the diorama's edge, redrawn when the zoom changes
        // enough to matter.
        if (force || !this.lastFenceZoom || Math.abs(this.zoom / this.lastFenceZoom - 1) > 0.08) {
            this.lastFenceZoom = this.zoom;
            this.drawFences();
            this.drawGates();
            this.drawSkirt();
            this.drawContours();
        }
        this.placeLabels();
    }

    // Posts every few metres along each fence, and at its far end - fewer
    // and taller when the cartoon style draws the fence bigger than life.
    drawPosts() {
        const scale = this.fenceScale;
        const g = this.posts;
        g.clear();
        for (const run of this.runs ?? []) {
            const spacing = Math.max((FENCE_STYLES[run.type] ?? FENCE_STYLES.plain).posts, this.cartoon ? MIN_POST_GAP_PX / this.zoom : 0);
            for (const [a, b] of segments(run)) {
                const steps = Math.max(1, Math.round(Math.hypot(b.x - a.x, b.y - a.y) / spacing));
                for (let s = 0; s <= steps; s++) {
                    if (s === steps && (run.closed || b !== run.points[run.points.length - 1])) continue;
                    const x = a.x + ((b.x - a.x) * s) / steps;
                    const y = a.y + ((b.y - a.y) * s) / steps;
                    const bottom = this.view(x, y, 0);
                    const top = this.view(x, y, (TOP_WIRE + 0.1) * scale);
                    g.moveTo(bottom.x, bottom.y).lineTo(top.x, top.y);
                }
            }
        }
        g.stroke({ width: Math.max(0.12, 2 / this.zoom), color: 0x6b4f35 });
    }

    // Far off (true to scale), each fence is a line along the ground; close
    // up, or in the cartoon style, its wires (or rails, or netting) by type.
    // Paddock edges without a fence asset along them are faint.
    drawFences() {
        const px = 1 / this.zoom;
        const close = this.cartoon || this.zoom >= SHOW_POSTS_AT;
        const scale = this.fenceScale;
        const g = this.fences;
        g.clear();
        this.drawPosts();

        const path = (points, closed, z) => {
            points.forEach((p, i) => {
                const q = this.view(p.x, p.y, z);
                if (i) g.lineTo(q.x, q.y);
                else g.moveTo(q.x, q.y);
            });
            if (closed) g.closePath();
        };

        for (const polygon of this.edgeLines ?? []) {
            path(polygon, true, 0);
            // Pale, so it isn't taken for a contour line.
            g.stroke({ width: Math.max(0.04, 1.2 * px), color: 0xf1ecd8, alpha: 0.6 });
        }

        for (const run of this.fenceRuns ?? []) {
            const style = FENCE_STYLES[run.type] ?? FENCE_STYLES.plain;
            if (!close) {
                path(run.points, run.closed, 0);
                g.stroke({ width: Math.max(0.05, 1.4 * px), color: run.type === 'electric' ? 0xc9a227 : 0x6b4f35, alpha: 0.85 });
                continue;
            }
            if (style.mesh) {
                // Netting: a see-through band up to the top wire.
                for (const [a, b] of segments(run)) {
                    const a0 = this.view(a.x, a.y, 0.05 * scale);
                    const b0 = this.view(b.x, b.y, 0.05 * scale);
                    const b1 = this.view(b.x, b.y, TOP_WIRE * scale);
                    const a1 = this.view(a.x, a.y, TOP_WIRE * scale);
                    g.poly([a0.x, a0.y, b0.x, b0.y, b1.x, b1.y, a1.x, a1.y]);
                }
                g.fill({ color: 0x9aa0a6, alpha: 0.35 });
            }
            for (const z of wireHeights(run)) {
                path(run.points, run.closed, z * scale);
                g.stroke({ width: Math.max(0.03, style.width * px), color: style.color, alpha: 0.95 });
            }
        }
    }

    // The property sits on a slab of earth, its sides following the ground
    // down to a base a constant depth on screen below the lowest of it.
    // The ground is drawn over the far sides, so only the near ones show.
    drawSkirt() {
        const depth = 16 / this.zoom;
        this.skirt.clear();
        for (const polygon of this.edgeRuns ?? []) {
            for (let i = 0; i < polygon.length; i++) {
                const a = polygon[i];
                const b = polygon[(i + 1) % polygon.length];
                const ta = this.view(a.x, a.y);
                const tb = this.view(b.x, b.y);
                const ma = this.view(a.x, a.y, -depth / 3);
                const mb = this.view(b.x, b.y, -depth / 3);
                const ba = this.viewAt(a.x, a.y, -depth);
                const bb = this.viewAt(b.x, b.y, -depth);
                this.skirt.poly([ma.x, ma.y, mb.x, mb.y, bb.x, bb.y, ba.x, ba.y]).fill(0x8b6b47);
                this.skirt.poly([ta.x, ta.y, tb.x, tb.y, mb.x, mb.y, ma.x, ma.y]).fill(0x9d7b54);
            }
        }
    }

    fit() {
        if (!this.app || !this.fenceLines?.length) return;
        const points = [...this.fenceLines.flat(), ...this.runs.flatMap((run) => run.points)].map((p) => this.view(p.x, p.y));
        const b = bounds(points);
        const { width, height } = this.app.screen;
        this.zoom = Math.min(width / (b.maxX - b.minX || 1), height / (b.maxY - b.minY || 1)) * 0.82;
        this.minZoom = this.zoom * 0.4;
        this.world.position.set(width / 2 - ((b.minX + b.maxX) / 2) * this.zoom, height / 2 - ((b.minY + b.maxY) / 2) * this.zoom);
        this.updateCamera(true);
    }

    zoomAt(sx, sy, factor) {
        const zoom = Math.min(60, Math.max(this.minZoom ?? 0.05, this.zoom * factor));
        const wx = (sx - this.world.x) / this.zoom;
        const wy = (sy - this.world.y) / this.zoom;
        this.zoom = zoom;
        this.world.position.set(sx - wx * zoom, sy - wy * zoom);
        this.updateCamera();
    }

    // Drag to pan, wheel to zoom about the pointer.
    listen(canvas) {
        let drag = null;
        const down = (e) => {
            const orbit = e.button === 2 || e.shiftKey;
            drag = { x: e.clientX, y: e.clientY, ox: this.world.x, oy: this.world.y, orbit, degrees: this.degrees };
            this.dragged = false;
        };
        const move = (e) => {
            if (!drag) return;
            const dx = e.clientX - drag.x;
            const dy = e.clientY - drag.y;
            if (Math.abs(dx) + Math.abs(dy) > 4) this.dragged = true;
            if (!this.dragged) return;
            if (drag.orbit) {
                // Across the screen turns it about a third of the way round.
                this.rotateTo(drag.degrees - dx * 0.25);
                canvas.style.cursor = 'ew-resize';
            } else {
                this.world.position.set(drag.ox + dx, drag.oy + dy);
                canvas.style.cursor = 'grabbing';
            }
        };
        const menu = (e) => e.preventDefault();
        const up = () => {
            drag = null;
            canvas.style.cursor = '';
        };
        const wheel = (e) => {
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            this.zoomAt(e.clientX - rect.left, e.clientY - rect.top, Math.exp(-e.deltaY * 0.0015));
        };

        canvas.addEventListener('pointerdown', down);
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        canvas.addEventListener('wheel', wheel, { passive: false });
        canvas.addEventListener('contextmenu', menu);
        this.unlisten = () => {
            canvas.removeEventListener('contextmenu', menu);
            canvas.removeEventListener('pointerdown', down);
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            canvas.removeEventListener('wheel', wheel);
        };
    }

    zoomBy(factor) {
        if (!this.app) return;
        this.zoomAt(this.app.screen.width / 2, this.app.screen.height / 2, factor);
    }
}
