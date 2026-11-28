// While drawing a line or shape with Geoman, a tag by the pointer giving
// the length of the segment being placed (the "rubber band" from the last
// point), and for a line the length so far including it. Shared by every
// map that draws (asset locations, the property boundary and zones).
//
// Reads Geoman's draw handler (its _hintMarker, which follows the pointer
// and snaps), so it follows the same point the rubber band does.

export function formatLength(metres) {
    return metres >= 1000 ? `${(metres / 1000).toFixed(2)} km` : `${Math.round(metres)} m`;
}

/**
 * Lets a line be closed: once it has two segments, the pointer snaps to its
 * first point when close to it (within the map's snap distance), and
 * clicking there (or on that first point) adds the closing point and
 * finishes - a fenced paddock drawn with the line tool rather than the
 * polygon tool. Geoman on its own finishes there without adding it.
 *
 * Call once, after the draw controls are added and before drawing starts.
 */
export function closeLinesAtStart(map) {
    const draw = map.pm.Draw.Line;
    if (!draw || draw._closesAtStart) return;
    draw._closesAtStart = true;

    const nearStart = (latlng) => {
        const points = draw._layer?.getLatLngs?.() ?? [];
        if (points.length < 3 || !latlng) return false;
        const distance = map.latLngToContainerPoint(latlng).distanceTo(map.latLngToContainerPoint(points[0]));
        return distance <= (draw.options.snapDistance ?? map.pm.getGlobalOptions().snapDistance ?? 20);
    };

    // Snap the pointer (and so the rubber band) onto the first point.
    const snap = () => {
        const hint = draw._hintMarker;
        const first = draw._layer?.getLatLngs?.()[0];
        if (!hint || !first || !nearStart(hint.getLatLng()) || hint.getLatLng().equals(first)) return;
        hint._snapped = true;
        hint.setLatLng(first);
    };
    map.on('pm:drawstart', (e) => {
        if (e.shape === 'Line') draw._hintMarker?.on('move', snap);
    });

    // Finishing with the pointer on the first point closes the line first.
    const finish = draw._finishShape;
    draw._finishShape = function (...args) {
        const points = this._layer?.getLatLngs?.() ?? [];
        const first = points[0];
        if (first && points.length >= 3 && nearStart(this._hintMarker?.getLatLng()) && !points[points.length - 1].equals(first)) {
            this._layer.addLatLng(first);
        }
        return finish.apply(this, args);
    };
}

/** Shows segment lengths while drawing on `map`; returns a function that stops it. */
export function showDrawLengths(L, map) {
    let tooltip = null;
    let draw = null;

    const update = () => {
        const points = draw?._layer?.getLatLngs?.() ?? [];
        const at = draw?._hintMarker?.getLatLng?.();
        if (!at || points.length === 0) {
            tooltip?.remove();
            return;
        }
        const last = points[points.length - 1];
        const segment = last.distanceTo(at);
        let text = formatLength(segment);
        if (draw._shape === 'Line' && points.length > 1) {
            let total = segment;
            for (let i = 1; i < points.length; i++) total += points[i - 1].distanceTo(points[i]);
            text += ` · ${formatLength(total)} in all`;
        }
        tooltip ??= L.tooltip({ permanent: true, direction: 'right', offset: [14, 0], className: 'draw-length-tooltip' });
        tooltip.setLatLng(at).setContent(text);
        if (!map.hasLayer(tooltip)) tooltip.addTo(map);
    };

    const stop = () => {
        draw?._hintMarker?.off('move', update);
        draw?._layer?.off('pm:vertexadded', update);
        draw = null;
        tooltip?.remove();
    };

    const start = (e) => {
        stop();
        if (!['Line', 'Polygon'].includes(e.shape)) return;
        draw = map.pm.Draw[e.shape];
        draw?._hintMarker?.on('move', update);
        e.workingLayer?.on('pm:vertexadded', update);
    };

    map.on('pm:drawstart', start);
    map.on('pm:drawend', stop);

    return () => {
        map.off('pm:drawstart', start);
        map.off('pm:drawend', stop);
        stop();
    };
}
