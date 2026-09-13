import Spinner from '@/Components/Spinner';

export default function UploadingOverlay({ show, label = 'Uploading photo…' }) {
    if (!show) return null;

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center">
            <div className="bg-white rounded-lg shadow-lg px-6 py-5 flex flex-col items-center gap-3">
                <Spinner className="h-10 w-10 text-green-600" />
                <p className="text-sm text-gray-700">{label}</p>
            </div>
        </div>
    );
}
