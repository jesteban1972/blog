// file ~/Sites/blog/assets/scripts/language-bar.js

/**
 * LanguageManager is the universal linguistic agent, responsible for capturing user intent via UI togglers and
 * persisting that choice in a cross-subdomain cookie.
 *
 * this script is a "universal blueprint" for handling UX languages in all apps within pendoncete.org domain.
 */
const LanguageManager = {

    // the shared key used by all applications in the domain:
    COOKIE_NAME: 'pendoncete_ux_language',

    // the manifest of supported languages:
    LANGUAGES: {
        // ar: 'عربية',
        // de: 'Deutsch',
        en: 'English',
        el: 'Ἑλληνικά',
        es: 'Español',
        // fr: 'Français',
        // it: 'Italiano'
    },

    /**
     * this method retrieves a cookie value by name using a regular expression.
     */
    getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    },

    /**
     * this function persists the locale in a cookie. it also performs a "cleanup" to prevent duplicate cookies on
     * subdomains.
     * @param {string} locale the ISO code (e.g., 'el').
     * @param {string|null} domain the parent domain (e.g., '.pendoncete.org'). this parameter is crucial for making the
     * cookie visible to other apps.
     */
    setLocale(locale, domain) {

        const maxAge = 31536000; // persistence for one solar year
        const path = '/';

        // 1. (the Janitor) attempt to delete any host-only cookie that might conflict:.
        document.cookie = `${this.COOKIE_NAME}=; path=${path}; expires=Thu, 01 Jan 1970 00:00:00 GMT`;

        // 2. (the architect) set the new sovereign global cookie.
        // SameSite=Lax balances security and the need for cross-site navigation.
        // Secure is mandatory for modern browsers, especially with SameSite=None/Lax.
        let cookieString = `${this.COOKIE_NAME}=${locale}; path=/; max-age=${maxAge}; SameSite=Lax; Secure`;

        if (domain) {
            cookieString += `; domain=${domain}`;
        }

        document.cookie = cookieString;
    },

    /**
     * this function initializes the logic and updates the UI placeholder.
     */
    init() {

        // 1. update all display spans on the page (age verification & sidebar) on load:
        const currentLocale = this.getCookie(this.COOKIE_NAME) || 'en';
        const displaySpans = document.querySelectorAll('.ux-language-display');
        displaySpans.forEach(span => {
            span.textContent = this.LANGUAGES[currentLocale] || 'English';
        });

        // 2. delegate click events to the language togglers:
        document.querySelectorAll('.ux-language-toggler').forEach(button => {

            button.addEventListener('click', (event) => {

                event.preventDefault();

                // the dataset attribute 'data-ux-language' must exist on the button:
                const newLocale = event.currentTarget.dataset.uxLanguage;

                // the 'data-cookie-domain' attribute should be placed on a parent container (e.g.
                // <div data-cookie-domain=".pendoncete.org">) to keep this JS generic.
                const domain = event.currentTarget.closest('[data-cookie-domain]')?.dataset.cookieDomain;

                if (newLocale && this.LANGUAGES[newLocale]) {

                    this.setLocale(newLocale, domain);

                    // visual feedback (opacity change) before reload:
                    document.body.style.opacity = '0.5';

                    // 1. (the navigator) identify the current path to perform an SEO-friendly redirect:
                    let currentPath = window.location.pathname;

                    // 2. define the locales that use a URL prefix (matching your routes.yaml config):
                    const supportedPrefixes = ['en', 'es', 'el'];
                    let segments = currentPath.split('/');

                    /**
                     * 3. (the re-router) logic for prefix transformation:
                     * segments[0] is empty (string before the first /)
                     * segments[1] is the first actual part of the path
                     * we check if the first path segment (segments[1]) is a supported locale.
                     */
                    if (supportedPrefixes.includes(segments[1])) {
                        // we are currently on a localized route (e.g., /en/..., /es/..., /el/...)
                        // simply replace the existing locale with the new one:
                        segments[1] = newLocale;
                    } else {
                        // re are at the absolute root / (matches app_root in routes.yaml)
                        // or on an infrastructure route like /login.
                        // in this case, we just prepend the locale.
                        segments.splice(1, 0, newLocale);
                    }

                    // 4. reconstruct the URL:
                    const newPath = segments.join('/').replace(/\/+$/, '') || '/';

                    // 5. full reload (allows the PHP Subscriber to catch the new URL/cookie and re-render the context correctly):
                    window.location.href = newPath + window.location.search + window.location.hash;
                }
            });
        });
    }
};

// start the agent once the DOM is ready:
document.addEventListener('DOMContentLoaded', () => LanguageManager.init());
