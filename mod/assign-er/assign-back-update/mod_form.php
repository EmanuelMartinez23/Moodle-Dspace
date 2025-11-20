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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Assignment settings form.
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_assign_mod_form extends moodleform_mod {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {
        global $CFG, $COURSE, $DB,$PAGE, $USER;

        $PAGE->requires->css(new moodle_url('/mod/assign/styles.css', array('v' => time())));
        $PAGE->requires->js(new moodle_url('/mod/assign/toggle.js'));

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('assignmentname', 'assign'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addElement('html', '
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/css/dataTables.bootstrap5.min.css">
        <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/js/dataTables.bootstrap5.min.js"></script>
        ');


        $this->standard_intro_elements(get_string('description', 'assign'));

        // Activity.
        $mform->addElement('editor', 'activityeditor',
             get_string('activityeditor', 'assign'), array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true, 'context' => $this->context, 'subdirs' => true));
        $mform->addHelpButton('activityeditor', 'activityeditor', 'assign');
        $mform->setType('activityeditor', PARAM_RAW);

        $mform->addElement('filemanager', 'introattachments',
                            get_string('introattachments', 'assign'),
                            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes) );
        $mform->addHelpButton('introattachments', 'introattachments', 'assign');

        $mform->addElement('advcheckbox', 'submissionattachments', get_string('submissionattachments', 'assign'));
        $mform->addHelpButton('submissionattachments', 'submissionattachments', 'assign');

        
        //funciona
        //Obtenemos el ID del curso
        $courseid = $this->current->course; 
        // Verificamos si el id del curso es valido
        if ($courseid) {
            // Obtener el contexto del curso
            $context = context_course::instance($courseid);
            // Obtenemos o creamos la instancia del bloque
            $block_instance = create_or_get_block_instance('dspace_integration', $context->id);
            if ($block_instance) {
                $block = block_instance($block_instance->blockname, $block_instance);

                if ($block && $block->get_content()) {
                    //Mostramos el contenido del bloque (comunidades, colecciones, etc.)
                    $block_content = $block->get_content()->text;
                    $mform->addElement('html', 
                        '<div class="dspace-block mb-3 row  fitem ">
                                <div class ="text-block col-md-3 col-form-label d-flex pb-0 pr-md-30 pl-md-4 ">
                                <p class="mt-3 mr-3">Repositorio Institucional (Colecciones)</p>
                                
                                </div>
                                <div class="content col-md-9 d-flex flex-wrap align-items-start felement">'
                                . $block_content . '
                                </div>
                        </div>');
                        
                } else {
                    $mform->addElement('html', '<p>No se pudo crear la instancia del bloque.</p>');
                }
            } else {
                $mform->addElement('html', '<p>No se encontró la instancia del bloque especificado.</p>');
            }
        } else {
            $mform->addElement('html', '<p>Error: No se pudo obtener el ID del curso.</p>');
        }
        // Buton Descarga     
        $mform->addElement('submit', 'process_bitstreams', get_string('download_and_store_files', 'mod_assign'));

        // funcional
        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('assign', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }
        $assignment = new assign($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id'=>$this->current->course), '*', MUST_EXIST);
            $assignment->set_course($course);
        }

        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', true);

        $name = get_string('allowsubmissionsfromdate', 'assign');
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'assign');

        $name = get_string('duedate', 'assign');
        $mform->addElement('date_time_selector', 'duedate', $name, array('optional'=>true));
        $mform->addHelpButton('duedate', 'duedate', 'assign');

        $name = get_string('cutoffdate', 'assign');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional'=>true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'assign');

        $name = get_string('gradingduedate', 'assign');
        $mform->addElement('date_time_selector', 'gradingduedate', $name, array('optional' => true));
        $mform->addHelpButton('gradingduedate', 'gradingduedate', 'assign');

        $timelimitenabled = get_config('assign', 'enabletimelimit');
        // Time limit.
        if ($timelimitenabled) {
            $mform->addElement('duration', 'timelimit', get_string('timelimit', 'assign'),
                array('optional' => true));
            $mform->addHelpButton('timelimit', 'timelimit', 'assign');
        }

        $name = get_string('alwaysshowdescription', 'assign');
        $mform->addElement('checkbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'assign');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');

        $assignment->add_all_plugin_settings($mform);

        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'assign'));

        $name = get_string('submissiondrafts', 'assign');
        $mform->addElement('selectyesno', 'submissiondrafts', $name);
        $mform->addHelpButton('submissiondrafts', 'submissiondrafts', 'assign');
        if ($assignment->has_submissions_or_grades()) {
            $mform->freeze('submissiondrafts');
        }

        $name = get_string('requiresubmissionstatement', 'assign');
        $mform->addElement('selectyesno', 'requiresubmissionstatement', $name);
        $mform->addHelpButton('requiresubmissionstatement',
                              'requiresubmissionstatement',
                              'assign');
        $mform->setType('requiresubmissionstatement', PARAM_BOOL);

        $options = [ASSIGN_UNLIMITED_ATTEMPTS => get_string('unlimitedattempts', 'mod_assign')];
        $options += array_combine(range(1, 30), range(1, 30));
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_assign'), $options);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'assign');

        $choice = new core\output\choicelist();

        $choice->add_option(
            value: ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
            name: get_string('attemptreopenmethod_manual', 'mod_assign'),
            definition: ['description' => get_string('attemptreopenmethod_manual_help', 'mod_assign')]
        );
        $choice->add_option(
            value: ASSIGN_ATTEMPT_REOPEN_METHOD_AUTOMATIC,
            name: get_string('attemptreopenmethod_automatic', 'mod_assign'),
            definition: ['description' => get_string('attemptreopenmethod_automatic_help', 'mod_assign')]
        );
        $choice->add_option(
            value: ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS,
            name: get_string('attemptreopenmethod_untilpass', 'mod_assign'),
            definition: ['description' => get_string('attemptreopenmethod_untilpass_help', 'mod_assign')]
        );

        $mform->addElement('choicedropdown', 'attemptreopenmethod', get_string('attemptreopenmethod', 'mod_assign'), $choice);
        $mform->hideIf('attemptreopenmethod', 'maxattempts', 'eq', 1);

        $mform->addElement('header', 'groupsubmissionsettings', get_string('groupsubmissionsettings', 'assign'));

        $name = get_string('teamsubmission', 'assign');
        $mform->addElement('selectyesno', 'teamsubmission', $name);
        $mform->addHelpButton('teamsubmission', 'teamsubmission', 'assign');
        if ($assignment->has_submissions_or_grades()) {
            $mform->freeze('teamsubmission');
        }

        $name = get_string('preventsubmissionnotingroup', 'assign');
        $mform->addElement('selectyesno', 'preventsubmissionnotingroup', $name);
        $mform->addHelpButton('preventsubmissionnotingroup',
            'preventsubmissionnotingroup',
            'assign');
        $mform->setType('preventsubmissionnotingroup', PARAM_BOOL);
        $mform->hideIf('preventsubmissionnotingroup', 'teamsubmission', 'eq', 0);

        $name = get_string('requireallteammemberssubmit', 'assign');
        $mform->addElement('selectyesno', 'requireallteammemberssubmit', $name);
        $mform->addHelpButton('requireallteammemberssubmit', 'requireallteammemberssubmit', 'assign');
        $mform->hideIf('requireallteammemberssubmit', 'teamsubmission', 'eq', 0);
        $mform->disabledIf('requireallteammemberssubmit', 'submissiondrafts', 'eq', 0);

        $groupings = groups_get_all_groupings($assignment->get_course()->id);
        $options = array();
        $options[0] = get_string('none');
        foreach ($groupings as $grouping) {
            $options[$grouping->id] = $grouping->name;
        }

        $name = get_string('teamsubmissiongroupingid', 'assign');
        $mform->addElement('select', 'teamsubmissiongroupingid', $name, $options);
        $mform->addHelpButton('teamsubmissiongroupingid', 'teamsubmissiongroupingid', 'assign');
        $mform->hideIf('teamsubmissiongroupingid', 'teamsubmission', 'eq', 0);
        if ($assignment->has_submissions_or_grades()) {
            $mform->freeze('teamsubmissiongroupingid');
        }

        $mform->addElement('header', 'notifications', get_string('notifications', 'assign'));

        $name = get_string('sendnotifications', 'assign');
        $mform->addElement('selectyesno', 'sendnotifications', $name);
        $mform->addHelpButton('sendnotifications', 'sendnotifications', 'assign');

        $name = get_string('sendlatenotifications', 'assign');
        $mform->addElement('selectyesno', 'sendlatenotifications', $name);
        $mform->addHelpButton('sendlatenotifications', 'sendlatenotifications', 'assign');
        $mform->disabledIf('sendlatenotifications', 'sendnotifications', 'eq', 1);

        $name = get_string('sendstudentnotificationsdefault', 'assign');
        $mform->addElement('selectyesno', 'sendstudentnotifications', $name);
        $mform->addHelpButton('sendstudentnotifications', 'sendstudentnotificationsdefault', 'assign');

        $this->standard_grading_coursemodule_elements();
        $name = get_string('blindmarking', 'assign');
        $mform->addElement('selectyesno', 'blindmarking', $name);
        $mform->addHelpButton('blindmarking', 'blindmarking', 'assign');
        if ($assignment->has_submissions_or_grades() ) {
            $mform->freeze('blindmarking');
        }

        $name = get_string('hidegrader', 'assign');
        $mform->addElement('selectyesno', 'hidegrader', $name);
        $mform->addHelpButton('hidegrader', 'hidegrader', 'assign');

        $name = get_string('markingworkflow', 'assign');
        $mform->addElement('selectyesno', 'markingworkflow', $name);
        $mform->addHelpButton('markingworkflow', 'markingworkflow', 'assign');

        $name = get_string('markingallocation', 'assign');
        $mform->addElement('selectyesno', 'markingallocation', $name);
        $mform->addHelpButton('markingallocation', 'markingallocation', 'assign');
        $mform->hideIf('markingallocation', 'markingworkflow', 'eq', 0);

        $name = get_string('markinganonymous', 'assign');
        $mform->addElement('selectyesno', 'markinganonymous', $name);
        $mform->addHelpButton('markinganonymous', 'markinganonymous', 'assign');
        $mform->hideIf('markinganonymous', 'markingworkflow', 'eq', 0);
        $mform->hideIf('markinganonymous', 'blindmarking', 'eq', 0);

        $this->standard_coursemodule_elements();
        $this->apply_admin_defaults();

        $this->add_action_buttons();

        //funcional
                //  la URL dinámica.
                $course_module_id = $PAGE->cm->id ?? 0; // 
                // Controla el comportamiento de retorno., volver a curso después de guardar
                $return = 1; 
    
                $url = new moodle_url('/course/modedit.php', [
                    'update' => $course_module_id,
                    'return' => $return,
                ]);
        
                // Test la URL .
                // $mform->addElement('html', '<div>URL de edición: <a href="'.$url->out().'">'.$url->out().'</a></div>');
 
            if (optional_param('process_bitstreams', false, PARAM_RAW)) {
            if (isset($_POST['bitstreams'])) {
                $selectedItems = $_POST['bitstreams'];
                $messages = [];
                foreach ($selectedItems as $bitstreamId) {
                    $result = $this->download_and_store_dspace_file($bitstreamId);
                    if ($result['success']) {
                        $messages[] = "✅ Archivo descargado del repositorio y almacenado en la actividad en Moodle: " . $result['file']->get_filename();
                    } else {
                        foreach ($result['errors'] as $err) {
                            $messages[] = $err;
                        }
                    }
                }
                redirect($url->out(), implode('<br>', $messages), 4);
            }
    }

    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])) {
            if ($data['duedate'] <= $data['allowsubmissionsfromdate']) {
                $errors['duedate'] = get_string('duedateaftersubmissionvalidation', 'assign');
            }
        }
        if (!empty($data['cutoffdate']) && !empty($data['duedate'])) {
            if ($data['cutoffdate'] < $data['duedate'] ) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'assign');
            }
        }
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['cutoffdate'])) {
            if ($data['cutoffdate'] < $data['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'assign');
            }
        }
        if ($data['gradingduedate']) {
            if ($data['allowsubmissionsfromdate'] && $data['allowsubmissionsfromdate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingduefromdatevalidation', 'assign');
            }
            if ($data['duedate'] && $data['duedate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingdueduedatevalidation', 'assign');
            }
        }
        $multipleattemptsallowed = $data['maxattempts'] > 1 || $data['maxattempts'] == ASSIGN_UNLIMITED_ATTEMPTS;
        if ($data['blindmarking'] && $multipleattemptsallowed &&
                $data['attemptreopenmethod'] == ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS) {
            $errors['attemptreopenmethod'] = get_string('reopenuntilpassincompatiblewithblindmarking', 'assign');
        }

        return $errors;
    }

    /**
     * Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('assign', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }
        $assignment = new assign($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id'=>$this->current->course), '*', MUST_EXIST);
            $assignment->set_course($course);
        }

        $draftitemid = file_get_submitted_draft_itemid('introattachments');
        file_prepare_draft_area($draftitemid, $ctx->id, 'mod_assign', ASSIGN_INTROATTACHMENT_FILEAREA,
                                0, array('subdirs' => 0));
        $defaultvalues['introattachments'] = $draftitemid;

        // Activity editor fields.
        $activitydraftitemid = file_get_submitted_draft_itemid('activityeditor');
        if (!empty($defaultvalues['activity'])) {
            $defaultvalues['activityeditor'] = array(
                'text' => file_prepare_draft_area($activitydraftitemid, $ctx->id, 'mod_assign', ASSIGN_ACTIVITYATTACHMENT_FILEAREA,
                    0, array('subdirs' => 0), $defaultvalues['activity']),
                'format' => $defaultvalues['activityformat'],
                'itemid' => $activitydraftitemid
            );
        }

        $assignment->plugin_data_preprocessing($defaultvalues);
    }


    
    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $suffix = $this->get_suffix();
        $completionsubmitel = 'completionsubmit' . $suffix;
        $mform->addElement('advcheckbox', $completionsubmitel, '', get_string('completionsubmit', 'assign'));
        // Enable this completion rule by default.
        $mform->setDefault($completionsubmitel, 1);

        return [$completionsubmitel];
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionsubmit' . $suffix]);
    }

    /**
     * Get the list of admin settings for this module and apply any defaults/advanced/locked/required settings.
     *
     * @param array $datetimeoffsets  - If passed, this is an array of fieldnames => times that the
     *                          default date/time value should be relative to. If not passed, all
     *                          date/time fields are set relative to the users current midnight.
     * @return void
     */
    public function apply_admin_defaults($datetimeoffsets = []): void {
        parent::apply_admin_defaults($datetimeoffsets);

        $isupdate = !empty($this->_cm);
        if ($isupdate) {
            return;
        }

        $settings = get_config('mod_assign');
        $mform = $this->_form;

        if ($mform->elementExists('grade')) {
            $element = $mform->getElement('grade');

            if (property_exists($settings, 'defaultgradetype')) {
                $modgradetype = $element->getName() . '[modgrade_type]';
                switch ((int)$settings->defaultgradetype) {
                    case GRADE_TYPE_NONE :
                        $mform->setDefault($modgradetype, 'none');
                        break;
                    case GRADE_TYPE_SCALE :
                        $mform->setDefault($modgradetype, 'scale');
                        break;
                    case GRADE_TYPE_VALUE :
                        $mform->setDefault($modgradetype, 'point');
                        break;
                }
            }

            if (property_exists($settings, 'defaultgradescale')) {
                /** @var grade_scale|false $gradescale */
                $gradescale = grade_scale::fetch(['id' => (int)$settings->defaultgradescale, 'courseid' => 0]);

                if ($gradescale) {
                    $mform->setDefault($element->getName() . '[modgrade_scale]', $gradescale->id);
                }
            }
        }
    }


    // Función que descarga y almacena un archivo
   function download_and_store_dspace_file($bitstreamid) {
    global $DB, $USER;

    $errors = [];

    // Credenciales DSpace
    $dspace_api_url   = get_config('block_dspace_integration', 'server');
    $username    = get_config('block_dspace_integration', 'email');
    $password = get_config('block_dspace_integration', 'password');


    debugging("➡️ Iniciando descarga de $bitstreamid", DEBUG_DEVELOPER);

    // --- 1) Obtener CSRF token ---
    $csrf_url = "$dspace_api_url/security/csrf";
    $ch = curl_init($csrf_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $csrf_response = curl_exec($ch);
    curl_close($ch);

    if (!$csrf_response || !preg_match('/DSPACE-XSRF-COOKIE=([^;]+)/', $csrf_response, $matches)) {
        $errors[] = "❌ No se pudo obtener CSRF token";
        return ['success' => false, 'errors' => $errors];
    }
    $csrf_token = $matches[1];
    debugging("✅ CSRF token obtenido: $csrf_token", DEBUG_DEVELOPER);

    // --- 2) Login ---
    $login_url = "$dspace_api_url/authn/login";
    $ch = curl_init($login_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "user=$username&password=$password");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-XSRF-TOKEN: $csrf_token",
        "Cookie: DSPACE-XSRF-COOKIE=$csrf_token",
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
    ]);
    $login_response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($login_response, 0, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        $errors[] = "❌ Error de login en DSpace (HTTP $http_code)";
        return ['success' => false, 'errors' => $errors];
    }

    if (!preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $headers, $matches)) {
        $errors[] = "❌ No se pudo obtener token de login";
        return ['success' => false, 'errors' => $errors];
    }
    $token = $matches[1];
    debugging("✅ Login exitoso, token obtenido", DEBUG_DEVELOPER);

    // --- 3) Obtener info del bitstream ---
    $bitstream_info_url = "$dspace_api_url/core/bitstreams/$bitstreamid";
    $ch = curl_init($bitstream_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Accept: application/json"
    ]);
    $bitstream_info_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        $errors[] = "❌ Error al obtener info del bitstream (HTTP $http_code)";
        return ['success' => false, 'errors' => $errors];
    }

    $bitstream_info = json_decode($bitstream_info_response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($bitstream_info['name'])) {
        $errors[] = "❌ No se pudo procesar la info del bitstream";
        return ['success' => false, 'errors' => $errors];
    }

    $filename = $bitstream_info['name'];
    debugging("📄 Bitstream encontrado: $filename", DEBUG_DEVELOPER);

    // --- 4) Descargar contenido ---
    $bitstream_download_url = "$dspace_api_url/core/bitstreams/$bitstreamid/content";
    $ch = curl_init($bitstream_download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $file_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($file_content)) {
        $errors[] = "❌ Error al descargar bitstream (HTTP $http_code)";
        return ['success' => false, 'errors' => $errors];
    }
    debugging("📥 Bitstream descargado correctamente", DEBUG_DEVELOPER);

    // --- 5) Guardar en Moodle ---
    $mform_data = $this->current;
    $assignid = $mform_data->instance;
    $cm = get_coursemodule_from_instance('assign', $assignid);
    if (!$cm) {
        $errors[] = "❌ Módulo de curso inválido";
        return ['success' => false, 'errors' => $errors];
    }

    $context = context_module::instance($cm->id);
    $fs = get_file_storage();

    // --- Verificar existencia en filearea final ---
    $existingfile = $fs->get_file(
        $context->id,
        'mod_assign',
        'introattachment',
        $assignid,
        '/',
        $filename
    );

    if ($existingfile) {
        $errors[] = "ℹ️ El archivo ya existe en Moodle (introattachment): $filename";
        return ['success' => false, 'errors' => $errors, 'file' => $existingfile];
    }

    // --- Verificar existencia en draft ---
    $draftitemid = file_get_submitted_draft_itemid('introattachments');
    $draftfiles = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, 'filename', false);
    foreach ($draftfiles as $df) {
        if ($df->get_filename() === $filename) {
            $errors[] = "ℹ️ El archivo ya existe en el draft de Moodle: $filename";
            return ['success' => false, 'errors' => $errors, 'file' => $df];
        }
    }

    // --- Crear archivo en draft ---
    $filerecord = new stdClass();
    $filerecord->contextid = $context->id;
    $filerecord->component = 'mod_assign';
    $filerecord->filearea = 'introattachment';
    $filerecord->itemid = 0;
    $filerecord->filepath = '/';
    $filerecord->filename = $filename;

    try {
        $storedfile = $fs->create_file_from_string($filerecord, $file_content);
        debugging("✅ Archivo guardado del repositorio y almacenado en la actividad en Moodle: $filename", DEBUG_DEVELOPER);
        return ['success' => true, 'file' => $storedfile];
    } catch (Exception $e) {
        $errors[] = "❌ Error al guardar archivo en Moodle: " . $e->getMessage();
        return ['success' => false, 'errors' => $errors];
    }
}

}


