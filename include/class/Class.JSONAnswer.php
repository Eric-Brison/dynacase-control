<?php

class JSONAnswer {
  public $error;
  public $data;
  public $success;

  public function __construct($data = null, $error = null, $success = true) {
    $this->data = $data;
    $this->error = $error;
    $this->success = $success;
  }

  public function encode() {
    return json_encode($this);
  }
}

?>