import { useEffect } from 'react';
import { TransformWrapper, TransformComponent } from 'react-zoom-pan-pinch';

/**
 * Full-screen pan/zoom viewer for a job's photos - opened by tapping a
 * thumbnail. `index` is the currently-shown photo (null/undefined = closed).
 * `onIndexChange` is a plain setState setter, so it can be called with
 * either a number or an updater function.
 */
export default function PhotoLightbox({ photos, index, onClose, onIndexChange }) {
    const open = index !== null && index !== undefined;

    useEffect(() => {
        if (!open) return;

        const handleKey = (e) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowLeft') onIndexChange((i) => (i - 1 + photos.length) % photos.length);
            if (e.key === 'ArrowRight') onIndexChange((i) => (i + 1) % photos.length);
        };
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, [open, photos.length, onClose, onIndexChange]);

    if (!open) return null;

    const photo = photos[index];

    return (
        <div className="fixed inset-0 z-[3000] bg-black/90 flex flex-col" onClick={onClose}>
            <div
                className="flex items-center justify-between px-4 py-3 text-white flex-shrink-0"
                onClick={(e) => e.stopPropagation()}
            >
                {photos.length > 1 && (
                    <span className="text-sm text-gray-300">{index + 1} / {photos.length}</span>
                )}
                <button onClick={onClose} aria-label="Close" className="ml-auto text-3xl leading-none px-2">
                    ×
                </button>
            </div>

            <div className="flex-1 min-h-0 relative" onClick={(e) => e.stopPropagation()}>
                <TransformWrapper key={photo.id} doubleClick={{ mode: 'toggle' }} wheel={{ step: 0.2 }}>
                    <TransformComponent
                        wrapperStyle={{ width: '100%', height: '100%' }}
                        contentStyle={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                    >
                        <img src={photo.url} className="max-w-full max-h-full object-contain select-none" draggable={false} />
                    </TransformComponent>
                </TransformWrapper>

                {photos.length > 1 && (
                    <>
                        <button
                            onClick={(e) => { e.stopPropagation(); onIndexChange((i) => (i - 1 + photos.length) % photos.length); }}
                            aria-label="Previous photo"
                            className="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 text-white rounded-full w-9 h-9 flex items-center justify-center text-xl"
                        >
                            ‹
                        </button>
                        <button
                            onClick={(e) => { e.stopPropagation(); onIndexChange((i) => (i + 1) % photos.length); }}
                            aria-label="Next photo"
                            className="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 text-white rounded-full w-9 h-9 flex items-center justify-center text-xl"
                        >
                            ›
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}
