// assets/js/course_units_view.js
(function () {
  "use strict";

  /* =========================
   * DEBUG (turn off later)
   * ========================= */
  const DEBUG = true;
  function log(...args) { if (DEBUG) console.log("[course_units_view]", ...args); }
  function warn(...args) { console.warn("[course_units_view]", ...args); }
  function err(...args) { console.error("[course_units_view]", ...args); }

  log("Script loaded ?");

  /* =========================
   * Wait for DOM to be ready
   * ========================= */
  function init() {
    log("Initializing...");

    /* =========================
     * Helpers
     * ========================= */
    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(text) {
      const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
      return String(text || "").replace(/[&<>"']/g, (m) => map[m]);
    }

    function hasJQ() { 
      const has = !!(window.$ && window.jQuery);
      if (!has) warn("jQuery not available");
      return has;
    }

    function hasBSModal() { 
      const has = !!(window.$ && $.fn && typeof $.fn.modal === "function");
      if (!has) warn("Bootstrap modal not available");
      return has;
    }

    function showModal(sel) {
      log("Attempting to show modal:", sel);
      
      if (!hasBSModal()) {
        err("Bootstrap modal not available. Check order: jquery -> bootstrap.js -> pdf.js -> course_units_view.js");
        alert("Modal system not ready. Please refresh the page.");
        return false;
      }

      const $m = $(sel);
      if (!$m.length) {
        err("Modal not found:", sel);
        alert("Modal element not found: " + sel);
        return false;
      }

      try {
        $m.modal("show");
        log("Modal shown successfully:", sel);
        return true;
      } catch (e) {
        err("Error showing modal:", e);
        alert("Error opening modal. Please try again.");
        return false;
      }
    }

    function showNotification(message, type) {
      const alertClass = type === "success" ? "alert-success" : (type === "error" ? "alert-danger" : "alert-info");
      const icon = type === "success" ? "fa-check-circle" : (type === "error" ? "fa-times-circle" : "fa-info-circle");

      const el = document.createElement("div");
      el.className = `alert ${alertClass}`;
      el.style.position = "fixed";
      el.style.top = "20px";
      el.style.right = "20px";
      el.style.zIndex = "10000";
      el.style.minWidth = "300px";
      el.style.boxShadow = "0 2px 10px rgba(0,0,0,0.2)";
      el.innerHTML = `<i class="fa ${icon}"></i> ${escapeHtml(message)}`;
      document.body.appendChild(el);

      setTimeout(() => {
        el.style.opacity = "0";
        el.style.transition = "opacity .3s ease";
        setTimeout(() => el.remove(), 300);
      }, 3000);
    }

    /* =========================================================
     * A) COMMENTS
     * ========================================================= */
    function displayComments(comments) {
      const container = qs("#commentsContainer");
      if (!container) {
        err("Missing #commentsContainer");
        return;
      }

      if (!comments || comments.length === 0) {
        container.innerHTML = `
          <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No comments yet. Be the first to comment!
          </div>`;
        return;
      }

      let html = "";
      comments.forEach((c) => {
        const isOwn = !!c.is_own_comment;
        const bg = isOwn ? "#f3f9ff" : "#f9f9f9";
        const border = isOwn ? "#1c84c6" : "#e7eaec";
        html += `
          <div class="comment-item ${isOwn ? "own-comment" : ""}"
               style="margin-bottom:20px;padding:15px;background:${bg};border-left:3px solid ${border};border-radius:4px;">
            <div style="margin-bottom:8px;">
              <strong style="color:#676a6c;font-size:14px;">
                <i class="fa fa-user"></i> ${escapeHtml(c.author_name)}
              </strong>
              ${isOwn ? '<span class="badge" style="background:#1c84c6;margin-left:8px;">You</span>' : ""}
              <span style="float:right;color:#999;font-size:12px;">
                <i class="fa fa-clock-o"></i> ${escapeHtml(c.time_ago)}
              </span>
            </div>
            <div style="color:#333;line-height:1.6;font-size:13px;">
              ${escapeHtml(c.comment_text).replace(/\n/g, "<br>")}
            </div>
          </div>`;
      });

      container.innerHTML = html;
    }

    function loadComments(noteId) {
      log("Loading comments for note:", noteId);
      const container = qs("#commentsContainer");
      if (!container) {
        err("Missing #commentsContainer in HTML");
        return;
      }

      container.innerHTML = `
        <div class="text-center" style="padding:20px;">
          <i class="fa fa-spinner fa-spin fa-2x"></i>
          <p>Loading comments...</p>
        </div>`;

      if (!hasJQ()) {
        err("jQuery missing - cannot load comments.");
        container.innerHTML = `<div class="alert alert-danger">jQuery is required to load comments.</div>`;
        return;
      }

      $.ajax({
        url: "scripts/get_comments.php",
        method: "GET",
        data: { note_id: noteId },
        dataType: "json",
        success: function (res) {
          log("get_comments response:", res);
          if (res && res.success) {
            displayComments(res.comments || []);
          } else {
            container.innerHTML = `<div class="alert alert-warning">${escapeHtml(res?.message || "Failed to load comments")}</div>`;
          }
        },
        error: function (xhr) {
          err("get_comments AJAX error:", xhr.status, xhr.responseText);
          container.innerHTML = `<div class="alert alert-danger">Failed to load comments. Try again.</div>`;
        }
      });
    }

    function openComments(noteId, title) {
      log("Open comments:", { noteId, title });

      const noteIdInput = qs("#commentNoteId");
      const titleEl = qs("#commentNoteTitle");

      if (noteIdInput) {
        noteIdInput.value = String(noteId);
        log("Set comment note ID to:", noteId);
      } else {
        err("Missing #commentNoteId input");
      }

      if (titleEl) {
        titleEl.textContent = title || "";
      } else {
        warn("Missing #commentNoteTitle element");
      }

      if (!showModal("#commentsModal")) {
        err("Failed to show comments modal");
        return;
      }

      loadComments(noteId);
    }

    // ? Global function for inline onclick handlers
    window.openCommentsModal = function (noteId, title) {
      log("openCommentsModal called (global):", noteId, title);
      openComments(noteId, title);
    };

    /* =========================================================
     * B) PDF VIEWER
     * ========================================================= */
    if (window.pdfjsLib) {
      pdfjsLib.GlobalWorkerOptions.workerSrc =
        "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
      log("PDF.js worker configured");
    } else {
      warn("pdfjsLib not found. Make sure pdf.min.js is loaded BEFORE this file.");
    }

    let pdfDoc = null;
    let pageNum = 1;
    let pageRendering = false;
    let pageNumPending = null;

    let fitMode = "width";
    let zoomMul = 1.0;

    let currentNoteId = null;
    let progressUpdateTimer = null;

    function getViewerSize() {
      const modalBody = qs("#pdfViewerModal .modal-body");
      const rect = modalBody ? modalBody.getBoundingClientRect() : { width: 900, height: 700 };
      return { w: rect.width, h: rect.height };
    }

    function computeFitScale(page) {
      const { w, h } = getViewerSize();
      const v1 = page.getViewport({ scale: 1 });
      const sWidth = (w - 24) / v1.width;
      const sPage = Math.min((w - 24) / v1.width, (h - 24) / v1.height);
      return (fitMode === "page" ? sPage : sWidth) * zoomMul;
    }

    function renderPage(num) {
      if (!pdfDoc) return;
      pageRendering = true;

      pdfDoc.getPage(num).then((page) => {
        const canvas = qs("#pdfCanvas");
        if (!canvas) { 
          err("Missing #pdfCanvas in HTML"); 
          pageRendering = false;
          return; 
        }

        const ctx = canvas.getContext("2d");
        const scale = computeFitScale(page);
        const viewport = page.getViewport({ scale });

        const dpr = window.devicePixelRatio || 1;
        canvas.style.width = Math.floor(viewport.width) + "px";
        canvas.style.height = Math.floor(viewport.height) + "px";
        canvas.width = Math.floor(viewport.width * dpr);
        canvas.height = Math.floor(viewport.height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const task = page.render({ canvasContext: ctx, viewport });
        task.promise.then(() => {
          pageRendering = false;
          const p = qs("#pageNum");
          if (p) p.textContent = String(num);

          if (pageNumPending !== null) {
            const next = pageNumPending;
            pageNumPending = null;
            renderPage(next);
          }
        });
      });
    }

    function queueRenderPage(num) {
      if (pageRendering) {
        pageNumPending = num;
      } else {
        renderPage(num);
      }
    }

    function onPrevPage() { 
      if (pdfDoc && pageNum > 1) { 
        pageNum--; 
        queueRenderPage(pageNum); 
      } 
    }

    function onNextPage() { 
      if (pdfDoc && pageNum < pdfDoc.numPages) { 
        pageNum++; 
        queueRenderPage(pageNum); 
      } 
    }

    function zoomIn() { 
      zoomMul = Math.min(zoomMul + 0.1, 3.0); 
      queueRenderPage(pageNum); 
    }

    function zoomOut() { 
      zoomMul = Math.max(zoomMul - 0.1, 0.4); 
      queueRenderPage(pageNum); 
    }

    function calculateReadingProgress() {
      if (!pdfDoc) return 0;
      return Math.round((pageNum / pdfDoc.numPages) * 100);
    }

    function updateProgressDisplay() {
      const progress = calculateReadingProgress();
      const bar = qs("#readingProgressBar");
      const txt = qs("#readingProgressText");
      if (bar) {
        bar.style.width = progress + "%";
        bar.className = "progress-bar";
        if (progress < 50) bar.className += " progress-bar-danger";
        else if (progress < 100) bar.className += " progress-bar-warning";
        else bar.className += " progress-bar-success";
      }
      if (txt) txt.textContent = progress + "% Read";
    }

    function saveReadingProgress() {
      if (!currentNoteId || !hasJQ()) return;
      const progress = calculateReadingProgress();
      $.ajax({
        url: "scripts/save_reading_progress.php",
        method: "POST",
        data: { note_id: currentNoteId, percentage: progress }
      });
    }

    function startProgressTracking() {
      if (progressUpdateTimer) clearInterval(progressUpdateTimer);
      updateProgressDisplay();
      progressUpdateTimer = setInterval(() => {
        updateProgressDisplay();
        saveReadingProgress();
      }, 5000);
    }

    function stopProgressTracking() {
      if (progressUpdateTimer) clearInterval(progressUpdateTimer);
      progressUpdateTimer = null;
      saveReadingProgress();
    }

    function openPDF(noteId, title, url) {
      log("Open PDF:", { noteId, title, url });

      if (!window.pdfjsLib) {
        err("pdfjsLib missing. Include pdf.min.js before this file.");
        alert("PDF library not loaded. Please refresh the page.");
        return;
      }

      currentNoteId = noteId;

      const titleEl = qs("#pdfTitle");
      const noteIdEl = qs("#currentNoteId");
      
      if (titleEl) {
        titleEl.textContent = title || "";
        log("Set PDF title to:", title);
      } else {
        warn("Missing #pdfTitle element");
      }

      if (noteIdEl) {
        noteIdEl.value = String(noteId);
        log("Set current note ID to:", noteId);
      } else {
        warn("Missing #currentNoteId input");
      }

      pageNum = 1;
      zoomMul = 1.0;
      fitMode = "width";

      if (!showModal("#pdfViewerModal")) {
        err("Failed to show PDF viewer modal");
        return;
      }

      log("Loading PDF from URL:", url);
      pdfjsLib.getDocument(url).promise
        .then((pdf) => {
          log("PDF loaded successfully. Pages:", pdf.numPages);
          pdfDoc = pdf;
          const pc = qs("#pageCount");
          if (pc) pc.textContent = String(pdf.numPages);
          queueRenderPage(pageNum);
          startProgressTracking();
        })
        .catch((e) => {
          err("PDF load error:", e);
          alert("Failed to load PDF: " + e.message);
        });
    }

    // ? Global function for inline onclick handlers
    window.openPDFViewer = function (noteId, title, url) {
      log("openPDFViewer called (global):", noteId, title, url);
      openPDF(parseInt(noteId, 10), title, url);
    };

    /* =========================================================
     * C) BUTTON CLICKS (Delegated)
     * ========================================================= */
    document.addEventListener("click", function (e) {
      // COMMENTS button with class .js-open-comments
      const cbtn = e.target.closest(".js-open-comments");
      if (cbtn) {
        e.preventDefault();
        const noteId = cbtn.dataset.noteId;
        const title = cbtn.dataset.title || "";
        log("Comments button clicked:", { noteId, title });
        if (!noteId) { 
          err("Missing data-note-id on comments button"); 
          alert("Error: Missing note ID");
          return; 
        }
        openComments(noteId, title);
        return;
      }

      // READ PDF button with class .js-open-pdf
      const pbtn = e.target.closest(".js-open-pdf");
      if (pbtn) {
        e.preventDefault();
        const noteId = pbtn.dataset.noteId;
        const title = pbtn.dataset.title || "";
        const url = pbtn.dataset.url || "";
        log("PDF button clicked:", { noteId, title, url });
        if (!noteId || !url) { 
          err("Missing data-note-id or data-url on PDF button"); 
          alert("Error: Missing note ID or PDF URL");
          return; 
        }
        openPDF(parseInt(noteId, 10), title, url);
        return;
      }

      // Navigation buttons inside PDF viewer
      const nav = e.target.closest("#prevPage, #nextPage, #zoomIn, #zoomOut");
      if (nav) {
        e.preventDefault();
        if (nav.id === "prevPage") onPrevPage();
        if (nav.id === "nextPage") onNextPage();
        if (nav.id === "zoomIn") zoomIn();
        if (nav.id === "zoomOut") zoomOut();
        return;
      }
    });

    /* =========================================================
     * D) KEYBOARD NAVIGATION
     * ========================================================= */
    document.addEventListener("keydown", function (e) {
      if (!hasBSModal()) return;
      
      const $pdfModal = $("#pdfViewerModal");
      if (!$pdfModal.length || !$pdfModal.is(":visible")) return;

      if (e.key === "ArrowLeft") {
        e.preventDefault();
        onPrevPage();
      }
      if (e.key === "ArrowRight") {
        e.preventDefault();
        onNextPage();
      }
      if (e.key === "+" || e.key === "=") {
        e.preventDefault();
        zoomIn();
      }
      if (e.key === "-" || e.key === "_") {
        e.preventDefault();
        zoomOut();
      }
    });

    /* =========================================================
     * E) MODAL CLEANUP
     * ========================================================= */
    if (hasBSModal()) {
      $("#pdfViewerModal").on("hidden.bs.modal", function () {
        log("PDF viewer modal closed - cleaning up");
        stopProgressTracking();

        pdfDoc = null;
        pageNum = 1;
        zoomMul = 1.0;
        fitMode = "width";
        currentNoteId = null;

        const canvas = qs("#pdfCanvas");
        if (canvas) {
          const ctx = canvas.getContext("2d");
          ctx.setTransform(1, 0, 0, 1, 0, 0);
          ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
      });
    }

    /* =========================================================
     * F) COMMENT FORM SUBMISSION
     * ========================================================= */
    const commentForm = qs("#addCommentForm");
    if (commentForm) {
      log("Comment form found, attaching submit handler");
      commentForm.addEventListener("submit", function (e) {
        e.preventDefault();
        log("Comment form submitted");
        
        if (!hasJQ()) { 
          alert("jQuery required."); 
          return; 
        }

        const noteIdEl = qs("#commentNoteId");
        const noteId = noteIdEl ? noteIdEl.value : "";
        const commentTextEl = qs("#commentText");
        const commentText = commentTextEl ? commentTextEl.value.trim() : "";

        if (!commentText) { 
          alert("Please enter a comment."); 
          return; 
        }

        const $btn = $("#submitCommentBtn");
        $btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Posting...');

        $.ajax({
          url: "scripts/add_comment.php",
          method: "POST",
          data: { note_id: noteId, comment_text: commentText },
          dataType: "json",
          success: function (res) {
            log("add_comment response:", res);
            if (res && res.success) {
              if (commentTextEl) commentTextEl.value = "";
              const cc = qs("#charCount");
              if (cc) cc.textContent = "0";
              showNotification("Comment posted successfully!", "success");
              loadComments(noteId);
            } else {
              showNotification(res?.message || "Failed to post comment.", "error");
            }
          },
          error: function (xhr) {
            err("add_comment AJAX error:", xhr.status, xhr.responseText);
            showNotification("Failed to post comment. Please try again.", "error");
          },
          complete: function () {
            $btn.prop("disabled", false).html('<i class="fa fa-send"></i> Post Comment');
          }
        });
      });
    } else {
      warn("Comment form (#addCommentForm) not found");
    }

    /* =========================================================
     * G) CHARACTER COUNTER
     * ========================================================= */
    const commentText = qs("#commentText");
    if (commentText) {
      log("Comment text area found, attaching character counter");
      commentText.addEventListener("input", function () {
        const cc = qs("#charCount");
        if (!cc) return;
        const len = commentText.value.length;
        cc.textContent = String(len);
        cc.style.color = len > 900 ? "#ed5565" : len > 700 ? "#f8ac59" : "#676a6c";
      });
    } else {
      warn("Comment textarea (#commentText) not found");
    }

    log("Initialization complete ?");
  }

  // Wait for DOM to be ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    // DOM already loaded
    init();
  }

})();