<?php
/*
Plugin Name: Invisible Anti Spam for Contact Form 7 (Simple No-Bot)
Plugin URI: http://www.lilaeamedia.com/simple-no-bot/
Description: Simple, lightweight, no captcha, no configuration. Just works.
Version: 2.2.5
Author: Lilaea Media
License: GPLv2
(c) 2019 J Fleming Lilaea Media LLC
*/

if ( !class_exists( 'SimpleNoBot' ) ):

class SimpleNoBot {
        
    /**
     * define WP action/filter hooks
     */
    function __construct(){
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_nopriv_snb_get_token', array( $this, 'get_token' ) );
        add_action( 'wp_ajax_snb_get_token', array( $this, 'get_token' ) );
        add_filter( 'wpcf7_spam', array( $this, 'validate_session' ) );
        // v.2.0 filter for any form
        add_filter( 'snb_test_spam', array( $this, 'validate_session' ), 10, 2 );
        if ( isset( $_REQUEST[ 'snb_flush' ] ) )
            add_action( 'init', array( $this, 'reset_spam_ips' ) );
    }
    
    /**
     * load the script and set locale vars
     */
    function enqueue(){
        wp_enqueue_script( 'snbvars', plugin_dir_url( __FILE__ ) . 'snb' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js', array( 'jquery' ), SNB_VERSION, TRUE );
        wp_localize_script( 
            'snbvars', 
            'snbvars', 
            array( 
                'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                'minEvents'     => SNB_MIN_EVENTS > 2 ? SNB_MIN_EVENTS : 2,
                'disableSubmit' => SNB_DISABLE_SUBMIT,
                'verify'        => wp_create_nonce( 'snbvars' ),
            ) 
        );
    }
                   
    /**
     * Get token (unique id) and return to browser. Protocol:
     * 1. verify nonce (which also verifies timestamp)
     * 2. create unique id
     * 3. save as transient with IP + unique id as key
     * 4. respond to browser with token.
     */
    function get_token(){
        if ( $this->is_spam_ip() 
            && defined( 'SNB_BLOCK_SPAM_IPS' ) 
            && SNB_BLOCK_SPAM_IPS ):
            $this->log_debug( __METHOD__, 'spam ip: reporting as spam.' );
            die();
        endif;
        // check nonce
        if ( check_ajax_referer( 'snbvars', 'verify' ) ):
            // decode data with it
            if ( $data = json_decode( stripslashes( $this->fromPad( $_POST[ 'verify' ], $_POST[ 'data' ] ) ) ) ):
                //create unique id
                $uniqId = uniqid();
                // examine data
                $flags = $this->turing( $data );
                // save uniqId as transient 
                set_transient( $this->session_token( $uniqId ), $flags, SNB_SESSION_LIFESPAN ); // token valid for SNB_SESSION_LIFESPAN seconds
                $this->log_debug( __METHOD__, 'token: ' . $uniqId . ' flags: ' . $flags );
                // return the uniqId
                die( $uniqId );
            else:
                $this->log_debug( __METHOD__, 'no data object!' );
            endif;
        else:
            $this->log_debug( __METHOD__, 'invalid token request' );
        endif;
        // don't add to spam list - there may valid reason for failing here
        die();
    }
    
    /**
     * check remote ip against transient list of known offenders
     * return true if ip is in list, false otherwise.
     */
    function is_spam_ip( $unset = FALSE ){
        if ( $spam_ips = get_transient( 'snb_spam_ips' ) ):
            if ( FALSE !== array_search( $_SERVER[ 'REMOTE_ADDR' ], $spam_ips ) ):
                $this->log_debug( __METHOD__, '***SPAM IP DETECTED!!!*** ' . $_SERVER[ 'REMOTE_ADDR' ] );
                return TRUE;
            endif;
        endif;
        return FALSE;
    }
    
    /**
     * add offending ip to transient list of ips. rotate oldest if greater than maximum number of ips
     */
    function add_spam_ip(){
        if ( $this->is_spam_ip() )
            return;
        if ( !( $spam_ips = get_transient( 'snb_spam_ips' ) ) )
            $spam_ips = array();
        array_push( $spam_ips, $_SERVER[ 'REMOTE_ADDR' ] );
        $count = count( $spam_ips );
        if ( $count > SNB_MAX_SPAM_IPS )
            array_shift( $spam_ips );
        $this->log_debug( __METHOD__, 'adding spam ip ' . $count . ': ' . $_SERVER[ 'REMOTE_ADDR' ] );
        set_transient( 'snb_spam_ips', $spam_ips, SNB_SPAM_IP_LIFESPAN );
    }
    
    function reset_spam_ips(){
        if ( current_user_can( 'administrator' ) ):
            $this->log_debug( __METHOD__, 'clearing spam ips' );
            delete_transient( 'snb_spam_ips' );
        endif;
    }
    
    /**
     * link unique id to remote ip to prevent reuse of tokens across multiple requests
     */
    function session_token( $token ){
        return 'snbvars' . str_replace( '.', '', $_SERVER[ 'REMOTE_ADDR' ] ) . $token;
    }
    
    /**
     * spam tests are run after input validation,
     * so this won't affect legitimate users with invalid inputs.
     */
    function validate_session( $spam, $arg = 'contact-form-7' ) {
        $this->log_debug( __METHOD__, 'request: ' . print_r( $_REQUEST, TRUE ) );
        if ( $this->is_spam_ip() 
            && defined( 'SNB_BLOCK_SPAM_IPS' ) 
            && SNB_BLOCK_SPAM_IPS ):
            $this->log_debug( __METHOD__, 'spam ip: reporting as spam.' );
            return TRUE;
        endif;
        if ( isset( $_POST[ 'snb-token' ] ) ):
            //$this->log_debug( __METHOD__, 'snb-token: ' . $_POST[ 'snb-token' ] );
            $token = sanitize_text_field( $_POST[ 'snb-token' ] );
            if ( FALSE === ( $flags = get_transient( $this->session_token( $token ) ) ) ):
                // no corresonding transient is a non-starter
                $this->log_debug( __METHOD__, 'no session!' );
                return TRUE;
            endif;
        
            /**
            // field tests - not using these for high risk of false positives but listing for reference
            // name contains a number - Hi, jake56! You look like you were harvested! +2
            // email domain NOT net, com, org, edu, gov +1
            // email domain is subdomain - most emails are TLDs +1
            // email username contains name (-) -- bonus points for normalizing your spam list -1
            // textarea contains 1, 2 or 3 words -- rude and probably spam +1
            // textarea contains > 1 url -- almost always spam +2
            */
            //$this->log_debug( __METHOD__, 'checking flags' );
            if ( $flags > SNB_SPAM_THRESHOLD ):
                $this->log_debug( __METHOD__, $flags . ' flags over threshold of ' . SNB_SPAM_THRESHOLD );
                // soft fail this -- may be legitimate
                return TRUE;
            else:
                $this->log_debug( __METHOD__, 'this message seems OK.' );
                delete_transient( $this->session_token( $token ) );
                return $spam;
            endif;
        else:
            $this->log_debug( __METHOD__, ' invalid ' . $arg . ' submission: no token' );
        endif;
        $this->add_spam_ip();
		return TRUE;
	}

    /**
     * super secret Turing device
     * this is likely to change as bots evolve
     */
    function turing( $data ){
        $flags = 0;
        $this->log_debug( __METHOD__, 'data: ' . print_r( $data, TRUE ) );
        // test navigator
        if ( intval( $data->nav->c )
           && $data->nav->c > 12 ):
            // powerful computer > 6 cores uncommon
            $this->log_debug( __METHOD__, 'more than 6 processors!' );
            ++$flags;
        endif;
        if ( empty( $data->nav->p ) ):
            $this->log_debug( __METHOD__, 'platform missing' );
            $flags += 2;
        endif;
        if ( preg_match( '{Linux}i', $data->nav->p ) && !$data->nav->t ):
            // linux with touch common (android) but unlikely to use linux desktop, more likely a server
            $this->log_debug( __METHOD__, 'linux desktop' );
            $flags += 2;
        endif;
        if ( '600' == $data->nav->vh && '800' == $data->nav->vw ):
            // pefectly sized windows extremely rare in real life
            $this->log_debug( __METHOD__, 'perfect window' );
            $flags += 2;
        endif;
        // test events
        $eventcount = 0;
        $lastevent = NULL;
        foreach ( $data->events as $thisevent ):
            ++$eventcount;
            $thisevent->isNew = empty( $lastevent ) ? TRUE : $lastevent->name != $thisevent->name;
            if ( 1 == $eventcount && 'f' == $thisevent->code ):
                // first event is a focus -- may be keyboard only user but flag in case
                $this->log_debug( __METHOD__, 'focus first event' );
                $flags += 1;
                // first event is a focus and phone - red flag!
                if ( preg_match( '{linux}i', $data->nav->p ) && $data->nav->t 
                    || preg_match( '{iphone}i', $data->nav->p ) ):
                    $this->log_debug( __METHOD__, 'phone + focus first' );
                    $flags += 2;
                endif;
            endif;
            if ( !empty( $lastevent ) ):
                if ( $lastevent->isNew
                    && 'f' == $lastevent->code
                    && 'm' == $thisevent->code
                    && !$data->nav->t
                    && in_array( $thisevent->descr, array( 'text', 'tel', 'url', 'number', 'password', 'textarea' ) )
                    ):
                    // unlikely to use a mouse after tabbing to text field 
                    $this->log_debug( __METHOD__, 'mousedown after non-mouse focus on text field' );
                    $flags += 2;
                endif;
                if ( 'f' == $lastevent->code
                    && 'f' == $thisevent->code
                    //&& !$data->moves->moves
                    ):
                    // unlikely to have consecutive focus without a mouse or keystroke 
                    $this->log_debug( __METHOD__, 'consecutive focus without key/mouse/touch input?' );
                    $flags += 2;
                endif;
            endif;
            //$this->log_debug( __METHOD__, 'event ' . $eventcount . ': ' . json_encode( $thisevent ) );
            $lastevent = $thisevent;
        endforeach;
        // test mouse/touch
        // It is not unusual to have zero mouse movements (keyboard-only user) but a single value suggests automation
        if ( $data->moves->moves && !$data->moves->distance ):
            $this->log_debug( __METHOD__, 'mousedown but no mouse/touch moves' );
            $flags += 2;
        endif;

        $this->log_debug( __METHOD__, 'flags: ' . $flags );
        return $flags;
    }
    
    function fromPad( $token, $string ){
        $mask   = $dec = '';
        $pad    = base64_decode( $string );
        $len    = strlen( $pad );
        while ( strlen( $mask ) < $len )
            $mask .= $token;
        for( $i = 0; $i < $len; $i++ )
            $dec .= chr( ord( substr( $pad, $i, 1 ) ) ^ ord( substr( $mask, $i, 1 ) ) );
        return $dec;
    }
    
    function log_debug( $fn, $msg ){
        if ( SNB_DEBUG ):
            @file_put_contents( 
                SNB_DIR . '/debug.txt', 
                implode( ':' , array(
                    current_time( 'mysql' ),
                    $_SERVER[ 'REMOTE_ADDR' ],
                    $_SERVER['REQUEST_URI'],
                    $fn,
                    $msg 
                ) ) . "\n", 
                FILE_APPEND );
        endif;
    }
}

defined( 'SNB_DEBUG' )              || define( 'SNB_DEBUG', FALSE );
defined( 'SNB_SPAM_THRESHOLD' )     || define( 'SNB_SPAM_THRESHOLD', 2 );
defined( 'SNB_DISABLE_SUBMIT' )     || define( 'SNB_DISABLE_SUBMIT', FALSE );
defined( 'SNB_MIN_EVENTS' )         || define( 'SNB_MIN_EVENTS', 2 );
defined( 'SNB_BLOCK_SPAM_IPS' )     || define( 'SNB_BLOCK_SPAM_IPS', FALSE );
defined( 'SNB_SPAM_IP_LIFESPAN' )   || define( 'SNB_SPAM_IP_LIFESPAN', 60 * 60 * 24 * 30 ); // 30 days
defined( 'SNB_MAX_SPAM_IPS' )       || define( 'SNB_MAX_SPAM_IPS', 100 );
defined( 'SNB_SESSION_LIFESPAN' )   || define( 'SNB_SESSION_LIFESPAN', 60 * 30 ); // 30 minutes
define( 'SNB_VERSION', '2.2.4' );
define( 'SNB_URL', plugin_dir_url( __FILE__ ) );
define( 'SNB_DIR', dirname( __FILE__ ) );

new SimpleNoBot();

endif;
