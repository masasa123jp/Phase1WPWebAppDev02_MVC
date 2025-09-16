/*
  web-vitals.js – Measures Core Web Vitals (LCP, CLS, INP) and sends
  them to the WordPress back‑end via the REST API.  This script runs
  on all WordPress pages.  INP measurement is experimental; when
  unsupported the value defaults to 0.
*/

(function() {
  if (typeof window === 'undefined' || !('PerformanceObserver' in window)) {
    return;
  }
  var lcpValue = 0;
  var clsValue = 0;
  var inpValue = 0;

  // Observe LCP entries
  try {
    var lcpObserver = new PerformanceObserver(function(entryList) {
      var entries = entryList.getEntries();
      for (var i = 0; i < entries.length; i++) {
        var entry = entries[i];
        if (entry.entryType === 'largest-contentful-paint') {
          lcpValue = entry.startTime;
        }
      }
    });
    lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
  } catch (e) {
    // Browser may not support this entry type
  }

  // Observe layout shifts to accumulate CLS
  try {
    var clsObserver = new PerformanceObserver(function(entryList) {
      var entries = entryList.getEntries();
      for (var i = 0; i < entries.length; i++) {
        var entry = entries[i];
        // Only count layout shifts not triggered by user input
        if (entry.entryType === 'layout-shift' && !entry.hadRecentInput) {
          clsValue += entry.value;
        }
      }
    });
    clsObserver.observe({ type: 'layout-shift', buffered: true });
  } catch (e) {
    // Not supported
  }

  // Attempt to measure Interaction to Next Paint (INP) via the Event Timing API.
  // This API is still experimental and may not be available.  INP
  // represents the 98th percentile of latency experienced by the user.
  try {
    var inpSamples = [];
    var inpObserver = new PerformanceObserver(function(list) {
      var entries = list.getEntries();
      for (var i = 0; i < entries.length; i++) {
        var ev = entries[i];
        // Only consider "first-input" and other interaction types
        if (ev.entryType === 'event' && ev.name) {
          var duration = ev.processingEnd - ev.startTime;
          inpSamples.push(duration);
        }
      }
    });
    inpObserver.observe({ type: 'event', buffered: true });
    // Compute INP at page hide
    window.addEventListener('pagehide', function() {
      if (inpSamples.length > 0) {
        // Use 98th percentile as INP approximation
        inpSamples.sort(function(a,b){ return a - b; });
        var idx = Math.floor(0.98 * inpSamples.length);
        inpValue = inpSamples[idx] || 0;
      }
    });
  } catch (e) {
    // Fallback will leave inpValue at default 0
  }

  // Configuration: sample rate controls how frequently metrics are sent (0–1).  A value
  // of 0.1 means that roughly 10% of page loads will report metrics.  The
  // theme may set RORO_WEB_VITALS_SAMPLING via wp_localize_script; default is 1 (always send).
  var samplingRate = 1.0;
  try {
    if (typeof window.RORO_WEB_VITALS_SAMPLING !== 'undefined') {
      var sr = parseFloat(window.RORO_WEB_VITALS_SAMPLING);
      if (!isNaN(sr) && sr >= 0 && sr <= 1) samplingRate = sr;
    }
  } catch (e) {}

  // After the page has fully loaded, send the collected metrics.  Delay
  // execution to ensure the LCP entry has been recorded.  Use
  // requestIdleCallback if available to avoid interfering with critical
  // rendering tasks.
  var sendMetrics = function() {
    var endpoint = window.RORO_WEB_VITALS_ENDPOINT;
    if (!endpoint) {
      return;
    }
    // Apply sampling: randomly skip reporting for some page views to reduce
    // server load.  Unsent metrics simply get dropped; accuracy is preserved
    // statistically when sampling rates are consistent.
    if (Math.random() > samplingRate) {
      return;
    }
    var payload = {
      metrics: [
        { metric: 'LCP', value: Math.round(lcpValue), url: location.pathname },
        { metric: 'CLS', value: parseFloat(clsValue.toFixed(4)), url: location.pathname },
        { metric: 'INP', value: Math.round(inpValue), url: location.pathname }
      ]
    };
    // Prefer sendBeacon for non-blocking, retriable uploads.  Fallback to
    // fetch() if sendBeacon is unavailable or fails.
    try {
      if (navigator.sendBeacon) {
        var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        var ok = navigator.sendBeacon(endpoint, blob);
        if (ok) return;
      }
    } catch (e) {
      // Ignore and fallback to fetch
    }
    try {
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
    } catch (err) {
      console.warn('web‑vitals send failed:', err);
    }
  };
  var scheduleSend = function() {
    if ('requestIdleCallback' in window) {
      window.requestIdleCallback(sendMetrics, { timeout: 5000 });
    } else {
      setTimeout(sendMetrics, 3000);
    }
  };
  window.addEventListener('load', scheduleSend);
})();