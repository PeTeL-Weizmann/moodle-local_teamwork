define([
    'jquery',
    'core/str',
    'core/ajax',
    'local_teamwork/render',
    'local_teamwork/popup',
    'local_teamwork/loading',
    'local_teamwork/dragulasource',
    'core/modal_factory',
    'core/modal_events',
    'local_teamwork/keyboardnav',
    'core/notification',
], function ($, Str, Ajax, render, popup, loadingIcon, dragula, ModalFactory, ModalEvents, Keyboardnav, Notification) {
    'use strict';

    const renderPageAfterDrag = (el, removeteam, callback) => {

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
                removeteam: removeteam,
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

        // If drag to student list.
        if($(target).hasClass('teamwork_students')){
            let item = $(source).parent().find('.teamwork_team-inner');

            renderPageAfterDrag(el, true, function () {
                render.setDefaultData();
                render.studentList();
                render.teamsCard();
            });

            // Delete user submission.
            // if ($(el).find('.delete_user_submit-btn').length) {
            //     $(el).find('.delete_user_submit-btn')[0].click();
            // } else {
            //     renderPageAfterDrag(el, false, function () {
            //         render.setDefaultData();
            //         render.studentList();
            //         render.teamsCard();
            //     });
            // }

            // Close popup "remove teamcard".
            // if(item.find('.teamwork_student').length === 0){
            //     Str.get_strings([
            //         { key: 'titlepopupremoveteam', component: 'local_teamwork' },
            //         { key: 'contentpopupremoveteam', component: 'local_teamwork' },
            //         { key: 'buttonpopupremoveteam', component: 'local_teamwork' },
            //     ]).done(function (strings) {
            //
            //         var modalPromise = ModalFactory.create({
            //             type: ModalFactory.types.SAVE_CANCEL,
            //             title: strings[0],
            //             body: strings[1]
            //         });
            //
            //         $.when(modalPromise).then(function (fmodal) {
            //
            //             fmodal.setSaveButtonText(strings[2]);
            //
            //             // Handle save event.
            //             fmodal.getRoot().on(ModalEvents.save, function (e) {
            //                 e.preventDefault();
            //
            //                 renderPageAfterDrag(el, true, function () {
            //                     render.setDefaultData();
            //                     render.studentList();
            //                     render.teamsCard();
            //                 });
            //
            //                 fmodal.destroy();
            //             });
            //
            //             fmodal.getRoot().on(ModalEvents.hidden, function () {
            //
            //                 renderPageAfterDrag(el, false, function () {
            //                     render.setDefaultData();
            //                     render.studentList();
            //                     render.teamsCard();
            //                 });
            //
            //                 fmodal.destroy();
            //             });
            //
            //             return fmodal;
            //         }).done(function (modal) {
            //             modal.show();
            //         }).fail(Notification.exception);
            //     })
            // }else{
            //     renderPageAfterDrag(el, false, function () {
            //         render.setDefaultData();
            //         render.studentList();
            //         render.teamsCard();
            //     });
            // }
        }else{


            let item = $(source).parent().find('.teamwork_team-inner');

            // Delete user submission.
            // if ($(el).find('.delete_user_submit-btn').length) {
            //
            //     Str.get_strings([
            //         {key: 'titlepopupremoveuser', component: 'local_teamwork'},
            //         {key: 'contentpopupremoveuser', component: 'local_teamwork'},
            //         {key: 'buttonpopupremoveuser', component: 'local_teamwork'},
            //     ]).done(function(strings) {
            //         var modalPromise = ModalFactory.create({
            //             type: ModalFactory.types.SAVE_CANCEL,
            //             title: strings[0],
            //             body: strings[1]
            //         });
            //
            //         $.when(modalPromise).then(function(fmodal) {
            //
            //             fmodal.setSaveButtonText(strings[2]);
            //
            //             // Handle save event.
            //             fmodal.getRoot().on(ModalEvents.save, function(e) {
            //                 e.preventDefault();
            //
            //                 renderPageAfterDrag(el, false, function () {
            //                     render.setDefaultData();
            //                     render.studentList();
            //                     render.teamsCard();
            //                     $('.skin.shadow').removeAttr("style");
            //                     fmodal.destroy();
            //                 });
            //             });
            //
            //             fmodal.getRoot().on(ModalEvents.cancel, function(e) {
            //                 render.setDefaultData();
            //                 render.studentList();
            //                 render.teamsCard();
            //                 $('.skin.shadow').removeAttr("style");
            //                 fmodal.destroy();
            //                 Keyboardnav.setFocusOnPrevfocusedElement();
            //             });
            //
            //             var root = fmodal.getRoot();
            //             root.on(ModalEvents.shown, function() {
            //                 $('.skin.shadow').css("z-index", "1000");
            //             });
            //
            //             root.on(ModalEvents.hidden, function() {
            //                 render.setDefaultData();
            //                 render.studentList();
            //                 render.teamsCard();
            //                 $('.skin.shadow').removeAttr("style");
            //                 fmodal.destroy();
            //                 Keyboardnav.setFocusOnPrevfocusedElement();
            //             });
            //
            //             return fmodal;
            //         }).done(function(modal) {
            //             modal.show();
            //         }).fail(Notification.exception);
            //     });
            // } else {
            //     renderPageAfterDrag(el, false, function () {
            //         render.setDefaultData();
            //         render.studentList();
            //         render.teamsCard();
            //     });
            // }

            renderPageAfterDrag(el, false, function () {
                render.setDefaultData();
                render.studentList();
                render.teamsCard();
            });
        }
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
