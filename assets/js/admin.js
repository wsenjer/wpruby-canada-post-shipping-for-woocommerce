/* global wprubyCps */
( function () {
	'use strict';

	if ( 'undefined' === typeof wprubyCps ) {
		return;
	}

	function format( template, a, b ) {
		return template.replace( '%1$d', a ).replace( '%2$d', b );
	}

	/*
	 * Services table (zone settings page and the zone modal). Delegated, because
	 * WooCommerce renders the modal after the page loads.
	 */
	function refreshGroup( group ) {
		var toggles = group.querySelectorAll( '.wpruby-cps-services__toggle' );
		var on = 0;
		toggles.forEach( function ( toggle ) {
			var row = toggle.closest( 'tr' );
			if ( toggle.checked ) {
				on++;
			}
			if ( row ) {
				row.classList.toggle( 'is-off', ! toggle.checked );
			}
		} );
		var all = group.querySelector( '.wpruby-cps-services__all' );
		if ( all ) {
			all.checked = on === toggles.length;
			all.indeterminate = on > 0 && on < toggles.length;
		}
		var count = group.querySelector( '.wpruby-cps-services__count' );
		if ( count ) {
			count.textContent = format( wprubyCps.countOn, on, toggles.length );
		}
	}

	function refreshAll( root ) {
		( root || document ).querySelectorAll( '.wpruby-cps-services__group' ).forEach( refreshGroup );
	}

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var group = target.closest && target.closest( '.wpruby-cps-services__group' );
		if ( ! group ) {
			return;
		}
		if ( target.classList.contains( 'wpruby-cps-services__all' ) ) {
			group.querySelectorAll( '.wpruby-cps-services__toggle' ).forEach( function ( toggle ) {
				toggle.checked = target.checked;
			} );
		}
		refreshGroup( group );
	} );

	// The modal's markup arrives later; set the "some selected" states when it does.
	if ( 'MutationObserver' in window ) {
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.querySelectorAll ) {
						refreshAll( node );
					}
				} );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	}
	refreshAll();

	/*
	 * Settings page: the contract number only matters for commercial accounts.
	 */
	var accountType = document.getElementById( 'woocommerce_canada_post_settings_account_type' );
	var contract = document.getElementById( 'woocommerce_canada_post_settings_contract_number' );
	if ( accountType && contract ) {
		var contractRow = contract.closest( 'tr' );
		var syncContract = function () {
			if ( contractRow ) {
				contractRow.hidden = 'commercial' !== accountType.value;
			}
		};
		accountType.addEventListener( 'change', syncContract );
		syncContract();
	}

	/*
	 * Settings page: Test connection.
	 */
	var button = document.getElementById( 'wpruby-cps-test-connection' );
	var result = document.getElementById( 'wpruby-cps-test-result' );
	var status = document.getElementById( 'wpruby-cps-status' );
	if ( ! button || ! result ) {
		return;
	}

	function show( state, message ) {
		result.hidden = false;
		result.className = 'wpruby-cps-result' + ( state ? ' is-' + state : '' );
		result.textContent = message;
	}

	button.addEventListener( 'click', function () {
		var body = new FormData();
		body.append( 'action', wprubyCps.action );
		body.append( '_ajax_nonce', wprubyCps.nonce );

		button.disabled = true;
		button.classList.add( 'is-busy' );
		show( 'busy', wprubyCps.checking );

		fetch( wprubyCps.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				var ok = !! ( json && json.success );
				show( ok ? 'ok' : 'error', json && json.data && json.data.message ? json.data.message : wprubyCps.failed );
				if ( ok && status && status.classList.contains( 'wpruby-cps-pill--ready' ) ) {
					status.textContent = wprubyCps.connected;
				}
			} )
			.catch( function () {
				show( 'error', wprubyCps.failed );
			} )
			.finally( function () {
				button.disabled = false;
				button.classList.remove( 'is-busy' );
			} );
	} );
}() );
