<?php
/**
 * Auction bid system class.
 * 
 * This class extends the base Bid System class to create an auction bid system. User's can
 * submit a bid 
 * forms the basis for all bid systems. It provides a framework for creating new bid systems
 * and is extended to implement the core auction and reverse auction formats that ship with Prospress.
 *
 * @package Prospress
 * @since 0.1
 */
require_once ( PP_BIDS_DIR . '/bid-system.class.php' ); // Base class

class PP_Auction_Bid_System extends PP_Bid_System {
	
	// Constructors

	// PHP4 constructor
	function PP_Auction_Bid_System() {
		$this->__construct();
	}

	// PHP5 constructor
	function __construct() {
		if ( !defined( 'BID_INCREMENT' ) )
			define( 'BID_INCREMENT', '0.05' );

		parent::__construct( __('auction'), __('Auction Bid'), __('Bid!'), array( 'post_fields' ) );
	}

	function form_fields( $post_id = NULL ) { 
		global $post_ID, $currency_symbol;

		$post_id = ( $post_id === NULL ) ? $post_ID : $post_id;
		$bid_count = $this->get_bid_count( $post_id );
		$bid_form_fields = '';
		$dont_echo = false;

		if( $bid_count == 0 ){
			$bid_form_fields .= '<div id="current_bid_val">' . __("Starting Bid: ") . pp_money_format( get_post_meta( $post_id, 'start_price', true ) ) . '</div></p>';
		} else {
			$bid_form_fields .= '<div id="current_bid_num">' . __("Number of Bids: ") . $this->the_bid_count( $post_id, $dont_echo ) . '</div>';
			$bid_form_fields .= '<div id="winning_bidder">' . __("Winning Bidder: ") . $this->the_winning_bidder( $post_id, $dont_echo ) . '</div>';
			$bid_form_fields .= '<div id="current_bid_val">' . __("Current Bid: ") . $this->the_winning_bid_value( $post_id, $dont_echo ) . '</div>';
		}
		
		$bid_form_fields .= '<label for="bid_value" class="bid-label">' . __( 'Enter max bid: ' ) . $currency_symbol . '</label>';
		$bid_form_fields .= '<input type="text" aria-required="true" tabindex="1" size="22" value="" id="bid_value" name="bid_value"/>';
		
		return $bid_form_fields;
	}

	function form_submission( $post_id = NULL, $bid_value = NULL, $bidder_id = NULL ){
		global $user_ID, $wpdb;
		nocache_headers();

		error_log('in form_submission _REQUEST = ' . print_r($_REQUEST, true));
		//error_log('in form_submission _GET = ' . print_r($_GET, true));
		//error_log('in form_submission _POST = ' . print_r($_POST, true));

		//Get Bid details
		//$post_id 		= ( isset( $_GET[ 'post_ID' ] ) ) ? intval( $_GET[ 'post_ID' ] ) : $post_id;
		$post_id 		= ( isset( $_REQUEST[ 'post_ID' ] ) ) ? intval( $_REQUEST[ 'post_ID' ] ) : $post_id;
		//$bid_value		= ( isset( $_GET[ 'bid_value' ] ) ) ? str_replace( ',', '', trim( $_GET[ 'bid_value' ] ) ) : $bid_value;
		$bid_value		= ( isset( $_REQUEST[ 'bid_value' ] ) ) ? str_replace( ',', '', trim( $_REQUEST[ 'bid_value' ] ) ) : $bid_value;
		$bidder_id 		= ( isset( $bidder_id ) ) ? $bidder_id : $user_ID;
		$bid_date 		= current_time( 'mysql' );
		$bid_date_gmt 	= current_time( 'mysql', 1 );

		global $blog_id;
		error_log( "in Auction form_submission bidder_id = $bidder_id, blog_id = $blog_id, post_id = $post_id, user_ID = $user_ID");
		do_action( 'get_auction_bid', $post_id, $bid_value, $bidder_id, $_GET );

		$post_status	= $this->verify_post_status( $post_id );
		$bid_ms			= $this->form_validation( $post_id, $bid_value, $bidder_id );
		//error_log("*** bid_ms  = " . print_r($bid_ms, true));

		//if ( $bid_ms[ 'bid_msg' ] < 5 && $bid_ms[ 'bid_msg' ] != 3 ) {
		if ( $bid_ms[ 'bid_status' ] != 'invalid' ) {
			$bid = compact("post_id", "bidder_id", "bid_value", "bid_date", "bid_date_gmt" );
			$bid[ 'bid_status' ] = $bid_ms[ 'bid_status' ];
			$bid = apply_filters('bid_pre_db_insert', $bid);
			//$this->update_winning_bid( $bid_ms, $post_id, $bid_value, $bidder_id );
			$this->update_bid( $bid, $bid_ms );
			error_log("*** Winning Bid updated ***");
			//$wpdb->insert( $wpdb->bids, $bid );
			error_log("*** Bid inserted ***");
			error_log("*** Bid  = " . print_r($bid, true));
		}

		return $bid_ms[ 'bid_msg' ];
	}

	function form_validation( $post_id, $bid_value, $bidder_id ){
		$post_max_bid		= $this->get_max_bid( $post_id );
		$bidders_max_bid	= $this->get_users_max_bid( $bidder_id, $post_id );

		if ( empty( $bid_value ) || $bid_value === NULL || !preg_match( '/^[0-9]*\.?[0-9]*$/', $bid_value ) ) {
			error_log('INVALID: Invalid bid...');
			$bid_msg = 7;
			$bid_status = 'invalid';
		} elseif ( $bidder_id != $this->get_winning_bid( $post_id )->bidder_id ) {
			error_log("Bidder is different to winning bidder: bidder_id = $bidder_id & this->get_winning_bid( $post_id )->bidder_id  = " . $this->get_winning_bid( $post_id )->bidder_id );
			$current_winning_bid_value = $this->get_winning_bid_value( $post_id );
			if ( $this->get_bid_count( $post_id ) == 0 ) {
				$start_price = get_post_meta( $post_ID, 'start_price', true );
				if ( $bid_value < $start_price ){
					error_log("INVALID: Bid value ($bid_value) is first bid, but must bid higher than starting price");
					$bid_msg = 9;
					$bid_status = 'invalid';
				} else {
					error_log("WINNING: Bid value ($bid_value) is first bid, setting bid status to 0");
					$bid_msg = 0;
					$bid_status = 'winning';
				}
			} elseif ( $bid_value > $post_max_bid->bid_value ) {
				error_log("WINNING: Bid value ($bid_value) is over max bid ($post_max_bid->bid_value), setting bid status to 1");
				$bid_msg = 1;
				$bid_status = 'winning';
			} elseif ( $bid_value <= $current_winning_bid_value ) {
				error_log("INVALID: bid_too_low with a bid of $bid_value, setting bid status to 3");
				$bid_msg = 3;
				$bid_status = 'invalid';
			} elseif ( $bid_value <= $post_max_bid->bid_value ) {
				error_log('OUTBID: bid_less_than_max_more_than_winning, setting bid status to 2');
				$bid_msg = 2;
				$bid_status = 'outbid';
			}
		} elseif ( $bid_value > $bidders_max_bid->bid_value ){ //user increasing max bid
			error_log("WINNING: bidder_increasing_max, setting bid status to 4");
			$bid_msg = 4;
			$bid_status = 'winning';
		} elseif ( $bid_value < $bidders_max_bid->bid_value ) { //user trying to decrease max bid
			error_log("INVALID: bidder_decreasing_max, setting bid status to 5");
			$bid_msg = 5;
			$bid_status = 'invalid';
		} else {
			error_log("INVALID: bidder_rebidding_max, setting bid status to 6");	
			$bid_msg = 6;
			$bid_status = 'invalid';
		}
		return compact( 'bid_status', 'bid_msg' );
	}

	function update_bid( $bid, $bid_ms ){
		global $wpdb;
		error_log("** update_winning_bid called **");

		$current_winning_bid_value 	= $this->get_winning_bid_value( $bid[ 'post_id' ] );
		error_log('$current_winning_bid_value = ' . print_r($current_winning_bid_value, true));

		// No need to update winning bid for invalid bids, bids too low
		//if ( $bid_ms[ 'bid_msg' ] > 2 )
		if ( $bid_ms[ 'bid_status' ] == 'invalid' ) // nothing to update
			return $current_winning_bid_value;

		$posts_max_bid				= $this->get_max_bid( $bid[ 'post_id' ] );
		$current_winning_bid_id		= $this->get_winning_bid( $bid[ 'post_id' ] )->bid_id;

		//if( $posts_max_bid->bid_value === false || $posts_max_bid->bid_value == 0 ) { //if first bid
		if( $bid_ms[ 'bid_msg' ] == 0 ) { //if first bid
			error_log("** First bid.");
			$new_winning_bid_value = ( $bid[ 'bid_value' ] * BID_INCREMENT );
		} elseif ( $bid_ms[ 'bid_msg' ] == 1 ) { //Bid value is over max bid & bidder different to current winning bidder
			error_log("** Bid value is over max bid & bidder different to current winning bidder");
			if ( $bid[ 'bid_value' ] > ( $posts_max_bid->bid_value * ( BID_INCREMENT + 1 ) ) ) {
				error_log("** Bid value (bid[ 'bid_value' ]) is more than current max ($posts_max_bid->bid_value) + bid increment (" . BID_INCREMENT . ") * current max ($posts_max_bid->bid_value)");
				$new_winning_bid_value = $posts_max_bid->bid_value * ( BID_INCREMENT + 1 );
			} else {
				error_log("** Bid value (bid[ 'bid_value' ]) is less than current max ($posts_max_bid->bid_value) + bid increment (" . BID_INCREMENT . ") * current max ($posts_max_bid->bid_value)");
				$new_winning_bid_value = $bid[ 'bid_value' ];
			}
			//update_post_meta( $bid[ 'post_id' ], 'winning_bidder_id', $bidder_id );
		} elseif ( $bid_ms[ 'bid_msg'] == 2 ) {
			error_log('** bid less than max but more than winning');
			$bid_value_incremented = $bid[ 'bid_value' ] * ( 1 + BID_INCREMENT );
			if ( $posts_max_bid->bid_value > $bid_value_incremented ) {
				$new_winning_bid_value = $bid_value_incremented;
			} else {
				$new_winning_bid_value = $posts_max_bid->bid_value;
			}
		} elseif ( $bid_ms[ 'bid_msg'] == 4 ) { // bidder increasing max bid, just need to set their previous bid as 'outbid'
			$wpdb->update( $wpdb->bids, array( 'bid_status' => 'outbid' ), array( 'bid_id' => $current_winning_bid_id ) );
			$new_winning_bid_value = $current_winning_bid_value;
		}

		$wpdb->insert( $wpdb->bids, $bid );

		if( $bid_ms[ 'bid_msg'] != 2 ){
			$wpdb->update( $wpdb->bids, array( 'bid_status' => 'outbid' ), array( 'bid_id' => $current_winning_bid_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->bidsmeta WHERE bid_id = %d AND meta_key = 'winning_bid_value'", $current_winning_bid_id ) );
			$new_winning_bid_id = $this->get_winning_bid( $bid[ 'post_id' ] )->bid_id;
			$wpdb->insert( $wpdb->bidsmeta, array( 'bid_id' => $new_winning_bid_id, 'meta_key' => 'winning_bid_value', 'meta_value' => $new_winning_bid_value ) );
			error_log("** insert bidsmeta with new_winning_bid = $new_winning_bid_value and new_winning_bid_id = $new_winning_bid_id");
		} else {
			$wpdb->update( $wpdb->bidsmeta, array( 'meta_value' => $new_winning_bid_value ), array( 'bid_id' => $current_winning_bid_id, 'meta_key' => 'winning_bid_value' ) );
			error_log("** update bidsmeta with new_winning_bid = $new_winning_bid_value and current_winning_bid_id = $current_winning_bid_id");
		}
		error_log("** winning_bid value calculated as = $new_winning_bid_value");
		//update_post_meta( $bid[ 'post_id' ], 'winning_bid_value', $new_winning_bid_value );
		//$wpdb->insert( $wpdb->bidsmeta, array( 'meta_value' => $new_winning_bid_value ), array( 'bid_id' => $current_winning_bid_id, 'meta_key' => 'winning_bid_value' ) );


		return $new_winning_bid_value;
	}


	function post_fields(){
		global $post_ID, $currency_symbol;
		$start_price = get_post_meta( $post_ID, 'start_price', true );
		$reserve_price = get_post_meta( $post_ID, 'reserve_price', true );

		wp_nonce_field( __FILE__, 'selling_options_nonce', false ) ?>
		<table>
		  <tbody>
				<tr>
				  <td class="left">
					<label for="start_price"><?php echo __("Starting Price: " ) . $currency_symbol; ?></label>
					</td>
					<td>
				 		<input type="text" name="start_price" value="<?php echo number_format_i18n( $start_price, 2 ); ?>" size="20" />
					</td>
				</tr>
				<tr>
				  <td class="left">
					<label for="reserve_price"><?php echo __("Reserve Price: " ) . $currency_symbol; ?></label>
				</td>
				<td>
					<input type="text" name="reserve_price" value="<?php echo number_format_i18n( $reserve_price, 2 ); ?>" size="20" />
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	function post_fields_submit( $post_id, $post ){
		global $wpdb;

		if(wp_is_post_revision($post_id))
			$post_id = wp_is_post_revision($post_id);

		if ( 'page' == $_POST['post_type'] )
			return $post_id;
		elseif ( !current_user_can( 'edit_post', $post_id ))
			return $post_id;

		/** @TODO casting start_price as a float and removing ',' and ' ' will cause a bug for international currency formats. */
		$_POST[ 'start_price' ] = (float)str_replace( array(",", " "), "", $_POST[ 'start_price' ]);
		$_POST[ 'reserve_price' ] = (float)str_replace( array(",", " "), "", $_POST[ 'reserve_price' ]);
		// Verify options nonce because save_post can be triggered at other times
		if ( !isset( $_POST[ 'selling_options_nonce' ] ) || !wp_verify_nonce( $_POST['selling_options_nonce'], __FILE__) ) {
			return $post_id;
		} else { //update post options
			update_post_meta( $post_id, 'start_price', $_POST[ 'start_price' ] );
			update_post_meta( $post_id, 'reserve_price', $_POST[ 'reserve_price' ] );
		}
	}
}

// Runs a suite of tests on the auction bid system.
function auction_test(){
	//global $bid_system, $wpdb;
	
	$bid_system_test = new PP_Auction_Bid_System();

	$post_id		= 333333;
	$bidder_id		= 333;
	$bid_value		= 11.23;
	$bid_status		= '';
	$bid_date 		= current_time( 'mysql' );
	$bid_date_gmt 	= current_time( 'mysql', 1 );

	if ( true ) { // test form_submission
		// Create first bid
		error_log( "******************* Create first bid *********************" );
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 1)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		//error_log( "winning_bid = ". $winning_bid->get_value() . " (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bid = ". $winning_bid . " (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder one try to decrease max bid
		error_log( "****************************************" );
		$bid_value	= 2;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 5)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder one increase max bid
		error_log( "****************************************" );
		$bid_value	= 20;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 4)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Change to new bidder
		$bidder_id	= 111;

		// Have bidder two bid below winning bid value
		error_log( "****************************************" );
		$bid_value	= 0.1;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 3)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder two bid above winning bid value, but below previous bidder's max bid
		error_log( "****************************************" );
		$bid_value	= 10;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 2)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be $bid_value * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder two bid exactly the same as bidder one's max bid
		error_log( "****************************************" );
		$bid_value	= 20;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 2)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be $bid_value)" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder two bid above bidder one's winning bid value but below winning bid * (1 + bid increment)
		error_log( "****************************************" );
		$bid_value	= 20.9;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 1)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be $bid_value)" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder two bid above own winning bid value and above winning bid * (1 + bid increment)
		error_log( "****************************************" );
		$bid_value	= 25;
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 4)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be $bid_value * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		// Have bidder two bid equal to his current max bid
		error_log( "****************************************" );
		$bid_status = $bid_system_test->form_submission( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 6)" );
		$winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "winning_bid = ". $winning_bid . " (should be $bid_value * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );
	}

	if ( false ) { // test form_validation
		// Control entry in bids table and post meta table, on mythical post id with mythical bidder
		$winning_bid = $bid_system_test->update_winning_bid( 1, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be 11.23 * " . BID_INCREMENT . ")" );
		$current_winning_bid = $bid_system_test->get_winning_bid_value( $post_id );
		error_log( "current_winning_bid = " . print_r($current_winning_bid, true) . " (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$wpdb->insert( $wpdb->bids, array( 'post_id' => $post_id, 'bidder_id' => $bidder_id, 'bid_value' => $bid_value, 'bid_date' => $bid_date, 'bid_date_gmt' => $bid_date_gmt ) );

		//$bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );

		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 6)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$bid_value	= 2;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 5)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$bid_value	= 20;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 4)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$wpdb->insert( $wpdb->bids, array( 'post_id' => $post_id, 'bidder_id' => $bidder_id, 'bid_value' => $bid_value, 'bid_date' => $bid_date, 'bid_date_gmt' => $bid_date_gmt ) );

		$bidder_id	= 111;

		$bid_value	= 0.1;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 3)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be 11.23 * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$bid_value	= 10;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 2)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be $bid_value * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$bid_value	= 20;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 2)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$bid_value	= 20.9;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 1)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be $bid_value * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );

		$bid_value	= 25;
		$bid_status = $bid_system_test->form_validation( $post_id, $bid_value, $bidder_id );
		error_log( "bid_status = $bid_status (should be 4)" );
		$winning_bid = $bid_system_test->update_winning_bid( $bid_status, $post_id, $bid_value, $bidder_id );
		error_log( "winning_bid = $winning_bid (should be $bid_value * " . BID_INCREMENT . ")" );
		error_log( "winning_bidder = " . $bid_system_test->get_winning_bid( $post_id )->bidder_id );
	}

	{ // clean up mythical post
		$wpdb->query( "DELETE FROM $wpdb->bids WHERE post_id = $post_id" );
		delete_post_meta( $post_id, 'winning_bid_value' );
		delete_post_meta( $post_id, 'winning_bidder_id' );
	}
}
//add_action( 'wp_footer', 'auction_test' );


?>