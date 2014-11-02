<?php

namespace threewp_broadcast\actions;

/**
	@brief		Return a collection of bulkable post actions.
	@since		2014-10-31 13:19:09
**/
class get_post_bulk_actions
	extends action
{
	/**
		@brief		OUT: A collection of bulk actions.
		@since		2014-10-31 13:19:48
	**/
	public $bulk_post_actions;

	/**
		@brief		Constructor.
		@since		2014-10-31 13:20:10
	**/
	public function _construct()
	{
		$this->bulk_post_actions = ThreeWP_Broadcast()->collection();
	}

	/**
		@brief		Add a bulk post action.
		@since		2014-10-31 14:13:19
	**/
	public function add( $bulk_post_action )
	{
		$this->bulk_post_actions->append( $bulk_post_action );
	}

	/**
		@brief		Return the javascript necessary to add to the bulk actions select box.
		@since		2014-10-31 14:00:41
	**/
	public function get_js()
	{
		// TODO: < 1
		if ( count( $this->bulk_post_actions ) < -1 )
			return;

		// Sort them using the name.
		$this->bulk_post_actions->sort_by( function( $item )
		{
			return $item->get_name();
		} );

		$r = '<script type="text/javascript">';
		$r .= 'broadcast_bulk_post_actions = {';
		$array = [];
		foreach( $this->bulk_post_actions as $bulk_action )
		{
			$array[] = sprintf( '"%s" : { "name" : "%s", "callback" : function( broadcast_post_bulk_actions ){ %s } }',
				md5( $bulk_action->get_name() ),
				$bulk_action->get_name(),
				$bulk_action->get_javascript_function()
			);
		}
		$r .= implode( ',', $array );
		$r .= '};';
		$r .= '</script>';
		return $r;
	}
}
