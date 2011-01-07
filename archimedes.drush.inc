<?php

/**
 * @file
 * Drush integration for the archimedes module.
 */

/**
 * Implements hook_drush_command().
 */
function archimedes_drush_command() {

  $items['arch-xml'] = array(
    'description' => "Provides a dump of the current XML output.",
  );
  $items['arch-send'] = array(
    'description' => "Provides a dump of the current XML output.",
    'options' => array(
      'unencrypt' => 'Even if an encryption key is stored, send unencrypted.',
      'mail' => 'Specify a target email address.',
    ),
    'examples' => array(
      'drush arch-send' => 'Send XML update using default settings.',
      'drush arch-send --unencrypt --mail=user@domain.com' => 'Send XML update unencrytped to chosen email address.',
      'drush -l http://website.com arch-send' => 'Send XML update for a specific servername.',
    ),
  );

  return $items;
}


/**
 * Command callback. Output XML.
 */
function drush_archimedes_arch_xml() {
  module_load_include('php','archimedes','archimedes.class');
  $owl = archimedes_collect();
  drush_print("\n" . $owl->toXML() . "\n");
}

/**
 * Command callback. Send an update to configured email address.
 */
function drush_archimedes_arch_send() {
  archimedes_send(drush_get_option('unencrypt'), drush_get_option('mail'));
  $messages = drupal_set_message();
  if (isset($messages['warning'])) {
    drupal_set_message('Try using drush -l http://servername if servername cannot be determined.','warning');
  }
}