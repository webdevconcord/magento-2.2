define(
    [
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'mage/url'
    ],
    function (ko, Component, url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Concordpay_Payment/payment/concordpay',
                //redirectAfterPlaceOrder: false
            },
            // afterPlaceOrder: function () {
            //     // Redirect to your controller action after place order button click
            //     //debugger;
            //     window.location.replace(url.build('concordpay/url/concordpaysuccess'));
            // },
            /**
             * Get value of instruction field.
             * @returns {String}
             */
            getInstructions: function () {
                //return window.checkoutConfig.payment.instructions[this.item.method];
            }
        });
    }
);
