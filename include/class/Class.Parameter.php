<?php

/**
 * Parameter Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

class Parameter
{
	
	public $name ;
	public $label ;
	public $default ;
	public $type ;
	public $needed ;
	
	public $values ; // Used for enum type parameters.
	
	public $value ;
	
}

?>