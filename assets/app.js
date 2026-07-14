// file ~/Sites/blog/assets/app.js

/**
 * this is the app main JavaScript file, performing several tasks:
 * - it contains global imports (e.g. entrypoint.css).
 * - it registers the live component for the wizard.
 * - it handles the logout propagation with a storage event listener.
 */

// import Twitter Bootstrap UI Framework from importmap:
import 'bootstrap';

// import FontAwesome:
import '@fortawesome/fontawesome-free/css/all.min.css';

import './stimulus_bootstrap.js';

// import Stimulus:
import { app } from 'bootstrap_app'; // TODO: my IDE grays out this line

/*
 * this file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// event listener to handle the tab logout broadcast (i.e. the logout propagation through different tabs):
window.addEventListener('storage', (ev) => {

    if (ev.key === 'pendoncete_logout') {
        console.log('[SSO] Logout broadcast detected — cleaning up.');

        // optional: clear any client-side app state

        // reload current page to reflect logout:
        window.location.reload();
    }
});
