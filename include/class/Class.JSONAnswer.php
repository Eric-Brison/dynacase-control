<?php

/**
 * JSONAnswer Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

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