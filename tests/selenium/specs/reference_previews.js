const assert = require( 'assert' ),
	page = require( '../pageobjects/popups.page' );

describe( 'Dwelling on a valid reference link', function () {
	before( function () {
		page.setup();
	} );

	it( 'I should see a reference preview', function () {
		if ( !page.hasReferencePopupsEnabled() ) {
			this.skip();
		}
		page.open();
		page.dwellReferenceLink( 1 );
		assert( page.seeReferencePreview(), 'Reference preview is shown.' );
		assert( !page.seeScrollableReferencePreview(), 'Reference preview is not scrollable.' );
		assert( !page.seeFadeoutOnReferenceText(), 'Reference preview has no fading effect' );
	} );

	it( 'Abandoning link hides reference preview', function () {
		if ( !page.hasReferencePopupsEnabled() ) {
			this.skip();
		}
		page.open();
		page.dwellReferenceLink( 1 );
		page.abandonLink();
		assert( page.doNotSeeReferencePreview(), 'Reference preview is kept hidden.' );
	} );

	it( 'References with lots of text are scrollable and fades', function () {
		if ( !page.hasReferencePopupsEnabled() ) {
			this.skip();
		}
		page.open();
		page.dwellReferenceLink( 2 );
		assert( page.seeScrollableReferencePreview(), 'Reference preview has a fading effect' );
		assert( page.seeFadeoutOnReferenceText(), 'Reference preview has a fading effect' );
	} );
} );
