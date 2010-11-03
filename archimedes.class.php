<?php
class Archimedes {
  
  public $fields;
  
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
    $this->addField($field);
    return $field;
  }
  
}

Class ArchimedesField {
  
  private $faceted = FALSE;
  private $multi = FALSE;
  private $type = '';
  private $value;
  public $fieldID;
  protected $namespace = '';
  
  public function setValue($value) {
    $this->value = $value;
    return $this;
  }
  
  public function getValue($value) {
    return $this-value;
  }
  
  public function invokeFaceting() {
    $this->faceted = TRUE;
    return $this;
  }
  
  public function revokeFaceting() {
    $this->faceted = FALSE;
    return $this;
  }
  
  public function addValue($value) {
    $node = $this->addChild("value", $value);
    return $this;
  } 
  
}

/* $field = $owl->createfield('field_drupal_mod', 'node_reference');
$field->addNamespace('monitor-plugin:drupal-module', 'module');
foreach ($modules as $module) {
  $value = $field->addValue(TRUE);
  $value->addAttribute('type', 'drupal_module')
        ->addAttribute('title', $module->title)
        ->addAttribute('field_drumod', $module->name)
        ->AddAttribute('version', $module->version, 'module');
}
*/

Class ArchimedesField_text extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->type = 'text';
  }
}

Class ArchimedesField_node_reference extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->namespace = 'node';
    $this->type = 'node_reference';
    $this->value = $value;
    //$xml->addAttribute("xmlns:node","monitor:node");
  }
}