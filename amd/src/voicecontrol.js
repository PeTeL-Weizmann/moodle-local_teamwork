define([
    'jquery',
    'core/ajax',
], function (
    $,
    Ajax,
) {
    'use strict';

    var timeout = 4;
    var timer = false;
    var timers = [];
    var clear_all_timers = () => {
        timers.forEach((i, t) => {
            clearInterval(i);
        });
        timers = [];
        timer = false;
    }
    const start_timer = (duration, callback) => {
        if (timer) {
            clear_all_timers()
        }
        var countDownDate = new Date().getTime() + (duration * 1000);
        var x = setInterval(function () {
            var now = new Date().getTime();
            var distance = countDownDate - now;
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            if (distance < 0) {
                clear_all_timers();
                clearInterval(x);
                callback();
            }
        }, 1000);
        if (!timers.includes(x)) {
            timers.push(x);
            timer = true;
        }
    }
    var current_langcode = '';
    var current_short_langcode = '';
    var set_lang = (lang = 'en-US') => {
        current_langcode = lang;
        current_short_langcode = lang.split('-')[0];
    }

    // TODO: Get tokens.
    var tokens = [];
    var schemes = [];
    var commands = [];
    var default_commands = [
        {
            public: true,
            scheme: {
                // en: 'sing a song',
                // iw: 'תשיר',
                // ru: 'спой песенку',
            },
            command_id: 'sing_a_song',
            command_exec: () => {
                sound('sample');
            },
            params: {},
        },
        // {
        //     public: false,
        //     scheme: {
        //         // en: '11 | eleven',
        //         // iw: '11',
        //         // ru: '11 | одинадцать',
        //     },
        //     command_id: '11',
        //     command_exec: () => {
        //         sound('please_repeat');
        //     },
        //     params: {},
        // },
        {
            public: true,
            command_id: 'add_new_teamcard',
            scheme: {
                // en: 'add a team | add new team',
                // iw: 'תוסיף צוות | תוסיף צוות חדש',
                // ru: 'добавь новую команду',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'local_teamwork/init',
                ], function (
                    render,
                    l_tw_init,
                ) {
                    let courseid = params.courseid;
                    let activityid = params.activityid;
                    let moduletype = params.moduletype;
                    let selectgroupid = params.selectgroupid;
                    l_tw_init.add_new_card(
                        courseid,
                        activityid,
                        moduletype,
                        selectgroupid,
                        null,
                        function () {
                            render.setDefaultData();
                            render.studentList();
                            render.teamsCard();
                        }
                    );
                });
            }
        },
        {
            public: true,
            command_id: 'add_new_named_teamcard',
            scheme: {
                // en: 'please create a team {_teamname} | create a team {_teamname}',
                // iw: 'נא ליצור צוות {_teamname} | אנא ליצור צוות {_teamname}',
                // ru: 'добав команду {_teamname}',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'local_teamwork/init',
                ], function (
                    render,
                    l_tw_init,
                ) {
                    let courseid = params.courseid;
                    let activityid = params.activityid;
                    let moduletype = params.moduletype;
                    let selectgroupid = params.selectgroupid;
                    let teamname = params._teamname;
                    l_tw_init.add_new_card(
                        courseid,
                        activityid,
                        moduletype,
                        selectgroupid,
                        teamname,
                        function () {
                            render.setDefaultData();
                            render.studentList();
                            render.teamsCard();
                        }
                    );
                });
            }
        },
        {
            public: true,
            command_id: 'create_numbers_teamcard', // create {number} teams
            scheme: {
                // en: 'create {_number} teams',
                // iw: 'יצירת {_number} צוותים',
                // ru: 'добавь {_number} команд',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'local_teamwork/init',
                ], function (
                    render,
                    l_tw_init,
                ) {
                    var number = text2num(params._number);
                    let courseid = params.courseid;
                    let activityid = params.activityid;
                    let moduletype = params.moduletype;
                    let selectgroupid = params.selectgroupid;
                    for (let index = 0; index < number; index++) {
                        l_tw_init.add_new_card(
                            courseid,
                            activityid,
                            moduletype,
                            selectgroupid,
                            null,
                            function () {
                                render.setDefaultData();
                                render.studentList();
                                render.teamsCard();
                            }
                        );
                    }
                });
            }
        },
        {
            public: true,
            command_id: 'drag_student_card',
            scheme: {
                // en: 'move {userfullname} to {teamname} card | add {userfullname} to {teamname} card',
                // iw: 'תעביר {userfullname} ל{teamname} | תעביר {userfullname} לצוות {teamname} | נא להעביר {userfullname} לצוות {teamname} | נא להעביר {userfullname} ל {teamname} | נא להעביר {userfullname} ל{teamname}',
                // ru: 'перемести {userfullname} в карточку {teamname}',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'core/ajax',
                    'local_teamwork/loading',
                ], function (
                    render,
                    Ajax,
                    loadingIcon,
                ) {
                    var draguserid = params.userfullname; // Yea it's ID, not name.
                    var stud_card = $("div.teamwork_student[data-student_id=" + draguserid + "]");
                    var groupid = params.teamname; // Yea it's ID, not name.
                    var group_card = $("div.teamwork_team[data-team_id=" + groupid + "]").find('.teamwork_team-inner.draggable');
                    group_card.append(stud_card);
                    const allTeamsBlocks = Array.from(document.querySelectorAll('div[data-team_id]'));
                    const allTeams = [];
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
                    let courseid = params.courseid;
                    let activityid = params.activityid;
                    let moduletype = params.moduletype;
                    let selectgroupid = params.selectgroupid;
                    let newteamspost = JSON.stringify(allTeams);
                    draguserid = Number(draguserid);
                    loadingIcon.show();
                    Ajax.call([{
                        methodname: 'local_teamwork_drag_student_card',
                        args: {
                            courseid: courseid,
                            activityid: activityid,
                            moduletype: moduletype,
                            selectgroupid: selectgroupid,
                            newteamspost: newteamspost,
                            draguserid: draguserid,
                            removeteam: false,
                        },
                        done: function (data) {
                            loadingIcon.remove();
                            render.setDefaultData();
                            render.studentList();
                            render.teamsCard();
                        },
                        fail: function () {
                            loadingIcon.remove();
                            popup.error();
                        }
                    }]);
                });
            }
        },
        {
            public: true,
            command_id: 'delete_teamcard',
            scheme: {
                // en: 'delete {teamname}',
                // iw: 'מחק צוות {teamname} |  תמחק צוות {teamname}',
                // ru: 'удали команду {teamname}',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'core/ajax',
                    'local_teamwork/loading',
                    'local_teamwork/init',
                ], function (
                    render,
                    Ajax,
                    loadingIcon,
                    l_tw_init,
                ) {
                    var teamid = params.teamname; // Yea it's ID, not name.
                    let courseid = params.courseid;
                    let activityid = params.activityid;
                    let moduletype = params.moduletype;
                    l_tw_init.delete_card(teamid, courseid, activityid, moduletype, function () {
                        render.setDefaultData();
                        render.teamsCard();
                        render.studentList();
                    });
                });
            }
        },
        {
            public: true,
            command_id: 'read_users',
            scheme: {
                // en: 'read user lists | read users',
                // iw: 'רשימת צוותים | תקראי רשימת צוותים',
                // ru: 'прочитай пользователей',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'core/ajax',
                    'local_teamwork/loading',
                ], function (
                    render,
                    Ajax,
                    loadingIcon,
                ) {
                    var user_list = '';
                    params.students.forEach(element => {
                        user_list += element.value + ', ';
                    });
                    say(user_list);
                });
            }
        },
        {
            public: true,
            command_id: 'read_teams',
            scheme: {
                // en: 'read teams',
                // iw: 'תקראי רשימת צוותים ', // TODO:
                // ru: 'прочитай команды',
            },
            params: {
            },
            command_exec: function (params) {
                require([
                    'local_teamwork/render',
                    'core/ajax',
                    'local_teamwork/loading',
                ], function (
                    render,
                    Ajax,
                    loadingIcon,
                ) {
                    var group_list = '';
                    params.groups.forEach(element => {
                        group_list += element.value + ', ';
                    });
                    say(group_list);
                });
            }
        },
    ];

    // TODO: commands w/ confirmation?
    let get_commands = () => {

        default_commands.forEach((element, index) => {

            var scheme_id = element.command_id;
            default_commands[index].scheme = schemes[scheme_id];
        });

        commands = default_commands;
    }

    // get_commands();
    var state = 'disabled'; // enabled, token, command...
    //////////////////////////////////////////////////////////////////////////////////////
    let similarity = (s1, s2) => {
        var longer = s1;
        var shorter = s2;
        if (s1.length < s2.length) {
            longer = s2;
            shorter = s1;
        }
        var longerLength = longer.length;
        if (longerLength === 0) {
            return 1.0;
        }
        return (longerLength - editDistance(longer, shorter)) / parseFloat(longerLength);
    }
    let editDistance = (s1, s2) => {
        s1 = s1.toLowerCase();
        s2 = s2.toLowerCase();
        var costs = new Array();
        for (var i = 0; i <= s1.length; i++) {
            var lastValue = i;
            for (var j = 0; j <= s2.length; j++) {
                if (i === 0)
                    costs[j] = j;
                else {
                    if (j > 0) {
                        var newValue = costs[j - 1];
                        if (s1.charAt(i - 1) !== s2.charAt(j - 1))
                            newValue = Math.min(Math.min(newValue, lastValue),
                                costs[j]) + 1;
                        costs[j - 1] = lastValue;
                        lastValue = newValue;
                    }
                }
            }
            if (i > 0)
                costs[s2.length] = lastValue;
        }
        return costs[s2.length];
    }

    const stringSimilarity = (a, b) =>
        _stringSimilarity(prep(a), prep(b))

    const _stringSimilarity = (a, b) => {
        const bg1 = bigrams(a)
        const bg2 = bigrams(b)
        const c1 = count(bg1)
        const c2 = count(bg2)
        const combined = uniq([...bg1, ...bg2])
            .reduce((t, k) => t + (Math.min(c1[k] || 0, c2[k] || 0)), 0)
        return 2 * combined / (bg1.length + bg2.length)
    }

    const prep = (str) => {  // TODO: unicode support?

        return current_short_langcode !== 'iw' ? str.toLowerCase().replace(/[^\w\s]/g, ' ').replace(/\s+/g, ' ') : str.toLowerCase().replace('^[a-z\u0590-\u05fe]+$', ' ');
    }

    // str.toLowerCase().replace(/[^\w\s]/g, ' ').replace(/\s+/g, ' ')
    // str.toLowerCase().replace('^[a-z\u0590-\u05fe]+$', ' ')

    const bigrams = (str) =>
        [...str].slice(0, -1).map((c, i) => c + str[i + 1])

    const count = (xs) =>
        xs.reduce((a, x) => ((a[x] = (a[x] || 0) + 1), a), {})

    const uniq = (xs) =>
        [... new Set(xs)]
    //////////////////////////////////////////////////////////////////////

    let find_match = (input, stack, threshold = 0.35) => {
        var result = null;
        var list = [];
        input = input.toLowerCase();
        stack.forEach((element, index) => {
            var el_vars = typeof (element) != 'string' ? element : [element];            // var el_vars = element;
            var el_weight = 0;
            var sim = 0;
            var scheme_option = 0;
            el_vars.forEach((el, i) => {
                // sim = similarity(input, el.toLowerCase().trim());
                sim = stringSimilarity(input, el.toLowerCase().trim()); // Another variant. HE fails!
                if (sim > el_weight) {
                    el_weight = sim;
                    scheme_option = i;
                }
            });
            list.push([index, el_weight, scheme_option]);
        });

        list.sort((a, b) => b[1] - a[1]);

        result = list[0];
        if (result[1] < threshold) {
            return null;
        }

        return result;
    }
    const extract = (template, str) => {

        const templateParts = template.split(/{|}/);
        const extracted = {};
        for (let index = 1; index < templateParts.length; index += 2) {
            const
                possibleKey = templateParts[index],
                keyPrefix = templateParts[index - 1],
                nextPrefix = templateParts[index + 1];
            const substringStartIndex = str.indexOf(keyPrefix) + keyPrefix.length;
            const substringEndIndex = nextPrefix ? str.indexOf(nextPrefix) : str.length;
            extracted[possibleKey] = str.substring(substringStartIndex, substringEndIndex);
        }
        return extracted;
    }

    var match_token = (input) => {
        // let match = find_match(input, tokens);
        var match = false;

        // tokens.forEach(token => {
        //     if (input.includes(token)) {
        //         match = true;
        //     }
        // });


        tokens.some(function (el) {
            if (input.includes(el)) {
                match = true;
                return;
            }
        });

        if (match) {

            // debugger;
            speechRecognition.abort();

            setTimeout(() => {
                speechRecognition.start();
                // set_state('enabled');
            }, 500)

            sound('ready');
            set_state('command');
            update_status(input, 'success');
            return true;
        }
        update_status(input, 'danger');
        set_state('enabled');
        return false;
    }

    var match_command = (input) => {

        var found = false;
        var commands_schemes = commands.map((i, el) => {
            return i.scheme;
        });

        var index = find_match(input, commands_schemes)
        if (index !== null) {
            found = commands[index[0]];

            var found_scheme = found.scheme[index[2]].trim();

            if (found.params.placeholders) {
                var matched_params = {};
                var input_values = extract(found_scheme, input);
                let plhdrs = found.params.placeholders;
                let arr_plhdrs = Object.entries(plhdrs);

                arr_plhdrs.forEach((element, key) => {
                    var el_name = element[0];
                    var el_options = element[1];

                    if (!el_name.startsWith('_')) {

                        var list = el_options.map((i, el) => {
                            return i.value;
                        });

                        var match_index = find_match(input_values[el_name], list);
                        if (match_index !== null) {
                            matched_params[el_name] = el_options[match_index[0]].id;
                        }
                    } else {
                        matched_params[el_name] = input_values[el_name];
                    }
                });

                if (arr_plhdrs.length !== Object.entries(matched_params).length) {
                    set_state('enabled');
                    sound('timeout');
                    return false;
                }
                found.params = {
                    ...found.params,
                    ...matched_params
                };
            }
            found.command_exec(found.params);
            sound('correct');
            set_state('enabled');
            update_status(input, 'success');
            return true;
        }
        say(strings[current_short_langcode]['unknown_command'] + ': "' + input + '"');
        set_state('enabled');
        update_status(input, 'danger');
        sound('timeout');
        return false;
    }

    var sound = (type) => {
        const audio = new Audio(eval(type + '_sound_path'));
        audio.play();
    }

    var cancel = false;

    var say = (text) => {
        if (speechRecognition) {
            speechRecognition.stop();
        }

        // SAY
        var msg = new SpeechSynthesisUtterance();
        // var voices = window.speechSynthesis.getVoices();
        // msg.voice = voices[0]; // Note: some voices don't support altering params
        // msg.voiceURI = 'native';
        // msg.volume = 1; // 0 to 1
        // msg.rate = 1; // 0.1 to 10
        // msg.pitch = 0; //0 to 2
        // msg.lang = 'en-US';
        // msg.lang = current_langcode;
        var lang_for_speech = '';

        if (current_short_langcode === 'iw') {
            lang_for_speech = 'he';
        } else {
            // TODO: HACK speech HE in EN
            function contains_heb(str) {
                return (/[\u0590-\u05FF]/).test(str);
            }
            var includes_he = contains_heb(text);
            if (includes_he) {
                lang_for_speech = 'he';
            } else {
                lang_for_speech = current_short_langcode;
            }
        }
        msg.lang = lang_for_speech;
        msg.onend = function (e) {
            if (!cancel) {
                speechRecognition.start();
                document.querySelector("#vc-start").style.display = "none";
                document.querySelector("#vc-stop").style.display = "block";
            } else {

                document.querySelector("#vc-start").style.display = "block";
                document.querySelector("#vc-stop").style.display = "none";
                cancel = false;
            }
        };

        msg.onstart = function (e) {
            document.querySelector("#vc-start").style.display = "none";
            document.querySelector("#vc-stop").style.display = "block";
        };

        msg.text = text;
        speechSynthesis.speak(msg);

        return;
    }
    var last_said = []
    var said_count = 0;
    let update_status = (info, type = 'info') => {
        last_said.push({
            id: said_count,
            type: type,
            info: info,
        });
        said_count++;
        render_status();
    }
    let render_status = (length = 10) => {
        var html = $('<div>');
        last_said.sort(function (a, b) {
            if (a.id < b.id) return 1;
            if (a.id > b.id) return -1;
            return 0;
        });
        var count = 0;
        for (const element of last_said) {
            var txt = $("<div></div>").html(element.info).addClass('mb-0 p-1 h4 alert alert-' + element.type);
            html.append(txt);
            count++;
            if (count >= length) {
                break;
            }
        }

        let last = last_said[0];
        $("#vc-status").html($("<div></div>").html(last.info).addClass('mb-0 p-1 h4 alert alert-' + last.type));
        $("#vc-status").attr('data-original-title', html.html())
    }
    var available_commands = []
    let update_commands = () => {
        available_commands = [];
        commands.forEach(element => {
            if (element.public) {
                available_commands.push(element);
            }
        });
        render_commands();
    }

    let help_string = {
        en: 'Please say "OK"',
        ru: 'Пожалуйста скажите "ОК"',
        iw: 'נא להגידת "אוקיי פטל"',
    };

    let list_of_orders_title = {
        en: 'List of orders',
        ru: 'Список комманд',
        iw: 'פקודות קוליות',
    };

    let strings = {
        en: {
            'unknown_command': 'Unknown command',
        },
        ru: {
            'unknown_command': 'Неизвестная комманда',
        },
        iw: {
            'unknown_command': 'פקודה לא מזוהה',
        },
    }; let render_commands = () => {
        var html = $('<div>');

        var txt = $("<div></div>").html(help_string[current_short_langcode]).addClass('h2');
        html.append(txt);

        var txt = $("<div></div>").html(list_of_orders_title[current_short_langcode]).addClass('h3');
        html.append(txt);

        available_commands.forEach(element => {

            var txt = $("<div></div>").html(element.scheme.join('<br>')).addClass('alert alert-info');
            html.append(txt);
        });
        $("#vc-commands-help").attr('data-content', html.html())
    }
    var set_state = (input_state) => {
        switch (input_state) {
            case 'enabled':
                clear_all_timers();
                break;
            case 'disabled':
                clear_all_timers();
                break;
            case 'command':
                start_timer(timeout, () => {
                    set_state('enabled');
                    sound('timeout');
                })
                break;
            default:
                break;
        }
        state = input_state;
    }

    var grammararray = [];
    let collect_grammar = () => {
        grammararray = grammararray.concat(tokens.join(' '));

        commands.forEach(element => {
            grammararray = grammararray.concat(element.scheme.join(' ')); // TODO: from array
        });
        grammararray = [...new Set(grammararray)];
    }

    set_state('enabled');


    var start_button = document.querySelector("#vc-start");
    var stop_button = document.querySelector("#vc-stop");
    var speechRecognition;
    function init_vtt() {
        if ("webkitSpeechRecognition" in window) {

            // set_state('enabled');

            speechRecognition = new webkitSpeechRecognition();
            var SpeechGrammarList = SpeechGrammarList || window.webkitSpeechGrammarList
            if (SpeechGrammarList) {
                var speechRecognitionList = new SpeechGrammarList();
                var grammar = '#JSGF V1.0; grammar commands; public <command> = ' + grammararray.join(' | ') + ' ;'
                speechRecognitionList.addFromString(grammar, 1);
                speechRecognition.grammars = speechRecognitionList;
            }
            speechRecognition.maxAlternatives = 1;
            speechRecognition.continuous = true;
            speechRecognition.interimResults = true;
            speechRecognition.lang = current_langcode;
            let final_transcript = "";
            speechRecognition.onstart = () => {
                // get_commands();
                document.querySelector("#vc-start").style.display = "none";
                document.querySelector("#vc-stop").style.display = "block";
                // set_state('enabled');
            };
            speechRecognition.onerror = (e) => {
                // document.querySelector("#vc-start").style.display = "block";
                // document.querySelector("#vc-stop").style.display = "none";

                speechRecognition.start();


            };
            speechRecognition.onend = () => {
                if (start_button) {
                    start_button.style.display = "block";
                }
                if (stop_button) {
                    stop_button.style.display = "none";
                }

                document.querySelector("#vc-start").style.display = "block";
                document.querySelector("#vc-stop").style.display = "none";

                if (!cancel) {
                    speechRecognition.start();
                }


                // set_state('disabled');
            };
            speechRecognition.onresult = (event) => {
                let interim_transcript = "";

                for (let i = event.resultIndex; i < event.results.length; ++i) {

                    if (event.results[i].isFinal) {
                        final_transcript = event.results[i][0].transcript.trim();
                        if (state === 'enabled') {
                            // match_token(final_transcript);
                        } else if (state === 'command') {
                            match_command(final_transcript);
                        }
                    } else {
                        interim_transcript = event.results[i][0].transcript.trim();

                        // var words = interim_transcript.split(); // TODO:

                        if (state === 'enabled') {
                            match_token(interim_transcript);
                        } else if (state === 'command') {
                            // match_command(interim_transcript);
                        }


                        if (state === 'command') {
                            start_timer(timeout, () => {
                                set_state('enabled');
                                sound('timeout');
                            })
                        }
                    }
                }

                // for (let i = event.resultIndex; i < event.results.length; ++i) {
                //     if (event.results[i].isFinal) {
                //         final_transcript = event.results[i][0].transcript.trim();
                //         if (state == 'enabled') {
                //             // match_token(final_transcript);
                //         } else if (state == 'command') {
                //             // match_command(final_transcript);
                //         }
                //     } else {
                //         // interim_transcript += event.results[i][0].transcript;
                //         interim_transcript = event.results[i][0].transcript;

                //         if (state == 'enabled') {
                //             match_token(interim_transcript);
                //         } else if (state == 'command') {
                //             match_command(interim_transcript);
                //         }


                //         if (state == 'command') {
                //             start_timer(timeout, () => {
                //                 set_state('enabled');
                //                 sound('timeout');
                //             })
                //         }
                //     }
                // }




            };
            listeners();
        } else { }
    }
    let listeners = () => {
        $(document).on("click", "#vc-start", function () {
            speechRecognition.start(); // TODO:
        });
        $(document).on("click", "#vc-stop", function () {
            cancel = true;
            if (speechRecognition) {
                speechRecognition.stop();
                speechSynthesis.cancel();
            }
        });
    }
    var ready_sound_path = '';
    var correct_sound_path = '';
    var timeout_sound_path = '';
    var please_repeat_sound_path = '';
    var sample_sound_path = '';

    // WORD 2 INT
    var Small = {
        // EN
        'zero': 0,
        'one': 1,
        'two': 2,
        'three': 3,
        'four': 4,
        'five': 5,
        'six': 6,
        'seven': 7,
        'eight': 8,
        'nine': 9,
        'ten': 10,

        // HE
        'אחד': 1,
        'אחת': 1,
        'שני': 2,
        'שתיים': 2,
        'שלושת': 3,
        'שלוש': 3,
        'ארבעת': 4,
        'ארבע': 4,
        'חמשת': 5,
        'חמש': 5,
        'ששת': 6,
        'שש': 6,
        'שבעת': 7,
        'שבע': 7,
        'שמונת': 8,
        'שְמוֹנֶה': 8,
        'תשעת': 9,
        'תשע': 9,
        'עשרת': 10,
        'עשר': 10,

        // 'eleven': 11,
        // 'twelve': 12,
        // 'thirteen': 13,
        // 'fourteen': 14,
        // 'fifteen': 15,
        // 'sixteen': 16,
        // 'seventeen': 17,
        // 'eighteen': 18,
        // 'nineteen': 19,
        // 'twenty': 20,
        // 'thirty': 30,
        // 'forty': 40,
        // 'fifty': 50,
        // 'sixty': 60,
        // 'seventy': 70,
        // 'eighty': 80,
        // 'ninety': 90
    };
    var Magnitude = {
        'thousand': 1000,
        'million': 1000000,
        'billion': 1000000000,
        'trillion': 1000000000000,
        'quadrillion': 1000000000000000,
        'quintillion': 1000000000000000000,
        'sextillion': 1000000000000000000000,
        'septillion': 1000000000000000000000000,
        'octillion': 1000000000000000000000000000,
        'nonillion': 1000000000000000000000000000000,
        'decillion': 1000000000000000000000000000000000,
    };

    var a, n, g;
    function text2num(s) {
        a = s.toString().split(/[\s-]+/);
        n = 0;
        g = 0;
        a.forEach(feach);
        return n + g;
    }
    function feach(w) {
        var x = Small[w];
        if (x != null) {
            g = g + x;
        }
        else if (w === "hundred") {
            g = g * 100;
        }
        else {
            x = Magnitude[w];
            if (x != null) {
                n = n + g * x
                g = 0;
            }
            else {
                if (w != null) {
                    g = w;
                }
            }
        }
    }
    ////////////////////////////

    return {
        reinit: function () {
            reinit();
        },
        init: function (currentlangcode, paths, inputtokens, inputschemes) {
            ready_sound_path = paths[0];
            correct_sound_path = paths[1];
            timeout_sound_path = paths[2];
            please_repeat_sound_path = paths[3];
            sample_sound_path = paths[4];
            tokens = inputtokens;
            schemes = inputschemes;
            set_lang(currentlangcode);
            init_vtt();
        },
        stop: function () {
            if (speechRecognition) {
                speechRecognition.stop();
            }
        },
        init_listeners: function () {
            listeners();
        },
        update_commands: function () {
            get_commands();
            update_commands();
            // collect_grammar();
        },
        reload: function (
            courseid, activityid, moduletype, selectgroupid,
        ) {
            $("body").on('DOMSubtreeModified', "#teamwork", function () {
                var groups = [];
                var groups_elements = $("input[data-handler='input_team_name']");
                Array.from(groups_elements).forEach(element => {
                    groups.push({
                        id: element.dataset.team_id,
                        value: element.value,
                    });
                });

                var all_students = [];
                var students_elements = $("div.teamwork_student");
                Array.from(students_elements).forEach(element => {
                    all_students.push({
                        id: element.dataset.student_id,
                        value: element.innerText,
                    });
                });

                var avail_students = [];
                var students_elements = $("#studentList div.teamwork_student");
                Array.from(students_elements).forEach(element => {
                    avail_students.push({
                        id: element.dataset.student_id,
                        value: element.innerText,
                    });
                });

                var index = default_commands.map(e => e.command_id).indexOf('add_new_teamcard');
                default_commands[index].params = {
                    courseid: courseid,
                    activityid: activityid,
                    moduletype: moduletype,
                    selectgroupid: selectgroupid,
                }

                var index = default_commands.map(e => e.command_id).indexOf('add_new_named_teamcard');
                default_commands[index].params = {
                    courseid: courseid,
                    activityid: activityid,
                    moduletype: moduletype,
                    selectgroupid: selectgroupid,
                    placeholders: {
                        _teamname: null,
                    }
                }

                var index = default_commands.map(e => e.command_id).indexOf('create_numbers_teamcard');
                default_commands[index].params = {
                    courseid: courseid,
                    activityid: activityid,
                    moduletype: moduletype,
                    selectgroupid: selectgroupid,
                    placeholders: {
                        _number: null,
                    }
                }

                var index = default_commands.map(e => e.command_id).indexOf('drag_student_card');
                default_commands[index].params = {
                    courseid: courseid,
                    activityid: activityid,
                    moduletype: moduletype,
                    selectgroupid: selectgroupid,
                    placeholders: {
                        userfullname: all_students,
                        teamname: groups,
                    }
                }

                var index = default_commands.map(e => e.command_id).indexOf('delete_teamcard');
                default_commands[index].params = {
                    courseid: courseid,
                    activityid: activityid,
                    moduletype: moduletype,
                    selectgroupid: selectgroupid,
                    placeholders: {
                        // userfullname: students,
                        teamname: groups,
                    }
                }

                var index = default_commands.map(e => e.command_id).indexOf('read_users');
                default_commands[index].params = {
                    students: avail_students,
                }

                var index = default_commands.map(e => e.command_id).indexOf('read_teams');
                default_commands[index].params = {
                    groups: groups,
                }

            });
        },
    };
});