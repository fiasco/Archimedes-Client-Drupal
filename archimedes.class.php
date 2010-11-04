<?php
class Archimedes {
  
  public $fields = array();
  public $xml;
  
  public function toXML() {
    
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    $node = new DOMElement('node',null,'monitor:node');
    $dom->appendChild($node); 
    $node->setAttribute('type','drupal'); // remove drupal specific
    $node->setAttribute('datetime',date('c'));
    $author = db_result(db_query("SELECT mail FROM {users} WHERE uid = 1")); // remove drupal specific
    $node->setAttribute('author','mailto:' . $author);
    
    foreach($this->fields as $field) {
      $fNode = new DOMElement('field');
      $node->appendChild($fNode);
      //$fNode->setAttributeNS('monitor-plugin:node','node:xmlns',null); // ARRRRRRGGGGGGGGGGGGGG!!!
      $fNode = $field->createXMLNode($fNode);
    }
    
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
  
  public $fieldID;
  protected $facet = FALSE;
  protected $multi = FALSE;
  protected $type = '';
  protected $value = array();
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
  
  // very basic XML node creator, will usuallly be overwritten by inherited class
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      if ($this->facet) {
        $fcNode = new DOMElement('facet',$value);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value;
      }
    }
    return $fNode;
  }
  
}

Class ArchimedesField_text extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('text');
  }
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','text');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value',$value);
      $fNode->appendChild($vNode);
    }
    return $fNode;
  }
}

Class ArchimedesField_uri extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('uri');
    $this->invokeMulti();
  }
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','link');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttribute('type','uri');
      if ($this->facet) {
        var_dump($value);
        $fcNode = new DOMElement('facet',$value);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value;
      }
      
    }
    return $fNode;
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
  }
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','user_reference');
    //$fNode->setAttributeNS(null,'xms:user','monitor-plugin:user');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttribute('type','uri');
      if ($this->facet) {
        $fcNode = new DOMElement('facet',$value);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value;
      }
    }
    return $fNode;
  }
  
}

Class ArchimedesField_drupal_mod extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('drupal_mod');
    $this->invokeFacet();
  }
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','node_reference');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttribute('version',$value['version']); //fix namespace
      if ($this->facet) {
        $fcNode = new DOMElement('facet',$value['name']);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value;
      }
    }
    return $fNode;
  }
  
}

Class ArchimedesField_git_repo extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('git_repo');
    $this->invokeFacet();
    $this->invokeMulti();
  }
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','text');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttribute('type','uri'); //fix namespace
      $vNode->setAttribute('remote',$value['remote']); //fix namespace
      if ($this->facet) {
        $fcNode = new DOMElement('facet',$value['uri']);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value['uri'];
      }
    }
    return $fNode;
  }
  
}

Class ArchimedesField_integer extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('integer');
  }
}