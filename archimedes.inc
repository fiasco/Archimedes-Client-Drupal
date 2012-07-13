<?php

/**
 * @file Archimedes toolset.
 */

/**
 * Find and load the archimedes library.
 */
function archimedes_load_library() {
  if (class_exists('Archimedes')) {
    return;
  }
  // Use the libraries module if its enabled.
  if (module_exists('libraries')) {
    $file = libraries_get_path('archimedes') . '/archimdes.class.php';
    if (file_exists($file)) {
      include_once $file;
      return TRUE;
    }
  }
  // If the file is already in the module, use that.
  if (file_exists(dirname(__FILE__) . '/archimedes.class.php')) {
    include_once dirname(__FILE__) . '/archimedes.class.php';
    return TRUE;
  }
  global $conf;
  $options = array(
    'sites/all/libraries/archimedes/archimedes.class.php',
    'profiles/' . $conf['install_profile'] . '/libraries/archimedes/archimedes.class.php',
    conf_path() . '/libraries/archimedes/archimedes.class.php',
  );
  foreach ($options as $path) {
    if (!file_exists($path)) {
      continue;
    }
    include_once $path;
    return TRUE;
  }
  return FALSE;
}

// Its safe to assume that if this file gets included that
// the archimedes library is needed so load it automatically.
class_exists('Archimedes') || archimedes_load_library();

/**
 * Implementation of hook_archimedes_id().
 *
 * A instance of an Archimedes report should be based
 * on a single Drupal install which is the environment
 * it says it is, coupled with the database credentials.
 */
function archimedes_archimedes_id() {
  $environment = environment_current();
  return variable_get('archimedes_common_key', '') . $environment;
}

/**
 * Produce a unique value to represent this instance of Drupal.
 */
function archimedes_get_token() {
  $keys = module_invoke_all('archimedes_id'); sort($keys);
  return drupal_get_token(implode('|', $keys));
}

/**
  * Send a report to the Archimedes server via email or HTTP.
  *
  * Doesn't use Drupal's messaging system because the communication
  * with the server is independant of Drupal.
  */
function archimedes_send_report() {
  global $base_url;
  if ($base_url == 'http://default'){ // must have accurate servername
    drupal_set_message('Cannot accurately determine servername. You should set this in settings.php. Update was not sent.','warning');
    return FALSE;
  }

  $server_key = variable_get('archimedes_server_key','');
  $site_name  = variable_get('site_name', 'unknown');

  if (variable_get('archimedes_send_method', 'postXML') == 'postXML') {
    $destination = variable_get('archimedes_server_url', FALSE);
  }
  else {
    $destination = 'Archimedes Server <' . variable_get('archimedes_server_email', FALSE) . '>';
  }
  if (!$destination) {
    drupal_set_message('Update failed to send as the ' . l('server email address','admin/reports/archimedes/settings') . ' is not yet set.','error');
    return FALSE;
  }

  $owl = archimedes_build_report();
  $ekey = $owl->encrypt($server_key);
  try {
    if (!call_user_func(array($owl, variable_get('archimedes_send_method', 'postXML')), $destination)) {
      throw new Exception('Update failed to send for an unknown reason.');
    }
    drupal_set_message('Update sent successfully to '  . $destination. '. This may take some time to become visible on the server.');
    variable_set('archimedes_cron_last',time());
  }
  catch (Exception $e) {
    drupal_set_message($e->getMessage(), 'error');
    watchdog('Archimedes', $e->getMessage(), array(), WATCHDOG_ERROR);
  }
}
/**
 * Use Archimedes to build a report of the site.
 */
function archimedes_build_report() {
  global $databases;

  // Produce a unique value to represent this instance of Drupal.
  $keys = module_invoke_all('archimedes_id'); sort($keys);
  $unique_id = drupal_get_token(implode('|', $keys));
  $unqiue_id = archimedes_get_token();

  $owl = new Archimedes('drupal', variable_get('site_mail', FALSE), $unique_id);

  $owl->createField('title', variable_get('site_name', "Drupal"));
  $owl->createField('field_drupal_version', VERSION);

  // This allows site instances of the same project to be
  // related. For example my.site.com may also have staging.my.site.com
  // and we don't want the reports to clash, but grouping these sites
  // is still useful.
  $owl->createField('field_common_hash', variable_get('archimedes_common_key', ''));

  if (variable_get('archimedes_description', '') != '') {
    $nid = arg(1,drupal_get_normal_path(variable_get('archimedes_description', 'node')));
    $node = node_load($nid);
    $body = trim(substr(drupal_html_to_text($node->body,array('b','strong','i','em','p')),0,500));
    $owl->createField('body', $body);
  } else {
    $owl->createField('body', variable_get('site_mission', 'No description has been set.'));
  }

  // If Drush (or some other CLI passed method) is used to 
  // run cron. You'll need to ensure a correct servername is
  // passed to PHP. With drush, use -l http://mysite.com.
  $owl->createField('field_servername', url('', array('absolute' => 1)));

  // The location where Drupal believes it resides.
  $owl->createField('field_webroot', DRUPAL_ROOT);

  // Build an understanding of the databases Drupal
  // can connect too. At this stage, Archimedes only
  // reports on the default connections, i.e, master
  // slave databases for Drupal only.
  $values = array();
  $connections = array();
  foreach($databases['default'] as $key => $connection) {
    if ($key == 'slave') {
      foreach ($connection as $slave) {
        $connections[] = $slave;
      }
    }
    else {
      $connections[] = $connection;
    }
  }

  foreach ($connections as $url) {
    //format into url like drupal 6
    $db_url = $url['driver'] . '://' . $url['username'] . ':' . $url['password'] . '@' . $url['host'] . '/' . $url['database'];
    $db = parse_url($db_url);
    if (!isset($database_name)) {
      $database_name = substr($db['path'],1);
      $owl->createField('field_dbname', $database_name);
    }
    $dbhost = ($db['host'] == 'localhost' || $db['host'] == '127.0.0.1') ? 'localhost' : $db['host'];
    $values[] = archimedes_value($dbhost,'nodereference')->addNode(array('title' => $dbhost, 'type' => 'host'));
  }

  $owl->createField('field_dbhost', $values)
      ->invokeFacet();

  $user = array(
    'type' => 'mail',
    'mailto' => 'mailto:' . db_query("SELECT u.mail FROM {users} u WHERE uid = 1 LIMIT 1")->fetchField(),
  );
  $value = archimedes_value($user['mailto'],'userreference')
            ->addUser($user);
  $owl->createField('field_users', array($value))
      ->invokeFacet();

  // Graphable data.
  $users = archimedes_value(db_query("SELECT COUNT(uid) as count FROM {users}")->fetchField() - 1, 'dataset')->setTitle('Users');
  $nodes = archimedes_value(db_query("SELECT COUNT(nid) as count FROM {node}")->fetchField(), 'dataset')->setTitle('Nodes');
  $revisions = archimedes_value(db_query("SELECT COUNT(nid) as count FROM {node_revision}")->fetchField(), 'dataset')->setTitle('Revisions');
  $owl->createField('field_c_dataset', array($nodes, $revisions, $users));

  $modules = $themes = array();
  foreach (module_list() as $module) {
    $info = drupal_parse_info_file(drupal_get_path('module', $module) . '/' . $module . '.info');
    $node = array(
      'title'   => (isset($info['name']) ? $info['name'] : ''),
      'body'    => (isset($info['description']) ? $info['description'] : ''),
      'field_name' => $module,
      'field_dru_pkg' => (isset($info['package']) ? $info['package'] : ''),
      'field_dru_proj' => (isset($info['project']) ? $info['project'] : 'drupal'),
      'field_mod_version' => (isset($info['version']) ? $info['version'] : ''),
      'field_mod_url' => (isset($info['project status url']) ? $info['project status url'] : ''),
      'type'    => 'drupal_module',
    );
    if (empty($node['field_dru_proj']) && !empty($node['field_dru_pkg']) && (strpos($node['field_dru_pkg'], 'Core -') !== FALSE)) {
      $node['field_dru_proj'] = 'drupal';
    }
    $value = archimedes_value($node['title'], 'drupalmod')
              ->addNode($node);
    $modules[] = $value;
  }

  $owl->createField('field_drupal_mod', $modules)
      ->invokeFacet();


  $result = db_query("SELECT name FROM {system} WHERE status = 1 AND type = 'theme'");
  while ($theme = $result->fetchField()) {
    $info = drupal_parse_info_file(drupal_get_path('theme', $theme) . '/' . $theme . '.info');
    $node = array(
      'title'   => (isset($info['name']) ? $info['name'] : ''),
      'body'    => (isset($info['description']) ? $info['description'] : ''),
      'field_name' => $theme,
      'field_mod_version' => (isset($info['version']) ? $info['version'] : ''),
      'field_dru_proj' => (isset($info['project']) ? $info['project'] : 'drupal'),
      'field_mod_url' => (isset($info['project status url']) ? $info['project status url'] : ''),
      'type'  => 'drupal_theme',
    );
    if (empty($node['field_dru_proj']) && in_array($theme, array('bluemarine', 'chameleon', 'garland', 'marvin', 'minnelli', 'pushbutton'))) {
      // Unfortunately, there's no way to tell if a theme is part of core,
      // so we must hard-code a list here.
      $node['field_dru_proj'] = 'drupal';
    }

    $value = archimedes_value($node['title'], 'drupalmod')
              ->addNode($node);

    $themes[] = $value;
  }

  $owl->createField('field_drupal_theme', $themes)
      ->invokeFacet();

  $environment = array_shift(environment_current(NULL,NULL,TRUE));

  $owl->createField('field_site_env', $environment['label']);

  $ignore = array('#(\w*/)*sites/\w*/files#', '#.*\.(git|svn|bzr|cvs)/.*#');
  
  // The point of the directory hash is to record the state of the deployed code base.
  // If the code base changes, then the site could have been redeployed or hacked! However,
  // in development scenarios, the code changes very frequently so we don't care to change
  // it.
  $dir_hash = archimedes_directory_hash(DRUPAL_ROOT, $ignore);
  $owl->createField('field_directory_hash', $dir_hash);

  if (variable_get('archimedes_use_unsafe_collection', FALSE)) {
    $collect = new ArchimedesDrupalUnsafeCollection($owl);
    $collect->servername()
            ->database_size()
            ->storage_usage();
  }

  // Allow other modules to add data via hook_archimedes.
  drupal_alter('archimedes', $owl);
  
  return $owl;
} // archimedes_collect()


