import { useState } from 'react';
import Cropper from 'react-easy-crop';
import Modal from '@/Components/Modal';
import { getCroppedImageFile } from '@/cropImage';

export default function AvatarCropModal({ imageSrc, onCancel, onCropped }) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
    const [saving, setSaving] = useState(false);

    const save = async () => {
        if (!croppedAreaPixels) return;
        setSaving(true);
        const file = await getCroppedImageFile(imageSrc, croppedAreaPixels);
        setSaving(false);
        onCropped(file);
    };

    return (
        <Modal show={Boolean(imageSrc)} onClose={onCancel} maxWidth="sm">
            <div className="p-4 space-y-4">
                <h3 className="text-sm font-medium text-gray-700">Adjust photo</h3>

                <div className="relative w-full h-72 bg-gray-900 rounded-lg overflow-hidden">
                    <Cropper
                        image={imageSrc}
                        crop={crop}
                        zoom={zoom}
                        aspect={1}
                        cropShape="round"
                        showGrid={false}
                        onCropChange={setCrop}
                        onZoomChange={setZoom}
                        onCropComplete={(_, pixels) => setCroppedAreaPixels(pixels)}
                    />
                </div>

                <div className="flex items-center gap-3">
                    <span className="text-xs text-gray-500">Zoom</span>
                    <input
                        type="range"
                        min={1}
                        max={3}
                        step={0.05}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                        className="flex-1"
                    />
                </div>

                <div className="flex gap-2">
                    <button
                        onClick={save}
                        disabled={saving || !croppedAreaPixels}
                        className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : 'Save'}
                    </button>
                    <button
                        onClick={onCancel}
                        className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    );
}
