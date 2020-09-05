define([
    'core/yui',
    "core/ajax",
    'local_teamwork/popup',
    'local_teamwork/skin',
], function (Y, Ajax, popup, skin) {
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
        mainBlock: function (searchInit) {
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
                },
                fail: function () {
                    popup.error();
                }
            }]);
        },

        studentList: function () {
            const targetBlock = document.querySelector('#studentList');

            Ajax.call([{
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
                },
                fail: function () {
                    popup.error();
                }
            }]);
        },

        teamsCard: function () {
            const targetBlock = document.querySelector('#teamsCard');

            Ajax.call([{
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
                },
                fail: function () {
                    popup.error();
                }
            }]);
        }

    };

    return render;

});
