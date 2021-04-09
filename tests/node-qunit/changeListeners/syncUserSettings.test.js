import syncUserSettings from '../../../src/changeListeners/syncUserSettings';

QUnit.module( 'ext.popups/changeListeners/syncUserSettings', {
	beforeEach() {
		this.userSettings = {
			storePreviewCount: this.sandbox.spy(),
			storePagePreviewsEnabled: this.sandbox.spy(),
			storeReferencePreviewsEnabled: this.sandbox.spy()
		};

		this.changeListener = syncUserSettings( this.userSettings );
	}
} );

QUnit.test(
	'it shouldn\'t update the storage if the preview count hasn\'t changed',
	function ( assert ) {
		const newState = {
			eventLogging: {
				previewCount: 222
			}
		};

		this.changeListener( undefined, newState );

		// ---

		const oldState = $.extend( true, {}, newState );

		this.changeListener( oldState, newState );

		assert.notOk(
			this.userSettings.storePreviewCount.called,
			'The preview count is unchanged.'
		);
	}
);

QUnit.test( 'it should update the storage if the previewCount has changed', function ( assert ) {
	const oldState = {
		eventLogging: {
			previewCount: 222
		}
	};

	const newState = $.extend( true, {}, oldState );
	++newState.eventLogging.previewCount;

	this.changeListener( oldState, newState );

	assert.ok(
		this.userSettings.storePreviewCount.calledWith( 223 ),
		'The preview count is updated.'
	);
} );

QUnit.test(
	'it shouldn\'t update the storage if the enabled state hasn\'t changed',
	function ( assert ) {
		const newState = {
			preview: {
				enabled: true
			}
		};

		this.changeListener( undefined, newState );

		// ---

		const oldState = $.extend( true, {}, newState );

		this.changeListener( oldState, newState );

		assert.notOk(
			this.userSettings.storePagePreviewsEnabled.called,
			'The user setting is unchanged.'
		);
	}
);

QUnit.test( 'it should update the storage if the enabled flag has changed', function ( assert ) {
	const oldState = {
		preview: {
			enabled: true
		}
	};

	const newState = $.extend( true, {}, oldState );
	newState.preview.enabled = false;

	this.changeListener( oldState, newState );

	assert.ok(
		this.userSettings.storePagePreviewsEnabled.calledWith( false ),
		'The user setting is disabled.'
	);
} );
