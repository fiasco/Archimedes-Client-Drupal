<?php

/**
 * @file Library to talk to debconf.
 *
 * @author Josh Waihi <josh@catalyst.net.nz>
 */

class DebConf {

  /**
   * The process resource to talk to debconf over.
   */
  protected $process;

  /**
   * The read/write/error handlers for debconf-communicate.
   */
  protected $pipe = array();

  /**
   * The package the retrieved values belong too.
   */
  protected $package;

  public function __construct($package) {
    $this->package = $package;
  }

  /**
   * Open a connection to Debconf-communicate.
   */
  private function open() {
    $this->process = proc_open('debconf-communicate', array(
      array("pipe","r"),
      array("pipe","w"),
      array("pipe","a")
    ), $this->pipe);
    if (!is_resource($this->process)) {
      throw new Exception("Failed to connect to debconf-communicate.");
    }
    return $this;
  }

  /**
   * Write a command to debconf.
   */
  private function write($cmd, $value) {
    fwrite($this->pipe[0], sprintf('%s %s/%s', strtoupper($cmd), $this->package, $value));
    return $this;
  }

  /**
   * Get a value from debconf. Alias to db_get.
   */
  public function get($name) {
    return $this->open()
      ->write('GET', $name)
      ->close();
  }

  /**
   * Prompt input from user. Alias to db_input.
   */
  public function input($name) {
    return $this->open()
      ->write('INPUT', $name)
      ->close();
  }

  /**
   * Alias to db_go.
   */
  public function go() {
    return $this->open()
      ->write('GO', '')
      ->close();
  }

  /**
   * Close connection to debconf-communicate.
   */
  private function close() {
    fclose($this->pipe[0]);
    $return = stream_get_contents($this->pipe[1]);
    fclose($this->pipe[1]);
    $return = trim($return);
    proc_close($this->process);
    list($status, $value) = explode(' ', trim($return), 2);
    if ((int) $status) {
      throw new Exception($value);
    }
    return $value;
  }

  /**
   * Close connection to debconf-communicate.
   */
  public function __destruct() {
    if (is_resource($this->process)) {
      $this->close();
    }
  }
}

class DrupalDebConf extends DebConf {

  /**
   * Allow caller to provide a default argument should the value be unavailable.
   */
  public function get($name, $default = NULL) {
    try {
      $return = parent::get($name);
      if (empty($return)) {
        return $default;
      }
      return $return;
    }
    catch (Exception $e) {
      return $default;
    }
  }

}
