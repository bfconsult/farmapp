import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FarmScene } from '@/FarmView/scene';

// Sensors and mobs are refreshed this often, so gates and tanks keep up.
const REFRESH_MS = 60 * 1000;

// Which way north points on screen at this view angle, in CSS degrees
// (0 = up) - north (0, -1) turned and put through the angled view.
function northOnScreen(degrees) {
    const a = (degrees * Math.PI) / 180;
    const x = Math.sin(a);
    const y = -Math.cos(a);
    return (Math.atan2((x + y) / 2, x - y) * 180) / Math.PI + 90;
}

function Compass({ degrees }) {
    return (
        <div className="w-9 h-9 rounded-full bg-white border border-gray-300 flex items-center justify-center" title="North">
            <div style={{ transform: `rotate(${northOnScreen(degrees)}deg)` }} className="flex flex-col items-center leading-none">
                <span className="text-[9px] font-bold text-red-600">N</span>
                <svg className="w-3 h-3 -mt-0.5" viewBox="0 0 12 12"><path d="M6 0 L10 12 L6 9 L2 12 Z" fill="#dc2626" /></svg>
            </div>
        </div>
    );
}

export default function FarmView({ boundary, zones, mobs, sensors, rotation, terrain, terrainStale }) {
    const [degrees, setDegrees] = useState(rotation);
    const [saving, setSaving] = useState(false);
    const [fetching, setFetching] = useState(false);
    const [contours, setContours] = useState(true);
    const containerRef = useRef(null);
    const sceneRef = useRef(null);
    const hasLand = (boundary?.length ?? 0) > 2 || zones.some((zone) => (zone.coordinates?.length ?? 0) > 2);

    useEffect(() => {
        if (!hasLand) return undefined;
        const scene = new FarmScene(containerRef.current, {
            onOpen: (url) => router.visit(url),
            onRotate: setDegrees,
            angle: rotation,
        });
        sceneRef.current = scene;
        scene.init();
        return () => {
            scene.destroy();
            sceneRef.current = null;
        };
    }, [hasLand]);

    useEffect(() => {
        sceneRef.current?.setData({ boundary, zones, mobs, sensors, terrain });
    }, [boundary, zones, mobs, sensors, terrain, hasLand]);

    useEffect(() => {
        sceneRef.current?.setContours(contours);
    }, [contours]);

    const fetchTerrain = () => router.post(route('farm-view.terrain'), {}, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => setFetching(true),
        onFinish: () => setFetching(false),
    });

    useEffect(() => {
        const timer = setInterval(() => router.reload({ only: ['mobs', 'sensors'] }), REFRESH_MS);
        return () => clearInterval(timer);
    }, []);

    const button = 'px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50';

    return (
        <AuthenticatedLayout title="Farm view">
            <Head title="Farm view" />

            <div className="mt-2 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <BackLink href={route('map')}>Map</BackLink>
                        <h1 className="text-lg font-semibold text-gray-900">Farm view</h1>
                        <span className="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium">Prototype</span>
                    </div>
                    {hasLand && (
                        <div className="flex items-center gap-2">
                            <span className="hidden md:inline text-xs text-gray-500 mr-1">
                                Drag to move, right-drag (or Shift + drag) to turn, scroll to zoom. Click a tag to open it.
                            </span>
                            {degrees !== rotation && (
                                <button
                                    type="button"
                                    disabled={saving}
                                    className="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                    onClick={() => router.put(route('farm-view.rotation'), { rotation: degrees }, {
                                        only: ['rotation'],
                                        preserveState: true,
                                        preserveScroll: true,
                                        onStart: () => setSaving(true),
                                        onFinish: () => setSaving(false),
                                    })}
                                >
                                    Make this the way up
                                </button>
                            )}
                            {(!terrain || terrainStale) && (
                                <button
                                    type="button"
                                    disabled={fetching}
                                    className={`${button} disabled:opacity-50`}
                                    onClick={fetchTerrain}
                                    title="Fetch the ground's heights (about 30 m detail), for hills and contour lines"
                                >
                                    {fetching ? 'Getting the hills…' : terrain ? 'Update the hills' : 'Add hills'}
                                </button>
                            )}
                            {terrain && (
                                <label className="flex items-center gap-1.5 text-sm text-gray-700 mr-1">
                                    <input type="checkbox" className="rounded border-gray-300 text-green-600" checked={contours} onChange={(e) => setContours(e.target.checked)} />
                                    Contours
                                </label>
                            )}
                            <Compass degrees={degrees} />
                            <button type="button" className={button} onClick={() => sceneRef.current?.rotateBy(-15)} aria-label="Turn left" title="Turn left">⟲</button>
                            <button type="button" className={button} onClick={() => sceneRef.current?.rotateBy(15)} aria-label="Turn right" title="Turn right">⟳</button>
                            <button type="button" className={button} onClick={() => sceneRef.current?.zoomBy(1.5)} aria-label="Zoom in">+</button>
                            <button type="button" className={button} onClick={() => sceneRef.current?.zoomBy(1 / 1.5)} aria-label="Zoom out">−</button>
                            <button type="button" className={button} onClick={() => sceneRef.current?.fit()}>Fit</button>
                        </div>
                    )}
                </div>

                {hasLand ? (
                    <div
                        ref={containerRef}
                        className="relative w-full rounded-lg overflow-hidden shadow border border-gray-200 select-none touch-none"
                        style={{ height: 'calc(100dvh - 11rem)', minHeight: 400 }}
                    />
                ) : (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-600">
                        <p>Draw the property's boundary or paddocks first, and the farm view will show them.</p>
                        <Link href={route('map')} className="text-green-600 font-medium">Go to the map</Link>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
