/*
  map-loader.js

  This tiny loader defines a global initMap() function that the
  Google Maps JavaScript API will call once it finishes loading.
  Without this shim, the callback in the API URL may fire before
  map.js has registered its own initMap() implementation or before
  initGoogleMap() is available.  When initMap() runs, it polls
  for the presence of either initGoogleMap() or initHereMap() and
  invokes whichever is found.  If neither is available yet, it
  retries for a short period.  This ensures the map initialises
  regardless of script load order or module timing.

  Note: map-loader.js must be loaded before the Google Maps API
  script.  The functions.php file enqueues this loader in the
  header when the Map Page template is in use.
*/

;(function() {
  // Expose initMap globally for the Google Maps callback
  window.initMap = function() {
    var attempts = 0;
    function tryInit() {
      // If map.js provided initGoogleMap, call it
      if (typeof window.initGoogleMap === 'function') {
        window.initGoogleMap();
        return;
      }
      // If a HERE fallback exists (used for Chinese localisation), call it
      if (typeof window.initHereMap === 'function') {
        window.initHereMap();
        return;
      }
      // Otherwise retry a few times and then give up
      attempts++;
      if (attempts < 200) {
        setTimeout(tryInit, 50);
      }
    }
    tryInit();
  };
})();