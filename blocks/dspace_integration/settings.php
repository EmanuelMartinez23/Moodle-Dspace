<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'block_dspace_integration', 
        get_string('pluginname', 'block_dspace_integration')
    );

    $settings->add(new admin_setting_configtext(
        'block_dspace_integration/server',
        get_string('server', 'block_dspace_integration'),
        get_string('server_desc', 'block_dspace_integration'),
        'http://192.168.1.27:8080/server/api'
    ));

    $settings->add(new admin_setting_configtext(
        'block_dspace_integration/email',
        get_string('email', 'block_dspace_integration'),
        get_string('email_desc', 'block_dspace_integration'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_dspace_integration/password',
        get_string('password', 'block_dspace_integration'),
        get_string('password_desc', 'block_dspace_integration'),
        ''
    ));

 
}
