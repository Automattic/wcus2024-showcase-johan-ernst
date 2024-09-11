/* global JohanErnstMapBlockData */

// External dependencies.
import Globe from 'globe.gl';

{
	const { apiURL, markers, nonce } = JohanErnstMapBlockData;

	/**
	 * The default "altitude" or zoom level of the globe.
	 */
	const defaultAltitude = 3.0;

	/**
	 * The increment used when changing altitude with zoom buttons.
	 */
	const altitudeIncrement = 0.5;

	/**
	 * The minimum altitude to allow when zooming in.
	 */
	const minAltitude = 1;

	/**
	 * The maximum altitude to allow when zooming out.
	 */
	const maxAltitude = 6;

	/**
	 * The minimum distance the camera can be from the globe.
	 *
	 * This and maximum distance work alongside altitude, but do not
	 * appear to have a direct correlation. Funny math.
	 */
	const minDistance = 200;

	/**
	 * The maximum distance the camera can be from the globe.
	 */
	const maxDistance = 700;

	const block = document.querySelector( '.wp-block-johan-ernst-map' );
	const locations = block.querySelectorAll( 'a.location' );
	const zoomControls = document.querySelectorAll(
		'.map-zoom-controls button'
	);
	const accordions = block.querySelectorAll( '.js-location-accordion' );
	const mapWrapper = block.querySelector( '.map' );
	const modalTemplate = block.querySelector( '.post-modal-template' );

	const parser = new DOMParser();

	const introduction = block.querySelector( '.introduction' );

	/**
	 * Track whether the introduction has been viewed or dismissed
	 * and is ready for removal.
	 */
	let isIntroductionDismissed = false;

	/**
	 * Track whether the map has loaded.
	 */
	let isMapLoaded = false;

	/**
	 * Remove the introduction message.
	 */
	const removeIntroduction = () => {
		if ( false === isMapLoaded ) {
			isIntroductionDismissed = true;
			return;
		}

		document
			.querySelector( 'body' )
			.classList.add( 'introduction-dismissed' );
		introduction.removeEventListener( 'animationend', removeIntroduction );

		// Remove the HTML entirely after 2 seconds, to allow the animation to finish.
		setTimeout( () => {
			introduction.remove();
		}, 2000 );

		localStorage.setItem( 'loadingTextViewed', 'true' );
	};

	if ( localStorage.getItem( 'loadingTextViewed' ) ) {
		removeIntroduction();
	} else {
		setTimeout( removeIntroduction, 10000 );
	}

	document
		.querySelectorAll( '.loading-audio-controls button' )
		.forEach( ( button ) => {
			button.addEventListener( 'click', removeIntroduction );
		} );

	/**
	 * Setup remaining UX when the globe is ready.
	 */
	const handleGlobeReady = () => {
		isMapLoaded = true;

		world.pointOfView( { altitude: defaultAltitude } );
		world.controls().minDistance = minDistance;
		world.controls().maxDistance = maxDistance;

		// Alter the disabled state of zoom buttons based on altitude.
		world.onZoom( ( { altitude } ) => {
			const zoomOut = document.querySelector(
				'.map-zoom-controls .button-zoom-out'
			);
			const zoomIn = document.querySelector(
				'.map-zoom-controls .button-zoom-in'
			);

			if ( altitude > minAltitude ) {
				zoomIn.disabled = false;
			} else if ( altitude <= minAltitude ) {
				zoomIn.disabled = true;
			}

			if ( altitude < maxAltitude ) {
				zoomOut.disabled = false;
			} else if ( altitude >= maxAltitude ) {
				zoomOut.disabled = true;
			}
		} );

		document.body.classList.add( 'map-loaded' );

		locations.forEach( ( location ) =>
			location.addEventListener( 'click', handleLocationClick )
		);

		zoomControls.forEach( ( control ) =>
			control.addEventListener( 'click', handleZoomClick )
		);

		if ( isIntroductionDismissed ) {
			removeIntroduction();
		}
	};

	const world = Globe( { animateIn: false } )
		.atmosphereColor( '#a5b1c2' )
		.backgroundColor( 'rgba(0,0,0,0)' )
		.bumpImageUrl(
			// Source https://planetpixelemporium.com/earth8081.html
			// Copyright and acceptable use statement: https://planetpixelemporium.com/planets.html
			'/wp-content/themes/johan-ernst/assets/img/earth-bump-7200x3600.jpg'
		)
		.globeImageUrl(
			// Source https://commons.wikimedia.org/wiki/File:Whole_world_-_land_and_oceans_12000.jpg
			'/wp-content/themes/johan-ernst/assets/img/blue-marble-2002-8192x4096.jpg'
		)
		.htmlElementsData( markers )
		.htmlElement( ( d ) => {
			const marker = document.createElement( 'a' );

			marker.classList.add( 'map-marker' );
			marker.classList.add( d.type );

			marker.dataset.type = d.type;
			marker.dataset.postId = d.id;

			marker.href = d.href;

			marker.append( getMarkerIcon( d.type ) );
			marker.append( getMarkerDetails( d ) );

			if ( 'rewild' === d.type ) {
				marker.addEventListener( 'click', handleMarkerClick );
			}

			return marker;
		} )
		.onGlobeReady( handleGlobeReady )( mapWrapper );

	window.addEventListener( 'resize', ( event ) => {
		world.width( [ event.target.innerWidth ] );
		world.height( [ event.target.innerHeight ] );
	} );

	/**
	 * Handle location clicks.
	 *
	 * @param {MouseEvent} e The event object.
	 */
	function handleLocationClick( e ) {
		e.preventDefault();

		const { target } = e;

		const coordinates = target.dataset.coordinates.split( ',' );

		if ( ! coordinates || coordinates.length < 2 ) {
			return;
		}

		world.pointOfView(
			{
				lat: parseFloat( coordinates[ 0 ] ),
				lng: parseFloat( coordinates[ 1 ] ),
				altitude: world.pointOfView().altitude, // Keep the current altitude.
			},
			500
		);

		const markerList = target.nextElementSibling;

		accordions.forEach( ( accordion ) => {
			if ( accordion !== markerList ) {
				accordion.hidden = true;
			} else {
				accordion.hidden = ! accordion.hidden;
			}
		} );
	}

	/**
	 * Handle the zoom controls.
	 *
	 * @param {MouseEvent} e
	 */
	const handleZoomClick = ( e ) => {
		e.preventDefault();

		if ( e.target.classList.contains( 'button-zoom-in' ) ) {
			world.pointOfView(
				{
					lat: world.pointOfView().lat,
					lng: world.pointOfView().lng,
					altitude: world.pointOfView().altitude - altitudeIncrement,
				},
				300
			);
		} else {
			world.pointOfView(
				{
					lat: world.pointOfView().lat,
					lng: world.pointOfView().lng,
					altitude: world.pointOfView().altitude + altitudeIncrement,
				},
				300
			);
		}
	};

	const displayCardModal = ( postId ) => {
		const modal = document.querySelector( `#post-${ postId }-modal` );

		if ( modal ) {
			openModal( modal );

			return;
		}

		const endpoint = 'exploration';
		const params =
			'?&_embed=wp:term&_fields=content,featured_image,id,title,_embedded';

		fetch( apiURL + endpoint + '/' + postId + params, {
			headers: { 'X-WP-Nonce': nonce },
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => openModal( createModal( response ) ) );
	};
	window.displayCardModal = displayCardModal;

	/**
	 * Handle marker clicks.
	 *
	 * @param {MouseEvent} e The event object.
	 */
	function handleMarkerClick( e ) {
		e.preventDefault();

		const anchor = e.target.closest( 'a' );
		const { postId } = anchor.dataset;

		displayCardModal( postId );
	}

	/**
	 * Open the given modal and add listeners for closing events.
	 *
	 * @param {Element} modal The modal to open.
	 */
	function openModal( modal ) {
		document.body.classList.add( 'has-open-modal' );

		const button = modal.querySelector( '.post-modal__close-button' );

		// Focus the close button when the modal is opened.
		button.focus();

		// Close the modal when the close button is clicked.
		button.addEventListener( 'click', () => closeModal( modal ) );

		// Close the modal when the overlay is clicked.
		modal.addEventListener(
			'click',
			( e ) => e.target === modal && closeModal( modal )
		);

		// Remove the `has-open-modal` class when the escape key is pressed.
		document.addEventListener( 'keydown', handleEscape );

		function handleEscape( e ) {
			if ( 'Escape' === e.key ) {
				document.body.classList.remove( 'has-open-modal' );
				document.removeEventListener( 'keydown', handleEscape );
			}
		}

		modal.showModal();
	}

	/**
	 * Close the given modal.
	 *
	 * @param {Element} modal The modal to close.
	 */
	function closeModal( modal ) {
		document.body.classList.remove( 'has-open-modal' );

		modal.close();
	}

	/**
	 * Create a modal for the given post data.
	 *
	 * @param {Object} data Post data to use for populating the modal.
	 *
	 * @return {Element} The created modal.
	 */
	function createModal( data ) {
		const modal = modalTemplate.content.cloneNode( true ).children[ 0 ];
		const categoryLink = modal.querySelector( '.wp-block-post-terms a' );
		const title = modal.querySelector( '.wp-block-post-title' );
		const image = modal.querySelector(
			'.wp-block-post-featured-image img'
		);
		const content = modal.querySelector( '.post-content' );
		const category = data._embedded[ 'wp:term' ][ 0 ][ 0 ];

		categoryLink.href = category.link;
		categoryLink.innerText = category.name;

		title.textContent = data.title.rendered;

		const featuredImage = parser.parseFromString(
			data.featured_image,
			'text/html'
		).body.firstElementChild;

		const imageAtts = [
			'width',
			'height',
			'src',
			'class',
			'alt',
			'decoding',
			'loading',
			'srcset',
			'sizes',
		];

		// There's probably a smarter way to do this, but this approach seems safe.
		imageAtts.forEach( ( att ) => {
			const value = featuredImage.getAttribute( att ) ?? false;

			if ( value ) {
				image.setAttribute( att, value );
			}
		} );

		const elements = parser.parseFromString(
			data.content.rendered,
			'text/html'
		);

		Array.from( elements.body.children ).forEach( ( e ) =>
			content.append( e )
		);

		modal.id = `post-${ data.id }-modal`;

		block.append( modal );

		return modal;
	}

	/**
	 * Get the marker SVG for the given post type.
	 *
	 * @param {string} type The post type for which to retrieve the marker.
	 *
	 * @return {SVGElement} Marker SVG element.
	 */
	function getMarkerIcon( type ) {
		const isRewild = 'rewild' === type;

		const marker = document.createElementNS(
			'http://www.w3.org/2000/svg',
			'svg'
		);
		const use = document.createElementNS(
			'http://www.w3.org/2000/svg',
			'use'
		);

		use.setAttributeNS(
			'http://www.w3.org/1999/xlink',
			'xlink:href',
			`#map-marker-${ type }`
		);

		marker.ariaHidden = 'true';
		marker.setAttribute( 'focusable', 'false' );
		marker.setAttribute( 'viewBox', isRewild ? '0 0 48 48' : '0 0 34 62' );
		marker.setAttribute( 'width', isRewild ? '24' : '34' );
		marker.setAttribute( 'height', isRewild ? '24' : '62' );
		marker.append( use );

		return marker;
	}

	/**
	 * Populate and return the details of the marker for the given post data.
	 *
	 * @param {Object} data The post data.
	 *
	 * @return {Element} Marker details.
	 */
	function getMarkerDetails( data ) {
		const details = document.createElement( 'div' );
		const title = document.createElement( 'h2' );

		details.classList.add( 'map-marker__details' );

		if ( 'exploration' === data.type ) {
			details.style.setProperty(
				'--thumbnail',
				`url('${ data.thumb }')`
			);
		}

		title.textContent = data.title;

		details.append( title );

		return details;
	}
}
