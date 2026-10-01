// file ~/Sites/blog/assets/app.js

import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'flag-icons/css/flag-icons.min.css';
import './stimulus_bootstrap.js';

// tab logout broadcast listener:
window.addEventListener('storage', (ev) => {
    if (ev.key === 'blog_logout') {
        console.log('[SSO] logout broadcast detected — reloading page.');
        window.location.reload();
    }
});
