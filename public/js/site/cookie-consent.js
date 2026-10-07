/* cookie-consent.js — from views/layouts/customer_footer.php, loaded where the inline script used to run. */
const cookieConsent = JSON.parse(document.getElementById('cookie-consent-data').textContent);

/* Deferred scripts run AFTER the document is parsed, but this script
   (loaded without defer) runs DURING parsing — so calling initCookiePopup() directly
   here always threw "initCookiePopup is not defined" and the cookie
   notice never appeared on any page. DOMContentLoaded fires after
   deferred scripts have executed, which is the point where site/app.js has
   actually defined it. */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCookiePopup === 'function') {
        initCookiePopup(cookieConsent.signedIn, cookieConsent.accepted);
    }
});
