<?php

namespace threewp_broadcast\meta_box;

/**
	@brief		The data class that is passed around when creating the broadcast meta box.
	@since		20130928
**/
class data
{
	/**
		@brief		HTML object containing data to be displayed.
		@since		20130928
		@var		$html
	**/
	public $html;

	/**
		@brief		The Wordpress Post object for this meta box.
		@since		20130928
		@var		$post
	**/
	public $post;

	public function __construct()
	{
		$this->html = new html;
	}
}
