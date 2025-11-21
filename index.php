<?php
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
 * Moodle frontpage.
 *
 * @package    core
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!file_exists('./config.php')) {
    header('Location: install.php');
    die;
}

require_once('config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');
// DSpace client (opcional). Solo si el bloque está instalado.
$dspaceclientpath = $CFG->dirroot . '/blocks/dspace_integration/dspace_client.php';
if (file_exists($dspaceclientpath)) {
    require_once($dspaceclientpath);
}

redirect_if_major_upgrade_required();

$urlparams = array();
if (!empty($CFG->defaulthomepage) &&
        ($CFG->defaulthomepage == HOMEPAGE_MY || $CFG->defaulthomepage == HOMEPAGE_MYCOURSES) &&
        optional_param('redirect', 1, PARAM_BOOL) === 0
) {
    $urlparams['redirect'] = 0;
}
$PAGE->set_url('/', $urlparams);
$PAGE->set_pagelayout('frontpage');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_other_editing_capability('moodle/course:update');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_other_editing_capability('moodle/course:activityvisibility');

// Prevent caching of this page to stop confusion when changing page after making AJAX changes.
$PAGE->set_cacheable(false);

require_course_login($SITE);

$hasmaintenanceaccess = has_capability('moodle/site:maintenanceaccess', context_system::instance());

// If the site is currently under maintenance, then print a message.
if (!empty($CFG->maintenance_enabled) and !$hasmaintenanceaccess) {
    print_maintenance_message();
}

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect($CFG->wwwroot .'/'. $CFG->admin .'/index.php');
}

// If site registration needs updating, redirect.
\core\hub\registration::registration_reminder('/index.php');

if (get_home_page() != HOMEPAGE_SITE) {
    // Redirect logged-in users to My Moodle overview if required.
    $redirect = optional_param('redirect', 1, PARAM_BOOL);
    if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
        set_user_preference('user_home_page_preference', HOMEPAGE_SITE);
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && $redirect === 1) {
        // At this point, dashboard is enabled so we don't need to check for it (otherwise, get_home_page() won't return it).
        redirect($CFG->wwwroot .'/my/');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MYCOURSES) && $redirect === 1) {
        redirect($CFG->wwwroot .'/my/courses.php');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_USER)) {
        $frontpagenode = $PAGE->settingsnav->find('frontpage', null);
        if ($frontpagenode) {
            $frontpagenode->add(
                get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING);
        } else {
            $frontpagenode = $PAGE->settingsnav->add(get_string('frontpagesettings'), null, navigation_node::TYPE_SETTING, null);
            $frontpagenode->force_open();
            $frontpagenode->add(get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING);
        }
    }
}

// Trigger event.
course_view(context_course::instance(SITEID));

$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('');
$editing = $PAGE->user_is_editing();
$PAGE->set_title(get_string('home'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_secondary_active_tab('coursehome');

$courserenderer = $PAGE->get_renderer('core', 'course');

if ($hassiteconfig) {
    $editurl = new moodle_url('/course/view.php', ['id' => SITEID, 'sesskey' => sesskey()]);
    $editbutton = $OUTPUT->edit_button($editurl);
    $PAGE->set_button($editbutton);
}

echo $OUTPUT->header();

$siteformatoptions = course_get_format($SITE)->get_format_options();
$modinfo = get_fast_modinfo($SITE);
$modnamesused = $modinfo->get_used_module_names();

// Print Section or custom info.
if (!empty($CFG->customfrontpageinclude)) {
    // Pre-fill some variables that custom front page might use.
    $modnames = get_module_types_names();
    $modnamesplural = get_module_types_names(true);
    $mods = $modinfo->get_cms();

    include($CFG->customfrontpageinclude);

} else if ($siteformatoptions['numsections'] > 0) {
    echo $courserenderer->frontpage_section1();
}
// Include course AJAX.
include_course_ajax($SITE, $modnamesused);

echo $courserenderer->frontpage();

// Sección extra debajo de cursos: Formulario de subida a DSpace (solo visible en modo edición).
if ($editing) {
    try {
        if (class_exists('dspace_client')) {
            global $USER, $OUTPUT; // Para iconos y nombre de usuario.
            $server = get_config('block_dspace_integration', 'server');
            $email = get_config('block_dspace_integration', 'email');
            $password = get_config('block_dspace_integration', 'password');

            if (!empty($server) && !empty($email) && !empty($password)) {
                $client = new dspace_client($server, $email, $password);
                $communities = $client->get_communities();

                if (!empty($communities)) {
                    // Render de card colapsable con encabezado e icono.
                    $cardid = 'frontpage-dspace-upload';
                    echo html_writer::start_tag('section', ['class' => 'my-5']);
                    echo html_writer::start_div('container');
                    echo html_writer::start_div('card');
                    // Header con botón de colapso e icono.
                    echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
                    $titleicon = $OUTPUT->pix_icon('i/upload', 'Subir');
                    echo html_writer::span($titleicon . ' Repositorio Institucional — Subir archivos a DSpace', 'h6 m-0');
                    $btnattrs = [
                        'class' => 'btn btn-link',
                        'type' => 'button',
                        'data-toggle' => 'collapse',
                        'data-target' => '#' . $cardid,
                        'data-bs-toggle' => 'collapse',
                        'data-bs-target' => '#' . $cardid,
                        'aria-expanded' => 'false',
                        'aria-controls' => $cardid
                    ];
                    echo html_writer::tag('button', 'Mostrar / Ocultar', $btnattrs);
                    echo html_writer::end_div(); // card-header

                    // Cuerpo colapsable con el formulario.
                    echo html_writer::start_tag('div', ['id' => $cardid, 'class' => 'collapse']);
                    echo html_writer::start_div('card-body');

                    // Construir formulario manualmente (action al manejador del bloque).
                    $action = new moodle_url('/blocks/dspace_integration/upload.php');
                    echo html_writer::start_tag('form', ['id' => 'frontpage-dspace-upload-form', 'action' => $action->out(false), 'method' => 'post', 'enctype' => 'multipart/form-data']);

                    // Selector de colección.
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Seleccione la colección', ['for' => 'collection_id']);
                    echo html_writer::start_tag('select', ['name' => 'collection_id', 'id' => 'collection_id', 'class' => 'custom-select form-control', 'required' => 'required']);
                    foreach ($communities as $community) {
                        try {
                            $collections = $client->get_collections($community['uuid']);
                        } catch (Exception $e) {
                            $collections = [];
                        }
                        if (!empty($collections)) {
                            // Agrupar por comunidad usando <optgroup>.
                            $label = format_string($community['name']);
                            echo html_writer::start_tag('optgroup', ['label' => $label]);
                            foreach ($collections as $col) {
                                $uuid = s($col['uuid']);
                                $name = format_string($col['name']);
                                echo html_writer::tag('option', $name, ['value' => $uuid]);
                            }
                            echo html_writer::end_tag('optgroup');
                        }
                    }
                    echo html_writer::end_tag('select');
                    echo html_writer::end_div();

                    // Campos dinámicos: usuario y fecha.
                    $fullname = fullname($USER);
                    $today = userdate(time(), '%Y-%m-%d');
                    echo html_writer::start_div('form-row');
                    // Uploader
                    echo html_writer::start_div('form-group col-md-6 mt-3');
                    echo html_writer::tag('label', 'Usuario que sube', ['for' => 'uploader']);
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'uploader',
                        'id' => 'uploader',
                        'class' => 'form-control',
                        'value' => s($fullname),
                        'required' => 'required'
                    ]);
                    echo html_writer::end_div();
                    // Fecha
                    echo html_writer::start_div('form-group col-md-6 mt-3');
                    echo html_writer::tag('label', 'Fecha de emisión', ['for' => 'dateissued']);
                    echo html_writer::empty_tag('input', [
                        'type' => 'date',
                        'name' => 'dateissued',
                        'id' => 'dateissued',
                        'class' => 'form-control',
                        'value' => s($today),
                        'required' => 'required'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div(); // form-row

                    // Título del item.
                    echo html_writer::start_div('form-group mt-3');
                    echo html_writer::tag('label', 'Título del item', ['for' => 'title']);
                    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'title', 'id' => 'title', 'class' => 'form-control', 'required' => 'required']);
                    echo html_writer::end_div();

                    // Archivos.
                    echo html_writer::start_div('form-group mt-3');
                    echo html_writer::tag('label', 'Seleccione uno o varios archivos', ['for' => 'files']);
                    echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'files[]', 'id' => 'files', 'class' => 'form-control-file form-control', 'multiple' => 'multiple', 'required' => 'required']);
                    echo html_writer::end_div();

                    // Submit.
                    echo html_writer::tag('button', 'Subir a DSpace', ['type' => 'submit', 'class' => 'btn btn-primary mt-2']);

                    echo html_writer::end_tag('form');
                    echo html_writer::end_div(); // card-body
                    echo html_writer::end_tag('div'); // collapse
                    echo html_writer::end_div(); // card
                    echo html_writer::end_div(); // container
                    echo html_writer::end_tag('section');
                } else {
                    // Si no hay comunidades configuradas, no mostramos el formulario.
                }
            } else {
                // Configuración ausente: no renderizar el formulario.
            }
        }
    } catch (Exception $e) {
        // Ante cualquier error, evitamos romper la portada y no mostramos el formulario.
        debugging('Error al renderizar formulario DSpace en inicio: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

if ($editing && has_capability('moodle/course:create', context_system::instance())) {
    echo $courserenderer->add_new_course_button();
}
echo $OUTPUT->footer();
