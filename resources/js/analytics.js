import Clarity from '@microsoft/clarity';

export const CONSENT_KEY = 'fieldwerkz_analytics_consent';

let started = false;

/**
 * Google's gtag.js is shared by both GA4 and Google Ads conversion tracking -
 * the same bootstrap script loads once, then each product just calls
 * `gtag('config', ITS_OWN_ID)`. Safe to call more than once (e.g. once per
 * configured Google product); the <script> tag itself is only injected once.
 */
function startGoogleTag(id) {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag() { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id);

    if (document.getElementById('gtag-js')) return;
    const script = document.createElement('script');
    script.id = 'gtag-js';
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${id}`;
    document.head.appendChild(script);
}

/**
 * Every optional tracker the app can load, in one place - add a new one
 * (Facebook Pixel, LinkedIn Insight, etc.) by adding another entry here, not
 * by touching the consent/gating logic below. Each entry's `id` comes from a
 * VITE_-prefixed env var (see .env.example and vapor.yml's production
 * `environment:` block - a var missing from vapor.yml doesn't survive the
 * production build even if it's set locally) and its `start` only runs if
 * that id is actually configured, so an unconfigured tracker is always a
 * silent no-op rather than an error.
 */
const trackers = [
    { id: import.meta.env.VITE_CLARITY_PROJECT_ID, start: (id) => Clarity.init(id) },
    { id: import.meta.env.VITE_GA_MEASUREMENT_ID, start: startGoogleTag },
    { id: import.meta.env.VITE_GOOGLE_ADS_ID, start: startGoogleTag },
];

// Only starts once consent has been explicitly granted (see SiteNotice.jsx)
// and only in a production build - safe to call any time regardless of
// consent/env state, since every no-op path is silent rather than erroring.
export function startAnalytics() {
    if (started || !import.meta.env.PROD) return;
    started = true;
    trackers.forEach(({ id, start }) => id && start(id));
}

export function hasConsentDecision() {
    return localStorage.getItem(CONSENT_KEY) !== null;
}

export function hasConsented() {
    return localStorage.getItem(CONSENT_KEY) === 'accepted';
}

export function setConsent(accepted) {
    localStorage.setItem(CONSENT_KEY, accepted ? 'accepted' : 'declined');
    if (accepted) startAnalytics();
}
