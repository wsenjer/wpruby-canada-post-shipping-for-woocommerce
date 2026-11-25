/* global wprubyCps */
( function () {
	'use strict';

	var button = document.getElementById( 'wpruby-cps-test-connection' );
	var result = document.getElementById( 'wpruby-cps-test-result' );
	if ( ! button || ! result || 'undefined' === typeof wprubyCps ) {
		return;
	}

	button.addEventListener( 'click', function () {
		var body = new FormData();
		body.append( 'action', wprubyCps.action );
		body.append( '_ajax_nonce', wprubyCps.nonce );

		button.disabled = true;
		result.className = '';
		result.textContent = wprubyCps.checking;

		fetch( wprubyCps.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				result.className = json && json.success ? 'wpruby-cps-ok' : 'wpruby-cps-error';
				result.textContent = json && json.data && json.data.message ? json.data.message : wprubyCps.failed;
			} )
			.catch( function () {
				result.className = 'wpruby-cps-error';
				result.textContent = wprubyCps.failed;
			} )
			.finally( function () {
				button.disabled = false;
			} );
	} );
}() );
