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
 * Javascript helper function for wikilv
 *
 * @package   mod-wikilv
 * @copyright 2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.mod_wikilv = {};

M.mod_wikilv.init = function(Y, args) {
    var WikilvHelper = function(args) {
        WikilvHelper.superclass.constructor.apply(this, arguments);
    }
    WikilvHelper.NAME = "WIKILV";
    WikilvHelper.ATTRS = {
        options: {},
        lang: {}
    };
    Y.extend(WikilvHelper, Y.Base, {
        initializer: function(args) {
        }
    });
    new WikilvHelper(args);
};
M.mod_wikilv.renew_lock = function() {
    var args = {
        sesskey: M.cfg.sesskey,
        pageid: wikilv.pageid
    };
    if (wikilv.section) {
        args.section = wikilv.section;
    }
    YUI().use('io', function(Y) {
        function renewLock() {
            Y.io('lock.php?' + build_querystring(args), {
                method: 'POST'
            });
        }
        setInterval(renewLock, wikilv.renew_lock_timeout * 1000);
    });
};
M.mod_wikilv.history = function(Y, args) {
    var compare = false;
    var comparewith = false;
    var radio  = document.getElementsByName('compare');
    var radio2 = document.getElementsByName('comparewith');
    for(var i=0; i<radio.length;i++){
          if(radio[i].checked){
            compare = true;
        }
        if(!comparewith){
            radio[i].disabled=true;
            radio2[i].disabled=false;
        } else if(!compare && comparewith){
            radio[i].disabled=false;
            radio2[i].disabled=false;
        } else {
            radio[i].disabled=false;
            radio2[i].disabled=true;
        }

        if(radio2[i].checked){
            comparewith = true;
        }
    }
}

M.mod_wikilv.deleteversion = function(Y, args) {
    var fromversion = false;
    var toversion = false;
    var radio  = document.getElementsByName('fromversion');
    var radio2 = document.getElementsByName('toversion');
    var length = radio.length;
    //version to should be more then version from
    for (var i = 0; i < radio.length; i++) {
        //if from-version is selected then disable all to-version options after that.
        if (fromversion) {
            radio2[i].disabled = true;
        } else {
            radio2[i].disabled = false;
        }
        //check when to-version option is selected
        if (radio2[i].checked) {
            toversion = true;
        }
        //make sure to-version should be >= from-version
        if (radio[i].checked) {
            fromversion = true;
            if (!toversion) {
                radio2[i].checked = true;
            }
        }
    }
    //avoid selecting first and last version
    if (radio[0].checked && radio2[length-1].checked) {
        radio2[length - 2].checked = true;
    } else if(radio[length - 1].checked && radio2[0].checked) {
        radio2[1].checked = true;
        radio2[0].disabled = true;
        toversion = true;
    }
}

M.mod_wikilv.init_tree = function(Y, expand_all, htmlid) {
    Y.use('yui2-treeview', function(Y) {
        var tree = new Y.YUI2.widget.TreeView(htmlid);

        tree.subscribe("clickEvent", function(node, event) {
            // we want normal clicking which redirects to url
            return false;
        });

        if (expand_all) {
            tree.expandAll();
        }

        tree.render();
    });
};
