// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript to initialise the myoverview block.
 *
 * @package    local_teamwork
 * @copyright  2018 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
        'jquery',
        'core/str',
        'core/ajax',
        'core/notification',
    ],
    function ($, Str, Ajax, Notification) {

        return {
            selector: {
                wrapper: '#rubric-advancedgrading',
                wrapper_error: '#id_error_advancedgrading',
                button: '#save-rubrics',
            },

            /**
             * Initialise all of the modules for the overview block.
             *
             * @param {object} courseids The root element for the overview block.
             */
            init: function (userid, callback) {

                // Get url param.
                var queryDict = {}
                location.search.substr(1).split("&").forEach(function(item) {
                    queryDict[item.split("=")[0]] = item.split("=")[1];
                });

                if (queryDict.id.length === 0 || queryDict.action.length === 0 || queryDict.action !== 'grader') return;

                var assignid = queryDict.id;

                var rubricsave_notice = '';
                Str.get_string('rubricsave_notice', 'local_teamwork').done(function(translation) {
                    rubricsave_notice = translation;
                });
                var button_name = '';
                Str.get_string('rubricsave', 'local_teamwork').done(function(translation) {
                    button_name = translation;
                });

                var self = this;
                this.waitForElement(this.selector.wrapper, function () {
                    $('<span></span><a id="save-rubrics" class="btn btn-primary mt-10" style="color:white;font-weight: bold;">' +
                        button_name + '</a>' + rubricsave_notice + '</span>').insertAfter(self.selector.wrapper);

                    var root = $(self.selector.button);
                    root.on('click', function (e) {
                        var target = $(e.target);

                        // Get the element.
                        var element = window.document.getElementsByClassName("gradingform_rubric")[0];

                        // Generate the PDF.
                        self.loadingPage();
                        require(['local_teamwork/html2pdf_bundle'], function(html2pdf) {

                            // html2pdf().from(element).set({
                            //     margin: 1,
                            //     filename: 'test.pdf',
                            //     html2canvas: { scale: 2 },
                            //     jsPDF: {orientation: 'portrait', unit: 'in', format: 'letter', compressPDF: true}
                            // }).save();

                            html2pdf().from(element).set({
                                margin: 1,
                                filename: 'test.pdf',
                                html2canvas: {scale: 2},
                                jsPDF: {orientation: 'portrait', unit: 'in', format: 'letter', compressPDF: true}
                            }).outputPdf().then(function (pdf) {

                                Ajax.call([{
                                    methodname: 'local_teamwork_save_rubrics_pdf',
                                    args: {
                                        assignid: Number(assignid),
                                        userid: Number(userid),
                                        content: btoa(pdf)
                                    },
                                    done: function () {
                                        callback();
                                    },
                                    fail: Notification.exception
                                }]);
                            });
                        });

                    });
                });
            },

            waitForElement: function (elementPath, callBack) {
                var self = this;
                window.setTimeout(function () {
                    if ($(elementPath).length) {
                        callBack(elementPath, $(elementPath));
                    } else {
                        self.waitForElement(elementPath, callBack);
                    }
                }, 500);
            },

            loadingPage: function () {
                var shadowStyle="display: flex; position: fixed; z-index: 99999; top: 0; bottom: 0; right: 0; left: 0; background: #0000006b;"
                var spinnerStyle = "margin: auto;" +
                    "width: 6rem;" +
                    "height: 6rem;" +
                    "border: .6em solid #fff;" +
                    "border-right-color: transparent;" +
                    "border-radius: 50%;" +
                    "animation: spinner-border .75s linear infinite;";
                var spinner = '<div class = "spinner" style = "'+ spinnerStyle +'"></div>';
                var shadow = $('<div class = "loading" style="'+ shadowStyle +'">'+ spinner +'</div>');
                $('body').append(shadow);
            }
        };
    });
