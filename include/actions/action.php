<?php

namespace threewp_broadcast\actions;

class action
	extends \plainview\sdk\wordpress\actions\action
{
	public function execute()
	{
		$action_name = $this->get_name();
		do_action( $action_name, $this );
		return $this;
	}

	public function get_prefix()
	{
		return 'threewp_broadcast_';
	}

	public function finish( $finished = true )
	{
		return $this->set_boolean( 'finished', $finished );
	}
}
