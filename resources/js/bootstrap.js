import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { Ziggy } from './ziggy';
import { route } from 'ziggy-js';
window.route = (name, params, absolute) => route(name, params, absolute, Ziggy);