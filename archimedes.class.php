<?php

/**
 * Class to generate a status report of an Web Application.
 */
class Archimedes {

  public $fields = array();
  public $type;
  public $author;
  public $id;

  public function __construct($type, $author, $id) {
    $this->type = $type;
    $this->author = $author;
    $this->id = $id;
  }

  public function toXML() {
    $this->validate();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    $node = new DOMElement('node',null,'monitor:node');
    $dom->appendChild($node);
    $node->setAttribute('type',$this->type);
    $node->setAttribute('id',$this->id);
    $node->setAttribute('datetime',date('c'));
    $node->setAttribute('author','mailto:' . $this->author);

    foreach($this->fields as $field) {
      $field->compile($node);
    }
    return $dom->saveXML();
  }

  /**
   * Validate the structure of the report.
   */
  protected function validate() {
    if (!isset($this->id)) {
      throw new ArchimedesClientException("No ID set.");
    }
    if (!isset($this->type)) {
      throw new ArchimedesClientException("No type defined.");
    }
    if (!isset($this->author)) {
      throw new ArchimedesClientException("No author given.");
    }
    if (!isset($this->fields['title'])) {
      throw new ArchimedesClientException("No title present.");
    }
    return TRUE;
  }

  /**
   * Send the XML report via email.
   */
  public function sendXML($email, $site_name, $key) {
    $boundary = '-----=' . md5(uniqid(rand()));
    $attachment = $this->toXML();

    $headers = 'From: ' . $site_name . ' <' . $this->author . '>' . "\r\n";
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'Mime-Version: 1.0' . "\r\n";
    $message = '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";

    $message .= "Archimedes XML update attached.\r\n";

    $message .= '--' . $boundary . "\r\n";

    if ($key != '') { // encrypt xml attachment and send environment keys
      $pubkey = openssl_pkey_get_public($key);
      openssl_seal($attachment,$sealed,$ekeys,array($pubkey));
      openssl_free_key($pubkey);

      $attachment = $sealed;

      $message .= "Content-Type: text/plain\r\n";
      $message .= "Content-Transfer-Encoding: base64\r\n\r\n";

      $message .= chunk_split(base64_encode("EKEY: " . $ekeys[0]));

      $message .= '--' . $boundary . "\r\n";

    }

    $attachment = chunk_split(base64_encode($attachment));

    $message .= 'Content-Type: application/xml; name="data.xml"' . "\r\n";
    $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $message .= 'Content-Disposition: attachment; filename="data.xml"' . "\r\n\r\n";
    $message .= $attachment . "\r\n";
    $message .= '--' . $boundary . "\r\n";


    return mail($email, 'XML Update from' . ' ' . $site_name, $message, $headers);
  }

  /**
   * Add a new field to the report.
   */
  public function createField($fieldID, $values = array()) {
    // Ensure the value is an array.
    // Strings will be type casted to arrays.
    $values = (array) $values;
    $field = new ArchimedesField($fieldID);
    $this->addField($fieldID,$field);
    foreach ($values as $value) {
      $field->addValue($value);
    }
    return $field;
  }

  protected function addField($fieldID,$field) {
    $this->fields[$fieldID] = $field;
  }

  public function getField($fieldID) {
    return $this->fields[$fieldID];
  }

}

Class ArchimedesField {

  public $fieldID;
  protected $facet = FALSE;
  protected $type = '';
  protected $values = array();
  protected $namespace = '';

  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
  }

  public function addValue($value) {
    if (!is_object($value)) {

      $value = new ANSValue($value);
    }
    $this->values[] = $value;
    return $this;
  }

  public function getValues() {
    return $this->values;
  }

  public function invokeFacet() {
    $this->facet = TRUE;
    return $this;
  }

  public function revokeFacet() {
    $this->facet = FALSE;
    return $this;
  }

  /**
   * Compile the field into a DOMElement.
   */
  function compile($node) {
    $field = new DOMElement('field');
    $node->appendChild($field);
    $field->setAttribute('id',$this->fieldID);
    foreach($this->values as $value) {
      $value->compile($field);
      if ($this->facet) {
        $value->nodeValue = '';
        $value->appendChild(new DOMElement('facet', (string) $value));
      }
    }
    return $field;
  }

  public function __toString() {
    $list = array();
    foreach($this->values as $value) {
      $list[] = (string) $value;
    }
    return implode(', ',$list);
  }
  public function toArray() {
    $list = array();
    foreach($this->values as $value) {
      $list[] = $value->toArray();
    }
    return $list;
  }
}

Class ANSValue extends DOMElement {


  // Namespace attributes.
  protected $ns_attr = array();
  protected $ns = null;

  // Normal attributes.
  protected $attr = array();

  protected $value = '';

  public $facet = FALSE;

  public function __construct($val) {
    parent::__construct('value', $val);
    $this->value = $val;
  }

  public function setAttribute($name, $value) {
    $this->attr[$name] = $value;
    return $this;
  }

  public function setAttributeNS($ns, $name, $value) {
    if (strpos($name, ':') === FALSE) {
      return $this->setAttribute($name, $value);
    }
    $this->ns_attr[$name] = $value;
    return $this;
  }

  public function getAttribute($name) {
    return $this->attr[$name];
  }

  public function getAttributeNS($name) {
    return $this->ns_attr[$name];
  }

  /**
   * Append a DOMElement to a parent node.
   */
  public function compile($field) {
    $field->appendChild($this);
    foreach ($this->attr as $key => $value) {
      parent::setAttribute($key, $value);
    }
    foreach ($this->ns_attr as $key => $value) {
      parent::setAttributeNS($this->ns, $key, $value);
    }
    return $this;
  }

  public function __toString() {
    return (string) $this->value;
  }
}

Class Archimedes_nodereference extends ANSValue {

  public function __construct($value) {
    if (!isset($this->ns))
      $this->ns = 'monitor-plugin:node';
    parent::__construct($value);
    $this->setAttributeNS($this->ns, 'node:title', $value);
  }
  public function addNode(Array $node) {
    $required_keys = array('title','type');
    $keys_diff = array_diff($required_keys, array_keys($node));
    if (!empty($keys_diff)) {
      throw new ArchimedesClientException("Missing required attributes for node reference: " . implode(', ', $keys_diff));
    }
    foreach ($node as $key => $value) {
      $this->setAttributeNS($this->ns, 'node:' . $key, $value);
    }
    return $this;
  }
}

Class Archimedes_userreference extends ANSValue {
  public function __construct($value) {
    $this->ns = 'monitor-plugin:user';
    parent::__construct($value);
  }
  public function addUser(Array $user) {
    $required_keys = array('type');
    $keys_diff = array_diff($required_keys, array_keys($user));
    if (!empty($keys_diff)) {
      throw new ArchimedesClientException("Missing required attributes for user reference: " . implode(', ', $keys_diff));
    }
    foreach ($required_keys as $key) {
      $this->setAttributeNS($this->ns, 'user:' . $key, $user[$key]);
    }
    return $this;
  }
}

Class Archimedes_drupalmod extends Archimedes_nodereference {

  public function __construct($value) {
    $this->ns = 'monitor-plugin:drupal-module';
    parent::__construct($value);
  }
  public function toArray() {
    return array('name' => (string) $this->value, 'version' => $this->getAttributeNS('node:field_mod_version'), 'desc' => $this->getAttributeNS('node:body'));
  }
}

Class Archimedes_moodlemod extends Archimedes_nodereference {

  public function __construct($value) {
    $this->ns = 'monitor-plugin:moodle-module';
    parent::__construct($value);
  }
  public function toArray() {
    return array('name' => (string) $this->value, 'version' => $this->getAttributeNS('node:field_mod_version','node:version'), 'instances' => $this->getAttributeNS('node:instances'));
  }
}

Class Archimedes_gitrepo extends ANSValue {
  public function __construct($value) {
    $this->ns = 'monitor-plugin:git';
    parent::__construct($value);
  }
  public function setRemoteName($name) {
    $this->setAttributeNS('monitor-plugin:git','git:remote', $name);
    return $this;
  }
  public function toArray() {
    return array('remote' => $this->getAttributeNS('git:remote'),'uri' => (string) $this->value);
  }
}

/**
 * Archimedes Exception Class.
 */
class ArchimedesClientException extends Exception {
}

/**
 * Wrapper function for creating a new value.
 */
function archimedes_value($value, $type = '') {
  if (empty($type)) {
    return new ANSValue($value);
  }
  $class = 'Archimedes_' . $type;
  if (!class_exists($class)) {
    throw new ArchimedesClientException("No such plugin available for $type");
  }
  return new $class($value);
}

class ArchimedesRemoteRequest {

  protected $hash;

  protected $key;

  protected $token;

  /**
   * @param field_unique_hash
   * @param public key.
   */
  public function getToken($hash, $key) {
    $this->hash = $hash;
    $this->key = $key;
    foreach (array('h', 't', 'i') as $k) {
      if (!isset($_GET[$k])) {
        return FALSE;
      }
    }
    // $_GET['i'] is the unique identifier for this site md5 hashed with the time.
    // If it doesn't match then its likely this request is forged. If the requester
    // does know the unique hash of this site then we will trust this request is
    // not a spammer.
    if ($_GET['i'] != md5($_GET['t'] . $hash)) {
      return FALSE;
    }

    // Add a random number prefix incase the time here is the same as the time passed
    // in the original request (cause then the hashes would be the same).
    return $this->token = md5(mt_rand(1000, 10000) . time());
  }

  public function validateRemoteUser($redirect = FALSE) {
    if (empty($this->token)) {
      return FALSE;
    }
    if (!$redirect) {
      $redirect = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REDIRECT_URL'];
    }
    $query = array(
      'token' => $this->token,
      'redirect' => $redirect,
      'hash' => $this->hash,
    );

    $pubkey = openssl_pkey_get_public($this->key);
    openssl_seal(serialize($query),$sealed,$ekeys,array($pubkey));
    openssl_free_key($pubkey);

    $url = 'http://' . $_GET['h'] . '/archimedes-server/verify-user?ekey=' . rawurlencode($ekeys[0]) . '&data=' . rawurlencode($sealed);

    header("Location: $url");
    die;
  }

  public function validateToken($local_token) {
    return $local_token == $_GET['token'];
  }

}
