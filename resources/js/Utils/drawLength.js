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
