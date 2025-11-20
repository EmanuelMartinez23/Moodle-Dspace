<?php
defined('MOODLE_INTERNAL') || die();

function block_dspace_integration_extend_navigation($nav) {
    $node = $nav->add('Subir a DSpace', new moodle_url('/blocks/dspace_integration/upload.php'));
}
