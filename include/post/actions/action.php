<?php

namespace threewp_broadcast\post\actions;

/**
	@brief		A bulk post action that can be applied to the posts overview.
	@since		2014-10-31 13:26:54
**/
class action
{
	/**
		@brief		A short name / verb that describes the action.
		@since		2014-10-31 14:14:15
	**/
	public $name;

	/**
		@brief		Get the action name.
		@see		$name
		@since		2014-10-31 14:14:31
	**/
	public function get_name()
	{
		return $this->name;
	}

	/**
		@brief		Set the action name.
		@see		$name
		@since		2014-10-31 14:14:34
	**/
	public function set_name( $name )
	{
		$this->name = $name;
	}
}
