/*
 * NOTE: the /mod/chatlv/gui_header_js/ is not a real plugin,
 * ideally this code should be in /mod/chatlv/module.js
 */

/**
 * @namespace M.mod_chatlv_ajax
 */
M.mod_chatlv_ajax = M.mod_chatlv_ajax || {};

/**
 * Init ajax based Chatlv UI.
 * @namespace M.mod_chatlv_ajax
 * @function
 * @param {YUI} Y
 * @param {Object} cfg configuration data
 */
M.mod_chatlv_ajax.init = function(Y, cfg) {

    var gui_ajax = {

        // Properties.
        api : M.cfg.wwwroot + '/mod/chatlv/chatlv_ajax.php?sesskey=' + M.cfg.sesskey,  // The path to the ajax callback script.
        cfg : {},                                       // A configuration variable.
        interval : null,                                // The interval object for refreshes.
        layout : null,                                  // A reference to the layout used in this module.
        messages : [],                                  // An array of messages.
        scrollable : true,                              // True is scrolling should occur.
        thememenu : null,                               // A reference to the menu for changing themes.

        // Elements
        messageinput : null,
        sendbutton : null,
        messagebox : null,

        init : function(cfg) {
            this.cfg = cfg;
            this.cfg.req_count = this.cfg.req_count || 0;
            participantswidth = 180;
            if (Y.one('#input-message').get('docWidth') < 640) {
                participantswidth = 120;
            }
            this.layout = new Y.YUI2.widget.Layout({
                units : [
                     {position: 'right', width: participantswidth, resize: true, gutter: '1px', scroll: true, body: 'chatlv-userlist', animate: false},
                     {position: 'bottom', height: 42, resize: false, body: 'chatlv-input-area', gutter: '1px', collapse: false, resize: false},
                     {position: 'center', body: 'chatlv-messages', gutter: '0px', scroll: true}
                ]
            });

            this.layout.on('render', function() {
                var unit = this.getUnitByPosition('right');
                if (unit) {
                    unit.on('close', function() {
                        closeLeft();
                    });
                }
            }, this.layout);
            this.layout.render();

            // Gather the general elements.
            this.messageinput = Y.one('#input-message');
            this.sendbutton = Y.one('#button-send');
            this.messagebox = Y.one('#chatlv-messages');

            // Set aria attributes to messagebox and chatlv-userlist.
            this.messagebox.set('role', 'log');
            this.messagebox.set('aria-live', 'polite');
            var userlist = Y.one('#chatlv-userlist');
            userlist.set('aria-live', 'polite');
            userlist.set('aria-relevant', 'all');

            // Attach the default events for this module.
            this.sendbutton.on('click', this.send, this);
            this.messagebox.on('mouseenter', function() {
                this.scrollable = false;
            }, this);
            this.messagebox.on('mouseleave', function() {
                this.scrollable = true;
            }, this);

            // Send the message when the enter key is pressed.
            Y.on('key', this.send, this.messageinput,  'press:13', this);

            document.title = this.cfg.chatlvroom_name;

            // Prepare and execute the first AJAX request of information.
            Y.io(this.api,{
                method : 'POST',
                data :  build_querystring({
                    action : 'init',
                    chatlv_init : 1,
                    chatlv_sid : this.cfg.sid,
                    theme : this.theme
                }),
                on : {
                    success : function(tid, outcome) {
                        this.messageinput.removeAttribute('disabled');
                        this.messageinput.set('value', '');
                        this.messageinput.focus();
                        try {
                            var data = Y.JSON.parse(outcome.responseText);
                        } catch (ex) {
                            return;
                        }
                        this.update_users(data.users);
                    }
                },
                context : this
            });

            var scope = this;
            this.interval = setInterval(function() {
                scope.update_messages();
            }, this.cfg.timer, this);

            // Create and initalise theme changing menu.
            this.thememenu = new Y.YUI2.widget.Menu('basicmenu', {xy:[0,0]});
            this.thememenu.addItems([
                //@lvs retirado a opção de tema bubble
//                {text: M.util.get_string('bubble', 'mod_chatlv'), url: this.cfg.chatlvurl + '&theme=bubble'},
                {text: M.util.get_string('compact', 'mod_chatlv'), url: this.cfg.chatlvurl + '&theme=compact'}
            ]);
            if (this.cfg.showcoursetheme == 1) {
                this.thememenu.addItem({text: M.util.get_string('coursetheme', 'mod_chatlv'), url: this.cfg.chatlvurl + '&theme=course_theme'});
            }
            this.thememenu.render(document.body);
            Y.one('#choosetheme').on('click', function(e) {
                this.moveTo((e.pageX - 20), (e.pageY - 20));
                this.show();
            }, this.thememenu);
        },

        append_message : function(key, message, row) {
            var item = Y.Node.create('<li id="mdl-chatlv-entry-' + key + '">' + message.message + '</li>');
            item.addClass((message.mymessage) ? 'mdl-chatlv-my-entry' : 'mdl-chatlv-entry');
            Y.one('#messages-list').append(item);
            if (message.type && message.type == 'beep') {
                Y.one('#chatlv-notify').setContent('<embed src="../beep.wav" autostart="true" hidden="true" name="beep" />');
            }
        },

        send : function(e, beep) {
            if((this.messageinput.get('value') != '') || (typeof beep != 'undefined')) {
                this.sendbutton.set('value', M.util.get_string('sending', 'chatlv'));
                var data = {
                    chatlv_message : (!beep) ? this.messageinput.get('value') : '',
                    chatlv_sid : this.cfg.sid,
                    theme : this.cfg.theme
                };
                if (beep) {
                    data.beep = beep
                }
                data.action = 'chatlv';

                Y.io(this.api, {
                    method : 'POST',
                    data : build_querystring(data),
                    on : {
                        success : this.send_callback
                    },
                    context : this
                });
            }
        },

        send_callback : function(tid, outcome, args) {
            try {
                var data = Y.JSON.parse(outcome.responseText);
            } catch (ex) {
                return;
            }
            this.sendbutton.set('value', M.util.get_string('send', 'chatlv'));
            this.messageinput.set('value', '');
            clearInterval(this.interval);
            this.update_messages();
            var scope = this;
            this.interval = setInterval(function() {
                scope.update_messages();
            }, this.cfg.timer, this);
        },

        talkto: function (e, name) {
            this.messageinput.set('value', "To " + name + ": ");
            this.messageinput.focus();
        },

        update_messages : function() {
            this.cfg.req_count++;
            Y.io(this.api, {
                method : 'POST',
                data : build_querystring({
                    action: 'update',
                    chatlv_lastrow : this.cfg.chatlv_lastrow || false,
                    chatlv_lasttime : this.cfg.chatlv_lasttime,
                    chatlv_sid : this.cfg.sid,
                    theme : this.cfg.theme
                }),
                on : {
                    success : this.update_messages_callback
                },
                context : this
            });
        },

        update_messages_callback : function(tid, outcome) {
            try {
                var data = Y.JSON.parse(outcome.responseText);
            } catch (ex) {
                return;
            }
            if (data.error) {
                clearInterval(this.interval);
                alert(data.error);
                window.location = this.cfg.home;
            }
            this.cfg.chatlv_lasttime = data.lasttime;
            this.cfg.chatlv_lastrow  = data.lastrow;
            // Update messages.
            for (var key in data.msgs){
                if (!M.util.in_array(key, this.messages)) {
                    this.messages.push(key);
                    this.append_message(key, data.msgs[key], data.lastrow);
                }
            }
            // Update users.
            this.update_users(data.users);
            // Scroll to the bottom of the message list
            if (this.scrollable) {
                Y.Node.getDOMNode(this.messagebox).parentNode.scrollTop += 500;
            }
            this.messageinput.focus();
        },

        update_users : function(users) {
            if (!users) {
                return;
            }
            var list = Y.one('#users-list');
            list.get('children').remove();
            for (var i in users) {
                var li = Y.Node.create('<li><table><tr><td>' + users[i].picture + '</td><td></td></tr></table></li>');
                if (users[i].id == this.cfg.userid) {
                    li.all('td').item(1).append(Y.Node.create('<strong><a target="_blank" href="' + users[i].url + '">' + users[i].name + '</a></strong>'));
                } else {
                    li.all('td').item(1).append(Y.Node.create('<div><a target="_blank" href="' + users[i].url + '">' + users[i].name + '</a></div>'));
                    var talk = Y.Node.create('<a href="###">' + M.util.get_string('talk', 'chatlv') + '</a>');
                    talk.on('click', this.talkto, this, users[i].name);
                    var beep = Y.Node.create('<a href="###">' + M.util.get_string('beep', 'chatlv') + '</a>');
                    beep.on('click', this.send, this, users[i].id);
                    li.all('td').item(1).append(Y.Node.create('<div></div>').append(talk).append('&nbsp;').append(beep));
                }
                list.append(li);
            }
        }

    };

    gui_ajax.init(cfg);
};
