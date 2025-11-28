<?php
// Este lanzador de previsualización SCORM ha sido deshabilitado.
require_once(__DIR__ . '/../../config.php');
require_login();
global $PAGE, $OUTPUT;
$PAGE->set_url(new moodle_url('/blocks/dspace_integration/launch_scorm.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('SCORM deshabilitado');
echo $OUTPUT->header();
echo html_writer::div('La previsualización con el reproductor SCORM de Moodle ha sido deshabilitada por el administrador.', 'alert alert-info');
echo $OUTPUT->footer();
exit;
