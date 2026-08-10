// Deterministic per-user color, so the same person's initials avatar looks
// the same across sessions/devices without storing a color anywhere.
const COLORS = [
    'bg-green-600', 'bg-blue-600', 'bg-purple-600', 'bg-red-600',
    'bg-amber-600', 'bg-teal-600', 'bg-pink-600', 'bg-indigo-600',
];

const SIZE_CLASSES = {
    sm: 'w-8 h-8 text-xs',
    md: 'w-10 h-10 text-sm',
    lg: 'w-16 h-16 text-lg',
};

function initials(name) {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function colorFor(seed) {
    const str = String(seed);
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = (hash * 31 + str.charCodeAt(i)) | 0;
    }
    return COLORS[Math.abs(hash) % COLORS.length];
}

export default function Avatar({ user, size = 'md', className = '' }) {
    const sizeClass = SIZE_CLASSES[size] ?? SIZE_CLASSES.md;

    if (user?.avatar_url) {
        return (
            <img
                src={user.avatar_url}
                alt={user.name}
                className={`${sizeClass} rounded-full object-cover flex-shrink-0 ${className}`}
            />
        );
    }

    return (
        <div
            className={`${sizeClass} rounded-full flex-shrink-0 flex items-center justify-center font-semibold text-white ${colorFor(user?.id ?? user?.name ?? '?')} ${className}`}
        >
            {initials(user?.name)}
        </div>
    );
}
