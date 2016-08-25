/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'Sugarcode_Test/js/view/checkout/summary/fee'
    ],
    function (Component) {
        'use strict';

        return Component.extend({

            /**
             * @override
             */
            isDisplayed: function () {
                return true;
            }
        });
    }
);