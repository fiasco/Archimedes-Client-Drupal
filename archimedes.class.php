<?php
class Archimedes {
  
  public $fields = array();
  
  public function __construct() {
    /*$root = '<?xml version="1.0" encoding="UTF-8" ?><node/>';*/
    //parent::__construct($root);
    //$this->addAttribute("xmlns","monitor:node");
    //$this->addAttribute("type","drupal");
    //$this->addAttribute("datetime",date('c'));
    
    //$author = db_result(db_query("SELECT mail FROM {users} WHERE uid = 1"));
  }
  
  
  public function createField($fieldID,$type) {
    $baseclass = "ArchimedesField";
    $extendedclass = $baseclass . '_' . $type;
    $field = new $extendedclass($fieldID);
    $this->addField($fieldID,$field); // fieldId is now saved twice. Better fix this.
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