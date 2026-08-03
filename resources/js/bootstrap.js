import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { Ziggy } from './ziggy';
import { route } from 'ziggy-js';
window.route = (name, params, absolute) => route(name, params, absolute, Ziggy);

// Lets the backend convert UTC timestamps to this browser's local time for
// server-rendered output (PDF/Excel exports) - see CaptureUserTimezone.
document.cookie = `timezone=${Intl.DateTimeFormat().resolvedOptions().timeZone}; path=/; max-age=31536000; samesite=lax`;