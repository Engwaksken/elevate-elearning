(function () {
  'use strict';

  function qs(id) {
    return document.getElementById(id);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function safeStr(value) {
    return value === undefined || value === null ? '' : String(value);
  }

  function setValue(id, value) {
    var el = qs(id);
    if (el) el.value = safeStr(value);
  }

  function setText(id, value) {
    var el = qs(id);
    if (el) el.textContent = safeStr(value);
  }

  function normalizeStatus(value) {
    value = safeStr(value).toLowerCase();
    return value === 'inactive' ? 'inactive' : 'active';
  }

  function normalizeClassMode(value) {
    value = safeStr(value).toLowerCase();
    return ['online', 'physical', 'both'].indexOf(value) !== -1 ? value : 'both';
  }

  function normalizeContentType(value) {
    value = safeStr(value).toLowerCase();
    if (['file', 'url', 'existing'].indexOf(value) !== -1) return value;
    return 'file';
  }

  function checkRadio(id) {
    var el = qs(id);
    if (el) el.checked = true;
  }

  function setRadioByName(form, name, value) {
    if (!form) return;
    var input = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
    if (input) input.checked = true;
  }

  function setStatusAndClass(prefix, status, classMode) {
    checkRadio(prefix + '_status_' + normalizeStatus(status));
    checkRadio(prefix + '_class_' + normalizeClassMode(classMode));
  }

  function show(el, display) {
    if (el) el.style.display = display || 'block';
  }

  function hide(el) {
    if (el) el.style.display = 'none';
  }

  function setRequired(container, required) {
    if (!container) return;
    qsa('input, select, textarea', container).forEach(function (el) {
      if (el.type === 'hidden') return;
      if (required) {
        if (el.dataset.wasRequired === '1' || el.hasAttribute('data-required-when-visible')) {
          el.required = true;
        }
      } else {
        if (el.required) el.dataset.wasRequired = '1';
        el.required = false;
      }
    });
  }

  window.openModal = function (id) {
    var modal = qs(id);
    if (!modal) {
      console.error('Modal not found:', id);
      return false;
    }

    modal.classList.add('is-open', 'active');
    modal.setAttribute('aria-hidden', 'false');
    modal.style.display = 'flex';
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';

    var body = modal.querySelector('.modal-body');
    if (body) body.scrollTop = 0;

    var first = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
    if (first && typeof first.focus === 'function') {
      setTimeout(function () { try { first.focus(); } catch (e) {} }, 50);
    }

    return true;
  };

  window.closeModal = function (id) {
    var modal = qs(id);
    if (!modal) return false;

    modal.classList.remove('is-open', 'active');
    modal.setAttribute('aria-hidden', 'true');
    modal.style.display = 'none';

    if (!document.querySelector('.modal-overlay.is-open, .modal-overlay.active')) {
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
    }

    return true;
  };

  window.toggleContentSource = function (form, type) {
    if (!form) return;

    var selected = form.querySelector('input[name="content_type"]:checked');
    var value = normalizeContentType(selected ? selected.value : 'file');

    var fileWrap = form.querySelector('.' + type + '-file-wrap');
    var urlWrap = form.querySelector('.' + type + '-url-wrap');
    var existingWrap = form.querySelector('.' + type + '-existing-wrap');

    if (value === 'url') {
      hide(fileWrap);
      show(urlWrap);
      hide(existingWrap);
      setRequired(fileWrap, false);
      setRequired(urlWrap, true);
      setRequired(existingWrap, false);
    } else if (value === 'existing') {
      hide(fileWrap);
      hide(urlWrap);
      show(existingWrap);
      setRequired(fileWrap, false);
      setRequired(urlWrap, false);
      setRequired(existingWrap, true);
    } else {
      show(fileWrap);
      hide(urlWrap);
      hide(existingWrap);
      setRequired(fileWrap, true);
      setRequired(urlWrap, false);
      setRequired(existingWrap, false);
    }
  };

  function resetContentSource(form, type) {
    if (!form) return;
    setRadioByName(form, 'content_type', 'file');
    window.toggleContentSource(form, type);
  }

  function setContentType(form, type, contentType, urlValue) {
    if (!form) return;

    contentType = normalizeContentType(contentType);
    setRadioByName(form, 'content_type', contentType);

    var urlName = type === 'assignment' ? 'assignment_url' : 'notes_url';
    var urlInput = form.querySelector('input[name="' + urlName + '"]');
    if (urlInput) urlInput.value = safeStr(urlValue);

    window.toggleContentSource(form, type);
  }

  window.changexps = function (selectEl) {
    var intakeId = selectEl && selectEl.value ? selectEl.value : '';
    var config = window.courseConfig || {};
    var url = new URL(window.location.href);
    var courseId = url.searchParams.get('course') || config.courseId || '';

    if (courseId) url.searchParams.set('course', courseId);

    if (!url.searchParams.get('view')) {
      url.searchParams.set('view', config.defaultView || 'open_coursex');
    }

    if (intakeId) {
      url.searchParams.set('ausydfgasuidgipppuwqu', intakeId);
    } else {
      url.searchParams.delete('ausydfgasuidgipppuwqu');
    }

    window.location.href = url.toString();
  };

  window.switchModuleTab = function (idx) {
    var total = (window.courseConfig && parseInt(window.courseConfig.moduleCount, 10)) || 0;

    for (var i = 0; i < total; i++) {
      var isActive = i === idx;
      var btn = qs('mod-btn-' + i);
      var panel = qs('mod-panel-' + i);
      if (btn) btn.classList.toggle('active', isActive);
      if (panel) panel.classList.toggle('active', isActive);
    }
  };

  window.switchSubTab = function (modIdx, type) {
    ['assignments', 'notes'].forEach(function (tab) {
      var active = tab === type;
      var btn = qs('sub-btn-' + modIdx + '-' + tab);
      var panel = qs('sub-panel-' + modIdx + '-' + tab);
      if (btn) btn.classList.toggle('active', active);
      if (panel) panel.classList.toggle('active', active);
    });
  };

  window.openAddNoteModal = function (unit) {
    var form = document.querySelector('#addNoteModal form');
    if (form) {
      form.reset();
      resetContentSource(form, 'note');
    }
    setValue('add_note_unit', unit || '');
    window.openModal('addNoteModal');
  };

  window.openAddAssignmentModal = function (unit) {
    var form = document.querySelector('#addAssignmentModal form');
    if (form) {
      form.reset();
      resetContentSource(form, 'assignment');
    }
    setValue('add_assignment_unit', unit || '');
    window.openModal('addAssignmentModal');
  };

  window.openEditAssignmentModal = function (id, unit, title, message, dueDate, status, classMode, contentType, urlValue) {
    var form = document.querySelector('#editAssignmentModal form');
    if (form) form.reset();

    setValue('edit_assignment_id', id || '');
    setValue('edit_assignment_unit', unit || '');
    setValue('edit_assignment_title', title || '');
    setValue('edit_assignment_message', message || '');
    setValue('edit_assignment_due', dueDate || '');

    setStatusAndClass('edit_assignment', status || 'active', classMode || 'both');
    setContentType(form, 'assignment', contentType || 'file', urlValue || '');

    window.openModal('editAssignmentModal');
  };

  window.openEditNoteModal = function (id, unit, title, message, status, classMode, contentType, urlValue) {
    var form = document.querySelector('#editNoteModal form');
    if (form) form.reset();

    setValue('edit_note_id', id || '');
    setValue('edit_note_unit', unit || '');
    setValue('edit_note_title', title || '');
    setValue('edit_note_message', message || '');

    setStatusAndClass('edit_note', status || 'active', classMode || 'both');
    setContentType(form, 'note', contentType || 'file', urlValue || '');

    window.openModal('editNoteModal');
  };

  window.openDeleteAssignmentModal = function (id, title, doc) {
    setValue('delete_assignment_id', id || '');
    setValue('delete_assignment_document', doc || '');
    setText('delete_assignment_title', title || 'this assignment');
    window.openModal('deleteAssignmentModal');
  };

  window.openDeleteNoteModal = function (id, title, doc) {
    setValue('delete_note_id', id || '');
    setValue('delete_note_document', doc || '');
    setText('delete_note_title', title || 'this note');
    window.openModal('deleteNoteModal');
  };

  function showBox(id, display) {
    var el = qs(id);
    if (el) el.style.display = display || 'block';
  }

  function hideBox(id) {
    var el = qs(id);
    if (el) el.style.display = 'none';
  }

  function setError(boxId, message) {
    var box = qs(boxId);
    if (!box) return;
    var span = box.querySelector('span');
    if (span) span.textContent = message || 'Request failed.';
    box.style.display = 'flex';
  }

  function fetchJson(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return response.text().then(function (text) {
        try {
          return JSON.parse(text);
        } catch (e) {
          throw new Error(text ? text.substring(0, 300) : 'Invalid server response.');
        }
      });
    });
  }

  window.viewNoteAnalytics = function (noteId, title) {
    setText('noteAnalyticsTitle', title || '');
    showBox('noteAnalyticsLoading');
    hideBox('noteAnalyticsError');
    hideBox('noteAnalyticsContent');
    window.openModal('noteAnalyticsModal');

    fetchJson('scripts/get_note_analytics.php?note_id=' + encodeURIComponent(noteId))
      .then(function (data) {
        hideBox('noteAnalyticsLoading');

        if (data.error) {
          setError('noteAnalyticsError', data.error);
          return;
        }

        setText('stat_total_students', data.total_students || 0);
        setText('stat_avg_progress', (data.avg_progress || 0) + '%');
        setText('stat_completed', data.completed || 0);
        setText('stat_not_started', data.not_started || 0);

        var tbody = qs('analyticsStudentsBody');
        if (tbody) {
          tbody.innerHTML = '';

          if (!data.students || !data.students.length) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:24px;color:var(--muted);">No student data yet.</td></tr>';
          } else {
            data.students.forEach(function (student) {
              var tr = document.createElement('tr');
              tr.innerHTML =
                '<td>' + safeStr(student.name || '-') + '</td>' +
                '<td>' + safeStr(parseInt(student.progress, 10) || 0) + '%</td>' +
                '<td>' + safeStr(student.last_accessed || '-') + '</td>';
              tbody.appendChild(tr);
            });
          }
        }

        showBox('noteAnalyticsContent');
      })
      .catch(function (error) {
        hideBox('noteAnalyticsLoading');
        setError('noteAnalyticsError', error.message || 'Network error. Please try again.');
      });
  };

  window.viewNoteComments = function (noteId, title) {
    setText('noteCommentsTitle', title || '');
    showBox('noteCommentsLoading');
    hideBox('noteCommentsError');
    hideBox('noteCommentsContent');
    window.openModal('noteCommentsModal');

    fetchJson('scripts/get_instructor_comments.php?note_id=' + encodeURIComponent(noteId))
      .then(function (data) {
        hideBox('noteCommentsLoading');

        if (data.error) {
          setError('noteCommentsError', data.error);
          return;
        }

        var container = qs('commentsContainer');
        if (container) {
          container.innerHTML = '';

          if (!data.comments || !data.comments.length) {
            container.innerHTML = '<div class="empty"><i class="fas fa-comments"></i><p>No comments yet.</p></div>';
          } else {
            data.comments.forEach(function (comment) {
              var card = document.createElement('div');
              card.style.cssText = 'background:var(--surface2,#f8fafc);border:1px solid var(--border,#e5e7eb);border-radius:12px;padding:12px 14px;';
              card.innerHTML =
                '<strong>' + safeStr(comment.student_name || 'Unknown') + '</strong>' +
                '<div style="font-size:.68rem;color:var(--muted,#6b7280);">' + safeStr(comment.created_at || '') + '</div>' +
                '<div style="margin-top:6px;">' + safeStr(comment.comment || '') + '</div>';
              container.appendChild(card);
            });
          }
        }

        showBox('noteCommentsContent');
      })
      .catch(function (error) {
        hideBox('noteCommentsLoading');
        setError('noteCommentsError', error.message || 'Network error. Please try again.');
      });
  };

  function bindStaticEvents() {
    qsa('.modal-overlay').forEach(function (overlay) {
      overlay.setAttribute('aria-hidden', overlay.classList.contains('is-open') ? 'false' : 'true');

      overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
          window.closeModal(overlay.id);
        }
      });
    });

    qsa('.modal-close, [data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var modal = btn.closest('.modal-overlay');
        if (modal) window.closeModal(modal.id);
      });
    });

    qsa('form').forEach(function (form) {
      ['note', 'assignment'].forEach(function (type) {
        if (form.querySelector('.' + type + '-file-wrap') || form.querySelector('.' + type + '-url-wrap') || form.querySelector('.' + type + '-existing-wrap')) {
          qsa('input[name="content_type"]', form).forEach(function (radio) {
            radio.addEventListener('change', function () {
              window.toggleContentSource(form, type);
            });
          });
          window.toggleContentSource(form, type);
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        qsa('.modal-overlay.is-open, .modal-overlay.active').forEach(function (modal) {
          window.closeModal(modal.id);
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindStaticEvents);
  } else {
    bindStaticEvents();
  }
})();
