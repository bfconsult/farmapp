import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';

const STORAGE_KEY = 'fieldwerkz_site_notice_ack';

/**
 * A panel embedded in the Home page itself (not a fixed-position overlay) -
 * some browsers/extensions block fixed "popup"-style cookie banners, and it
 * only needs to appear once on the public site anyway, not over the app
 * screens a logged-in user sees afterwards. Slides into view on mount
 * rather than just appearing.
 *
 * Deliberately named/filed generically (not "CookieBanner") - bundlers
 * name the built JS chunk after the component, and ad/tracker blockers
 * (Brave Shields, uBlock, etc.) ship filter rules that block resources
 * matching common cookie-consent-tool filenames, silently preventing the
 * component from ever loading.
 */
export default function SiteNotice() {
    const [dismissed, setDismissed] = useState(true);
    const [entered, setEntered] = useState(false);

    useEffect(() => {
        if (!localStorage.getItem(STORAGE_KEY)) {
            setDismissed(false);
            // Next frame, so the transition actually animates in rather than
            // starting already in its end state.
            requestAnimationFrame(() => setEntered(true));
        }
    }, []);

    const dismiss = () => {
        localStorage.setItem(STORAGE_KEY, '1');
        setDismissed(true);
    };

    if (dismissed) return null;

    return (
        <div
            className={`bg-gray-900 text-white px-4 py-3 flex flex-col sm:flex-row items-center gap-3 transition-all duration-300 ease-out ${
                entered ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-2'
            }`}
        >
            <p className="text-sm flex-1 text-center sm:text-left">
                FieldWerkz only uses essential cookies (to keep you logged in and secure) — no tracking or advertising cookies.{' '}
                <Link href={route('privacy-policy')} className="underline">Learn more</Link>
            </p>
            <button
                onClick={dismiss}
                className="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-md text-sm font-medium whitespace-nowrap flex-shrink-0"
            >
                Got it
            </button>
        </div>
    );
}
