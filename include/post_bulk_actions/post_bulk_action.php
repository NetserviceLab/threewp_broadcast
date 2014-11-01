<?php

namespace threewp_broadcast\post_bulk_actions;

/**
	@brief		A bulk post action that can be applied to the posts overview.
	@since		2014-10-31 13:26:54
**/
class post_bulk_action
{
	/**
		@brief		A short name / verb that describes the action.
		@since		2014-10-31 14:14:15
	**/
	public $name;

	/**
		@brief		Return the javascript function that is called when the submit button is pressedn.
		@since		2014-10-31 23:00:31
	**/
	public function get_javascript_function()
	{
		return "document.title = window.broadcast.post_bulk_actions.get_ids();";
	}

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
