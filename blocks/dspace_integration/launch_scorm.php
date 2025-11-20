<?php
// Lanza la previsualización SCORM usando el reproductor de Moodle.
// Opción A: utiliza tipo de paquete externo (requiere permitir "Enable external package type").

require_once(__DIR__ . '/../../config.php');
require_login();

global $CFG, $DB, $PAGE, $OUTPUT;

$uuid = optional_param('uuid', '', PARAM_ALPHANUMEXT);
if (empty($uuid)) {
    print_error('missingparam', 'error', '', 'uuid');
}

// URL directa de descarga del paquete desde DSpace (tal como usa el bloque)
$dspacebase = get_config('block_dspace_integration', 'server');
if (empty($dspacebase)) {
    print_error('configdata', 'error', '', 'block_dspace_integration: server');
}
// El bloque anterior construye descargas usando el host DSpace base de descargas; asumimos mismo host.
$packageurl = rtrim($dspacebase, '/') . "/bitstreams/{$uuid}/download";

// Verificar ajuste para permitir paquete externo
$allowexternal = get_config('scorm', 'allowtypeexternal');
if (empty($allowexternal)) {
    $PAGE->set_url(new moodle_url('/blocks/dspace_integration/launch_scorm.php', ['uuid' => $uuid]));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title('SCORM: configuración requerida');
    echo $OUTPUT->header();
    echo html_writer::div(
        'Para usar el reproductor SCORM de Moodle con un paquete externo, habilita en Administración del sitio → Plugins → Módulos de actividad → Paquete SCORM: "Enable external package type".',
        'alert alert-warning'
    );
    echo html_writer::div('Una vez habilitado, vuelve a intentar la previsualización.', 'text-muted');
    echo $OUTPUT->footer();
    exit;
}

require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/scorm/lib.php');

// Usaremos el curso de la portada del sitio para almacenar/usar una actividad reutilizable.
$course = get_course(SITEID);
$context = context_course::instance($course->id);

// Verificar permisos mínimos para crear/actualizar actividades.
if (!has_capability('moodle/course:manageactivities', $context)) {
    // Si el usuario no puede crear, no podemos usar el reproductor sin una actividad. Mostrar aviso y abrir URL directa.
    redirect(new moodle_url($packageurl), 'No tienes permisos para usar el reproductor SCORM de Moodle. Abriendo el paquete directamente…', 2);
    exit;
}

// Buscar si ya existe una actividad reutilizable.
$name = 'DSpace SCORM Preview';
$cm = $DB->get_record_sql(
    "SELECT cm.* FROM {course_modules} cm
      JOIN {modules} m ON m.id = cm.module
      JOIN {scorm} s ON s.id = cm.instance
     WHERE cm.course = :course AND m.name = 'scorm' AND s.name = :name
     ORDER BY cm.id DESC",
    ['course' => $course->id, 'name' => $name]
);

if ($cm) {
    // Actualizar la instancia con la nueva URL externa.
    $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
    $scorm->name = $name;
    $scorm->scormtype = 'external';
    $scorm->packageurl = $packageurl; // campo de formulario; scorm_update_instance moverá a reference
    $scorm->intro = '';
    $scorm->introformat = FORMAT_HTML;
    scorm_update_instance($scorm);
    $cmid = $cm->id;
} else {
    // Crear una nueva actividad SCORM externa.
    $fromform = new stdClass();
    $fromform->course = $course->id;
    $fromform->section = 0;
    $fromform->visible = 1;
    $fromform->modulename = 'scorm';
    // add_moduleinfo espera en algunos flujos $fromform->module (id en tabla {modules}).
    $fromform->module = $DB->get_field('modules', 'id', ['name' => 'scorm']);
    $fromform->name = $name;
    $fromform->intro = '';
    $fromform->introformat = FORMAT_HTML;
    $fromform->scormtype = 'external';
    $fromform->packageurl = $packageurl;
    // Ajustes básicos por defecto (se respetan los valores admin si no se definen aquí)
    $fromform->popup = get_config('scorm', 'popup') ?? 0;
    $fromform->width = get_config('scorm', 'framewidth') ?? 100;
    $fromform->height = get_config('scorm', 'frameheight') ?? 500;

    $moduleinfo = add_moduleinfo($fromform, $course, null);
    $cmid = $moduleinfo->coursemodule;
}

// Redirigir al reproductor de Moodle.
redirect(new moodle_url('/mod/scorm/view.php', ['id' => $cmid]));
exit;
