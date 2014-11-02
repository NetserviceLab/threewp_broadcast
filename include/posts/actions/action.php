<?php

namespace threewp_broadcast\posts\actions;

/**
	@brief		A bulk post action that can be applied to the posts overview.
	@since		2014-10-31 13:26:54
**/
class action
	extends generic
{
	/**
		@brief		IN: The action slub.
		@details	For example: delete, trash, restore, etc.
		@since		2014-11-02 21:37:35
	**/
	public $action;

	/**
		@brief		Sets the action for this post action.
		@since		2014-11-02 21:34:30
	**/
	public function set_action( $action )
	{
		$this->action = $action;
		return $this;
	}
}
