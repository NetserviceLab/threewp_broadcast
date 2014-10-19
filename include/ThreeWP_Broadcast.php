<?php

namespace threewp_broadcast;

use \Exception;
use \plainview\sdk\collections\collection;
use \threewp_broadcast\broadcast_data\blog;
use \plainview\sdk\html\div;

class ThreeWP_Broadcast
	extends \plainview\sdk\wordpress\base
{
	use \plainview\sdk\wordpress\traits\debug;

	use traits\admin_menu;
	use traits\broadcast_data;
	use traits\meta_boxes;
	use traits\post_methods;
	use traits\terms_and_taxonomies;

	/**
		@brief		Broadcasting stack.
		@details

		An array of broadcasting_data objects, the latest being at the end.

		@since		20131120
	**/
	private $broadcasting = [];

	/**
		@brief	Public property used during the broadcast process.
		@see	include/Broadcasting_Data.php
		@since	20130530
		@var	$broadcasting_data
	**/
	public $broadcasting_data = null;

	/**
		@brief		Display Broadcast completely, including menus and post overview columns.
		@since		20131015
		@var		$display_broadcast
	**/
	public $display_broadcast = true;

	/**
		@brief		Display the Broadcast columns in the post overview.
		@details	Disabling this will prevent the user from unlinking posts.
		@since		20131015
		@var		$display_broadcast_columns
	**/
	public $display_broadcast_columns = true;

	/**
		@brief		Display the Broadcast menu
		@since		20131015
		@var		$display_broadcast_menu
	**/
	public $display_broadcast_menu = true;

	/**
		@brief		Add the meta box in the post editor?
		@details	Standard is null, which means the plugin(s) should work it out first.
		@since		20131015
		@var		$display_broadcast_meta_box
	**/
	public $display_broadcast_meta_box = true;

	/**
		@brief	Display information in the menu about the premium pack?
		@see	threewp_broadcast_premium_pack_info()
		@since	20131004
		@var	$display_premium_pack_info
	**/
	public $display_premium_pack_info = true;

	/**
		@brief		Caches permalinks looked up during this page view.
		@see		post_link()
		@since		20130923
	**/
	public $permalink_cache;

	public $plugin_version = THREEWP_BROADCAST_VERSION;

	// 20140501 when debug trait is moved to SDK.
	protected $sdk_version_required = 20130505;		// add_action / add_filter

	public function _construct()
	{
		if ( ! $this->is_network )
			wp_die( $this->_( 'Broadcast requires a Wordpress network to function.' ) );

		$this->add_action( 'add_meta_boxes' );
		$this->add_action( 'admin_menu' );
		$this->add_action( 'admin_print_styles' );

		if ( $this->get_site_option( 'override_child_permalinks' ) )
		{
			$this->add_filter( 'post_link', 10, 3 );
			$this->add_filter( 'post_type_link', 'post_link', 10, 3 );
		}

		$this->add_filter( 'threewp_broadcast_add_meta_box' );
		$this->add_filter( 'threewp_broadcast_admin_menu', 'add_post_row_actions_and_hooks', 100 );
		$this->add_filter( 'threewp_broadcast_broadcast_post' );
		$this->add_action( 'threewp_broadcast_get_user_writable_blogs', 11 );		// Allow other plugins to do this first.
		$this->add_filter( 'threewp_broadcast_get_post_types', 9 );					// Add our custom post types to the array of broadcastable post types.
		$this->add_action( 'threewp_broadcast_manage_posts_custom_column', 9 );		// Just before the standard 10.
		$this->add_action( 'threewp_broadcast_maybe_clear_post', 11 );
		$this->add_action( 'threewp_broadcast_menu', 9 );
		$this->add_action( 'threewp_broadcast_menu', 'threewp_broadcast_menu_final', 100 );
		$this->add_action( 'threewp_broadcast_prepare_broadcasting_data' );
		$this->add_filter( 'threewp_broadcast_prepare_meta_box', 9 );
		$this->add_filter( 'threewp_broadcast_prepare_meta_box', 'threewp_broadcast_prepared_meta_box', 100 );
		$this->add_action( 'threewp_broadcast_wp_insert_term', 9 );
		$this->add_action( 'threewp_broadcast_wp_update_term', 9 );

		if ( $this->get_site_option( 'canonical_url' ) )
			$this->add_action( 'wp_head', 1 );

		$this->permalink_cache = (object)[];
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Activate / Deactivate
	// --------------------------------------------------------------------------------------------

	public function activate()
	{
		if ( !$this->is_network )
			wp_die("This plugin requires a Wordpress Network installation.");

		$db_ver = $this->get_site_option( 'database_version', 0 );

		if ( $db_ver < 1 )
		{
			// Remove old options
			$this->delete_site_option( 'requirewhenbroadcasting' );

			// Removed 1.5
			$this->delete_site_option( 'activity_monitor_broadcasts' );
			$this->delete_site_option( 'activity_monitor_group_changes' );
			$this->delete_site_option( 'activity_monitor_unlinks' );

			$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."_3wp_broadcast` (
			  `user_id` int(11) NOT NULL COMMENT 'User ID',
			  `data` text NOT NULL COMMENT 'User''s data',
			  PRIMARY KEY (`user_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Contains the group settings for all the users';
			");

			$this->query("CREATE TABLE IF NOT EXISTS `". $this->broadcast_data_table() . "` (
			  `blog_id` int(11) NOT NULL COMMENT 'Blog ID',
			  `post_id` int(11) NOT NULL COMMENT 'Post ID',
			  `data` text NOT NULL COMMENT 'Serialized BroadcastData',
			  KEY `blog_id` (`blog_id`,`post_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;
			");

			// Cats and tags replaced by taxonomy support. Version 1.5
			$this->delete_site_option( 'role_categories' );
			$this->delete_site_option( 'role_categories_create' );
			$this->delete_site_option( 'role_tags' );
			$this->delete_site_option( 'role_tags_create' );
			$db_ver = 1;
		}

		if ( $db_ver < 2 )
		{
			// Convert the array site options to strings.
			foreach( [ 'custom_field_exceptions', 'post_types' ] as $key )
			{
				$value = $this->get_site_option( $key, '' );
				if ( is_array( $value ) )
				{
					$value = array_filter( $value );
					$value = implode( ' ', $value );
				}
				$this->update_site_option( $key, $value );
			}
			$db_ver = 2;
		}

		if ( $db_ver < 3 )
		{
			$this->delete_site_option( 'always_use_required_list' );
			$this->delete_site_option( 'blacklist' );
			$this->delete_site_option( 'requiredlist' );
			$this->delete_site_option( 'role_taxonomies_create' );
			$this->delete_site_option( 'role_groups' );
			$db_ver = 3;
		}

		if ( $db_ver < 4 )
		{
			$exceptions = $this->get_site_option( 'custom_field_exceptions', '' );
			$this->delete_site_option( 'custom_field_exceptions' );
			$whitelist = $this->get_site_option( 'custom_field_whitelist', $exceptions );
			$db_ver = 4;
		}

		if ( $db_ver < 5 )
		{
			$this->create_broadcast_data_id_column();
			$db_ver = 5;
		}

		$this->update_site_option( 'database_version', $db_ver );
	}

	public function uninstall()
	{
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."_3wp_broadcast`");
		$query = sprintf( "DROP TABLE `%s`", $this->broadcast_data_table() );
		$this->query( $query );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Callbacks
	// --------------------------------------------------------------------------------------------

	public function post_link( $link, $post )
	{
		// Don't overwrite the permalink if we're in the editing window.
		// This allows the user to change the permalink.
		if ( $_SERVER[ 'SCRIPT_NAME' ] == '/wp-admin/post.php' )
			return $link;

		if ( isset( $this->_is_getting_permalink ) )
			return $link;

		$this->_is_getting_permalink = true;

		$blog_id = get_current_blog_id();

		// Have we already checked this post ID for a link?
		$key = 'b' . $blog_id . '_p' . $post->ID;
		if ( property_exists( $this->permalink_cache, $key ) )
		{
			unset( $this->_is_getting_permalink );
			return $this->permalink_cache->$key;
		}

		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );

		$linked_parent = $broadcast_data->get_linked_parent();

		if ( $linked_parent === false)
		{
			$this->permalink_cache->$key = $link;
			unset( $this->_is_getting_permalink );
			return $link;
		}

		switch_to_blog( $linked_parent[ 'blog_id' ] );
		$post = get_post( $linked_parent[ 'post_id' ] );
		$permalink = get_permalink( $post );
		restore_current_blog();

		$this->permalink_cache->$key = $permalink;

		unset( $this->_is_getting_permalink );
		return $permalink;
	}

	public function save_post( $post_id )
	{
		$this->debug( 'Running save_post hook.' );

		// We must be on the source blog.
		if ( ms_is_switched() )
		{
			$this->debug( 'Not on parent blog.' );
			return;
		}

		// Loop check.
		if ( $this->is_broadcasting() )
		{
			$this->debug( 'Already broadcasting.' );
			return;
		}

		// We must handle this post type.
		$post = get_post( $post_id );
		$action = new actions\get_post_types;
		$action->execute();
		if ( ! in_array( $post->post_type, $action->post_types ) )
		{
			$this->debug( 'We do not care about the %s post type.', $post->post_type );
			return;
		}

		// No post?
		if ( count( $_POST ) < 1 )
		{
			$this->debug( 'The POST is empty.' );
			return;
		}

		// Is this post a child?
		$broadcast_data = $this->get_post_broadcast_data( get_current_blog_id(), $post_id );
		if ( $broadcast_data->get_linked_parent() !== false )
			return;

		// No permission.
		if ( ! $this->role_at_least( $this->get_site_option( 'role_broadcast' ) ) )
		{
			$this->debug( 'User does not have permission to use Broadcast.' );
			return;
		}

		// Save the user's last settings.
		if ( isset( $_POST[ 'broadcast' ] ) )
			$this->save_last_used_settings( $this->user_id(), $_POST[ 'broadcast' ] );

		$this->debug( 'We are currently on blog %s (%s).', get_bloginfo( 'blogname' ), get_current_blog_id() );

		$meta_box_data = $this->create_meta_box( $post );

		$this->debug( 'Preparing the meta box.' );

		// Allow plugins to modify the meta box with their own info.
		$action = new actions\prepare_meta_box;
		$action->meta_box_data = $meta_box_data;
		$action->execute();

		$this->debug( 'Prepared.' );

		// Post the form.
		if ( ! $meta_box_data->form->has_posted )
		{
			$meta_box_data->form->post();
			$meta_box_data->form->use_post_values();
		}

		$broadcasting_data = new broadcasting_data( [
			'_POST' => $_POST,
			'meta_box_data' => $meta_box_data,
			'parent_blog_id' => get_current_blog_id(),
			'parent_post_id' => $post_id,
			'post' => $post,
			'upload_dir' => wp_upload_dir(),
		] );

		$this->debug( 'Preparing the broadcasting data.' );

		$action = new actions\prepare_broadcasting_data;
		$action->broadcasting_data = $broadcasting_data;
		$action->execute();

		$this->debug( 'Prepared.' );

		if ( $broadcasting_data->has_blogs() )
			$this->filters( 'threewp_broadcast_broadcast_post', $broadcasting_data );
		else
		{
			$this->debug( 'No blogs are selected. Not broadcasting anything.' );
		}
	}

	/**
		@brief		Return a collection of blogs that the user is allowed to write to.
		@since		20131003
	**/
	public function threewp_broadcast_get_user_writable_blogs( $action )
	{
		if ( $action->is_finished() )
			return;

		$blogs = get_blogs_of_user( $action->user_id, true );
		foreach( $blogs as $blog)
		{
			$blog = blog::make( $blog );
			$blog->id = $blog->userblog_id;
			if ( ! $this->is_blog_user_writable( $action->user_id, $blog ) )
				continue;
			$action->blogs->set( $blog->id, $blog );
		}

		$action->blogs->sort_logically();
		$action->finish();
	}

	/**
		@brief		Convert the post_type site option to an array in the action.
		@since		2014-02-22 10:33:57
	**/
	public function threewp_broadcast_get_post_types( $action )
	{
		$post_types = $this->get_site_option( 'post_types' );
		$post_types = explode( ' ', $post_types );
		foreach( $post_types as $post_type )
			$action->post_types[ $post_type ] = $post_type;
	}

	/**
		@brief		Decide what to do with the POST.
		@since		2014-03-23 23:08:31
	**/
	public function threewp_broadcast_maybe_clear_post( $action )
	{
		if ( $action->is_finished() )
		{
			$this->debug( 'Not maybe clearing the POST.' );
			return;
		}

		$clear_post = $this->get_site_option( 'clear_post', true );
		if ( $clear_post )
		{

			$this->debug( 'Clearing the POST.' );
			$action->post = [];
		}
		else
			$this->debug( 'Not clearing the POST.' );
	}

	/**
		@brief		Fill the broadcasting_data object with information.

		@details

		The difference between the calculations in this filter and the actual broadcast_post method is that this filter

		1) does access checks
		2) tells broadcast_post() WHAT to broadcast, not how.

		@since		20131004
	**/
	public function threewp_broadcast_prepare_broadcasting_data( $action )
	{
		$bcd = $action->broadcasting_data;
		$allowed_post_status = [ 'pending', 'private', 'publish' ];

		if ( $bcd->post->post_status == 'draft' && $this->role_at_least( $this->get_site_option( 'role_broadcast_as_draft' ) ) )
			$allowed_post_status[] = 'draft';

		if ( $bcd->post->post_status == 'future' && $this->role_at_least( $this->get_site_option( 'role_broadcast_scheduled_posts' ) ) )
			$allowed_post_status[] = 'future';

		if ( ! in_array( $bcd->post->post_status, $allowed_post_status ) )
			return;

		$form = $bcd->meta_box_data->form;
		if ( $form->is_posting() && ! $form->has_posted )
				$form->post();

		// Collect the list of blogs from the meta box.
		$blogs_input = $form->input( 'blogs' );
		foreach( $blogs_input->inputs() as $blog_input )
			if ( $blog_input->is_checked() )
			{
				$blog_id = $blog_input->get_name();
				$blog_id = str_replace( 'blogs_', '', $blog_id );
				$blog = new broadcast_data\blog;
				$blog->id = $blog_id;
				$bcd->broadcast_to( $blog );
			}

		// Remove the current blog
		$bcd->blogs->forget( $bcd->parent_blog_id );

		$bcd->post_type_object = get_post_type_object( $bcd->post->post_type );
		$bcd->post_type_supports_thumbnails = post_type_supports( $bcd->post->post_type, 'thumbnail' );
		//$bcd->post_type_supports_custom_fields = post_type_supports( $bcd->post->post_type, 'custom-fields' );
		$bcd->post_type_supports_custom_fields = true;
		$bcd->post_type_is_hierarchical = $bcd->post_type_object->hierarchical;

		$bcd->custom_fields = $form->checkbox( 'custom_fields' )->get_post_value()
			&& ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_custom_fields' ) ) );
		if ( $bcd->custom_fields )
			$bcd->custom_fields = (object)[];

		$bcd->link = $form->checkbox( 'link' )->get_post_value()
			&& ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_link' ) ) );

		$bcd->taxonomies = $form->checkbox( 'taxonomies' )->get_post_value()
			&& ( is_super_admin() || $this->role_at_least( $this->get_site_option( 'role_taxonomies' ) ) );

		// Is this post sticky? This info is hidden in a blog option.
		$stickies = get_option( 'sticky_posts' );
		$bcd->post_is_sticky = in_array( $bcd->post->ID, $stickies );
	}

	/**
		@brief		Broadcasts a post.
		@param		broadcasting_data		$broadcasting_data		Object containing broadcasting instructions.
		@since		20130927
	**/
	public function threewp_broadcast_broadcast_post( $broadcasting_data )
	{
		if ( ! is_a( $broadcasting_data, get_class( new broadcasting_data ) ) )
			return $broadcasting_data;
		return $this->broadcast_post( $broadcasting_data );
	}

	/**
		@brief		Use the correct canonical link.
	**/
	public function wp_head()
	{
		// Only override the canonical if we're looking at a single post.
		if ( ! is_single() )
			return;

		global $post;
		global $blog_id;

		// Find the parent, if any.
		$broadcast_data = $this->get_post_broadcast_data( $blog_id, $post->ID );
		$linked_parent = $broadcast_data->get_linked_parent();
		if ( $linked_parent === false)
			return;

		// Post has a parent. Get the parent's permalink.
		switch_to_blog( $linked_parent[ 'blog_id' ] );
		$url = get_permalink( $linked_parent[ 'post_id' ] );
		restore_current_blog();

		echo sprintf( '<link rel="canonical" href="%s" />', $url );
		echo "\n";

		// Prevent Wordpress from outputting its own canonical.
		remove_action( 'wp_head', 'rel_canonical' );

		// Remove Canonical Link Added By Yoast WordPress SEO Plugin
		$this->add_filter( 'wpseo_canonical', 'wp_head_remove_wordpress_seo_canonical' );;
	}

	/**
		@brief		Remove Wordpress SEO canonical link so that it doesn't conflict with the parent link.
		@since		2014-01-16 00:36:15
	**/

	public function wp_head_remove_wordpress_seo_canonical()
	{
		// Tip seen here: http://wordpress.org/support/topic/plugin-wordpress-seo-by-yoast-remove-canonical-tags-in-header?replies=10
		return false;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc functions
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Broadcast a post.
		@details	The BC data parameter contains all necessary information about what is being broadcasted, to which blogs, options, etc.
		@param		broadcasting_data		$broadcasting_data		The broadcasting data object.
		@since		20130603
	**/
	public function broadcast_post( $broadcasting_data )
	{
		$bcd = $broadcasting_data;

		$this->debug( 'Broadcasting the post %s <pre>%s</pre>', $bcd->post->ID, $bcd->post );

		$this->debug( 'The POST was <pre>%s</pre>', $bcd->_POST );

		// For nested broadcasts. Just in case.
		switch_to_blog( $bcd->parent_blog_id );

		if ( $bcd->link )
		{
			$this->debug( 'Linking is enabled.' );

			if ( $broadcasting_data->broadcast_data === null )
			{
				// Prepare the broadcast data for linked children.
				$bcd->broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID );

				// Does this post type have parent support, so that we can link to a parent?
				if ( $bcd->post_type_is_hierarchical && $bcd->post->post_parent > 0)
				{
					$parent_broadcast_data = $this->get_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->post_parent );
				}
				$this->debug( 'Post type is hierarchical: %s', $this->yes_no( $bcd->post_type_is_hierarchical ) );
			}
		}
		else
			$this->debug( 'Linking is disabled.' );

		if ( $bcd->taxonomies )
		{
			$this->debug( 'Will broadcast taxonomies.' );
			$this->collect_post_type_taxonomies( $bcd );
		}
		else
			$this->debug( 'Will not broadcast taxonomies.' );

		$bcd->attachment_data = [];
		$attached_files = get_children( 'post_parent='.$bcd->post->ID.'&post_type=attachment' );
		$has_attached_files = count( $attached_files) > 0;
		if ( $has_attached_files )
		{
			$this->debug( 'Has %s attachments.', count( $attached_files ) );
			foreach( $attached_files as $attached_file )
			{
				try
				{
					$data = attachment_data::from_attachment_id( $attached_file, $bcd->upload_dir );
					$data->set_attached_to_parent( $bcd->post );
					$bcd->attachment_data[ $attached_file->ID ] = $data;
					$this->debug( 'Attachment %s found.', $attached_file->ID );
				}
				catch( Exception $e )
				{
					$this->debug( 'Exception adding attachment: ' . $e->getMessage() );
				}
			}
		}

		if ( $bcd->custom_fields !== false )
		{
			if ( ! is_object( $bcd->custom_fields ) )
				$bcd->custom_fields = (object)[];

			$this->debug( 'Custom fields: Will broadcast custom fields.' );
			$bcd->post_custom_fields = get_post_custom( $bcd->post->ID );

			// Save the original custom fields for future use.
			$bcd->custom_fields->original = $bcd->post_custom_fields;
			$bcd->has_thumbnail = isset( $bcd->post_custom_fields[ '_thumbnail_id' ] );

			// Check that the thumbnail ID is > 0
			if ( $bcd->has_thumbnail )
			{
				$thumbnail_id = reset( $bcd->post_custom_fields[ '_thumbnail_id' ] );
				$thumbnail_post = get_post( $thumbnail_id );
				$bcd->has_thumbnail = $bcd->has_thumbnail && ( $thumbnail_post !== null );
			}

			if ( $bcd->has_thumbnail )
			{
				$this->debug( 'Custom fields: Post has a thumbnail (featured image).' );
				$bcd->thumbnail_id = $bcd->post_custom_fields[ '_thumbnail_id' ][0];
				$bcd->thumbnail = get_post( $bcd->thumbnail_id );
				unset( $bcd->post_custom_fields[ '_thumbnail_id' ] ); // There is a new thumbnail id for each blog.
				try
				{
					$data = attachment_data::from_attachment_id( $bcd->thumbnail, $bcd->upload_dir);
					$data->set_attached_to_parent( $bcd->post );
					$bcd->attachment_data[ 'thumbnail' ] = $data;
					// Now that we know what the attachment id the thumbnail has, we must remove it from the attached files to avoid duplicates.
					unset( $bcd->attachment_data[ $bcd->thumbnail_id ] );
				}
				catch( Exception $e )
				{
					$this->debug( 'Exception adding attachment: ' . $e->getMessage() );
				}
			}
			else
				$this->debug( 'Custom fields: Post does not have a thumbnail (featured image).' );

			$bcd->custom_fields->blacklist = array_filter( explode( ' ', $this->get_site_option( 'custom_field_blacklist' ) ) );
			$bcd->custom_fields->protectlist = array_filter( explode( ' ', $this->get_site_option( 'custom_field_protectlist' ) ) );
			$bcd->custom_fields->whitelist = array_filter( explode( ' ', $this->get_site_option( 'custom_field_whitelist' ) ) );

			foreach( $bcd->post_custom_fields as $custom_field => $ignore )
			{
				// If the field does not start with an underscore, it is automatically valid.
				if ( strpos( $custom_field, '_' ) !== 0 )
					continue;

				$keep = true;

				// Has the user requested that all internal fields be broadcasted?
				$broadcast_internal_custom_fields = $this->get_site_option( 'broadcast_internal_custom_fields' );
				if ( $broadcast_internal_custom_fields )
				{
					foreach( $bcd->custom_fields->blacklist as $exception)
						if ( strpos( $custom_field, $exception) !== false )
						{
							$keep = false;
							break;
						}
				}
				else
				{
					$keep = false;
					foreach( $bcd->custom_fields->whitelist as $exception)
						if ( strpos( $custom_field, $exception) !== false )
						{
							$keep = true;
							break;
						}

				}

				if ( ! $keep )
				{
					$this->debug( 'Custom fields: Deleting custom field %s', $custom_field );
					unset( $bcd->post_custom_fields[ $custom_field ] );
				}
				else
					$this->debug( 'Custom fields: Keeping custom field %s', $custom_field );
			}
		}
		else
			$this->debug( 'Will not broadcast custom fields.' );

		// Handle any galleries.
		$bcd->galleries = new collection;
		$matches = $this->find_shortcodes( $bcd->post->post_content, 'gallery' );
		$this->debug( 'Found %s gallery shortcodes.', count( $matches[ 2 ] ) );

		// [2] contains only the shortcode command / key. No options.
		foreach( $matches[ 2 ] as $index => $key )
		{
			// We've found a gallery!
			$bcd->has_galleries = true;
			$gallery = (object)[];
			$bcd->galleries->push( $gallery );

			// Complete matches are in 0.
			$gallery->old_shortcode = $matches[ 0 ][ $index ];

			// Extract the IDs
			$gallery->ids_string = preg_replace( '/.*ids=\"([0-9,]*)".*/', '\1', $gallery->old_shortcode );
			$this->debug( 'Gallery %s has IDs: %s', $gallery->old_shortcode, $gallery->ids_string );
			$gallery->ids_array = explode( ',', $gallery->ids_string );
			foreach( $gallery->ids_array as $id )
			{
				$this->debug( 'Gallery has attachment %s.', $id );
				try
				{
					$data = attachment_data::from_attachment_id( $id, $bcd->upload_dir );
					$data->set_attached_to_parent( $bcd->post );
					$bcd->attachment_data[ $id ] = $data;
				}
				catch( Exception $e )
				{
					$this->debug( 'Exception adding attachment: ' . $e->getMessage() );
				}
			}
		}

		// To prevent recursion
		array_push( $this->broadcasting, $bcd );

		// POST is no longer needed. Empty it so that other plugins don't use it.
		$action = new actions\maybe_clear_post;
		$action->post = $_POST;
		$action->execute();
		$_POST = $action->post;

		$action = new actions\broadcasting_started;
		$action->broadcasting_data = $bcd;
		$action->execute();

		$this->debug( 'The attachment data is: %s', $bcd->attachment_data );

		$this->debug( 'Beginning child broadcast loop.' );

		foreach( $bcd->blogs as $child_blog )
		{
			$child_blog->switch_to();
			$bcd->current_child_blog_id = $child_blog->get_id();
			$this->debug( 'Switched to blog %s (%s)', get_bloginfo( 'name' ), $bcd->current_child_blog_id );

			// Create new post data from the original stuff.
			$bcd->new_post = (array) $bcd->post;

			foreach( [ 'comment_count', 'guid', 'ID', 'post_parent' ] as $key )
				unset( $bcd->new_post[ $key ] );

			$action = new actions\broadcasting_after_switch_to_blog;
			$action->broadcasting_data = $bcd;
			$action->execute();

			if ( ! $action->broadcast_here )
			{
				$this->debug( 'Skipping this blog.' );
				$child_blog->switch_from();
				continue;
			}

			// Post parent
			if ( $bcd->link && isset( $parent_broadcast_data) )
				if ( $parent_broadcast_data->has_linked_child_on_this_blog() )
				{
					$linked_parent = $parent_broadcast_data->get_linked_child_on_this_blog();
					$bcd->new_post[ 'post_parent' ] = $linked_parent;
				}

			// Insert new? Or update? Depends on whether the parent post was linked before or is newly linked?
			$need_to_insert_post = true;
			if ( $bcd->broadcast_data !== null )
				if ( $bcd->broadcast_data->has_linked_child_on_this_blog() )
				{
					$child_post_id = $bcd->broadcast_data->get_linked_child_on_this_blog();
					$this->debug( 'There is already a child post on this blog: %s', $child_post_id );

					// Does this child post still exist?
					$child_post = get_post( $child_post_id );
					if ( $child_post !== null )
					{
						$temp_post_data = $bcd->new_post;
						$temp_post_data[ 'ID' ] = $child_post_id;
						wp_update_post( $temp_post_data );
						$bcd->new_post[ 'ID' ] = $child_post_id;
						$need_to_insert_post = false;
					}
				}

			if ( $need_to_insert_post )
			{
				$this->debug( 'Creating a new post.' );
				$temp_post_data = $bcd->new_post;
				unset( $temp_post_data[ 'ID' ] );

				$result = wp_insert_post( $temp_post_data, true );

				// Did we manage to insert the post properly?
				if ( intval( $result ) < 1 )
				{
					$this->debug( 'Unable to insert the child post.' );
					continue;
				}
				// Yes we did.
				$bcd->new_post[ 'ID' ] = $result;

				$this->debug( 'New child created: %s', $result );

				if ( $bcd->link )
				{
					$this->debug( 'Adding link to child.' );
					$bcd->broadcast_data->add_linked_child( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ] );
				}
			}

			$bcd->equivalent_posts()->set( $bcd->parent_blog_id, $bcd->post->ID, $bcd->current_child_blog_id, $bcd->new_post()->ID );
			$this->debug( 'Equivalent of %s/%s is %s/%s', $bcd->parent_blog_id, $bcd->post->ID, $bcd->current_child_blog_id, $bcd->new_post()->ID  );

			if ( $bcd->taxonomies )
			{
				$this->debug( 'Taxonomies: Starting.' );
				foreach( $bcd->parent_post_taxonomies as $parent_post_taxonomy => $parent_post_terms )
				{
					$this->debug( 'Taxonomies: %s', $parent_post_taxonomy );
					// If we're updating a linked post, remove all the taxonomies and start from the top.
					if ( $bcd->link )
						if ( $bcd->broadcast_data->has_linked_child_on_this_blog() )
							wp_set_object_terms( $bcd->new_post[ 'ID' ], [], $parent_post_taxonomy );

					// Skip this iteration if there are no terms
					if ( ! is_array( $parent_post_terms ) )
					{
						$this->debug( 'Taxonomies: Skipping %s because the parent post does not have any terms set for this taxonomy.', $parent_post_taxonomy );
						continue;
					}

					// Get a list of terms that the target blog has.
					$target_blog_terms = $this->get_current_blog_taxonomy_terms( $parent_post_taxonomy );

					// Go through the original post's terms and compare each slug with the slug of the target terms.
					$taxonomies_to_add_to = [];
					foreach( $parent_post_terms as $parent_post_term )
					{
						$found = false;
						$parent_slug = $parent_post_term->slug;
						foreach( $target_blog_terms as $target_blog_term )
						{
							if ( $target_blog_term[ 'slug' ] == $parent_slug )
							{
								$this->debug( 'Taxonomies: Found existing taxonomy %s.', $parent_slug );
								$found = true;
								$taxonomies_to_add_to[] = intval( $target_blog_term[ 'term_id' ] );
								break;
							}
						}

						// Should we create the taxonomy if it doesn't exist?
						if ( ! $found )
						{
							// Does the term have a parent?
							$target_parent_id = 0;
							if ( $parent_post_term->parent != 0 )
							{
								// Recursively insert ancestors if needed, and get the target term's parent's ID
								$target_parent_id = $this->insert_term_ancestors(
									(array) $parent_post_term,
									$parent_post_taxonomy,
									$target_blog_terms,
									$bcd->parent_blog_taxonomies[ $parent_post_taxonomy ][ 'terms' ]
								);
							}

							$new_term = clone( $parent_post_term );
							$new_term->parent = $target_parent_id;
							$action = new actions\wp_insert_term;
							$action->taxonomy = $parent_post_taxonomy;
							$action->term = $new_term;
							$action->execute();
							$new_taxonomy = $action->new_term;
							$term_id = $new_taxonomy[ 'term_id' ];
							$this->debug( 'Taxonomies: Created taxonomy %s (%s).', $parent_post_term->name, $term_id );

							$taxonomies_to_add_to []= intval( $term_id );
						}
					}

					$this->debug( 'Taxonomies: Syncing terms.' );
					$this->sync_terms( $bcd, $parent_post_taxonomy );
					$this->debug( 'Taxonomies: Synced terms.' );

					if ( count( $taxonomies_to_add_to ) > 0 )
					{
						// This relates to the bug mentioned in the method $this->set_term_parent()
						delete_option( $parent_post_taxonomy . '_children' );
						clean_term_cache( '', $parent_post_taxonomy );
						$this->debug( 'Setting taxonomies for %s: %s', $parent_post_taxonomy, $taxonomies_to_add_to );
						wp_set_object_terms( $bcd->new_post[ 'ID' ], $taxonomies_to_add_to, $parent_post_taxonomy );
					}
				}
				$this->debug( 'Taxonomies: Finished.' );
			}

			// Maybe remove the current attachments.
			if ( $bcd->delete_attachments )
			{
				$attachments_to_remove = get_children( 'post_parent='.$bcd->new_post[ 'ID' ] . '&post_type=attachment' );
				$this->debug( '%s attachments to remove.', count( $attachments_to_remove ) );
				foreach ( $attachments_to_remove as $attachment_to_remove )
				{
					$this->debug( 'Deleting existing attachment: %s', $attachment_to_remove->ID );
					wp_delete_attachment( $attachment_to_remove->ID );
				}
			}
			else
				$this->debug( 'Not deleting child attachments.' );

			// Copy the attachments
			$bcd->copied_attachments = [];
			$this->debug( 'Looking through %s attachments.', count( $bcd->attachment_data ) );
			foreach( $bcd->attachment_data as $key => $attachment )
			{
				if ( $key == 'thumbnail' )
					continue;
				$o = clone( $bcd );
				$o->attachment_data = clone( $attachment );
				$o->attachment_data->post = clone( $attachment->post );
				$this->debug( "The attachment's post parent is %s.", $o->attachment_data->post->post_parent );
				if ( $o->attachment_data->is_attached_to_parent() )
				{
					$this->debug( 'Assigning new post parent ID (%s) to attachment %s.', $bcd->new_post()->ID, $o->attachment_data->post->ID );
					$o->attachment_data->post->post_parent = $bcd->new_post[ 'ID' ];
				}
				else
				{
					$this->debug( 'Resetting post parent for attachment %s.', $o->attachment_data->post->ID );
					$o->attachment_data->post->post_parent = 0;
				}
				$this->maybe_copy_attachment( $o );
				$a = (object)[];
				$a->old = $attachment;
				$a->new = get_post( $o->attachment_id );
				$a->new->id = $a->new->ID;		// Lowercase is expected.
				$bcd->copied_attachments[] = $a;
				$this->debug( 'Copied attachment %s to %s', $a->old->id, $a->new->id );
			}

			// Maybe modify the post content with new URLs to attachments and what not.
			$unmodified_post = (object)$bcd->new_post;
			$modified_post = clone( $unmodified_post );

			// If there were any image attachments copied...
			if ( count( $bcd->copied_attachments ) > 0 )
			{
				$this->debug( '%s attachments were copied.', count( $bcd->copied_attachments ) );
				// Update the URLs in the post to point to the new images.
				$new_upload_dir = wp_upload_dir();
				foreach( $bcd->copied_attachments as $a )
				{
					// Replace the GUID with the new one.
					$modified_post->post_content = str_replace( $a->old->guid, $a->new->guid, $modified_post->post_content );
					// And replace the IDs present in any image captions.
					$modified_post->post_content = str_replace( 'id="attachment_' . $a->old->id . '"', 'id="attachment_' . $a->new->id . '"', $modified_post->post_content );
					$this->debug( 'Modifying attachment link from %s to %s', $a->old->id, $a->new->id );
				}
			}
			else
				$this->debug( 'No attachments were copied.' );

			// If there are galleries...
			$this->debug( '%s galleries are to be handled.', count( $bcd->galleries ) );
			foreach( $bcd->galleries as $gallery )
			{
				// Work on a copy.
				$gallery = clone( $gallery );
				$new_ids = [];

				// Go through all the attachment IDs
				foreach( $gallery->ids_array as $id )
				{
					// Find the new ID.
					foreach( $bcd->copied_attachments as $ca )
					{
						if ( $ca->old->id != $id )
							continue;
						$new_ids[] = $ca->new->id;
					}
				}
				$new_ids_string = implode( ',', $new_ids );
				$new_shortcode = $gallery->old_shortcode;
				$new_shortcode = str_replace( $gallery->ids_string, $new_ids_string, $gallery->old_shortcode );
				$this->debug( 'Replacing gallery shortcode %s with %s.', $gallery->old_shortcode, $new_shortcode );
				$modified_post->post_content = str_replace( $gallery->old_shortcode, $new_shortcode, $modified_post->post_content );
			}

			$bcd->modified_post = $modified_post;
			$action = new actions\broadcasting_modify_post;
			$action->broadcasting_data = $bcd;
			$action->execute();

			$this->debug( 'Checking for post modifications.' );
			$post_modified = false;
			foreach( (array)$unmodified_post as $key => $value )
				if ( $unmodified_post->$key != $modified_post->$key )
				{
					$this->debug( 'Post has been modified because of %s.', $key );
					$post_modified = true;
				}

			// Maybe updating the post is not necessary.
			if ( $post_modified )
			{
				$this->debug( 'Modifying new post.' );
				wp_update_post( $modified_post );	// Or maybe it is.
			}
			else
				$this->debug( 'No need to modify the post.' );

			if ( $bcd->custom_fields )
			{
				$this->debug( 'Custom fields: Started.' );
				// Remove all old custom fields.
				$old_custom_fields = get_post_custom( $bcd->new_post[ 'ID' ] );

				$protected_field = [];

				foreach( $old_custom_fields as $key => $value )
				{
					// This post has a featured image! Remove it from disk!
					if ( $key == '_thumbnail_id' )
					{
						$thumbnail_post = $value[0];
						$this->debug( 'Custom fields: The thumbnail ID is %s. Saved for later use.', $thumbnail_post );
					}

					// Do we delete this custom field?
					$delete = true;

					// For the protectlist to work the custom field has to already exist on the child.
					if ( in_array( $key, $bcd->custom_fields->protectlist ) )
					{
						if ( ! isset( $old_custom_fields[ $key ] ) )
							continue;
						if ( ! isset( $bcd->post_custom_fields[ $key ] ) )
							continue;
						$protected_field[ $key ] = true;
						$delete = false;
					}

					if ( $delete )
					{
						$this->debug( 'Custom fields: Deleting custom field %s.', $key );
						delete_post_meta( $bcd->new_post[ 'ID' ], $key );
					}
					else
						$this->debug( 'Custom fields: Keeping custom field %s.', $key );
				}

				foreach( $bcd->post_custom_fields as $meta_key => $meta_value )
				{
					// Protected = ignore.
					if ( isset( $protected_field[ $meta_key ] ) )
						continue;

					if ( is_array( $meta_value ) )
					{
						foreach( $meta_value as $single_meta_value )
						{
							$single_meta_value = maybe_unserialize( $single_meta_value );
							$this->debug( 'Custom fields: Adding array value %s', $meta_key );
							add_post_meta( $bcd->new_post[ 'ID' ], $meta_key, $single_meta_value );
						}
					}
					else
					{
						$meta_value = maybe_unserialize( $meta_value );
						$this->debug( 'Custom fields: Adding value %s', $meta_key );
						add_post_meta( $bcd->new_post[ 'ID' ], $meta_key, $meta_value );
					}
				}

				// Attached files are custom fields... but special custom fields.
				if ( $bcd->has_thumbnail )
				{
					$this->debug( 'Custom fields: Re-adding thumbnail.' );
					$o = clone( $bcd );
					$o->attachment_data = $bcd->attachment_data[ 'thumbnail' ];

					if ( $o->attachment_data->is_attached_to_parent() )
					{
						$this->debug( 'Assigning new parent ID (%s) to attachment %s.', $bcd->new_post()->ID, $o->attachment_data->post->ID );
						$o->attachment_data->post->post_parent = $bcd->new_post[ 'ID' ];
					}
					else
					{
						$this->debug( 'Resetting post parent for attachment %s.', $o->attachment_data->post->ID );
						$o->attachment_data->post->post_parent = 0;
					}

					$this->debug( 'Custom fields: Maybe copying attachment.' );
					$this->maybe_copy_attachment( $o );
					$this->debug( 'Custom fields: Maybe copied attachment.' );
					if ( $o->attachment_id !== false )
					{
						$this->debug( 'Handling post thumbnail: %s %s', $bcd->new_post[ 'ID' ], '_thumbnail_id', $o->attachment_id );
						update_post_meta( $bcd->new_post[ 'ID' ], '_thumbnail_id', $o->attachment_id );
					}
				}
				$this->debug( 'Custom fields: Finished.' );
			}

			// Sticky behaviour
			$child_post_is_sticky = is_sticky( $bcd->new_post[ 'ID' ] );
			if ( $bcd->post_is_sticky && ! $child_post_is_sticky )
				stick_post( $bcd->new_post[ 'ID' ] );
			if ( ! $bcd->post_is_sticky && $child_post_is_sticky )
				unstick_post( $bcd->new_post[ 'ID' ] );

			if ( $bcd->link )
			{
				$this->debug( 'Saving broadcast data of child.' );
				$new_post_broadcast_data = $this->get_post_broadcast_data( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ] );
				$new_post_broadcast_data->set_linked_parent( $bcd->parent_blog_id, $bcd->post->ID );
				$this->set_post_broadcast_data( $bcd->current_child_blog_id, $bcd->new_post[ 'ID' ], $new_post_broadcast_data );
			}

			$action = new actions\broadcasting_before_restore_current_blog;
			$action->broadcasting_data = $bcd;
			$action->execute();

			$child_blog->switch_from();
		}

		// For nested broadcasts. Just in case.
		restore_current_blog();

		// Save the post broadcast data.
		if ( $bcd->link )
		{
			$this->debug( 'Saving broadcast data.' );
			$this->set_post_broadcast_data( $bcd->parent_blog_id, $bcd->post->ID, $bcd->broadcast_data );
		}

		$action = new actions\broadcasting_finished;
		$action->broadcasting_data = $bcd;
		$action->execute();

		// Finished broadcasting.
		array_pop( $this->broadcasting );

		if ( $this->debugging() )
		{
			if ( ! $this->is_broadcasting() )
			{
				if ( isset( $bcd->stop_after_broadcast ) && ! $bcd->stop_after_broadcast )
				{
					$this->debug( 'Finished broadcasting.' );
				}
				else
				{
					$this->debug( 'Finished broadcasting. Now stopping Wordpress.' );
					exit;
				}
			}
			else
			{
				$this->debug( 'Still broadcasting.' );
			}
		}

		$this->load_language();

		return $bcd;
	}

	/**
		@brief		Creates a new attachment.
		@details

		The $o object is an extension of Broadcasting_Data and must contain:
		- @i attachment_data An attachment_data object containing the attachmend info.

		@param		object		$o		Options.
		@return		@i int The attachment's new post ID.
		@since		20130530
		@version	20131003
	*/
	public function copy_attachment( $o )
	{
		if ( ! file_exists( $o->attachment_data->filename_path ) )
		{
			$this->debug( 'Copy attachment: File %s does not exist!', $o->attachment_data->filename_path );
			return false;
		}

		// Copy the file to the blog's upload directory
		$upload_dir = wp_upload_dir();

		$source = $o->attachment_data->filename_path;
		$target = $upload_dir[ 'path' ] . '/' . $o->attachment_data->filename_base;
		$this->debug( 'Copy attachment: Copying from %s to %s', $source, $target );
		copy( $source, $target );

		// And now create the attachment stuff.
		// This is taken almost directly from http://codex.wordpress.org/Function_Reference/wp_insert_attachment
		$this->debug( 'Copy attachment: Checking filetype.' );
		$wp_filetype = wp_check_filetype( $target, null );
		$attachment = [
			'guid' => $upload_dir[ 'url' ] . '/' . $target,
			'menu_order' => $o->attachment_data->post->menu_order,
			'post_author' => $o->attachment_data->post->post_author,
			'post_excerpt' => $o->attachment_data->post->post_excerpt,
			'post_mime_type' => $wp_filetype[ 'type' ],
			'post_title' => $o->attachment_data->post->post_title,
			'post_content' => '',
			'post_status' => 'inherit',
		];
		$this->debug( 'Copy attachment: Inserting attachment.' );
		$o->attachment_id = wp_insert_attachment( $attachment, $target, $o->attachment_data->post->post_parent );

		// Now to maybe handle the metadata.
		if ( $o->attachment_data->file_metadata )
		{
			$this->debug( 'Copy attachment: Handling metadata.' );
			// 1. Create new metadata for this attachment.
			$this->debug( 'Copy attachment: Requiring image.php.' );
			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			$this->debug( 'Copy attachment: Generating metadata for %s.', $target );
			$attach_data = wp_generate_attachment_metadata( $o->attachment_id, $target );
			$this->debug( 'Copy attachment: Metadata is %s', $attach_data );

			// 2. Write the old metadata first.
			foreach( $o->attachment_data->post_custom as $key => $value )
			{
				$value = reset( $value );
				$value = maybe_unserialize( $value );
				switch( $key )
				{
					// Some values need to handle completely different upload paths (from different months, for example).
					case '_wp_attached_file':
						$value = $attach_data[ 'file' ];
						break;
				}
				update_post_meta( $o->attachment_id, $key, $value );
			}

			// 3. Overwrite the metadata that needs to be overwritten with fresh data.
			$this->debug( 'Copy attachment: Updating metadata.' );
			wp_update_attachment_metadata( $o->attachment_id,  $attach_data );
		}
	}

	/**
		@brief		Creates the ID column in the broadcast data table.
		@since		2014-04-20 20:19:45
	**/
	public function create_broadcast_data_id_column()
	{
		$query = sprintf( "ALTER TABLE `%s` ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'ID of row' FIRST;",
			$this->broadcast_data_table()
		);
		$this->query( $query );
	}

	/**
		@brief		Enqueue the JS file.
		@since		20131007
	**/
	public function enqueue_js()
	{
		if ( isset( $this->_js_enqueued ) )
			return;
		wp_enqueue_script( 'threewp_broadcast', $this->paths[ 'url' ] . '/js/user.min.js', '', $this->plugin_version );
		$this->_js_enqueued = true;
	}

	/**
		@brief		Find shortcodes in a string.
		@details	Runs a preg_match_all on a string looking for specific shortcodes.
					Overrides Wordpress' get_shortcode_regex without own shortcode(s).
		@since		2014-02-26 22:05:09
	**/
	public function find_shortcodes( $string, $shortcodes )
	{
		// Make the shortcodes an array
		if ( ! is_array( $shortcodes ) )
			$shortcodes = [ $shortcodes ];

		// We use Wordpress' own function to find shortcodes.

		global $shortcode_tags;
		// Save the old global
		$old_shortcode_tags = $shortcode_tags;
		// Replace the shortcode tags with just our own.
		$shortcode_tags = array_flip( $shortcodes );
		$rx = get_shortcode_regex();
		$shortcode_tags = $old_shortcode_tags;

		// Run the preg_match_all
		$matches = '';
		preg_match_all( '/' . $rx . '/', $string, $matches );

		return $matches;
	}

	/**
		@brief		Return an array of all callbacks of a hook.
		@since		2014-04-30 00:11:30
	**/
	public function get_hooks( $hook )
	{
		global $wp_filter;
		$filters = $wp_filter[ $hook ];
		ksort( $filters );
		$hook_callbacks = [];
		//$wp_filter[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
		foreach( $filters as $priority => $callbacks )
		{
			foreach( $callbacks as $callback )
			{
				$function = $callback[ 'function' ];
				if ( is_array( $function ) )
				{
					if ( is_object( $function[ 0 ] ) )
						$function[ 0 ] = get_class( $function[ 0 ] );
					$function = sprintf( '%s::%s', $function[ 0 ], $function[ 1 ] );
				}
				if ( is_a( $function, 'Closure' ) )
					$function = '[Anonymous function]';
				$hook_callbacks[] = $function;
			}
		}
		return $hook_callbacks;
	}

	/**
		@brief		Get some standardizing CSS styles.
		@return		string		A string containing the CSS <style> data, including the tags.
		@since		20131031
	**/
	public function html_css()
	{
		return file_get_contents( __DIR__ . '/../html/style.css' );
	}

	public function is_blog_user_writable( $user_id, $blog )
	{
		// Check that the user has write access.
		$blog->switch_to();

		global $current_user;
		wp_get_current_user();
		$r = current_user_can( 'edit_posts' );

		$blog->switch_from();

		return $r;
	}

	/**
		@brief		Are we in the middle of a broadcast?
		@return		bool		True if we're broadcasting.
		@since		20130926
	*/
	public function is_broadcasting()
	{
		return count( $this->broadcasting ) > 0;
	}

	/**
		@brief		Converts a textarea of lines to a single line of space separated words.
		@param		string		$lines		Multiline string.
		@return		string					All of the lines on one line, minus the empty lines.
		@since		20131004
	**/
	public function lines_to_string( $lines )
	{
		$lines = explode( "\n", $lines );
		$r = [];
		foreach( $lines as $line )
			if ( trim( $line ) != '' )
				$r[] = trim( $line );
		return implode( ' ', $r );
	}

	/**
		@brief		Load the user's last used settings from the user meta table.
		@details	Remove the sql_user_get call in v9 or v10, giving time for people to move the settings from the table to the user meta.
		@since		2014-10-09 06:27:32
	**/
	public function load_last_used_settings( $user_id )
	{
		$settings = get_user_meta( $user_id, 'broadcast_last_used_settings', true );
		if ( ! $settings )
		{
			$settings = $this->sql_user_get( $user_id );
			$settings = $settings[ 'last_used_settings' ];
		}
		if ( ! is_array( $settings ) )
			$settings = [];
		return $settings;
	}

	/**
		@brief		Will only copy the attachment if it doesn't already exist on the target blog.
		@details	The return value is an object, with the most important property being ->attachment_id.

		@param		object		$options		See the parameter for copy_attachment.
	**/
	public function maybe_copy_attachment( $options )
	{
		$attachment_data = $options->attachment_data;		// Convenience.

		$key = get_current_blog_id();

		$this->debug( 'Maybe copy attachment: Searching for attachment posts with the name %s.', $attachment_data->post->post_name );

		// Start by assuming no attachments.
		$attachment_posts = [];

		global $wpdb;
		// The post_name is the important part.
		$query = sprintf( "SELECT `ID` FROM `%s` WHERE `post_type` = 'attachment' AND `post_name` = '%s'",
			$wpdb->posts,
			$attachment_data->post->post_name
		);
		$results = $this->query( $query );
		if ( count( $results ) > 0 )
			foreach( $results as $result )
				$attachment_posts[] = get_post( $result[ 'ID' ] );
		$this->debug( 'Maybe copy attachment: Found %s attachment posts.', count( $attachment_posts ) );

		// Is there an existing media file?
		// Try to find the filename in the GUID.
		foreach( $attachment_posts as $attachment_post )
		{
			if ( $attachment_post->post_name !== $attachment_data->post->post_name )
			{
				$this->debug( "The attachment post name is %s, and we are looking for %s. Ignoring attachment.", $attachment_post->post_name, $attachment_data->post->post_name );
				continue;
			}
			$this->debug( "Found attachment %s and we are looking for %s.", $attachment_post->post_name, $attachment_data->post->post_name );
			// We've found an existing attachment. What to do with it...
			$existing_action = $this->get_site_option( 'existing_attachments', 'use' );
			$this->debug( 'Maybe copy attachment: The action for existing attachments is to %s.', $existing_action );
			switch( $existing_action )
			{
				case 'overwrite':
					// Delete the existing attachment
					$this->debug( 'Maybe copy attachment: Deleting current attachment %s', $attachment_post->ID );
					wp_delete_attachment( $attachment_post->ID, true );		// true = Don't go to trash
					break;
				case 'randomize':
					$filename = $options->attachment_data->filename_base;
					$filename = preg_replace( '/(.*)\./', '\1_' . rand( 1000000, 9999999 ) .'.', $filename );
					$options->attachment_data->filename_base = $filename;
					$this->debug( 'Maybe copy attachment: Randomizing new attachment filename to %s.', $options->attachment_data->filename_base );
					break;
				case 'use':
				default:
					// The ID is the important part.
					$options->attachment_id = $attachment_post->ID;
					$this->debug( 'Maybe copy attachment: Using existing attachment %s.', $attachment_post->ID );
					return $options;

			}
		}

		// Since it doesn't exist, copy it.
		$this->debug( 'Maybe copy attachment: Really copying attachment.' );
		$this->copy_attachment( $options );
		return $options;
	}

	/**
		@brief		Save the user's last used settings.
		@details	Since v8 the data is stored in the user's meta.
		@since		2014-10-09 06:19:53
	**/
	public function save_last_used_settings( $user_id, $settings )
	{
		update_user_meta( $user_id, 'broadcast_last_used_settings', $settings );
	}

	public function site_options()
	{
		return array_merge( [
			'blogs_to_hide' => 5,								// How many blogs to auto-hide
			'broadcast_internal_custom_fields' => true,		// Broadcast internal custom fields?
			'canonical_url' => true,							// Override the canonical URLs with the parent post's.
			'clear_post' => true,								// Clear the post before broadcasting.
			'custom_field_whitelist' => '_wp_page_template _wplp_ _aioseop_',				// Internal custom fields that should be broadcasted.
			'custom_field_blacklist' => '',						// Internal custom fields that should not be broadcasted.
			'custom_field_protectlist' => '',					// Internal custom fields that should not be overwritten on broadcast
			'database_version' => 0,							// Version of database and settings
			'debug' => false,									// Display debug information?
			'debug_ips' => '',									// List of IP addresses that can see debug information, when debug is enabled.
			'save_post_priority' => 640,						// Priority of save_post action. Higher = lets other plugins do their stuff first
			'override_child_permalinks' => false,				// Make the child's permalinks link back to the parent item?
			'post_types' => 'post page',						// Custom post types which use broadcasting
			'existing_attachments' => 'use',					// What to do with existing attachments: use, overwrite, randomize
			'role_broadcast' => 'super_admin',					// Role required to use broadcast function
			'role_link' => 'super_admin',						// Role required to use the link function
			'role_broadcast_as_draft' => 'super_admin',			// Role required to broadcast posts as templates
			'role_broadcast_scheduled_posts' => 'super_admin',	// Role required to broadcast scheduled, future posts
			'role_taxonomies' => 'super_admin',					// Role required to broadcast the taxonomies
			'role_custom_fields' => 'super_admin',				// Role required to broadcast the custom fields
		], parent::site_options() );
	}

	/**
		@brief		Return yes / no, depending on value.
		@since		20140220
	**/
	public function yes_no( $value )
	{
		return $value ? 'yes' : 'no';
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- SQL
	// --------------------------------------------------------------------------------------------

	/**
	 * Gets the user data.
	 *
	 * Returns an array of user data.
	 */
	public function sql_user_get( $user_id)
	{
		$r = $this->query("SELECT * FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$r = @unserialize( base64_decode( $r[0][ 'data' ] ) );		// Unserialize the data column of the first row.
		if ( $r === false)
			$r = [];

		// Merge/append any default values to the user's data.
		return array_merge(array(
			'groups' => [],
		), $r);
	}

	/**
	 * Saves the user data.
	 */
	public function sql_user_set( $user_id, $data)
	{
		$data = serialize( $data);
		$data = base64_encode( $data);
		$this->query("DELETE FROM `".$this->wpdb->base_prefix."_3wp_broadcast` WHERE user_id = '$user_id'");
		$this->query("INSERT INTO `".$this->wpdb->base_prefix."_3wp_broadcast` (user_id, data) VALUES ( '$user_id', '$data' )");
	}
}
