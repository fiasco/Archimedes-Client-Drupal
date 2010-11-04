<?php
class Archimedes {
  
  public $fields = array();
  public $xml;
  
  public function toXML() {
    
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    $node = new DOMElement('node',null,'monitor:node');
    $dom->appendChild($node);
    $node->setAttribute('type','drupal');
    $node->setAttribute('datetime',date('c'));
    $author = db_result(db_query("SELECT mail FROM {users} WHERE uid = 1"));
    $node->setAttribute('author','mailto:'.$author);
    
    foreach($this->fields as $field) {
      $fNode = new DOMElement('field');
      $node->appendChild($fNode);
      $fNode = $field->createXMLNode($fNode);
    }
    
    //echo '<pre>' . htmlentities($dom->saveXML()) . '</pre>';die;
    
    return $dom->saveXML();
    
  }
  
  
  public function createField($fieldID,$type) {
    $baseclass = "ArchimedesField";
    $extendedclass = $baseclass . '_' . $type;
    $field = new $extendedclass($fieldID);
    $this->addField($fieldID,$field);
    return $field;
  }
  
  private function addField($fieldID,$field) {
    $this->fields[$fieldID] = $field;
  }
  
  public function getField($fieldID) {
    return $this->fields[$fieldID];
  }
  
}

Class ArchimedesField {
  
  private $facet = FALSE;
  private $multi = FALSE;
  private $type = '';
  private $value = array();
  public $fieldID;
  protected $namespace = '';
  
  public function addValue($value,$index=null) {
    if (isset($index))
      $this->value[$index] = $value;
    else
      $this->value[] = $value;
    return $this;
  }
  
  public function getValues() {
    if ($this->multi)
      return $this->value;
    else
      return implode(', ',$this->value);
  }
  
  public function setType($type) {
    $this->type = $type;
    return $this;
  }
  
  public function getType($type) {
    return $this->type;
  }
  
  public function invokeMulti() {
    $this->multi = TRUE;
    return $this;
  }
  
  public function revokeMulti() {
    $this->multi = FALSE;
    return $this;
  }
  
  public function invokeFacet() {
    $this->facet = TRUE;
    return $this;
  }
  
  public function revokeFacet() {
    $this->facet = FALSE;
    return $this;
  }
  
  public function createXMLNode($node) {
    $node->setAttribute('id',$this->fieldID);
    return $node;
  }
  
}

Class ArchimedesField_text extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('text');
  }
}

Class ArchimedesField_uri extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('uri');
    $this->invokeMulti();
  }
}

Class ArchimedesField_node_reference extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->namespace = 'node';
    $this->setType('node_reference');
    $this->invokeFacet();
  }
}

Class ArchimedesField_user_reference extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('user_reference');
    $this->invokeFacet();
    $this->invokeMulti();
  }
}
Class ArchimedesField_integer extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('integer');
  }
}