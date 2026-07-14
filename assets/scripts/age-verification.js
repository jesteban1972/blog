// file ~/Sites/blog/assets/scripts/age-verification.js

(function() {

    const COOKIE_NAME = 'pendoncete_age_verified';

    const overlay = document.getElementById('age-verification-overlay');
    if (!overlay) {
        return;
    }

    // check if verified:
    const isVerified = document.cookie.split('; ').find(row => row.startsWith(COOKIE_NAME + '='));
    if (!isVerified) {
        overlay.style.display = 'flex';
    }

    // handle the button click:
    const buttonElement = overlay.querySelector('button');
    if (buttonElement) {
        buttonElement.addEventListener('click', function() {

            const currentDate = new Date();
            currentDate.setTime(currentDate.getTime() + (30*24*60*60*1000)); // one month
            let expires = "expires="+ currentDate.toUTCString();

            // dynamic domain logic (pendoncete.org|localhost):
            const host = window.location.hostname;
            const parts = host.split('.');
            const rootDomain = parts.length > 2
                ? "." + parts.slice(-2).join('.')
                : "." + host;

            // set cookie:
            document.cookie = `${COOKIE_NAME}=true;${expires};path=/;domain=${rootDomain};SameSite=Lax`;

            // remove the overlay:
            overlay.remove();
        });
    }
})();
