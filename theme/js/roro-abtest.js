/**
 * roro-abtest.js
 * Stage31/32: Front instrumentation for A/B testing and CTR measurement.
 *
 * This script automatically assigns a visitor to a variant for the experiment
 * "recommend_algo_v1" via the REST endpoint. It records exposures and clicks
 * to the A/B testing API and sends recommend-events-hit calls with experiment
 * metadata. It also exposes helper functions for retrieving variant-specific
 * weights for recommendation algorithms and marking DOM elements as
 * recommendable cards.
 */

(function(){
  function getCookie(name) {
    const m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? m.pop() : '';
  }
  function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + (days*24*60*60*1000));
    document.cookie = name + "=" + value + "; expires=" + d.toUTCString() + "; path=/; SameSite=Lax";
  }
  function ensureSid() {
    var sid = getCookie('roro_sid');
    if (!sid) {
      sid = Math.random().toString(36).slice(2,12) + Math.random().toString(36).slice(2,12);
      setCookie('roro_sid', sid, 180);
    }
    return sid;
  }

  if (typeof window.RORO_ABCFG === 'undefined') return;
  const restBase = window.RORO_ABCFG.rest;
  const headers = { 'Content-Type': 'application/json' };

  // Ensure a session id cookie exists for anonymous tracking
  ensureSid();

  // Experiment identifier
  const EXP = 'recommend_algo_v1';
  const key = 'roro_ab_' + EXP;

  /**
   * Request assignment from the back-end if no variant is stored. The assignment
   * is stored in localStorage and cookie to persist across visits.
   * Returns a Promise resolving to the variant string (e.g., 'A' or 'B').
   */
  function assignIfNeeded(){
    const v = localStorage.getItem(key) || getCookie(key);
    if (v) {
      window.RORO_AB_VARIANT = v;
      return Promise.resolve(v);
    }
    const url = restBase + '/ab/assign?experiment=' + encodeURIComponent(EXP) + '&variants=A,B&split=50';
    return fetch(url, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); }).then(function(j){
        if (j && j.ok && j.variant) {
          localStorage.setItem(key, j.variant);
          setCookie(key, j.variant, 180);
          window.RORO_AB_VARIANT = j.variant;
          return j.variant;
        }
        return 'A';
      }).catch(function(){ return 'A'; });
  }

  /**
   * Send a generic A/B event (exposure, click, etc.) to the REST API. This
   * function is called internally when exposures or clicks occur.
   */
  function postAbEvent(evName, ctx, value) {
    const variant = window.RORO_AB_VARIANT || localStorage.getItem(key) || 'A';
    const body = JSON.stringify({
      experiment: EXP,
      variant: variant,
      event_name: evName,
      context: ctx || '',
      value: (typeof value === 'number' ? value : 1.0)
    });
    return fetch(restBase + '/ab/event', {
      method: 'POST', headers: headers, credentials: 'same-origin', body: body
    }).catch(function(){});
  }

  /**
   * DOM ready helper. Executes the callback as soon as the document is ready.
   */
  function onReady(fn){
    if(document.readyState !== 'loading'){ fn(); } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  onReady(function(){
    assignIfNeeded().then(function(variant){
      // automatically record exposure for the page view
      postAbEvent('exposure', 'pageview');

      // delegate click recording on elements with data-roro-event-id
      document.body.addEventListener('click', function(e){
        let el = e.target;
        while (el && el !== document.body) {
          if (el.hasAttribute('data-roro-event-id')) {
            const id = el.getAttribute('data-roro-event-id');
            // send AB click event
            postAbEvent('click', 'card:'+id, 1.0);
            // also hit the recommendation click endpoint for consistency
            try {
              fetch(restBase + '/recommend-events-hit', {
                method: 'POST', headers: headers, credentials: 'same-origin',
                body: JSON.stringify({ event_id: parseInt(id,10)||0, experiment: EXP, variant: variant })
              });
            } catch(e) {}
            break;
          }
          el = el.parentElement;
        }
      }, true);
    });
  });

  /**
   * Expose helper to obtain variant-specific weights for configurable recommendation.
   * When variant is 'B', it shifts weight more toward proximity and less toward history.
   */
  window.roroRecommendWeights = function(){
    const v = window.RORO_AB_VARIANT || localStorage.getItem(key) || 'A';
    if (v === 'B') {
      return { w_similarity: 0.25, w_history: 0.15, w_novelty: 0.10, w_popularity: 0.20, w_proximity: 0.30 };
    }
    return { w_similarity: 0.30, w_history: 0.20, w_novelty: 0.10, w_popularity: 0.30, w_proximity: 0.10 };
  };

  /**
   * Mark a DOM element as a recommendation card. Assigns the given event ID
   * to the data attribute used for click tracking.
   * @param {Element} el The DOM element to mark
   * @param {Number|String} eventId The event ID to associate
   */
  window.roroAbMark = function(el, eventId) {
    if (!el) return;
    try { el.setAttribute('data-roro-event-id', String(eventId)); } catch(e){}
  };
})();