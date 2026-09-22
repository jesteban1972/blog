// file ~/Sites/blog/assets/app.js

import 'bootstrap';
import './stimulus_bootstrap.js';

// tab logout broadcast listener:
window.addEventListener('storage', (ev) => {
    if (ev.key === 'blog_logout') {
        console.log('[SSO] logout broadcast detected — reloading page.');
        window.location.reload();
    }
});
