/* eslint-disable no-console */
define([
    'core/yui',
    "core/ajax",
    'local_teamwork/popup',
    'local_teamwork/skin',
    'local_teamwork/keyboardnav',
    'local_teamwork/voicecontrol',
], function (Y, Ajax, popup, skin, Keyboardnav, Voicecontrol) {
    'use strict';

    let render = {
        data: '',
        sesskey: M.cfg.sesskey,

        // Set default data.
        setDefaultData: function () {
            let sesskey = this.data.sesskey;
            let courseid = this.data.courseid;
            let activityid = this.data.activityid;
            let moduletype = this.data.moduletype;
            let selectgroupid = this.data.selectgroupid;

            this.data = {
                sesskey: sesskey,
                courseid: courseid,
                activityid: activityid,
                moduletype: moduletype,
                selectgroupid: selectgroupid
            };
        },

        // Open main block.
        mainBlock: function (searchInit, voicecontrolenabled) {


            Ajax.call([{
                methodname: 'local_teamwork_render_teamwork_html',
                args: {
                    courseid: Number(this.data.courseid),
                    activityid: Number(this.data.activityid),
                    moduletype: this.data.moduletype,
                    selectgroupid: this.data.selectgroupid
                },
                done: function (data) {
                    let result = JSON.parse(data.result);
                    skin.shadow = result.shadow;
                    skin.content = result.content;
                    skin.show();
                    searchInit();

                    Keyboardnav.getFocusableElements('.teamworkdialog');
                    Keyboardnav.setAccessabilityBehaviuor();


                    // Voicecontrol.voice_add_new_teamcard(
                    //     Number(self.data.courseid),
                    //     Number(self.data.activityid),
                    //     self.data.moduletype,
                    //     self.data.selectgroupid);


                    // Voicecontrol.voice_drag_student_card(
                    //     Number(self.data.courseid),
                    //     Number(self.data.activityid),
                    //     self.data.moduletype,
                    //     self.data.selectgroupid
                    //     // courseid, activityid, moduletype, selectgroupid,
                    //     // self
                    // );


                    if (voicecontrolenabled !== 0) {
                        Voicecontrol.update_commands();
                    }

                },
                fail: function () {
                    popup.error();
                }
            }]);
        },

        studentList: function () {
            const targetBlock = document.querySelector('#studentList');

            return Ajax.call([{
                methodname: 'local_teamwork_render_student_list',
                args: {
                    courseid: Number(this.data.courseid),
                    activityid: Number(this.data.activityid),
                    moduletype: this.data.moduletype,
                    selectgroupid: this.data.selectgroupid
                },
                done: function (data) {
                    let result = JSON.parse(data.result);
                    targetBlock.innerHTML = result.content;
                    Keyboardnav.getFocusableElements('.teamworkdialog');
                    Keyboardnav.setAccessabilityBehaviuor();
                    Keyboardnav.setFocusOnPrevfocusedElement();
                },
                fail: function () {
                    popup.error();
                }
            }]);
        },

        teamsCard: function () {
            const targetBlock = document.querySelector('#teamsCard');

            return Ajax.call([{
                methodname: 'local_teamwork_render_teams_card',
                args: {
                    courseid: Number(this.data.courseid),
                    activityid: Number(this.data.activityid),
                    moduletype: this.data.moduletype,
                    selectgroupid: this.data.selectgroupid
                },
                done: function (data) {
                    let result = JSON.parse(data.result);
                    targetBlock.innerHTML = result.content;
                    Keyboardnav.getFocusableElements('.teamworkdialog');
                    Keyboardnav.setAccessabilityBehaviuor();
                    Keyboardnav.setFocusOnPrevfocusedElement();
                },
                fail: function () {
                    popup.error();
                }
            }]);
        }

    };

    return render;

});
