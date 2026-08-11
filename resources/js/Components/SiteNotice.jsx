import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { hasConsentDecision, setConsent } from '@/analytics';

/**
 * A panel embedded in the page flow itself (not a fixed-position overlay) -
 * some browsers/extensions block fixed "popup"-style cookie banners. Shown
 * once on the public site and once inside the authenticated app, since
 * consent covers analytics that runs across both, not just the marketing
 * page. Slides into view on mount rather than just appearing.
 *
 * Deliberately named/filed generically (not "CookieBanner"/"ConsentBanner") -
 * bundlers name the built JS chunk after the component, and ad/tracker
 * blockers (Brave Shields, uBlock, etc.) ship filter rules that block
 * resources matching common cookie-consent-tool filenames, silently
 * preventing the component from ever loading.
 */
export default function SiteNotice({ className = '' }) {
    const [dismissed, setDismissed] = useState(true);
    const [entered, setEntered] = useState(false);

    useEffect(() => {
        if (!hasConsentDecision()) {
            setDismissed(false);
            // Next frame, so the transition actually animates in rather than
            // starting already in its end state.
            requestAnimationFrame(() => setEntered(true));
        }
    }, []);

    const respond = (accepted) => {
        setConsent(accepted);
        setDismissed(true);
    };

    if (dismissed) return null;

    return (
        <div
            className={`bg-gray-900 text-white px-4 py-3 flex flex-col sm:flex-row items-center gap-3 transition-all duration-300 ease-out ${
                entered ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-2'
            } ${className}`}
        >
            <p className="text-sm flex-1 text-center sm:text-left">
                FieldWerkz uses essential cookies to keep you logged in, and — with your
                permission — analytics and advertising tools (Microsoft Clarity, Google
                Analytics, Google Ads) to understand how the app is used and measure our
                own advertising.{' '}
                <Link href={route('privacy-policy')} className="underline">Learn more</Link>
            </p>
            <div className="flex gap-2 flex-shrink-0">
                <button
                    onClick={() => respond(false)}
                    className="px-4 py-2 border border-gray-500 hover:border-gray-400 rounded-md text-sm font-medium whitespace-nowrap"
                >
                    Decline
                </button>
                <button
                    onClick={() => respond(true)}
                    className="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-md text-sm font-medium whitespace-nowrap"
                >
                    Accept
                </button>
            </div>
        </div>
    );
}
