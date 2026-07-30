/**
 * Opens the "Mark paid" modal for pending Venmo donations and submits the
 * (optional) payment details, marking the donation complete on success.
 *
 * Config is provided via the localized `bhVenmoMarkPaid` object.
 */
( function () {
	'use strict';

	const config = window.bhVenmoMarkPaid || {};

	const modal = document.getElementById( 'bh-venmo-mark-paid-modal' );
	const form = document.getElementById( 'bh-venmo-mark-paid-form' );

	if ( ! modal || ! form ) {
		return;
	}

	const donationIdField = form.querySelector( 'input[name="donation_id"]' );
	const errorEl = modal.querySelector( '.bh-venmo-modal__error' );
	const submitButton = form.querySelector( 'button[type="submit"]' );

	function openModal( donationId ) {
		donationIdField.value = donationId;
		hideError();
		modal.hidden = false;
		const firstInput = form.querySelector( 'input[name="venmo_username"]' );
		if ( firstInput ) {
			firstInput.focus();
		}
	}

	function closeModal() {
		modal.hidden = true;
		form.reset();
	}

	function showError( message ) {
		errorEl.textContent = message;
		errorEl.hidden = false;
	}

	function hideError() {
		errorEl.textContent = '';
		errorEl.hidden = true;
	}

	// Open the modal from any "Mark paid" link (event-delegated so it works
	// for every row without per-row listeners).
	document.addEventListener( 'click', function ( event ) {
		const trigger = event.target.closest( '.bh-venmo-mark-paid' );
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		openModal( trigger.getAttribute( 'data-donation-id' ) );
	} );

	// Close on overlay / cancel / Escape.
	modal.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-bh-venmo-close]' ) ) {
			event.preventDefault();
			closeModal();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! modal.hidden ) {
			closeModal();
		}
	} );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		hideError();
		submitButton.disabled = true;

		const body = new URLSearchParams();
		body.append( 'action', config.action );
		body.append( 'security', config.nonce );
		body.append( 'donation_id', donationIdField.value );
		body.append( 'venmo_username', form.venmo_username.value );
		body.append( 'transaction_id', form.transaction_id.value );
		body.append( 'payment_date', form.payment_date.value );
		body.append( 'payment_time', form.payment_time.value );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result && result.success ) {
					// Reload so the Status column re-renders as "Completed", and
					// pass the id so the page shows a "marked paid" admin notice.
					const url = new URL( window.location.href );
					url.searchParams.set(
						'bh-venmo-marked-paid',
						result.data.donationId
					);
					window.location.assign( url.toString() );
					return;
				}
				const message =
					result && result.data && result.data.message
						? result.data.message
						: 'Unable to mark the donation paid.';
				showError( message );
				submitButton.disabled = false;
			} )
			.catch( function () {
				showError( 'Unable to mark the donation paid.' );
				submitButton.disabled = false;
			} );
	} );
} )();
