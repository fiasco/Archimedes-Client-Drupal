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
      //$fNode = new DOMElement('field');
      //$node->appendChild($fNode);
      //$fNode = $field->createXMLNode($fNode);
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
  public function sendXML($email, $site_name) {
    $boundary = '-----=' . md5(uniqid(rand()));
    $attachment = chunk_split(base64_encode($this->toXML()));
    $headers = 'From: ' . $site_name . ' <' . $this->author . '>' . "\r\n";
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'Mime-Version: 1.0' . "\r\n";
    $message = '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";

    $message .= "Archimedes XML update attached.\r\n";

    $message .= '--' . $boundary . "\r\n";

    $message .= 'Content-Type: application/xml; name="data.xml"' . "\r\n";
    $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $message .= 'Content-Disposition: attachment; filename="data.xml"' . "\r\n\r\n";
    $message .= $attachment . "\r\n";
    $message .= '--' . $boundary . "\r\n";

    return mail($email, t('XML Update from') . ' ' . $site_name, $message, $headers);
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
      if (!is_object($value)) {
        $value = new ANSValue($value);
      }
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
  protected $multi = FALSE;
  protected $type = '';
  protected $values = array();
  protected $namespace = '';

  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
  }

  public function addValue($value) {
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
        $field_value->appendChild(new DOMElement('facet', (string) $value));
      }
    }
    return $field;
  }
}

Class ANSValue extends DOMElement {

  /**
   * Namespace attributes.
   */
  protected $ns_attr = array();

  /**
   * Normal attributes.
   */
  protected $attr = array();

  protected $value = '';

  public $facet = FALSE;

  public function __construct($value) {
    parent::__construct('value', $value);
    $this->value = $value;
  }

  public function setAttribute($name, $value) {
    $this->attr[$name] = $value;
    return $this;
  }

  public function setAttributeNS($ns, $name, $value) {
    if (strpos($name, ':') === FALSE) {
      return $this->setAttribute($name, $value);
    }
    $this->ns_attr[$ns][$name] = $value;
    return $this;
  }

  /**
   * Append a DOMElement to a parent node.
   */
  public function compile($field) {
    $field->appendChild($this);
    foreach ($this->attr as $key => $value) {
      parent::setAttribute($key, $value);
    }
    foreach ($this->ns_attr as $ns => $attr) {
      foreach ($attr as $key => $value) {
        parent::setAttributeNS($ns, $key, $value);
      }
    }
    return $this;
  }

  public function __toString() {
    return $this->value;
  }
}

Class Archimedes_nodereference extends ANSValue {

  public function __construct($value) {
    parent::__construct($value);
    $this->setAttributeNS('monitor-plugin:node', 'node:title', $value);
  }
  public function addNode(Array $node) {
    $required_keys = array('title');
    $keys_diff = array_diff($required_keys, array_keys($node));
    if (!empty($keys_diff)) {
      throw new ArchimedesClientException("Missing required attributes for node reference: " . implode(', ', $keys_diff));
    }
    foreach ($node as $key => $value) {
      $this->setAttributeNS('monitor-plugin:node', 'node:' . $key, $value);
    }
    return $this;
  }
}

Class Archimedes_userreference extends ANSValue {
  public function __construct(Array $user) {
    $required_keys = array('mailto');
    $keys_diff = array_diff($required_keys, array_keys($user));
    if (!empty($keys_diff)) {
      throw new ArchimedesClientException("Missing required attributes for user reference: " . implode(', ', $keys_diff));
    }
    foreach ($required_keys as $key) {
      $this->setAttributeNS('monitor-plugin:user', 'user:' . $key, $user[$key]);
    }
    parent::__construct($user['mailto']);
    return $this;
  }
}

Class Archimedes_drupalmod extends ANSNode_reference {
  public function setVersion($version) {
    $this->setAttributeNS('monitor-plugin:drupal-module','module:version', $version);
    return $this;
  }
}

Class Archimedes_gitrepo extends ANSValue {
  public function __construct($value) {
    parent::__construct($value);
    $this->setAttribute('type','uri');
  }
  public function setRemoteName($name) {
    $this->setAttributeNS('monitor-plugin:git','git:remote', $name);
    return $this;
  }
}

/**
 * Archimedes Exception Class.
 */
class ArchimedesClientException extends Exception {
}

/**
 * Wrapper function for createing a new value.
 */
function archimedes_value($value, $type = '') {
  if (empty($type)) {
    return new ANSValue($value);
  }
  $class = 'Archimedes_' . $type;
  if (!class_exists($type)) {
    throw new ArchimedesClientException("No such plugin available for $type");
  }
  return new $class($value);
}
