import { Link } from '@inertiajs/react';

export default function BackLink({ href, children }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-2 pl-1.5 pr-3 py-1.5 bg-white border border-gray-200 rounded-full shadow-sm hover:bg-gray-50"
        >
            <span className="w-[22px] h-[22px] rounded-full bg-green-50 flex items-center justify-center shrink-0">
                <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round">
                    <path d="M15 5l-7 7 7 7" />
                </svg>
            </span>
            <span className="text-sm font-semibold text-gray-800">{children}</span>
        </Link>
    );
}
