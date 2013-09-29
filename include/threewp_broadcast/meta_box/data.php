<?php

namespace threewp_broadcast\meta_box;

/**
	@brief		The data class that is passed around when creating the broadcast meta box.
	@since		20130928
**/
class data
{
	/**
		@brief		Array of HTML data.
		@details	To ease manipulation, each part of the meta box should have a named key.

		The html for the link checkbox should, for example, be in a key called input_link.
		@since		20130928
		@var		$html
	**/
	public $html;

	/**
		@brief
		@since		20130928
		@var		$
	**/
	public $post;

	public function __construct()
	{
		$this->html = new html;
	}
}
