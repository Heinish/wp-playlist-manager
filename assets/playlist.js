(function () {
    'use strict';

    const slides   = Array.from( document.querySelectorAll( '.ppm-slide' ) );
    const splash   = document.getElementById( 'ppm-splash' );
    let current    = 0;

    if ( slides.length === 0 ) return;

    function showSlide( index ) {
        slides[ current ].classList.remove( 'active' );
        current = index % slides.length;
        slides[ current ].classList.add( 'active' );
    }

    function getDuration( slide ) {
        return ( parseInt( slide.dataset.duration, 10 ) || 10 ) * 1000;
    }

    function advance() {
        const nextIndex = ( current + 1 ) % slides.length;
        showSlide( nextIndex );
        setTimeout( advance, getDuration( slides[ current ] ) );
    }

    function start() {
        const firstImg = slides[ 0 ].querySelector( 'img' );
        function onReady() {
            splash.classList.add( 'hidden' );
            setTimeout( advance, getDuration( slides[ 0 ] ) );
        }
        if ( ! firstImg || firstImg.complete ) {
            onReady();
        } else {
            firstImg.addEventListener( 'load', onReady, { once: true } );
        }
    }

    start();

    /* ── Auto-refresh ─────────────────────────────────────────── */
    const { hash: initHash, restUrl } = window.PPM;

    function checkForUpdates() {
        fetch( restUrl, { cache: 'no-store' } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( data ) {
                if ( data.hash && data.hash !== initHash ) {
                    window.location.reload();
                }
            } )
            .catch( function () { /* ignore network errors */ } );
    }

    setInterval( checkForUpdates, 30000 );

})();
