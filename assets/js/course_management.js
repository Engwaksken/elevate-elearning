(function () {
    'use strict';

    console.log('Course Management JS loaded');

    function byId(id) {
        return document.getElementById(id);
    }

    function escapeHtml(text) {
        text = (text ?? '').toString();

        return text.replace(/[&<>"']/g, function (m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    function setValue(ids, value) {
        ids.forEach(function (id) {
            var el = byId(id);
            if (el) {
                el.value = value ?? '';
            }
        });
    }

    function setText(ids, value) {
        ids.forEach(function (id) {
            var el = byId(id);
            if (el) {
                el.textContent = value ?? '';
            }
        });
    }

    function toDateTimeLocal(value) {
        if (!value) return '';

        value = String(value).trim();

        if (!value) return '';

        return value.replace(' ', 'T').substring(0, 16);
    }

    function openOverlayModal(id) {
        var modal = byId(id);

        if (!modal) {
            alert('Modal not found: ' + id);
            console.error('Modal not found:', id);
            return false;
        }

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        return true;
    }

    function closeOverlayModal(id) {
        var modal = byId(id);

        if (!modal) return;

        modal.classList.remove('is-open');

        if (!document.querySelector('.modal-overlay.is-open')) {
            document.body.style.overflow = '';
        }
    }

    function openBootstrapModal(id) {
        if (
            window.jQuery &&
            typeof window.jQuery.fn.modal === 'function' &&
            window.jQuery('#' + id).length
        ) {
            window.jQuery('#' + id).modal({
                backdrop: 'static',
                keyboard: true,
                show: true
            });

            return true;
        }

        return false;
    }

    function openAnyModal(id) {
        if (byId(id) && byId(id).classList.contains('modal-overlay')) {
            return openOverlayModal(id);
        }

        if (openBootstrapModal(id)) {
            return true;
        }

        return openOverlayModal(id);
    }

    window.openModal = function (id) {
        openAnyModal(id);
    };

    window.closeModal = function (id) {
        closeOverlayModal(id);

        if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            window.jQuery('#' + id).modal('hide');
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Intake change
    |--------------------------------------------------------------------------
    */
    window.changexps = function (sel) {
        var doit = sel.value;

        if (doit) {
            var courseId = (window.courseConfig && window.courseConfig.courseId)
                ? window.courseConfig.courseId
                : '';

            window.location.href =
                "?view=open_coursex&course=" +
                encodeURIComponent(courseId) +
                "&ausydfgasuidgipppuwqu=" +
                encodeURIComponent(doit);
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Add notes / assignments
    |--------------------------------------------------------------------------
    */
    window.openAddNoteModal = function (moduleId, moduleCode, moduleTitle) {
        openAnyModal('myModal6');

        setValue(['themodule'], moduleId || '');
        setText(['hghg'], moduleCode + " (" + moduleTitle + ")");

        var form = byId('addNotesForm');
        if (form) form.reset();

        if (window.jQuery) {
            window.jQuery('#fileInfo').hide();
        }
    };

    window.openAddAssignmentModal = function (moduleId, moduleCode, moduleTitle) {
        openAnyModal('myModal7');

        setValue(['theass'], moduleId || '');
        setText(['hxhx'], moduleCode + " (" + moduleTitle + ")");

        var form = byId('addAssignmentForm');
        if (form) form.reset();

        setValue(['assignmentTime'], '23:59');

        if (window.jQuery) {
            window.jQuery('#assignmentFileInfo').hide();
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Edit notes
    |--------------------------------------------------------------------------
    */
    window.openEditNoteModal = function (noteId, moduleId, title, shortMsg) {
        setValue(['edit_note_id'], noteId || '');
        setValue(['edit_note_unit', 'edit_note_module'], moduleId || '');
        setValue(['edit_note_title'], title || '');
        setValue(['edit_note_message', 'edit_note_short'], shortMsg || '');

        openAnyModal('editNoteModal');
    };

    /*
    |--------------------------------------------------------------------------
    | Edit assignments
    |--------------------------------------------------------------------------
    */
    window.openEditAssignmentModal = function (assId, moduleId, title, shortMsg, dueRaw) {
        var dueLocal = toDateTimeLocal(dueRaw);
        var datePart = '';
        var timePart = '';

        if (dueLocal) {
            var split = dueLocal.split('T');
            datePart = split[0] || '';
            timePart = split[1] || '';
        }

        setValue(['edit_assignment_id', 'edit_ass_id'], assId || '');
        setValue(['edit_assignment_unit', 'edit_ass_module'], moduleId || '');
        setValue(['edit_assignment_title', 'edit_ass_title'], title || '');
        setValue(['edit_assignment_message', 'edit_ass_short'], shortMsg || '');
        setValue(['edit_assignment_due'], dueLocal);
        setValue(['edit_ass_due_date'], datePart);
        setValue(['edit_ass_due_time'], timePart);

        openAnyModal('editAssignmentModal');
    };

    /*
    |--------------------------------------------------------------------------
    | Delete modals
    |--------------------------------------------------------------------------
    */
    window.openDeleteNoteModal = function (noteId, title, doc) {
        setValue(['del_note_id'], noteId || '');
        setValue(['del_note_doc'], doc || '');
        setText(['del_note_title'], title || 'this note');

        openAnyModal('deleteNoteModal');
    };

    window.openDeleteAssignmentModal = function (assId, title, doc) {
        setValue(['del_ass_id'], assId || '');
        setValue(['del_ass_doc'], doc || '');
        setText(['del_ass_title'], title || 'this assignment');

        openAnyModal('deleteAssignmentModal');
    };

    /*
    |--------------------------------------------------------------------------
    | Analytics modal
    |--------------------------------------------------------------------------
    */
    window.viewNoteAnalytics = function (noteId, title) {
        setText(['noteAnalyticsTitle'], title || '');

        if (window.jQuery) {
            window.jQuery('#noteAnalyticsError').hide().find('span').text('');
            window.jQuery('#noteAnalyticsContent').hide();
            window.jQuery('#noteAnalyticsLoading').show();
        }

        openAnyModal('noteAnalyticsModal');

        if (!window.jQuery) {
            fetch('scripts/get_note_analytics.php?note_id=' + encodeURIComponent(noteId))
                .then(function (r) {
                    return r.json();
                })
                .then(renderAnalytics)
                .catch(function () {
                    showAnalyticsError('Network error. Please try again.');
                });

            return;
        }

        window.jQuery.ajax({
            url: 'scripts/get_note_analytics.php',
            method: 'GET',
            dataType: 'json',
            data: { note_id: noteId },
            success: renderAnalytics,
            error: function (xhr, status, error) {
                console.error('Analytics error:', { xhr: xhr, status: status, error: error });

                showAnalyticsError(
                    'Request failed: ' +
                    (xhr.status || '') +
                    ' ' +
                    (xhr.statusText || error || '')
                );
            },
            complete: function () {
                window.jQuery('#noteAnalyticsLoading').hide();
            }
        });
    };

    function renderAnalytics(resp) {
        if (window.jQuery) {
            window.jQuery('#noteAnalyticsLoading').hide();
        }

        if (!resp || (!resp.success && !resp.data && !resp.students)) {
            showAnalyticsError(resp?.message || resp?.error || 'Failed to load analytics.');
            return;
        }

        var stats = resp.data?.statistics || resp.statistics || {};
        var students = resp.data?.student_progress || resp.students || [];

        setText(['stat_total_students'], stats.total_students ?? resp.total_students ?? 0);
        setText(['stat_avg_progress'], (stats.avg_progress ?? resp.avg_progress ?? 0) + '%');
        setText(['stat_completed'], stats.completed_count ?? resp.completed ?? 0);
        setText(['stat_not_started'], stats.not_started_count ?? resp.not_started ?? 0);

        var tbody = byId('analyticsStudentsBody');

        if (!tbody) return;

        var rows = '';

        if (!students.length) {
            rows =
                '<tr>' +
                    '<td colspan="3" style="text-align:center; padding:24px; color:var(--muted);">' +
                        'No student data available' +
                    '</td>' +
                '</tr>';
        } else {
            students.forEach(function (s) {
                var name = escapeHtml(s.student_name || s.name || 'Unknown');
                var perc = Number(s.reading_percentage ?? s.progress ?? 0).toFixed(1);
                var last = escapeHtml(s.last_accessed || 'Never');

                rows +=
                    '<tr>' +
                        '<td><i class="fa fa-user"></i> ' + name + '</td>' +
                        '<td><span class="label label-primary">' + perc + '%</span></td>' +
                        '<td><small>' + last + '</small></td>' +
                    '</tr>';
            });
        }

        tbody.innerHTML = rows;

        if (window.jQuery) {
            window.jQuery('#noteAnalyticsContent').show();
        } else {
            var c = byId('noteAnalyticsContent');
            if (c) c.style.display = 'block';
        }
    }

    function showAnalyticsError(message) {
        if (window.jQuery) {
            window.jQuery('#noteAnalyticsLoading').hide();
            window.jQuery('#noteAnalyticsError').show().find('span').text(message);
            return;
        }

        var loading = byId('noteAnalyticsLoading');
        var err = byId('noteAnalyticsError');

        if (loading) loading.style.display = 'none';

        if (err) {
            var span = err.querySelector('span');
            if (span) span.textContent = message;
            err.style.display = 'flex';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Comments modal
    |--------------------------------------------------------------------------
    */
    window.viewNoteComments = function (noteId, title) {
        setText(['noteCommentsTitle'], title || '');

        if (window.jQuery) {
            window.jQuery('#noteCommentsError').hide().find('span').text('');
            window.jQuery('#noteCommentsContent').hide();
            window.jQuery('#commentsContainer').empty();
            window.jQuery('#noteCommentsLoading').show();
        }

        openAnyModal('noteCommentsModal');

        if (!window.jQuery) {
            fetch('scripts/get_instructor_comments.php?note_id=' + encodeURIComponent(noteId))
                .then(function (r) {
                    return r.json();
                })
                .then(renderComments)
                .catch(function () {
                    showCommentsError('Network error. Please try again.');
                });

            return;
        }

        window.jQuery.ajax({
            url: 'scripts/get_instructor_comments.php',
            method: 'GET',
            dataType: 'json',
            data: { note_id: noteId },
            success: renderComments,
            error: function (xhr, status, error) {
                console.error('Comments error:', { xhr: xhr, status: status, error: error });

                showCommentsError(
                    'Request failed: ' +
                    (xhr.status || '') +
                    ' ' +
                    (xhr.statusText || error || '')
                );
            },
            complete: function () {
                window.jQuery('#noteCommentsLoading').hide();
            }
        });
    };

    function renderComments(resp) {
        if (window.jQuery) {
            window.jQuery('#noteCommentsLoading').hide();
        }

        if (!resp || (!resp.success && !resp.comments)) {
            showCommentsError(resp?.message || resp?.error || 'Failed to load comments.');
            return;
        }

        var comments = resp.comments || [];
        var container = byId('commentsContainer');

        if (!container) return;

        if (!comments.length) {
            container.innerHTML =
                '<div class="empty"><i class="fas fa-comments"></i><p>No comments yet.</p></div>';
        } else {
            var html = '';

            comments.forEach(function (c) {
                var name = escapeHtml(c.author_name || c.student_name || 'Unknown');
                var time = escapeHtml(c.time_ago || c.created_at || '');
                var text = escapeHtml(c.comment_text || c.comment || '').replace(/\n/g, '<br>');

                html +=
                    '<div style="background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius); padding:12px 14px; margin-bottom:12px;">' +
                        '<div style="font-size:.82rem; font-weight:600; color:var(--text); margin-bottom:6px;">' +
                            '<i class="fas fa-user"></i> ' + name +
                            '<span style="float:right; color:var(--muted); font-size:.68rem;">' + time + '</span>' +
                        '</div>' +
                        '<div style="font-size:.84rem; color:var(--text); line-height:1.6;">' + text + '</div>' +
                    '</div>';
            });

            container.innerHTML = html;
        }

        if (window.jQuery) {
            window.jQuery('#noteCommentsContent').show();
        } else {
            var ctn = byId('noteCommentsContent');
            if (ctn) ctn.style.display = 'block';
        }
    }

    function showCommentsError(message) {
        if (window.jQuery) {
            window.jQuery('#noteCommentsLoading').hide();
            window.jQuery('#noteCommentsError').show().find('span').text(message);
            return;
        }

        var loading = byId('noteCommentsLoading');
        var err = byId('noteCommentsError');

        if (loading) loading.style.display = 'none';

        if (err) {
            var span = err.querySelector('span');
            if (span) span.textContent = message;
            err.style.display = 'flex';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Overlay close handlers
    |--------------------------------------------------------------------------
    */
    document.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-close-modal], .modal-close');

        if (closeBtn) {
            var modal = closeBtn.closest('.modal-overlay');

            if (modal) {
                closeOverlayModal(modal.id);
            }
        }

        if (e.target.classList && e.target.classList.contains('modal-overlay')) {
            closeOverlayModal(e.target.id);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.is-open').forEach(function (modal) {
                closeOverlayModal(modal.id);
            });
        }
    });

    if (window.jQuery) {
        window.jQuery(document).on('hidden.bs.modal', '.modal', function () {
            if (window.jQuery('.modal.in').length || window.jQuery('.modal.show').length) {
                window.jQuery('body').addClass('modal-open');
            }
        });
    }

    console.log('Course Management JS initialized successfully');
})();