import Clarity from '@microsoft/clarity';

export const CONSENT_KEY = 'fieldwerkz_analytics_consent';

const projectId = import.meta.env.VITE_CLARITY_PROJECT_ID;
let started = false;

// Only starts once consent has been explicitly granted (see SiteNotice.jsx)
// and only in a production build - a missing project ID or local dev both
// no-op silently rather than erroring, so this is always safe to call.
export function startAnalytics() {
    if (started || !projectId || !import.meta.env.PROD) return;
    started = true;
    Clarity.init(projectId);
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
