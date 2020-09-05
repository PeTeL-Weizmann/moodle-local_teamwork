define([
    'core/str'
], function (str) {
    'use strict';

    str.get_strings([
        {key: 'close', component: 'local_teamwork'},
        {key: 'popuperrormessage', component: 'local_teamwork'}
    ]).done(function () {
    });

    const mainBlock = document.querySelector('body');

    const closeBtn = M.util.get_string('close', 'local_social');

    const skin = {

        content: '',
        shadow: 'skin_hide',

        show: function () {

            const popup = document.createElement('div');
            popup.classList.add('skin', 'shadow');
            popup.innerHTML = `
                <div class = "skin_close"></div>
                    <div class="skin_inner"></div>
                    <div class="skin_shadow ${this.shadow}"></div>
                `;
            const popupInner = popup.querySelector('.skin_inner');
            popupInner.innerHTML = this.content;
            this.remove();
            mainBlock.appendChild(popup);
        },

        remove: function () {
            if (mainBlock.querySelector('.skin')) {
                mainBlock.querySelector('.skin').remove();
            }
        }

    };

    return skin;

});
