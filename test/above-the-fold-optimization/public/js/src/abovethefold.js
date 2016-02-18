/**
 * Above the fold optimization Javascript
 *
 * This javascript handles the CSS delivery optimization.
 *
 * @package    abovethefold
 * @subpackage abovethefold/public
 * @author     Optimalisatie.nl <info@optimalisatie.nl>
 */

window['abovethefold'] = {

    debug: false,

    /**
     * Load CSS asynchronicly
     *
     * @link https://developers.google.com/speed/docs/insights/OptimizeCSSDelivery
     */
    css: function(files) {

        if (this.debug) {
            if (!files) {
                return;
            } else {
                console.log('abovethefold.css()', files);
            }
        }
        if (!files) {
            return;
        }

        for (i in files) {
            m = files[i][0].join(',');
            this.loadCSS(files[i][1],document.getElementById('atfcss'),m);
        }
    },

    /**
     * DomReady
     */
    ready: function(a, b, c) {
        b = document;
        c = 'addEventListener';
        b[c] ? b[c]('DocumentContentLoaded', a) : window.attachEvent('onload', a);
    }
};