/*
Plugin Name: Captcha Free Anti Spam for Contact Form 7 (Simple No-Bot)
Plugin URI: http://www.lilaeamedia.com/simple-no-bot/
Description: Simple, lightweight, no captcha, no configuration. Just works.
Version: 2.2.4
Author: Lilaea Media
License: GPLv2
(c) 2019 J Fleming Lilaea Media LLC
*/
;(function($){
    'use strict';
    
    /**
     * Bind form inputs to mouse/keyboard/touch events
     */
    function init(){
        var selector = '.wpcf7-form input,.wpcf7-form textarea,.wpcf7-form select,.snb-form input,.snb-form textarea,.snb-form select';
        if ( navigator.platform.match( /iPhone/i ) || navigator.maxTouchPoints > 0 ){
            document.addEventListener( 'touchmove', touchevent );
        } else {
            $( document ).on( 'mousemove', mousemove );
        }
        $( selector ).on( 'mousedown', mousedown ); // touchstart
        if ( navigator.maxTouchPoints ){
            $( selector ).on( 'keypress', keydown );
        } else {
            $( selector ).on( 'keydown', keydown );
        }
        $( selector ).on( 'focus', focus );
        if ( window.snbvars.disableSubmit ){
            $( '.wpcf7-form input[type="submit"]' ).prop( 'disabled', true );
        }
        $( document ).on( 'wpcf7submit', resetVars );
        
        // get the profile from navigator data, not the useless user agent
        nav = {
            p:  navigator.platform,
            c:  navigator.hardwareConcurrency ? navigator.hardwareConcurrency : null,
            t:  navigator.maxTouchPoints ? navigator.maxTouchPoints : 0,
            vh: window.innerHeight,
            vw: window.innerWidth
        };
    }
    
    function resetVars(){
        interacted  = 0;
        eventCount  = 0;
        moves       = 0;
        distance    = 0;
        lastX       = 0;
        lastY       = 0;
        allEvents   = [];
        $( 'input[name="snb-token"]' ).val( '' );
        if ( window.snbvars.disableSubmit ){
            $( '.wpcf7-form input[type="submit"]' ).prop( 'disabled', true );
        }
    }
        
    function getEvent( e, code ){
        eventCount++;
        var target = $( e.target ).closest( 'input,select,textarea' ),
            descr = target.attr( 'type' ) ? target.attr( 'type' ) : 
                        target.prop( 'tagName' ),
            name  = $( target ).attr( 'name' ) ? $( target ).attr( 'name' ) : '',

            key = 'k' === code ? e.which : '',
            event = { 
                code:  code, 
                descr: descr.toLowerCase(), 
                name:  name, 
                key:   key,
            };
        test( event );
    }
    
    /**
     * Event Listeners
     */
    function mousedown( e ){
        getEvent( e, 'm' );
    }
    
    function mousemove( e ){
        if ( !interacted ){
            ++moves;
            if ( lastX || lastY ){
                var dX      = Math.abs( e.clientX - lastX ),
                    dY      = Math.abs( e.clientY - lastY ),
                    thisD   = dX + dY;
                // we are measuring city distance, not crow distance
                distance += thisD;
            } 
            lastX = e.clientX;
            lastY = e.clientY;
        }
    }
    
    /**
     * convert touches to mousemoves
     */
    function touchevent( e ){
        if ( !interacted ){
            var touches = e.changedTouches,
                event = { clientX: touches[ 0 ].clientX, clientY: touches[ 0 ].clientY };
            mousemove( event );
        }
    }

    function focus( e ){
        getEvent( e, 'f' );
    }
    
    function keydown( e ){
        getEvent( e, 'k' );
    }
    
    function test( thisEvent ){
        if ( !interacted ){
            allEvents.push( thisEvent );
            if ( eventCount >= window.snbvars.minEvents ){
                interacted++;
                getToken();
            }
        }
    }
    
    /**
     * Pass navigator object over XHR and retrieve unique token from server.
     * Inject new input into any/all contact forms to pass back as verification.
     * Input contains unique token.
     * Only do this once until form comes back as OK.
     */
    function getToken(){
        if ( pending ) {
            return;
        }
        pending++;
        var postdata = {
                action: 'snb_get_token',
                data:   toPad( window.snbvars.verify, JSON.stringify( 
                    { 
                        nav: nav,
                        moves: { moves: moves, distance: distance },
                        events: allEvents,
                    } 
                ) ),
                verify: window.snbvars.verify
            };
        // console.log( postdata );
        $.ajax( {
            url:        window.snbvars.ajaxurl,
            type:       'post',
            dataType:   'text',
            data:       postdata
            
        } ).done( function( response ){
            // console.log( 'response: ' + response );
            if ( pending ){
                pending = 0;
                if ( response ){
                    if ( !appended ){
                        $( '.wpcf7-form, .snb-form form, form.snb-form' ).each( function( ndx, el ){
                            $( el ).append( '<input type="hidden" name="snb-token" />' );
                            forms++;
                        } );
                        appended++;
                    }
                    if ( forms ){
                        $( '.wpcf7-form input[name="snb-token"], .snb-form input[name="snb-token"]' ).val( response );
                    }
                    $( '.wpcf7-form input[type="submit"], .snb-form input[type="submit"]' ).prop( 'disabled', false );
                }
            }
        } ).fail( function(){
            pending = 0;
            // console.log( 'ajax failed.' );
        } );
    }

    function toPad( token, string ){
        var mask = '', pad = '', enc, len = string.length;
        while ( mask.length < len ){
            mask += token;
        }
        for( var i = 0; i < len; i++ ){
            pad += String.fromCharCode( string.charCodeAt( i ) ^ mask.charCodeAt( i ) );
        }
        enc = window.btoa( pad );
        return enc;
    }
    
    /**
     * initialize vars
     */
    var forms       = 0,    // are there contact forms on page?
        interacted  = 0,    // has user triggered minimum form events?
        appended    = 0,    // has token input field been injected?
        pending     = 0,    // is XHR awaiting response?
        eventCount  = 0,    // number of form events so far
        moves       = 0,    // number of mouse events before minimum form events
        distance    = 0,    // pixels mouse has traveled before minimum form events
        lastX       = 0,    // last mouse horizontal position
        lastY       = 0,    // last mouse vertical position
        allEvents   = [],   // information about recorded form events
        nav         = {};   // navigator data
    
    $( document ).ready( function(){
        init(); // wait until all forms are loaded
    });
    
})(jQuery);