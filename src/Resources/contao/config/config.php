<?php
// CSS global einbinden (Frontend)
if (TL_MODE === 'FE') {
    $GLOBALS['TL_CSS'][] = 'bundles/fussball/css/fe_fussball_style.css|static';
/*
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/fussball/js/TableSort.js';   // JS ohne |static ? kommt vor </body>
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/fussball/js/pbbootstrap.js';
*/
}
        
// CSS global einbinden (Backend)
if (TL_MODE === 'BE') {
}
