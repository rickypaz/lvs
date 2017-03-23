Official Chatlv Module for Moodle
------------------------------

The chatlv module now supports a backend daemon for
more efficiency.

It's still buggy and being worked on, but if you
want to test it and help out here are some quick
instructions:

1) Admin -> Config -> Modules -> Chatlv -> Settings

2) Set the method to "sockets" and set up the ports etc

3) Start the server like this (from the Unix command line):

   cd moodle/mod/chatlv
   php chatlvd.php --start &

4) Go to a chatlv room in Moodle and open it as normal.

------

KNOWN PROBLEMS

 - User list is not always working
 - Some browsers (eg Safari) cause lines to be repeated
   by 10 - 20 times
 - Occasionally "Document was empty" messages

Help solving these very welcome!


Martin, 31 July 2004
