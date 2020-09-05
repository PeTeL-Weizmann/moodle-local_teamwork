define([
    'core/ajax',
    'local_teamwork/render',
    'local_teamwork/popup',
    'local_teamwork/loading',
    'local_teamwork/dragulasource',
], function (Ajax, render, popup, loadingIcon, dragula) {
    'use strict';

    const renderPageAfterDrag = (el, callback) => {

        const allTeamsBlocks = Array.from(document.querySelectorAll('div[data-team_id]'));
        const allTeams = [];
        const draguserid = el.dataset.student_id;

        let teamid;
        allTeamsBlocks.forEach((item) => {
            let team = {};
            team.teamid = item.dataset.team_id;
            team.studentid = [];

            let allStudents = Array.from(item.querySelectorAll('.teamwork_student'));
            allStudents.forEach((student) => {
                team.studentid.push(student.dataset.student_id);
            });
            allTeams.push(team);
        });

        loadingIcon.show();
        Ajax.call([{
            methodname: 'local_teamwork_drag_student_card',
            args: {
                courseid: Number(render.data.courseid),
                activityid: Number(render.data.activityid),
                moduletype: render.data.moduletype,
                selectgroupid: render.data.selectgroupid,
                newteamspost: JSON.stringify(allTeams),
                draguserid: Number(draguserid),
            },
            done: function (data) {
                loadingIcon.remove();
                callback(data);
            },
            fail: function () {
                loadingIcon.remove();
                popup.error();
            }
        }]);
    };

    const checkOverflowAndRender = (el, target, source) => {

        let maxCount = source.dataset.max_count;

        if (target.childElementCount === maxCount) {
            target.classList.add('stop-drag');
        }
        if (source.childElementCount < maxCount) {
            source.classList.remove('stop-drag');
        }

        renderPageAfterDrag(el, function () {
            render.setDefaultData();
            render.studentList();
            render.teamsCard();
        });

    };

    const drag = {
        startDrag: function () {
            dragula({
                isContainer: function (el) {
                    return el.classList.contains('draggable');
                },
                accepts: function (el, target) {
                    if (!target.classList.contains('stop-drag')) {
                        return target;
                    }

                },
                invalid: function (el, handle) {
                    if (el.classList.contains('stop-drag-item')) {
                        return true;
                    }
                }
            }).on('drop', checkOverflowAndRender)
        }
    };

    return drag;
});
