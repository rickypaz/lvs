<?php
include('../../../config.php');

$course_id = required_param('curso', PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
} else if (!$USER->id) {
	print_error('Invalid user');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$nota_individual_str = 'Notas LV';// FIXME usar get_string('nota_individual', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/lista_alunos.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $nota_individual_str));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $nota_individual_str));

$PAGE->navbar->add($nota_individual_str);

$query_users = "SELECT * FROM {role_assignments} r, {user} u WHERE r.contextid = ? AND u.id = r.userid AND r.roleid = 5 ORDER BY u.firstname ";
$users = $DB->get_records_sql($query_users, array($PAGE->context->id));

$table = new html_table();
$table->width = '100%';
$table->head = array('Foto', 'Nome', 'A&ccedil;&atilde;o');
$table->align = array("center", "left", "center");

$imglink = html_writer::empty_tag('img', array('src'=>"$CFG->wwwroot/blocks/lvs/imgs/menu/relatorio.png"));

foreach ($users as $user) {
	$userpic = $OUTPUT->user_picture($user);
	$link = html_writer::link("$CFG->wwwroot/blocks/lvs/pages/relatorio_notas.php?curso=$course->id&usuario=$user->userid&tipo=html", $imglink, array('style'=>"text-decoration:none; color:000;"));
	$table->data[] = array($userpic, $user->firstname . ' ' . $user->lastname, $link);
}

echo $OUTPUT->header();
echo '<center>';
echo html_writer::table($table);
echo '</center><br><hr>';
echo $OUTPUT->footer();