/* eslint-disable require-jsdoc */
/* eslint-disable max-len */
/* eslint-disable no-console */
/* eslint-disable no-unused-vars */
define([
    'core/str',
    'jquery',
    'local_teamwork/keyboardnav',
], function (str, $, Keyboardnav) {
    'use strict';

    str.get_strings([
        { key: 'close', component: 'local_teamwork' },
        { key: 'popuperrormessage', component: 'local_teamwork' }
    ]).done(function () {
    });

    const mainBlock = document.querySelector('body');

    const closeBtnStr = M.util.get_string('close', 'local_teamwork');

    const skin = {
        SKINSTATE: null,
        content: '',
        shadow: 'skin_hide',

        show: function () {

            const popup = document.createElement('div');
            popup.classList.add('skin', 'shadow', 'teamworkdialog');
            popup.innerHTML = `
                <div class = "skin_close" role="button" tabindex="0" aria-label="${closeBtnStr}"></div>
                <div class="skin_inner"></div>
                <div class="skin_shadow ${this.shadow}"></div>
                `;
            const popupInner = popup.querySelector('.skin_inner');
            popupInner.innerHTML = this.content;
            this.remove();
            mainBlock.appendChild(popup);

            // Loop focused elements inside popup. Accessibility task

            // this.popupSkinState();
            this.checkPopupSkinState();
            if(!this.SKINSTATE) {
                Keyboardnav.getFocusableElements('.teamworkdialog', '[data-handler="teamwork_toggle"], .skin_close');
            } else {
                Keyboardnav.getFocusableElements('.teamworkdialog');
            }
            Keyboardnav.setFocusOnElement('[data-handler="teamwork_toggle"]');
            Keyboardnav.setAccessabilityBehaviuor();

        },
         /**
        * Check if popup shadow skin is visible.
         * @param  {string} skinState - The state of modal shadow block.
         */
         checkPopupSkinState: function (skinState) {
            // Set skin_shadow state on popup parrent element.
            if (!skinState) {
                skinState = $('[data-handler="teamwork_toggle"]').data('state');
            }
            this.SKINSTATE = skinState === 'active' ? true : false;
            $(".teamworkdialog").attr('data-state', skinState);
        },


        /**
         * Check popup skin state and set approporate behaviour.
         */
        // checkPopupSkinState: function () {
        //     if (this.SKINSTATE) {
        //         this.getFocusableElements('.teamworkdialog');
        //     } else if (!this.SKINSTATE) {
        //         this.getFocusableElements('.teamworkdialog', '[data-handler="teamwork_toggle"], .skin_close');
        //     }
        // },

        remove: function () {
            if (mainBlock.querySelector('.skin')) {
                mainBlock.querySelector('.skin').remove();

                let links = $('.submissionlinks a');
                if (links.length !== 0) {
                    links[0].focus();
                }
            }
        }

    };

    return skin;

});
