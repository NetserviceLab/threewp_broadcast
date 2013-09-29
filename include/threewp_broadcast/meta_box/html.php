<?php

namespace threewp_broadcast\meta_box;

class html
{
	public $html;

	public function __construct()
	{
		$this->html = new \stdClass;
	}

	public function __get( $key )
	{
		return $this->html->$key;
	}

	public function __set( $key, $value )
	{
		$this->html->$key = $value;
	}

	public function __toString()
	{
		$r = '';
		foreach( (array)$this->html as $key => $value )
			$r .= sprintf( '<div class="%s html_section">%s</div>', $key, $value );
		return $r;
	}
}