/*
 * NOTE: the /mod/chatlv/gui_header_js/ is not a real plugin,
 * ideally this code should be in /mod/chatlv/module.js
 */

/**
 * @namespace M.mod_chatlv_header
 */
M.mod_chatlv_header = M.mod_chatlv_ajax || {};

/**
 * Init header based Chatlv UI - frame input
 *
 * @namespace M.mod_chatlv_header
 * @function
 * @param {YUI} Y
 * @param {Boolean} forcerefreshasap refresh users frame asap
 */
M.mod_chatlv_header.init_insert = function(Y, forcerefreshasap) {
    if (forcerefreshasap) {
        parent.jsupdate.location.href = parent.jsupdate.document.anchors[0].href;
    }
    parent.input.enableForm();
};

/**
 * Init header based Chatlv UI - frame input
 *
 * @namespace M.mod_chatlv_header
 * @function
 * @param {YUI} Y
 */
M.mod_chatlv_header.init_input = function(Y) {

    var inputframe = {

        waitflag : false,       // True when a submission is in progress

        /**
         * Initialises the input frame
         *
         * @function
         */
        init : function() {
            Y.one('#inputForm').on('submit', this.submit, this);
        },
        /**
         * Enables the input form
         * @this {M.mod_chatlv.js}
         */
        enable_form : function() {
            var el = Y.one('#input_chatlv_message');
            this.waitflag = false;
            el.set('className', '');
            el.focus();
        },
        /**
         * Submits the entered message
         * @param {Event} e
         */
        submit : function(e) {
            e.halt();
            if (this.waitflag) {
                return false;
            }
            this.waitflag = true;
            var inputchatlvmessage = Y.one('#input_chatlv_message');
            Y.one('#insert_chatlv_message').set('value', inputchatlvmessage.get('value'));
            inputchatlvmessage.set('value', '');
            inputchatlvmessage.addClass('wait');
            Y.one('#sendForm').submit();
            this.enable_form();
            return false;
        }

    };

    inputframe.init();
};

/**
 * Init header based Chatlv UI - frame users
 *
 * @namespace M.mod_chatlv_header
 * @function
 * @param {YUI} Y
 * @param {Array} users
 */
M.mod_chatlv_header.init_users = function(Y, users) {

    var usersframe = {

        timer : null,           // Stores the timer object
        timeout : 1,            // The seconds between updates
        users : [],             // An array of users

        /**
         * Initialises the frame with list of users
         *
         * @function
         * @this
         * @param {Array|null} users
         */
        init : function(users) {
            this.users = users;
            this.start();
            Y.one(document.body).on('unload', this.stop, this);
        },
        /**
         * Starts the update timeout
         *
         * @function
         * @this
         */
        start : function() {
            this.timer = setTimeout(function(self) {
                self.update();
            }, this.timeout * 1000, this);
        },
        /**
         * Stops the update timeout
         * @function
         * @this
         */
        stop : function() {
            clearTimeout(this.timer);
        },
        /**
         * Updates the user information
         *
         * @function
         * @this
         */
        update : function() {
            for (var i in this.users) {
                var el  = Y.one('#uidle' + this.users[i]);
                if (el) {
                    var parts = el.get('innerHTML').split(':');
                    var time = this.timeout + (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
                    var min = Math.floor(time / 60);
                    var sec = time % 60;
                    el.set('innerHTML', ((min < 10) ? "0" : "") + min + ":" + ((sec < 10) ? "0" : "") + sec);
                }
            }
            this.start();
        }
    };

    usersframe.init(users);
};
