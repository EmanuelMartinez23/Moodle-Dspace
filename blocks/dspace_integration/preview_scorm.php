<?php
// Previsualización SCORM deshabilitada: mantener endpoint inofensivo.
require_once(__DIR__ . '/../../config.php');
require_login();

// Mostrar un mensaje claro sin exponer detalles internos.
global $PAGE, $OUTPUT;
$PAGE->set_url(new moodle_url('/blocks/dspace_integration/preview_scorm.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('SCORM deshabilitado');
echo $OUTPUT->header();
echo html_writer::div('La previsualización de paquetes SCORM ha sido deshabilitada por el administrador de la plataforma.', 'alert alert-info');
echo $OUTPUT->footer();
