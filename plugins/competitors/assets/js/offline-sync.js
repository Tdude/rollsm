/**
 * Offline-first scoring sync module.
 *
 * localStorage key: comp_scores_{competition_id}
 *
 * Rules:
 * 1. Every score change writes to localStorage immediately
 * 2. On save: POST to server if online, otherwise show "saved locally" notice
 * 3. On reconnect (online event): auto-sync unsynced scores in background
 * 4. NEVER delete localStorage data — it persists as backup even after sync
 * 5. On page load: compare localStorage timestamps vs server; use whichever is newer
 * 6. Conflict resolution: last-write-wins by scored_at timestamp
 * 7. Cleanup: only remove localStorage 30 days after competition is locked
 */
(function () {
  "use strict";

  // Bail if not on scoring page or config not provided
  if (typeof competitorsOfflineSync === "undefined") return;

  const config = competitorsOfflineSync;
  const STORAGE_PREFIX = "comp_scores_";
  const SYNC_STATUS_PREFIX = "comp_sync_status_";

  /**
   * Get the localStorage key for this competition.
   */
  function storageKey() {
    return STORAGE_PREFIX + config.competitionId;
  }

  /**
   * Get all locally stored scores for this competition.
   * @returns {Object} { [competitorId]: { [rollId]: { ...scoreData, scored_at, synced } } }
   */
  function getLocalScores() {
    try {
      const raw = localStorage.getItem(storageKey());
      return raw ? JSON.parse(raw) : {};
    } catch (e) {
      console.error("OfflineSync: Failed to read localStorage", e);
      return {};
    }
  }

  /**
   * Write scores to localStorage (never deletes — only adds/updates).
   */
  function saveLocalScores(scores) {
    try {
      localStorage.setItem(storageKey(), JSON.stringify(scores));
    } catch (e) {
      console.error("OfflineSync: Failed to write localStorage", e);
      showNotice("Warning: Could not save locally. Storage may be full.", "error");
    }
  }

  /**
   * Record a single score change locally.
   * Called on every input change in the scoring form.
   */
  function recordScore(competitorId, rollId, scoreData) {
    const scores = getLocalScores();
    if (!scores[competitorId]) {
      scores[competitorId] = {};
    }

    scores[competitorId][rollId] = Object.assign({}, scoreData, {
      scored_at: new Date().toISOString(),
      synced: false,
    });

    saveLocalScores(scores);
  }

  /**
   * Mark scores as synced (but never delete them).
   */
  function markSynced(competitorId, rollId) {
    const scores = getLocalScores();
    if (scores[competitorId] && scores[competitorId][rollId]) {
      scores[competitorId][rollId].synced = true;
      scores[competitorId][rollId].synced_at = new Date().toISOString();
      saveLocalScores(scores);
    }
  }

  /**
   * Get all unsynced scores.
   * @returns {Array} [{ competitor_id, roll_id, ...scoreData }]
   */
  function getUnsyncedScores() {
    const scores = getLocalScores();
    const unsynced = [];

    Object.keys(scores).forEach(function (compId) {
      Object.keys(scores[compId]).forEach(function (rollId) {
        const s = scores[compId][rollId];
        if (!s.synced) {
          unsynced.push(
            Object.assign({}, s, {
              competitor_id: compId,
              competition_roll_id: rollId,
            })
          );
        }
      });
    });

    return unsynced;
  }

  /**
   * Sync unsynced scores to server.
   * @returns {Promise}
   */
  function syncToServer() {
    const unsynced = getUnsyncedScores();
    if (unsynced.length === 0) {
      return Promise.resolve({ synced: 0 });
    }

    showNotice("Syncing " + unsynced.length + " score(s)...", "info");

    const body = new URLSearchParams();
    body.append("action", "competitors_offline_sync");
    body.append("nonce", config.nonce);
    body.append("competition_id", config.competitionId);
    body.append("scores", JSON.stringify(unsynced));

    return fetch(config.ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body,
    })
      .then(function (response) {
        if (!response.ok) throw new Error("Network error: " + response.status);
        return response.json();
      })
      .then(function (data) {
        if (data.success && data.data.results) {
          var syncedCount = 0;
          data.data.results.forEach(function (result) {
            if (result.status === "saved" || result.status === "skipped_older") {
              markSynced(result.competitor_id, result.competition_roll_id);
              syncedCount++;
            }
          });
          showNotice("Synced " + syncedCount + " score(s) successfully.", "success");
          return { synced: syncedCount };
        } else {
          throw new Error(data.data ? data.data.message : "Unknown sync error");
        }
      })
      .catch(function (err) {
        console.error("OfflineSync: Sync failed", err);
        showNotice("Sync failed: " + err.message + ". Data is safe locally.", "error");
        return { synced: 0, error: err.message };
      });
  }

  /**
   * Restore scores from localStorage into the DOM on page load.
   * Compares timestamps — uses whichever (local vs server) is newer.
   */
  function restoreFromLocal() {
    const scores = getLocalScores();
    var restored = 0;

    Object.keys(scores).forEach(function (compId) {
      Object.keys(scores[compId]).forEach(function (rollId) {
        const s = scores[compId][rollId];
        const localTime = new Date(s.scored_at).getTime();

        // Find the DOM row for this score
        const row = document.querySelector(
          "tr[data-competitor-id='" + compId + "'][data-index='" + rollId + "']"
        );
        if (!row) return;

        // Check server timestamp (stored as data attribute if available)
        const serverTime = parseInt(row.getAttribute("data-scored-at") || "0", 10);

        if (localTime > serverTime) {
          // Local is newer — apply to DOM
          applyScoreToRow(row, s);
          restored++;
        }
      });
    });

    if (restored > 0) {
      showNotice("Restored " + restored + " score(s) from local backup.", "info");
    }
  }

  /**
   * Apply a score object to a DOM row.
   */
  function applyScoreToRow(row, scoreData) {
    // Radio buttons (left_group, right_group)
    if (scoreData.left_group) {
      var leftRadio = row.querySelector(
        "input[name*='[left_group]'][value='" + scoreData.left_group + "']"
      );
      if (leftRadio) leftRadio.checked = true;
    }
    if (scoreData.right_group) {
      var rightRadio = row.querySelector(
        "input[name*='[right_group]'][value='" + scoreData.right_group + "']"
      );
      if (rightRadio) rightRadio.checked = true;
    }

    // Numeric inputs (left_score, right_score)
    if (scoreData.left_score !== undefined) {
      var leftInput = row.querySelector("input[name*='[left_score]']");
      if (leftInput) leftInput.value = scoreData.left_score;
    }
    if (scoreData.right_score !== undefined) {
      var rightInput = row.querySelector("input[name*='[right_score]']");
      if (rightInput) rightInput.value = scoreData.right_score;
    }

    // Update total display
    var total = (parseInt(scoreData.left_group) || 0)
              + (parseInt(scoreData.right_group) || 0)
              + (parseInt(scoreData.left_score) || 0)
              + (parseInt(scoreData.right_score) || 0);
    var totalCell = row.querySelector(".total-score-row");
    if (totalCell) totalCell.textContent = total;

    var hiddenTotal = row.querySelector("input[name$='[total_score]']");
    if (hiddenTotal) hiddenTotal.value = total;
  }

  /**
   * Show a status notice on the scoring page.
   */
  function showNotice(message, type) {
    var existing = document.getElementById("offline-sync-notice");
    if (existing) existing.remove();

    var notice = document.createElement("div");
    notice.id = "offline-sync-notice";
    notice.className = "notice notice-" + (type === "error" ? "error" : type === "success" ? "success" : "info");
    notice.style.cssText = "padding:8px 12px;margin:5px 0;border-left-width:4px;";
    var strong = document.createElement("strong");
    strong.textContent = "Offline Sync: ";
    notice.appendChild(strong);
    notice.appendChild(document.createTextNode(message));

    var timer = document.getElementById("timer");
    if (timer && timer.parentNode) {
      timer.parentNode.insertBefore(notice, timer.nextSibling);
    } else {
      var container = document.getElementById("judges-scoring-container");
      if (container) container.insertBefore(notice, container.firstChild);
    }

    // Auto-dismiss success/info after 5s
    if (type !== "error") {
      setTimeout(function () {
        if (notice.parentNode) notice.remove();
      }, 5000);
    }
  }

  /**
   * Cleanup old localStorage data for locked competitions (30+ days old).
   */
  function cleanupOldData() {
    var now = Date.now();
    var thirtyDays = 30 * 24 * 60 * 60 * 1000;

    // Collect keys first to avoid index-shift bugs when removing during iteration
    var keysToCheck = [];
    for (var i = 0; i < localStorage.length; i++) {
      var k = localStorage.key(i);
      if (k && k.startsWith(STORAGE_PREFIX)) {
        keysToCheck.push(k);
      }
    }

    keysToCheck.forEach(function (key) {
      if (key) {
        try {
          var data = JSON.parse(localStorage.getItem(key));
          // Check if all scores are synced and the oldest is 30+ days
          var allSynced = true;
          var oldestSync = now;

          Object.keys(data).forEach(function (compId) {
            Object.keys(data[compId]).forEach(function (rollId) {
              var s = data[compId][rollId];
              if (!s.synced) allSynced = false;
              if (s.synced_at) {
                var t = new Date(s.synced_at).getTime();
                if (t < oldestSync) oldestSync = t;
              }
            });
          });

          if (allSynced && (now - oldestSync) > thirtyDays) {
            localStorage.removeItem(key);
          }
        } catch (e) {
          // Skip corrupt entries
        }
      }
    });
  }

  // ─── Attach to scoring form events ────────────────────────────

  /**
   * Hook into score input changes to record locally.
   */
  function attachScoreListeners() {
    document.querySelectorAll(".competitor-scores").forEach(function (row) {
      var compId = row.getAttribute("data-competitor-id");
      var rollId = row.getAttribute("data-index");

      row.querySelectorAll(".score-input, .deduct-input, .numeric-input").forEach(function (input) {
        input.addEventListener("change", function () {
          var scoreData = collectRowScores(row);
          recordScore(compId, rollId, scoreData);
        });
      });
    });
  }

  /**
   * Collect all score values from a row.
   */
  function collectRowScores(row) {
    var data = { left_group: 0, right_group: 0, left_score: 0, right_score: 0 };

    row.querySelectorAll(".score-input, .deduct-input").forEach(function (input) {
      if (input.checked) {
        if (input.name.indexOf("[left_group]") !== -1) data.left_group = parseInt(input.value) || 0;
        if (input.name.indexOf("[right_group]") !== -1) data.right_group = parseInt(input.value) || 0;
        if (input.name.indexOf("[score]") !== -1) data.left_group = parseInt(input.value) || 0;
      }
    });

    row.querySelectorAll(".numeric-input").forEach(function (input) {
      if (input.name.indexOf("[left_score]") !== -1) data.left_score = parseInt(input.value) || 0;
      if (input.name.indexOf("[right_score]") !== -1) data.right_score = parseInt(input.value) || 0;
    });

    return data;
  }

  // ─── Initialize ───────────────────────────────────────────────

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("scoring-form")) return;

    // Restore local scores that are newer than server
    restoreFromLocal();

    // Attach listeners to record every change
    attachScoreListeners();

    // Auto-sync when coming back online
    window.addEventListener("online", function () {
      showNotice("Connection restored. Syncing...", "info");
      syncToServer();
    });

    // Show offline warning
    window.addEventListener("offline", function () {
      showNotice("You are offline. Scores are saved locally.", "error");
    });

    // If online, sync any leftover unsynced scores from a previous session
    if (navigator.onLine) {
      var unsynced = getUnsyncedScores();
      if (unsynced.length > 0) {
        syncToServer();
      }
    } else {
      showNotice("You are offline. Scores will sync when connection returns.", "error");
    }

    // Cleanup old data periodically
    cleanupOldData();
  });

  // Re-attach listeners after AJAX filter reloads the container
  // (called from admin-script.js reattachAllEvents if available)
  window.competitorsOfflineSyncReattach = function () {
    attachScoreListeners();
  };

  // Expose sync function for manual trigger
  window.competitorsForceSync = syncToServer;
})();
