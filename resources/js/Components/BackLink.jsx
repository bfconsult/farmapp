import { Link } from '@inertiajs/react';

const className = "inline-flex items-center gap-2 pl-1.5 pr-3 py-1.5 bg-white border border-gray-200 rounded-full shadow-sm hover:bg-gray-50";

const icon = (
    <span className="w-[22px] h-[22px] rounded-full bg-green-50 flex items-center justify-center shrink-0">
        <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 5l-7 7 7 7" />
        </svg>
    </span>
);

// onClick lets a caller replace the plain navigation with custom behaviour
// (e.g. discarding a just-created record instead of leaving it behind) -
// href is still required in that case as a fallback/accessible destination.
export default function BackLink({ href, onClick, children }) {
    if (onClick) {
        return (
            <button type="button" onClick={onClick} className={className}>
                {icon}
                <span className="text-sm font-semibold text-gray-800">{children}</span>
            </button>
        );
    }

    return (
        <Link href={href} className={className}>
            {icon}
            <span className="text-sm font-semibold text-gray-800">{children}</span>
        </Link>
    );
}
