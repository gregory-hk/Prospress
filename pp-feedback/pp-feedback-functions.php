<?php
/**
 * An assortment of useful functions.
 * 
 * @package Prospress
 * @subpackage Feedback
 */

/**
 * Determines if a user has already given feedback on a post. 
 * 
 * @param $post_id the post to check feedback records for 
 * @param $user_id optionally specify if a feedback item has been place from a specified user. 
 */
function pp_post_has_feedback( $post_id, $user_id = NULL ){
	global $wpdb; 

	if( $user_id === NULL )
		$feedback = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_parent = %d", $post_id ) );
		//$feedback = $wpdb->get_var( $wpdb->prepare( "SELECT feedback_id FROM $wpdb->feedback WHERE feedback_status = 'publish' AND post_id = %d", $post_id ) );
	else
		$feedback = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_parent = %d AND post_author = %d", $post_id, $user_id ) );
		//$feedback = $wpdb->get_var( $wpdb->prepare( "SELECT feedback_id FROM $wpdb->feedback WHERE feedback_status = 'publish' AND post_id = %d AND from_user_id = %d", $post_id, $user_id ) );

	if( NULL != $feedback ){
		return true;		
	} else {
		return false;
	}
}

/**
 * Determines if a user has already given feedback on a post. 
 * 
 * @param $post_id the post to check feedback records for 
 * @param $user_id optionally specify if a feedback item has been place from a specified user. 
 */
function pp_user_has_feedback( $post_id, $user_id = '' ){
	global $wpdb; 

	if( empty( $user_id ) ) // shouldn't ever be a user with ID = 0 
		$feedback = $wpdb->get_var( $wpdb->prepare( "SELECT feedback_id FROM $wpdb->feedback WHERE feedback_status = 'publish' AND post_id = %d", $post_id ) );
	else
		$feedback = $wpdb->get_var( $wpdb->prepare( "SELECT feedback_id FROM $wpdb->feedback WHERE feedback_status = 'publish' AND post_id = %d AND from_user_id = %d", $post_id, $user_id ) );

	if( NULL != $feedback ){
		return true;		
	} else {
		return false;
	}
}


/**
 * Gets latest feedback item received by a given user.
 * 
 * @return array feedback items, or NULL if feedback doesn't exist
 */
function pp_get_latest_feedback( $user_id ){
	global $wpdb;

	return $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->feedback WHERE for_user_id = %d AND feedback_status = 'publish' ORDER BY feedback_date_gmt DESC LIMIT 1", $user_id ), ARRAY_A);
}

/**
 * Gets feedback optionally filtered with parameters matching the get_posts function.
 * 
 * @param array filters an array of name value pairs that can be used to modify which feedback items are returned
 * @return array feedback items, or NULL if feedback doesn't exist
 */
function pp_get_feedback( $args = array() ){

	$args[ 'post_type' ] = 'feedback'; //brute force

	error_log( 'in pp_get_feedback, args = ' . print_r( $args, true ) );

	$feedback = get_posts( $args );

	foreach( $feedback as $feedback_id => $feedback_item ){
		$feedback[ $feedback_id ]->feedback_recipient = get_post_meta( $feedback_id, 'feedback_recipient' );
		$feedback[ $feedback_id ]->feedback_score = get_post_meta( $feedback_id, 'feedback_score' );
	}

	return $feedback;
/*
	if( isset( $filters[ 'post' ] ) ){
		$query .= $wpdb->prepare( " AND post_parent = %d ", $filters[ 'post' ] );
	} else if( isset( $filters[ 'given' ] ) ){
		$query .= $wpdb->prepare( " AND post_author = %d ", $user_id );
		$feedback = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->feedback WHERE from_user_id = %d AND feedback_status = 'publish' ORDER BY feedback_date_gmt DESC", $user_id), ARRAY_A);
	} else if( isset( $filters[ 'received' ] ) ){
		$feedback = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->feedback WHERE for_user_id = %d AND feedback_status = 'publish' ORDER BY feedback_date_gmt DESC", $user_id), ARRAY_A);
	} else if( isset( $filters[ 'role' ] ) ){
		$filters[ 'role' ] = (int)$filters[ 'role' ];
		$feedback = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->feedback WHERE for_user_id = %d AND role = %s AND feedback_status = 'publish' ORDER BY feedback_date_gmt DESC", $user_id, $filters[ 'role' ]), ARRAY_A);
	} else {
		$feedback = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->feedback WHERE feedback_status = 'publish' AND ( for_user_id = %d OR from_user_id = %d ) ORDER BY feedback_date_gmt DESC", $user_id, $user_id), ARRAY_A);
	}

	return $feedback;
*/
}

//Returns a count of all the feedback for a user, optionally returns only given or as specified by filters array received by a user
function pp_users_feedback_count( $user_id, $filter = 'received' ){
	global $wpdb;

	if( $filter == 'given'  )
		$args[ 'author' ] = $user_id;
	else if( $filter == 'received' )
		$args[ 'feedback_recipient' ] = $user_id;
	else
		$args[ 'author' ] = $args[ 'feedback_recipient' ] = $user_id;

	return count( pp_get_feedback( $args ) );
}


//Returns the number of positive feedback a user given or received ( as specified by filter )
function pp_users_positive_feedback( $user_id, $filter = '' ){
	global $wpdb;

	if( $filter == 'given'  )
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND from_user_id = %d AND feedback_score = 2", $user_id ) );
	else if( $filter == 'received' )
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND for_user_id = %d AND feedback_score = 2", $user_id ) );
	else
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND feedback_score = 2 AND ( for_user_id = %d OR from_user_id = %d )", $user_id, $user_id ) );
}


//Returns the number of positive feedback a user given or received ( as specified by filter )
function pp_users_neutral_feedback( $user_id, $filter = '' ){
	global $wpdb;

	if( $filter == 'given'  )
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND from_user_id = %d AND feedback_score = 1", $user_id ) );
	else if( $filter == 'received' )
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND for_user_id = %d AND feedback_score = 1", $user_id ) );
	else
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND feedback_score = 1  AND ( for_user_id = %d OR from_user_id = %d )", $user_id, $user_id ) );
}

//Returns the number of positive feedback a user given or received ( as specified by filter )
function pp_users_negative_feedback( $user_id, $filter = '' ){
	global $wpdb;

	if( $filter == 'given'  )
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND from_user_id = %d AND feedback_score = 0", $user_id ) );
	else if( $filter == 'received' )
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND for_user_id = %d AND feedback_score = 0", $user_id ) );
	else
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(feedback_id) FROM $wpdb->feedback WHERE feedback_status = 'publish' AND feedback_score = 0  AND ( for_user_id = %d OR from_user_id = %d )", $user_id, $user_id ) );
}

//Returns the percentage of positive feedback a user has given or received ( as specified by filter )
function pp_users_feedback_percent( $user_id, $filter = '' ){
	global $wpdb;

	$all_feedback = pp_users_feedback_count( $user_id, $filter );
	$postitive_feedback = pp_users_feedback_count( $user_id, $filter ) - pp_users_negative_feedback( $user_id, $filter );
	return (int)( $postitive_feedback / $all_feedback * 100 );
}


//Echos a link to the feedback table for a given user.
function pp_users_feedback_link( $user_id ){
	return "<a href='" . add_query_arg ( array( 'uid' => $user_id ), 'users.php?page=feedback' ) . "'> (" . pp_users_feedback_count( $user_id ) . ")</a>";
}
