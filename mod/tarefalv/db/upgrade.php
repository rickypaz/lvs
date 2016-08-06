<?php

// This file keeps track of upgrades to
// the tarefalv module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

function xmldb_tarefalv_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();


    // Moodle v2.2.0 release upgrade line
    // Put any upgrade step following this

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this


    if ($oldversion < 2012061701) {
        // Fixed/updated numfiles field in tarefalv_submissions table to count the actual
        // number of files has been uploaded when sendformarking is disabled
        upgrade_set_timeout(600);  // increase excution time for in large sites
        $fs = get_file_storage();

        // Fetch the moduleid for use in the course_modules table
        $moduleid = $DB->get_field('modules', 'id', array('name' => 'tarefalv'), MUST_EXIST);

        $selectcount = 'SELECT COUNT(s.id) ';
        $select      = 'SELECT s.id, cm.id AS cmid ';
        $query       = 'FROM {tarefalv_submissions} s
                        JOIN {tarefalv} a ON a.id = s.tarefalv
                        JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = :moduleid
                        WHERE tarefalvtype = :tarefalvtype';

        $params = array('moduleid' => $moduleid, 'tarefalvtype' => 'upload');

        $countsubmissions = $DB->count_records_sql($selectcount.$query, $params);
        $submissions = $DB->get_recordset_sql($select.$query, $params);

        $pbar = new progress_bar('tarefalvupgradenumfiles', 500, true);
        $i = 0;
        foreach ($submissions as $sub) {
            $i++;
            if ($context = context_module::instance($sub->cmid)) {
                $sub->numfiles = count($fs->get_area_files($context->id, 'mod_tarefalv', 'submission', $sub->id, 'sortorder', false));
                $DB->update_record('tarefalv_submissions', $sub);
            }
            $pbar->update($i, $countsubmissions, "Counting files of submissions ($i/$countsubmissions)");
        }
        $submissions->close();

        // tarefalv savepoint reached
        upgrade_mod_savepoint(true, 2012061701, 'tarefalv');
    }

    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this


    return true;
}


