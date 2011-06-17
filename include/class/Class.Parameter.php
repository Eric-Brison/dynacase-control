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
	public $volatile ;
	public $oninstall ;
	public $onupgrade ;
	public $onedit ;
	public $values ; // Used for enum type parameters.
	public $value ;

	public function getVisibility($operation) {
		$visibility = '';
		switch( $operation ) {
			case 'install':
				$visibility = ( $this->oninstall != '' ) ? $this->oninstall : 'W';
				break;
			case 'upgrade':
				$visibility = ( $this->onupgrade != '' ) ? $this->onpugrade : 'H';
				if( $this->needed == 'Y' && $this->value == '' ) {
					$visibility = 'W';
				}
				break;
			case 'parameter':
				$visibility = ( $this->onedit != '' ) ? $this->onedit : 'R';
				break;
		}
		return $visibility;
	}
}

?>