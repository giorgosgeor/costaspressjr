/* i18n.js — from views/layouts/customer_footer.php, loaded where the inline script used to run. */

/* Translation strings exposed to client-side JS: set by /js/i18n/<locale>.js,
   loaded just before this (I18n::clientScript), or inline in the page when
   that file couldn't be built. */
window.I18N = window.I18N || JSON.parse(document.getElementById('i18n-data').textContent);
window.I18N.t = function(key, params) {
    var s = (this.messages && this.messages[key]) || key;
    if (params) {
        for (var k in params) {
            s = s.split('{' + k + '}').join(params[k]);
        }
    }
    return s;
};
