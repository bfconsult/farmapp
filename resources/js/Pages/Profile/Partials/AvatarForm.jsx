import Avatar from '@/Components/Avatar';
import AvatarCropModal from '@/Components/AvatarCropModal';
import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function AvatarForm() {
    const user = usePage().props.auth.user;
    const fileInput = useRef(null);
    const [imageSrc, setImageSrc] = useState(null);
    const [uploading, setUploading] = useState(false);

    const pickFile = () => fileInput.current?.click();

    const selectFile = (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;

        setImageSrc(URL.createObjectURL(file));
    };

    const cancelCrop = () => {
        if (imageSrc) URL.revokeObjectURL(imageSrc);
        setImageSrc(null);
    };

    const upload = (croppedFile) => {
        if (imageSrc) URL.revokeObjectURL(imageSrc);
        setImageSrc(null);
        setUploading(true);

        const formData = new FormData();
        formData.append('avatar', croppedFile);

        router.post(route('profile.avatar.update'), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    const remove = () => {
        if (confirm('Remove your avatar?')) {
            router.delete(route('profile.avatar.destroy'), { preserveScroll: true });
        }
    };

    return (
        <section className="flex items-center gap-4">
            <Avatar user={user} size="lg" />

            <div className="space-y-2">
                <div className="flex gap-3">
                    <button
                        type="button"
                        onClick={pickFile}
                        disabled={uploading}
                        className="text-sm font-medium text-green-600"
                    >
                        {uploading ? 'Uploading...' : user.avatar_url ? 'Change photo' : 'Add photo'}
                    </button>
                    {user.avatar_url && (
                        <button type="button" onClick={remove} className="text-sm text-red-500">
                            Remove
                        </button>
                    )}
                </div>
                <p className="text-xs text-gray-500">JPG, PNG, or GIF. Max 10MB.</p>
            </div>

            <input
                ref={fileInput}
                type="file"
                accept="image/*"
                onChange={selectFile}
                className="hidden"
            />

            {imageSrc && (
                <AvatarCropModal imageSrc={imageSrc} onCancel={cancelCrop} onCropped={upload} />
            )}
        </section>
    );
}
