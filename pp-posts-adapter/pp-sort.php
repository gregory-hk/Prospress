<?php

add_action('parse_query','set_pp_sort_flag');
function set_pp_sort_flag( $query ) {
	global $pp_sort;

	if( !isset( $query->query_vars['pp_sort'] ) ){
		//error_log('*** NOT SET query->query_vars[pp_sort] ***');
		//error_log('in set_pp_sort $query->query_vars = ' . print_r($query->query_vars, true));
		return $query;
	}

	$pp_sort = explode( '-', $query->query_vars['pp_sort'] );
	$pp_sort['orderby']	= $pp_sort[ 0 ];
	$pp_sort['order']	= $pp_sort[ 1 ];
	unset( $pp_sort[ 0 ] );
	unset( $pp_sort[ 1 ] );
	error_log('****** in set_pp_sort $pp_sort = ' . print_r($pp_sort, true));
}

add_filter('posts_join','pp_join_filter');
function pp_join_filter( $arg ) {
	global $wpdb, $wp_query, $pp_sort;

	if( !isset( $pp_sort['orderby'] ) )
		return $arg;

	if( $pp_sort['orderby'] == 'end_date' )
		$arg .= "JOIN $wpdb->postmeta ON ($wpdb->posts".".ID = $wpdb->postmeta".".post_id) ";
	else
		$arg = apply_filters('pp_posts_join', $arg);

	error_log('in pp_join_filter, query_vars, arg = ' . $arg);

	return $arg;
}

add_filter('posts_where','pp_where_filter');
function pp_where_filter( $arg ) {
	global $wpdb, $wp_query, $pp_sort;

	if( !isset( $pp_sort['orderby'] ) )
		return $arg;

	if( $pp_sort['orderby'] == 'end_date' )
		$arg = $arg . " AND " . $wpdb->postmeta . ".meta_key = 'post_end_date_gmt' ";
	else
		$arg = apply_filters('pp_posts_where', $arg);

	error_log('in pp_where_filter, arg = ' . $arg);

	return $arg;
}

add_filter('posts_orderby','pp_orderby_filter');
function pp_orderby_filter( $arg ) {
	global $wpdb, $wp_query, $pp_sort;

	error_log('* in start of pp_orderby_filter, arg = ' . $arg);

	if( !isset( $pp_sort['orderby'] ) )
		return $arg;

	if( $pp_sort['orderby'] == 'end_date' )
		$arg = $wpdb->postmeta . ".meta_key " . $pp_sort['order'];//$arg = str_replace("$wpdb->posts.post_date",$wpdb->postmeta . ".meta_key ",$arg);
	else
		$arg = apply_filters('pp_posts_orderby', $arg);

	error_log('** in pp_orderby_filter, arg = ' . $arg);

	return $arg;
}

add_action('query_vars','pp_insert_rewrite_query_vars');
function pp_insert_rewrite_query_vars( $vars ) {
	$vars[] = 'pp_sort';

	//error_log('in pp_insert_rewrite_query_vars, vars = ' . print_r($vars,true));
	return $vars;
}

/**************************************************************************************
 *************************************** WIDGET ***************************************
 **************************************************************************************/
class PP_Sort_Widget extends WP_Widget {
	function PP_Sort_Widget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'pp_sort', 'description' => __( 'Sort posts in your Prospress Marketplace.' ) );

		/* Widget control settings. */
		$control_ops = array( 'id_base' => 'pp_sort' );

		/* Create the widget. */
		$this->WP_Widget( 'pp_sort', 'Prospress Sort', $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {
		global $pp_sort_options;
		
		$pp_sort_options = array( 'price-asc' => __('Price: low to high'),
								 'price-desc'=> __('Price: high to low'),
								 'end-asc'	=> __('Time: Ending soon'),
								 'end-desc'	=> __('Time: Newly posted') );

		error_log( "in widget, pp_sort_options = " . print_r($pp_sort_options, true) );
		error_log( "in widget, args = " . print_r($args, true) );
		error_log( "in widget, instance = " . print_r($instance, true) );

		extract( $args );

		//Don't want to print on single posts or pages
		if( is_single() || is_page() ){
			error_log('in widget, is single true');
			return;
		}

		$end_date = ( $instance['end_date'] == 'on' ) ? $instance['end_date'] : false;
		$price = ( $instance['price'] == 'on' ) ? $instance['price'] : false;
		$sorted_by = trim( @$_GET[ 'pp_sort' ] );

		echo $before_widget;

		echo $before_title;
		echo ( $instance['title'] ) ? $instance['title'] : __( 'Sort By:' );
		echo $after_title;

		echo '<form id="pp_sort" method="get" action="' . esc_url_raw( remove_query_arg( 'pp_sort', $_SERVER['REQUEST_URI'] ) ) . '">';
		echo '<select name="pp_sort" >';
		foreach ( $pp_sort_options as $key => $label ) {
			if( $instance[ $key ] != 'on' )
				continue;
			echo "<option value='".$key."-ASC'>".$label." ". __('Ascending')."</option>";
			echo "<option value='".$key."-DESC'>".$label." ". __('Descending')."</option>";
		}
		echo '</select>';
		echo '<input type="submit" value="' . __("Sort") . '">';
		echo '</form>';
		
		echo $after_widget;

		echo $after_widget;
	}

	function form( $instance ) {
		global $pp_sort_options;
		$pp_sort_options = array( 'price-asc' => __('Price: low to high'),
								 'price-desc'=> __('Price: high to low'),
								 'end-asc'	=> __('Time: Ending soon'),
								 'end-desc'	=> __('Time: Newly posted') );

		/* Set up some default widget settings. */
		//$defaults = array( 'title' => 'Sort By:', 'end_date' => true, 'price' => true );
		$instance = wp_parse_args( (array) $instance, $defaults );
		error_log('in form, $instance = ' . print_r($instance, true));
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php __('Title') ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" />
		</p>
		<?php
		foreach( $pp_sort_options as $key => $label ){
			$field_id = $this->get_field_id( $key );
			echo "<p><input class='checkbox' type='checkbox'" . checked( $instance[ $key ], 'on' ) . "id='$field_id' name='$field_id' />";
			echo "<label for='$field_id'> " . __('Sort by' ) . " $label</label></p>";
		}
	}

	function update( $new_instance, $old_instance ) {
		global $pp_sort_options;
		$pp_sort_options = array( 'price-asc' => __('Price: low to high'),
								 'price-desc'=> __('Price: high to low'),
								 'end-asc'	=> __('Time: Ending soon'),
								 'end-desc'	=> __('Time: Newly posted') );

		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );		
		//$instance['end_date'] = $new_instance['end_date'];
		//$instance['price'] = $new_instance['price'];
		error_log('in update, $instance = ' . print_r($instance, true));
		error_log('in update, $new_instance = ' . print_r($new_instance, true));
		foreach( $pp_sort_options as $key => $label )
			$instance[ $key ] = $new_instance[ $key ];

		error_log('** in update, $instance = ' . print_r($instance, true));
		error_log('** in update, $new_instance = ' . print_r($new_instance, true));
		return $instance;
	}
}
add_action('widgets_init', create_function('', 'return register_widget("PP_Sort_Widget");'));

add_action('wp_head', 'pp_print_query');
function pp_print_query(){
	global $wp_query;
	error_log('in pp_print_query, $wp_query request = ' . print_r($wp_query->request, true));
	//error_log('in pp_print_query, $wp_query request = ' . print_r($wp_query, true));
}

?>