/**
 * loadCSS (installed from bower module)
 *
 * @link https://github.com/filamentgroup/loadCSS/
 *
 * @package    abovethefold
 * @subpackage abovethefold/public
 * @author     Optimalisatie.nl <info@optimalisatie.nl>
 */

window['abovethefold'].loadCSS = (typeof loadCSS !== 'undefined') ? function( href, before, media, callback ) {

    if (window['abovethefold'].debug) {
        console.info('abovethefold.css() > loadCSS() async download start', href);
    }
    loadCSS( href, before, media, function() {
        if (window['abovethefold'].debug) {
            console.info('abovethefold.css() > loadCSS() render', href);
        }
        if (callback) {
            callback();
        }
    } );

} : function() {};