/* eslint-disable no-console */
/* eslint-disable no-unused-vars */
define([], function () {
    const FOCUSABLE = {};
    const PREVFOCUSED = {};
    return {
           /**
         * Find focusable elements inside parent.
         * @param  {Object} parentElement - dom element, where finding will be execute.
         * @param  {string} targetElements='' - comma separated specific elements to be included in the searchю
         */
           getFocusableElements: function (parentElement, targetElements = '') {
            const parent = document.querySelector(parentElement);
            if (targetElements.length === 0) {
                FOCUSABLE.elements = Array.from(parent.querySelectorAll(`
                    a[href], button, input:not(:disabled),
                    textarea:not(:disabled), select:not(:disabled),
                    [tabindex]:not([tabindex="-1"])`
                ));
            } else {
                FOCUSABLE.elements = Array.from(parent.querySelectorAll(targetElements));
            }
        },

        /**
         * @param  {string} value - value, to find inside array, taht should be focused.
         */
        setFocusOnElement: function (value) {
            let el = document.querySelector(value);
           if (FOCUSABLE.elements.includes(el)) {
            const index = FOCUSABLE.elements.indexOf(el);
                FOCUSABLE.index = index;
                FOCUSABLE.elements[index].focus();
           } else {
            FOCUSABLE.index = 0;
            FOCUSABLE.elements[0].focus();

           }
        },

        setPrevfocusedElement: function (target) {
            PREVFOCUSED.element = target;
        },
        setFocusOnPrevfocusedElement: function () {
            if (document.querySelector(PREVFOCUSED.element)) {
                document.querySelector(PREVFOCUSED.element).focus();
            } else {
                PREVFOCUSED.element = null;
                this.setFocusOnElement();
            }
        },
        setAccessabilityBehaviuor: function () {
            FOCUSABLE.elements.forEach(element => {
                element.onkeydown = (e) => {
                    if (e.which === 9 && !e.shiftKey) {
                        e.preventDefault();
                         const index = FOCUSABLE.elements.indexOf(e.target) + 1;
                        const newIndex = index >= FOCUSABLE.elements.length ? 0 : index;
                        FOCUSABLE.elements[newIndex].focus();
                    } else if (e.shiftKey && e.which === 9) {
                        e.preventDefault();
                        const index = FOCUSABLE.elements.indexOf(e.target) - 1;
                        const newIndex = index < 0 ? FOCUSABLE.elements.length - 1 : index;
                        FOCUSABLE.elements[newIndex].focus();
                    }
                };
            });
        },
    };
});
