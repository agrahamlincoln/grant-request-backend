<?php

namespace FormApi;

/**
 *	Generic class for person
 */
class Person {
	/**
	 *	@var Person Name
	 */
	protected $name;
	public $name_name = "Full Name";
	public function getName() { return $this->name; }
	public function setName($name) { $this->name = $name; }


	public function __construct($name) {
		$this->name = $name;
	}
}
