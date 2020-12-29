define([
    'jquery',
    'core/str',
    'core/ajax',
    'local_teamwork/popup',
    'local_teamwork/render',
    'local_teamwork/dragula',
    'local_teamwork/skin',
    'local_teamwork/loading',
    'core/notification',
], function ($, str, Ajax, popup, render, drag, skin, loadingIcon, Notification) {
    'use strict';

    const mainBlock = document.querySelector('body');

    const set_teamwork_enable = (courseid, activityid, moduletype, callback) => {
        Ajax.call([{
            methodname: 'local_teamwork_set_teamwork_enable',
            args: {
                activityid: Number(activityid),
                moduletype: moduletype,
            },
            done: function (data) {
                callback(data);
            },
            fail: function () {
                popup.error();
            }
        }]);
    };

    const set_access_to_student = (courseid, activityid, moduletype, target) => {
        const access = target.checked;

        Ajax.call([{
            methodname: 'local_teamwork_set_access_to_student',
            args: {
                activityid: Number(activityid),
                moduletype: moduletype,
            },
            done: function (data) {

            },
            fail: function () {
                popup.error();
            }
        }]);
    };

    const add_new_card = (courseid, activityid, moduletype, selectgroupid, callback) => {

        loadingIcon.show();
        Ajax.call([{
            methodname: 'local_teamwork_add_new_card',
            args: {
                courseid: Number(courseid),
                activityid: Number(activityid),
                moduletype: moduletype,
                selectgroupid: selectgroupid,
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

    const delete_card = (teamid, courseid, activityid, moduletype, callback) => {

        loadingIcon.show();
        Ajax.call([{
            methodname: 'local_teamwork_delete_card',
            args: {
                teamid: teamid
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

    const show_random_popup = () => {
        Ajax.call([{
            methodname: 'local_teamwork_show_random_popup',
            args: {},
            done: function (data) {
                let result = JSON.parse(data.result);
                popup.textHead = result.header;
                popup.text = result.content;
                popup.show();
            },
            fail: function () {
                popup.error();
            }
        }]);
    };

    /* toggle actions on allow enddate checkbox status change in student settings popup */
    const student_settings_enddate_state = () => {
        const teamuserallowenddatechkbox = document.querySelector("#teamuserallowenddate");
        const allinputs = Array.from(
            document.querySelectorAll(".teamuserenddate-inputs-wrapper input, .teamuserenddate-inputs-wrapper select")
        );
        teamuserallowenddatechkbox.addEventListener("change", e => {
            if (e.target.checked) {
                allinputs.forEach(item => {
                    item.removeAttribute("disabled");
                });
                e.target.value = "1";
            } else {
                allinputs.forEach(item => {
                    item.setAttribute("disabled", "disabled");
                });
                e.target.value = "0";
            }
        });
    };

    /* render_student_settings_popup */
    const render_student_settings_popup = (activityid, moduletype) => {
        Ajax.call([{
            methodname: 'local_teamwork_render_student_settings_popup',
            args: {
                activityid: activityid,
                moduletype: moduletype
            },
            done: function (data) {
                let result = JSON.parse(data.result);
                popup.textHead = result.header;
                popup.text = result.content;
                popup.show();
                setTimeout(student_settings_enddate_state, 1000);
            },
            fail: function () {
                popup.error();
            }
        }]);
    };

    const student_settings_popup_data = (courseid, activityid, moduletype) => {
        const popupForm = document.querySelector(".teamwork-modal");
        const teamNumbers = popupForm.querySelector('#teamnumbers').value;
        const teamUserNumbers = popupForm.querySelector('#teamusernumbers').value;
        const teamUserendDate = popupForm.querySelector(
            "input[name=team-userend-date]"
        ).value;
        const teamUserendMonth = popupForm.querySelector(
            "select[name=team-userend-month]"
        ).value;
        const teamUserendYear = popupForm.querySelector(
            "input[name=team-userend-year]"
        ).value;
        const teamUserendHour = popupForm.querySelector(
            "input[name=team-userend-hour]"
        ).value;
        const teamUserendMinute = popupForm.querySelector(
            "input[name=team-userend-minute]"
        ).value;
        const teamuserallowenddate = popupForm.querySelector(
            "input[name=teamuserallowenddate]"
        ).value;

        loadingIcon.show();
        Ajax.call([{
            methodname: 'local_teamwork_student_settings_popup_data',
            args: {
                courseid: Number(courseid),
                activityid: Number(activityid),
                moduletype: moduletype,
                teamnumbers: Number(teamNumbers),
                teamusernumbers: Number(teamUserNumbers),
                teamuserenddate: Number(teamUserendDate),
                teamuserendmonth: Number(teamUserendMonth),
                teamuserendyear: Number(teamUserendYear),
                teamuserendhour: Number(teamUserendHour),
                teamuserendminute: Number(teamUserendMinute),
                teamuserallowenddate: Number(teamuserallowenddate),
            },
            done: function (data) {
                loadingIcon.remove();
            },
            fail: function () {
                popup.error();
            }
        }]);
    };

    const set_random_teams = (target, courseid, activityid, moduletype, selectgroupid, callback) => {
        while (!target.classList.contains('teamwork-modal')) {
            target = target.parentNode;
        }
        const numberofstudent = target.querySelector('#student_number').value;

        loadingIcon.show();
        Ajax.call([{
            methodname: 'local_teamwork_set_random_teams',
            args: {
                courseid: Number(courseid),
                activityid: Number(activityid),
                moduletype: moduletype,
                selectgroupid: selectgroupid,
                numberofstudent: Number(numberofstudent),
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

    const set_new_team_name = target => {
        const cardid = target.dataset.team_id;
        const cardname = target.value;

        Ajax.call([{
            methodname: 'local_teamwork_set_new_team_name',
            args: {
                cardid: Number(cardid),
                cardname: cardname,
            },
            done: function (data) {

            },
            fail: function () {
                popup.error();
            }
        }]);
    };

    // Search student by name on student list.
    const searchStudentByName = target => {
        const studentList = Array.from(
            document.querySelectorAll("#studentList div[data-student_id]")
        );
        const hiddenClass = 'visuallyhidden';
        const searchItem = target.value;
    if (!searchItem) {
        studentList.forEach(item => {
            item.classList.remove(hiddenClass);
        });
        return;
    }

        studentList.forEach(item => {
            if (item.innerHTML.toLowerCase().search(searchItem.toLowerCase()) >= 0) {
                item.classList.remove(hiddenClass);
            } else {
                item.classList.add(hiddenClass);
            }
        });
    };

    const searchInit = () => {
        // Init search for student list.
        const searchInput = mainBlock.querySelector(
            'input[data-handler = "search_student"]'
        );
        searchInput.addEventListener("input", function (e) {
            searchStudentByName(searchInput);
        });
    };

    const searchReset = target => {
        const studentList = Array.from(
            document.querySelectorAll("#studentList div[data-student_id]")
        );
        const hiddenClass = 'visuallyhidden';
        const searchInput = mainBlock.querySelector(
            'input[data-handler = "search_student"]'
        );

        searchInput.value = '';
        studentList.forEach(item => {
            item.classList.remove(hiddenClass);
        });
    };

    return {
        init: function (courseid, activityid, moduletype, selectgroupid) {
            var self = this;

            Ajax.call([{
                methodname: 'local_teamwork_render_block_html_page',
                args: {
                    courseid: Number(courseid),
                    activityid: Number(activityid),
                    moduletype: moduletype,
                    selectgroupid: selectgroupid
                },
                done: function (data) {
                    var el = $("body#page-mod-assign-view #intro");
                    el.append(data.result);
                    self.renderHtmlToPage(courseid, activityid, moduletype, selectgroupid);
                },
                fail: Notification.exception
            }]);
        },
        renderHtmlToPage: function (courseid, activityid, moduletype, selectgroupid) {
            render.data = {
                sesskey: M.cfg.sesskey,
                courseid: courseid,
                activityid: activityid,
                moduletype: moduletype,
                selectgroupid: selectgroupid
            };

            // Run and open local window.
            $("#open_local").on("click", function () {
                render.mainBlock(searchInit);
            });

            document.addEventListener('click', function (event) {
                let target = event.target;
                while (target !== mainBlock) {
                    // Activate/diactivate teamwork.
                    if (target.dataset.handler === 'teamwork_toggle') {
                        target.classList.toggle('active');

                        $(".skin_shadow").toggleClass("skin_show");
                        $(".skin_shadow").toggleClass("skin_hide");
                        set_teamwork_enable(courseid, activityid, moduletype);
                        return;
                    }

                    // Close popups.
                    if (
                        target.classList.contains('close_popup') ||
                        target.classList.contains('teamwork-modal_close')
                    ) {
                        if (target.classList.contains('stop-close')) {
                            return;
                        }
                        popup.remove();
                        return;
                    }

                    // Close skin popup.
                    if (target.classList.contains('skin_close') || target.classList.contains('close_btn')) {
                        skin.remove();
                        return;
                    }

                    // Show show student_setting popup.
                    if (target.dataset.handler === 'access_to_student') {
                        if (target.classList.contains('active')) {
                            target.classList.remove('active');
                            set_access_to_student(courseid, activityid, moduletype, target);
                        } else {
                            target.classList.add('active');
                            render_student_settings_popup(activityid, moduletype);
                            set_access_to_student(courseid, activityid, moduletype, target);
                        }
                    }

                    // Get data from popup form.
                    if (target.dataset.handler === 'get_popup_data') {
                        event.preventDefault();
                        student_settings_popup_data(courseid, activityid, moduletype);
                        popup.remove();
                        return;
                    }

                    // Open select group menu.
                    if (target.dataset.handler === 'open_group_selection') {
                        $(target)
                            .next()
                            .slideToggle();
                        return;
                    }

                    // Handle select group.
                    if (target.dataset.handler === 'select_groups') {
                        let text = document.querySelector('html[lang="en"]')
                            ? "choose grous"
                            : "בחר קבוצה";
                        if (target.classList.contains('selected')) {
                            return
                        }
                        target.classList.toggle('selected');
                        $('div[data-handler="open_group_selection"]').html(
                            target.classList.contains('selected') ? target.innerHTML : text
                        );

                        $(target)
                            .siblings()
                            .removeClass("selected");

                        let result = [];
                        let val = Array.from(target.parentNode.children);
                        val.forEach(item => {
                            if (item.classList.contains('selected')) {
                                result.push(item.dataset.value);
                            }
                        });
                        $(target)
                            .parent()
                            .slideToggle();
                        selectgroupid = JSON.stringify(result);
                        render.data.selectgroupid = JSON.stringify(result);
                        render.studentList();
                        render.teamsCard();
                        return;
                    }

                    // Add new team card.
                    if (target.dataset.handler === 'add_new_teamcard') {
                        add_new_card(
                            courseid,
                            activityid,
                            moduletype,
                            selectgroupid,
                            function () {
                                render.setDefaultData();
                                render.studentList();
                                render.teamsCard();
                            }
                        );

                        return;
                    }

                    // Delete team card.
                    if (target.dataset.handler === 'delete_teamcard') {
                        let teamid = target.dataset.remove_team_id;
                        delete_card(teamid, courseid, activityid, moduletype, function () {
                            render.setDefaultData();
                            render.teamsCard();
                            render.studentList();
                        });

                        return;
                    }

                    // Show popup to determine the random number of students on the each team.
                    if (target.dataset.handler === 'random_popup') {
                        show_random_popup();
                        return;
                    }

                    // Set random teams.
                    if (target.dataset.handler === 'random') {
                        set_random_teams(
                            target,
                            courseid,
                            activityid,
                            moduletype,
                            render.data.selectgroupid,
                            function () {
                                render.teamsCard();
                                render.studentList();
                                popup.remove();
                            }
                        );
                        return;
                    }

                    // Search reset.
                    if (target.dataset.handler === 'search_reset') {
                        searchReset(target);
                        return;
                    }

                    target = target.parentNode;
                }
            });

            // Init drug and drop events.
            drag.startDrag();

            // Change team name.
            document.addEventListener("change", function (event) {
                if (event.target.dataset.handler === 'input_team_name') {
                    set_new_team_name(event.target);
                }
            });

            // Close all popups by esc.
            document.addEventListener("keydown", function (event) {
                if (event.keyCode === 27) {
                    skin.remove();
                    popup.remove();
                }
            });
        }
    };
});
