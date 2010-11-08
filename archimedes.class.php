<?php
class Archimedes {
  
  public $fields = array();
  public $type;
  public $author;
  
  public function __construct($type, $author) {
    $this->type = $type;
    $this->author = $author;
  }
  
  
  public function toXML() {
    
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    $node = new DOMElement('node',null,'monitor:node');
    $dom->appendChild($node); 
    if (isset($this->type))
      $node->setAttribute('type',$this->type);
    $node->setAttribute('datetime',date('c'));
    if (isset($this->author))
      $node->setAttribute('author','mailto:' . $this->author);
    
    foreach($this->fields as $field) {
      $fNode = new DOMElement('field');
      $node->appendChild($fNode);
      $fNode = $field->createXMLNode($fNode);
    }
    
    return $dom->saveXML();
    
  }
  
  public function sendXML($email,$site_name) {
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
    
    return mail($email,t('XML Update from') . ' ' . $site_name,$message,$headers);
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
  
  public function addValue($value) {

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
  
  // very basic XML node creator, will usually be overwritten by inherited class
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
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','node_reference');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttributeNS('monitor-plugin:node','node:type','host');
      $vNode->setAttributeNS('monitor-plugin:node','node:title',$value);
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

Class ArchimedesField_user_reference extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('user_reference');
    $this->invokeFacet();
  }
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','user_reference');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttribute('type','uri');
      $vNode->setAttributeNS('monitor-plugin:user','user:type','mail');
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
      $vNode->setAttributeNS('monitor-plugin:node','node:type','drupal_module');
      $vNode->setAttributeNS('monitor-plugin:node','node:title',$value['title']);
      $vNode->setAttributeNS('monitor-plugin:node','node:field_drupal_mod',$value['name']);
      $vNode->setAttributeNS('monitor-plugin:node','node:body',$value['desc']);
      $vNode->setAttributeNS('monitor-plugin:drupal-module','module:version',$value['version']);
      if ($this->facet) {
        $fcNode = new DOMElement('facet',$value['title']);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value['title'];
      }
    }
    return $fNode;
  }
  
}

Class ArchimedesField_drupal_theme extends ArchimedesField {
  public function __construct($fieldID) {
    $this->fieldID = $fieldID;
    $this->setType('drupal_theme');
    $this->invokeFacet();
  }
  
  public function createXMLNode($fNode) {
    $fNode->setAttribute('id',$this->fieldID);
    $fNode->setAttribute('type','node_reference');
    foreach($this->value as $value) {
      $vNode = new DOMElement('value');
      $fNode->appendChild($vNode);
      $vNode->setAttributeNS('monitor-plugin:node','node:type','drupal_theme');
      $vNode->setAttributeNS('monitor-plugin:node','node:title',$value['title']);
      $vNode->setAttributeNS('monitor-plugin:node','node:field_drupal_mod',$value['name']);
      $vNode->setAttributeNS('monitor-plugin:node','node:body',$value['desc']);
      $vNode->setAttributeNS('monitor-plugin:drupal-module','module:version',$value['version']);
      if ($this->facet) {
        $fcNode = new DOMElement('facet',$value['title']);
        $vNode->appendChild($fcNode);
      } else {
        $vNode->nodeValue = $value['title'];
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
      $vNode->setAttribute('type','uri');
      $vNode->setAttributeNS('monitor-plugin:git','git:remote',$value['remote']);
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