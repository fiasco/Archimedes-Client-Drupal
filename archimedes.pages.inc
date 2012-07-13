<?php

/**
 * Administration settings form.
 */
function archimedes_admin() {
  $form = array();

  $form['archimedes_server_email'] = array(
    '#type' => 'textfield',
    '#title' => t('Server email address'),
    '#default_value' => variable_get('archimedes_server_email', 'archimedes@server.com'),
    '#description' => t('Set Archimedes server email address.'),
  );

  $form['archimedes_server_url'] = array(
    '#type' => 'textfield',
    '#title' => 'Archimedes Server URL',
    '#default_value' =>  variable_get('archimedes_server_url', FALSE),
    '#description' => t("This is required if you wish to use a direct update method to the archimedes server apposed to using email."),
  );

  $form['archimedes_send_method'] = array(
    '#type' => 'radios',
    '#title' => 'Archimedes Send Method',
    '#default_value' => variable_get('archimedes_send_method', 'postXML'),
    '#options' => array(
      'sendXML' => 'Email',
      'postXML' => 'HTTP',
    ),
  );

  $form['archimedes_server_key'] = array(
    '#type' => 'textarea',
    '#title' => t('Server Public Key'),
    '#default_value' => variable_get('archimedes_server_key', ''),
    '#description' => t('Set the public key for the Archimedes server. This can be found on the Archimedes server administration pages. If left blank mail will be unencrypted.'),
  );
  $form['archimedes_description'] = array(
    '#type' => 'textfield',
    '#title' => t('Description node'),
    '#size' => 40,
    '#default_value' => variable_get('archimedes_description', ''),
    '#field_prefix' => url(NULL, array('absolute' => TRUE)) . (variable_get('clean_url', 0) ? '' : '?q='),
    '#description' => t('Set the path where Archimedes can find a description for the site. If left blank, the Site Mission is used.'),
  );

  $form['archimedes_cron_update'] = array(
    '#type' => 'textfield',
    '#title' => t('Cron upate every x days'),
    '#size' => 5,
    '#default_value' => variable_get('archimedes_cron_update', 1),
    '#description' => t('Set Archimedes minimum cron update period.'),
  );

  $form['archimedes_cron_first'] = array(
    '#type' => 'select',
    '#title' => t('Update on first cron after hour'),
    '#options' => range(0, 23),
    '#default_value' => variable_get('archimedes_cron_first', 0),
    '#description' => t('Set the hour at which Archimedes collects and sends its data. This should ideally be a period with minimal traffic.'),
  );

  $options = array();
  foreach (environment_load() as $key => $env) {
    $options[$key] = $env['label'];
  }

  $form['archimedes_site_environment'] = array(
    '#type' => 'select',
    '#title' => t('Site Environment Type'),
    '#options' => $options,
    '#default_value' => environment_current(),
    '#description' => t('This variable is controlled by the Environment module. See !link to alter the environment.', array('!link' => l('the environment admin page', 'admin/config/system/environment'))),
    '#disabled' => TRUE,
  );

  $form['archimedes_allow_administer'] = array(
    '#type' => 'checkbox',
    '#title' => t('Allow "Administer Site" functionality from the server'),
    '#default_value' => variable_get('archimedes_allow_administer', FALSE),
    '#description' => t('Checking this allows authenticated users to gain admin access to this site from the server.'),
  );

  return system_settings_form($form);
}

/**
  * Implementation of hook_validate()
  *
  * Validate the admin settings form when submitted
  *
  * @author Adam Bramley <adam@catalyst.net.nz>
  */
function archimedes_admin_validate($node) {
  $key = $node['archimedes_server_key']['#value'];
  if (!empty($key) && !openssl_pkey_get_public($key)) {
    form_set_error('archimedes_server_key', t('The public key you provided is invalid'));
  }
}

/**
 * Generate a report.
 */
function archimedes_out() {
  require_once dirname(__FILE__) . '/archimedes.inc';
  $table_vars['header'] = array('Component', 'Value');
  $table_vars['rows'] = array();
  $table_vars['empty'] = t("This report did not run. It is currently disabled.");
//  $owl = archimedes_build_report();

//  $header = array('Component', 'Value');
//  $rows = array(
//    array(t('Site title'),(string) $owl->getField('title')),
//    array(t('Description'),(string) $owl->getField('body')),
//    array(t('Servername'),(string) $owl->getField('field_servername')),
//    array(t('Web Server'),(string) $owl->getField('field_webserver')),
//    array(t('Web Root'),(string) $owl->getField('field_webroot')),
//    array(t('DB Host'),(string) $owl->getField('field_dbhost')),
//    array(t('DB Name'),(string) $owl->getField('field_dbname')),
//    array(t('Users'),(string) $owl->getField('field_users')),
//    array(t('DB Size'),(string) $owl->getField('field_db_size')),
//    array(t('Site Data Size'),(string) $owl->getField('field_sitedata')),
//    array(t('Webroot Size'),(string) $owl->getField('field_sitesize')),
//    array(t('Directory Hash'), (string) $owl->getField('field_directory_hash')),
//    array(t('Environment'), (string) $owl->getField('field_site_env')),
//  );
//  drupal_alter('archimedes_rows',$rows,$owl);
//  $table = theme('table', array('header' => $header, 'rows' => $rows));
//
//  $header = array('Modules','Version','Description');
//  $modules = theme('table', array('header' => $header, 'rows' => $owl->getField('field_drupal_mod')->toArray()));
//
//  $header = array('Themes','Version','Description');
//  $themes = theme('table', array('header' => $header, 'rows' => $owl->getField('field_drupal_theme')->toArray()));
//
//  $output = '<p>' . t('This page shows the current output from of Archimedes Client for this website. It will not necessarily match what is known by the server.'). '</p>';
//  return $output . $table . $modules . $themes;
  return theme('table', $table_vars);
} 

/**
 * Export report in XML to debug report output.
 */
function archimedes_export() {
  require_once dirname(__FILE__) . '/archimedes.inc';
  $owl = archimedes_build_report();

  return '<pre>' . htmlentities($owl->toXML()) . '</pre>';

} // archimedes_export()

/**
 * Drupal UI callback to force Archimedes client to send a report.
 */
function archimedes_send_force() {
  require_once dirname(__FILE__) . '/archimedes.inc';
  archimedes_send_report();
  drupal_goto('admin/reports/archimedes');
}

/**
 * Validation callback: Access check for Archimedes remote access.
 * Request Admin access to this Drupal site.
 *
 * If function is successfull, the user will be granted
 * user uid:1 access.
 */
function archimedes_request_admin_access() {
  global $user;
  if ($user->uid == 1) {
    drupal_goto('<front>');
  }

  if (!variable_get('archimedes_allow_administer', FALSE)) {
    return FALSE;
  }

  require_once dirname(__FILE__) . '/archimedes.inc';

  $request = new ArchimedesRemoteRequest();

  // State machine.
  // When a user is validated, this code will execute.
  if (isset($_SESSION['archimedes_token']) && $request->validateToken($_SESSION['archimedes_token'])) {
      $user = user_load(array('uid' => 1));
      unset($_SESSION['archimedes_token']);
      drupal_set_message("Login from administration server was successful");
      drupal_goto('<front>');
  }

  if (!$token = $request->getToken(archimedes_get_token(), variable_get('archimedes_server_key',''))) {
    return FALSE;
  }

  $_SESSION['archimedes_token'] = $token;

  $request->validateRemoteUser();
  return TRUE;
}
