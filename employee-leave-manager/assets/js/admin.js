(() => {
  'use strict';
  const locale = 'sq-XK';
  const albanianWeekdaysShort = ['HËN', 'MAR', 'MËR', 'ENJ', 'PRE', 'SHT', 'DIE'];
  const albanianMonths = ['Janar', 'Shkurt', 'Mars', 'Prill', 'Maj', 'Qershor', 'Korrik', 'Gusht', 'Shtator', 'Tetor', 'Nëntor', 'Dhjetor'];
  const albanianMonthLabel = (date) => `${albanianMonths[date.getMonth()]} ${date.getFullYear()}`;
  const cfg = window.ELMAdmin;
  if (!cfg) return;

  const syncFileInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
    const field = input.closest('.elm-file-field');
    const output = field?.querySelector('[data-file-name]');
    if (!output) return;
    const emptyLabel = output.dataset.emptyLabel || 'Nuk është zgjedhur asnjë skedar';
    output.textContent = input.files && input.files.length ? input.files[0].name : emptyLabel;
  };

  document.querySelectorAll('[data-file-input]').forEach((input) => {
    syncFileInput(input);
    input.addEventListener('change', () => syncFileInput(input));
  });

  const api = async (path, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set('X-WP-Nonce', cfg.nonce);
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    const response = await fetch(cfg.restUrl + path, { credentials: 'same-origin', ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || cfg.i18n.genericError);
    return data;
  };

  const addCell = (row, value) => {
    const cell = document.createElement('td');
    cell.textContent = String(value ?? '');
    row.appendChild(cell);
    return cell;
  };

  const renderTableState = (body, columns, message, kind = 'empty') => {
    if (!body) return;
    body.textContent = '';
    const row = document.createElement('tr');
    row.className = `elm-table-state elm-table-state--${kind}`;
    const cell = document.createElement('td');
    cell.className = 'elm-table-state__cell';
    cell.colSpan = Math.max(1, Number(columns) || 1);
    cell.textContent = String(message || '');
    row.appendChild(cell);
    body.appendChild(row);
  };

  const collectRequestReasons = (request) => {
    if (!Array.isArray(request.reason_history)) return [];
    return request.reason_history
      .map((entry) => ({
        actorName: String(entry.actor_name || '').trim(),
        text: String(entry.text || '').trim(),
        status: String(entry.status || 'pending').trim(),
        happenedAt: String(entry.happened_at || '').trim(),
      }))
      .filter((entry) => entry.text);
  };

  const appendReasonsNotice = (cell, request) => {
    const reasons = collectRequestReasons(request);
    if (!reasons.length) return;
    const notice = document.createElement('div');
    notice.className = 'elm-admin__reasons-notice';
    reasons.forEach((reason, index) => {
      const item = document.createElement('div');
      item.className = `elm-admin__reasons-item elm-admin__reasons-item--${reason.status}`;
      const number = document.createElement('span');
      number.className = 'elm-admin__reasons-number';
      number.textContent = `#${index + 1}`;
      const content = document.createElement('span');
      content.className = 'elm-admin__reasons-content';
      const actor = document.createElement('strong');
      actor.className = 'elm-admin__reasons-actor';
      actor.textContent = reason.actorName;
      content.append(actor, document.createTextNode(`: ${reason.text}`));
      item.append(number, content);
      notice.appendChild(item);
    });
    cell.appendChild(notice);
  };

  const dayNumber = (value) => {
    const match = String(value || '').match(/^\d{4}-\d{2}-(\d{2})$/);
    return match ? String(Number(match[1])) : String(value || '');
  };

  const parseIsoDate = (value) => {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return date.getFullYear() === Number(match[1]) && date.getMonth() === Number(match[2]) - 1 && date.getDate() === Number(match[3]) ? date : null;
  };
  const isoDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const monthCode = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  const shiftMonth = (date, count) => new Date(date.getFullYear(), date.getMonth() + count, 1);
  const editDateLabel = (value) => {
    const date = parseIsoDate(value);
    return date ? date.toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' }) : String(value || '');
  };
  const adminActiveStatuses = new Set(['pending', 'approved']);
  const statusLabel = (status) => ({
    pending: 'Në pritje',
    approved: 'Miratuar',
    rejected: 'Refuzuar',
    cancelled: 'Anuluar',
  }[String(status || '')] || String(status || ''));
  const leaveTypeLabel = (type) => String(type || '') === 'medical' ? 'Pushim mjekësor' : 'Pushim vjetor';

  const warningLabel = () => '';

  const showNotice = (root, message, kind = 'success') => {
    const notice = root.querySelector('[data-admin-notice]');
    if (!notice) return;
    notice.textContent = message;
    notice.className = `elm-admin__notice elm-admin__notice--${kind}`;
    notice.hidden = false;
  };

  const fillUsers = (selects, users) => {
    selects.forEach((select) => {
      const first = select.options[0]?.cloneNode(true);
      select.textContent = '';
      if (first) select.appendChild(first);
      users.forEach((user) => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = `${user.name} - ${user.email}`;
        select.appendChild(option);
      });
    });
  };

  let usersPromise;
  const getUsers = () => {
    if (!usersPromise) usersPromise = api('users');
    return usersPromise;
  };

  document.querySelectorAll('[data-elm-admin="requests"]').forEach((root) => {
    const body = root.querySelector('[data-admin-requests]');
    const userFilter = root.querySelector('[data-filter-user]');
    const statusFilter = root.querySelector('[data-filter-status]');
    const yearFilter = root.querySelector('[data-filter-year]');
    const editDialog = root.querySelector('[data-admin-edit-dialog]');
    const editForm = root.querySelector('[data-admin-edit-form]');
    const editEmployee = root.querySelector('[data-admin-edit-employee]');
    const editCalendars = root.querySelector('[data-admin-edit-calendars]');
    const editSelected = root.querySelector('[data-admin-edit-selected]');
    const editFeedback = root.querySelector('[data-admin-edit-feedback]');
    const editMedical = root.querySelector('[data-admin-edit-medical]');
    const editType = editForm?.querySelector('[name="leave_type"]');
    const editReason = editForm?.querySelector('[name="reason"]');
    const editMedicalAck = editForm?.querySelector('[name="medical_ack"]');
    const deleteDialog = root.querySelector('[data-admin-delete-dialog]');
    const deleteSummary = root.querySelector('[data-admin-delete-summary]');
    const deleteConfirmInput = root.querySelector('[data-admin-delete-confirm]');
    const deleteSubmit = root.querySelector('[data-admin-delete-submit]');
    const deleteFeedback = root.querySelector('[data-admin-delete-feedback]');
    let deleteState = null;
    let editState = null;
    let editBaseMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    let editCalendarDays = {};
    const editDates = new Set();

    const decide = async (id, decision) => {
      const promptText = decision === 'approved'
        ? cfg.i18n.approvePrompt
        : cfg.i18n.rejectPrompt;
      const note = window.prompt(promptText, '') ?? null;
      if (note === null) return;
      if (decision === 'rejected' && !note.trim()) {
        showNotice(root, 'Shkruani arsyetimin e refuzimit.', 'error');
        return;
      }
      try {
        await api(`requests/${id}/decision`, { method: 'POST', body: JSON.stringify({ decision, note }) });
        showNotice(root, `Kërkesa nr. ${id}: ${statusLabel(decision)}.`);
        await loadRequests();
      } catch (error) {
        showNotice(root, error.message, 'error');
      }
    };

    const showEditFeedback = (message, kind = 'error') => {
      if (!editFeedback) return;
      editFeedback.textContent = message;
      editFeedback.className = `elm-admin__edit-feedback elm-admin__edit-feedback--${kind}`;
      editFeedback.hidden = false;
    };

    const clearEditFeedback = () => {
      if (!editFeedback) return;
      editFeedback.hidden = true;
      editFeedback.textContent = '';
    };

    const closeEditRequest = () => {
      editState = null;
      editDates.clear();
      editCalendarDays = {};
      if (editForm) editForm.reset();
      if (editDialog) editDialog.hidden = true;
      document.body.classList.remove('elm-admin-edit-open');
      clearEditFeedback();
    };

    const editDayIsBlocked = (key, day = {}) => {
      if (editState?.originalDates.has(key)) return false;
      if (!day.working_day || day.holiday) return true;
      if (day.capacity_blocked || day.disabled) return true;
      return adminActiveStatuses.has(String(day.own_status || ''));
    };

    const renderEditSelected = () => {
      if (!editSelected) return;
      editSelected.textContent = '';
      const dates = [...editDates].sort();
      if (!dates.length) {
        const empty = document.createElement('span');
        empty.className = 'elm-admin__edit-selected-empty';
        empty.textContent = cfg.i18n.editInvalidDates;
        editSelected.appendChild(empty);
        return;
      }
      dates.forEach((date) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'elm-admin__edit-date-chip';
        chip.dataset.removeEditDate = date;
        chip.setAttribute('aria-label', `Hiq ${editDateLabel(date)}`);
        chip.textContent = `${editDateLabel(date)} ×`;
        editSelected.appendChild(chip);
      });
    };

    const renderEditMonth = (month) => {
      const section = document.createElement('section');
      section.className = 'elm-admin__edit-month';
      const title = document.createElement('h3');
      title.textContent = albanianMonthLabel(month);
      section.appendChild(title);

      const table = document.createElement('table');
      table.className = 'elm-admin__edit-calendar';
      const thead = document.createElement('thead');
      const headerRow = document.createElement('tr');
      albanianWeekdaysShort.forEach((weekday) => {
        const th = document.createElement('th');
        th.textContent = weekday;
        headerRow.appendChild(th);
      });
      thead.appendChild(headerRow);
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      const year = month.getFullYear();
      const monthIndex = month.getMonth();
      const first = new Date(year, monthIndex, 1);
      const offset = (first.getDay() + 6) % 7;
      const count = new Date(year, monthIndex + 1, 0).getDate();
      const cells = Array(offset).fill(null);
      for (let day = 1; day <= count; day += 1) cells.push(day);
      while (cells.length % 7) cells.push(null);

      cells.forEach((day, index) => {
        if (index % 7 === 0) tbody.appendChild(document.createElement('tr'));
        const td = document.createElement('td');
        if (day) {
          const key = isoDate(new Date(year, monthIndex, day));
          const data = editCalendarDays[key] || {};
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'elm-admin__edit-day';
          button.dataset.adminEditDate = key;
          const dayNumber = document.createElement('span');
          dayNumber.textContent = String(day);
          button.appendChild(dayNumber);
          if (data.holiday) {
            const holidayHelp = document.createElement('span');
            holidayHelp.className = 'elm-admin__holiday-help';
            holidayHelp.textContent = '?';
            holidayHelp.dataset.holidayName = data.holiday_name || data.day_note || 'Festë e institucionit';
            holidayHelp.setAttribute('aria-hidden', 'true');
            button.appendChild(holidayHelp);
          }
          const selected = editDates.has(key);
          const blocked = editDayIsBlocked(key, data);
          if (selected) button.classList.add('is-selected');
          if (!data.working_day || data.holiday) button.classList.add('is-nonworking');
          if (data.capacity_blocked || data.disabled) button.classList.add('is-capacity-full');
          if (editState?.originalDates.has(key)) button.classList.add('is-original');
          if (blocked) {
            button.disabled = true;
            button.title = data.disabled_reason || 'Datë pune e padisponueshme';
          } else {
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.title = selected ? `Hiqe ${editDateLabel(key)} nga përzgjedhja` : `Zgjidhe datën ${editDateLabel(key)}`;
          }
          td.appendChild(button);
        }
        tbody.lastElementChild.appendChild(td);
      });
      table.appendChild(tbody);
      section.appendChild(table);
      return section;
    };

    const renderEditCalendars = () => {
      if (!editCalendars) return;
      editCalendars.textContent = '';
      editCalendars.append(renderEditMonth(editBaseMonth), renderEditMonth(shiftMonth(editBaseMonth, 1)));
      renderEditSelected();
    };

    const loadEditCalendars = async () => {
      if (!editState || !editCalendars) return;
      editCalendars.textContent = cfg.i18n.editLoadingCalendar;
      const second = shiftMonth(editBaseMonth, 1);
      const employee = encodeURIComponent(String(editState.request.employee_id));
      const [firstData, secondData] = await Promise.all([
        api(`calendar?month=${encodeURIComponent(monthCode(editBaseMonth))}&employee_id=${employee}`),
        api(`calendar?month=${encodeURIComponent(monthCode(second))}&employee_id=${employee}`),
      ]);
      editCalendarDays = { ...editCalendarDays, ...(firstData.days || {}), ...(secondData.days || {}) };
      renderEditCalendars();
    };

    const openEditRequest = async (request) => {
      if (request.status !== 'pending') {
        showNotice(root, cfg.i18n.editPendingOnly, 'error');
        return;
      }
      if (!editDialog || !editForm || !editType || !editReason) {
        showNotice(root, cfg.i18n.genericError, 'error');
        return;
      }
      const existingDates = Array.isArray(request.selected_dates) && request.selected_dates.length
        ? request.selected_dates.filter((date) => parseIsoDate(date))
        : [request.start_date, request.end_date].filter((date) => parseIsoDate(date));
      editState = { request, originalDates: new Set(existingDates) };
      editDates.clear();
      existingDates.forEach((date) => editDates.add(date));
      editCalendarDays = {};
      const firstDate = parseIsoDate([...editDates].sort()[0]);
      editBaseMonth = firstDate ? new Date(firstDate.getFullYear(), firstDate.getMonth(), 1) : new Date(new Date().getFullYear(), new Date().getMonth(), 1);
      editForm.elements.request_id.value = String(request.id);
      if (editEmployee) editEmployee.value = request.employee_name || '';
      editType.value = request.leave_type === 'medical' ? 'medical' : 'annual';
      editReason.value = String(request.reason || '');
      if (editMedicalAck) editMedicalAck.checked = Boolean(request.medical_ack);
      if (editMedical) editMedical.hidden = editType.value !== 'medical';
      clearEditFeedback();
      editDialog.hidden = false;
      document.body.classList.add('elm-admin-edit-open');
      renderEditSelected();
      try {
        await loadEditCalendars();
      } catch (error) {
        showEditFeedback(error.message);
      }
      editReason.focus();
    };

    if (editCalendars) {
      editCalendars.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-admin-edit-date]') : null;
        if (!button || button.disabled || !editState) return;
        const key = button.dataset.adminEditDate;
        if (!key) return;
        clearEditFeedback();
        if (editDates.has(key)) {
          editDates.delete(key);
        } else {
          editDates.add(key);
        }
        renderEditCalendars();
      });
    }

    if (editSelected) {
      editSelected.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-remove-edit-date]') : null;
        if (!button) return;
        editDates.delete(button.dataset.removeEditDate || '');
        clearEditFeedback();
        renderEditCalendars();
      });
    }

    root.querySelectorAll('[data-admin-edit-nav]').forEach((button) => button.addEventListener('click', async () => {
      if (!editState) return;
      editBaseMonth = shiftMonth(editBaseMonth, Number(button.dataset.adminEditNav || 0));
      try {
        await loadEditCalendars();
      } catch (error) {
        showEditFeedback(error.message);
      }
    }));

    root.querySelectorAll('[data-admin-edit-close]').forEach((button) => button.addEventListener('click', closeEditRequest));
    editDialog?.addEventListener('click', (event) => {
      if (event.target === editDialog) closeEditRequest();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && editDialog && !editDialog.hidden) closeEditRequest();
      if (event.key === 'Escape' && deleteDialog && !deleteDialog.hidden) closeDeleteRequest();
    });

    editType?.addEventListener('change', () => {
      if (editMedical) editMedical.hidden = editType.value !== 'medical';
    });

    editForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!editState || !editType || !editReason) return;
      clearEditFeedback();
      const selectedDates = [...editDates].sort();
      const reason = editReason.value.trim();
      if (!selectedDates.length) {
        showEditFeedback(cfg.i18n.editInvalidDates);
        return;
      }
      if (!reason) {
        showEditFeedback(cfg.i18n.editReasonRequired);
        editReason.focus();
        return;
      }
      const submit = editForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        await api(`requests/${editState.request.id}`, {
          method: 'PUT',
          body: JSON.stringify({
            leave_type: editType.value,
            selected_dates: selectedDates,
            start_date: selectedDates[0],
            end_date: selectedDates[selectedDates.length - 1],
            reason,
            medical_ack_present: 1,
            medical_ack: editMedicalAck?.checked ? 1 : 0,
            medical_document_id: editState.request.medical_document_id || 0,
          }),
        });
        closeEditRequest();
        showNotice(root, cfg.i18n.editSaved);
        await loadRequests();
      } catch (error) {
        showEditFeedback(error.message);
      } finally {
        if (submit) submit.disabled = false;
      }
    });

    const cancelRequest = async (id) => {
      if (!window.confirm(cfg.i18n.cancelConfirm || 'Ta anuloni këtë kërkesë?')) return;
      try {
        await api(`requests/${id}/cancel`, { method: 'POST', body: JSON.stringify({ note: 'Anuluar nga administratori' }) });
        showNotice(root, cfg.i18n.cancelled || `Kërkesa nr. ${id} u anulua.`);
        await loadRequests();
      } catch (error) {
        showNotice(root, error.message, 'error');
      }
    };

    const setDeleteFeedback = (message = '', kind = 'error') => {
      if (!deleteFeedback) return;
      deleteFeedback.textContent = message;
      deleteFeedback.className = `elm-admin__edit-feedback elm-admin__edit-feedback--${kind}`;
      deleteFeedback.hidden = !message;
    };

    const closeDeleteRequest = () => {
      deleteState = null;
      if (deleteDialog) deleteDialog.hidden = true;
      if (deleteConfirmInput) deleteConfirmInput.value = '';
      if (deleteSubmit) deleteSubmit.disabled = true;
      setDeleteFeedback('');
    };

    const openDeleteRequest = (request) => {
      if (!cfg.canDeleteRequests || !deleteDialog || !deleteConfirmInput || !deleteSubmit) return;
      deleteState = request;
      const dates = Array.isArray(request.selected_dates) ? request.selected_dates.map(dayNumber).join(', ') : '';
      if (deleteSummary) deleteSummary.textContent = `#${request.id} - ${request.employee_name || ''}${dates ? ` - ${dates}` : ''}`;
      deleteConfirmInput.value = '';
      deleteSubmit.disabled = true;
      setDeleteFeedback('');
      deleteDialog.hidden = false;
      deleteConfirmInput.focus();
    };

    const deleteRequest = async () => {
      if (!deleteState || !deleteConfirmInput || deleteConfirmInput.value !== 'DELETE') {
        setDeleteFeedback(cfg.i18n.deleteRequestConfirm || 'Shkruani saktësisht DELETE për të konfirmuar fshirjen.');
        return;
      }
      const requestId = deleteState.id;
      if (deleteSubmit) deleteSubmit.disabled = true;
      try {
        await api(`requests/${requestId}`, { method: 'DELETE', body: JSON.stringify({ confirmation: 'DELETE' }) });
        closeDeleteRequest();
        showNotice(root, cfg.i18n.deleteRequestSuccess || `Kërkesa nr. ${requestId} u fshi.`);
        await loadRequests();
      } catch (error) {
        setDeleteFeedback(error.message);
        if (deleteSubmit) deleteSubmit.disabled = deleteConfirmInput.value !== 'DELETE';
      }
    };

    const loadRequests = async () => {
      const query = new URLSearchParams();
      if (statusFilter.value) query.set('status', statusFilter.value);
      if (userFilter.value) query.set('employee_id', userFilter.value);
      if (yearFilter.value) query.set('year', yearFilter.value);
      renderTableState(body, 7, 'Po ngarkohet...', 'loading');
      try {
        const requests = await api(`requests?${query.toString()}`);
        body.textContent = '';
        if (!requests.length) {
          const hasFilters = Boolean(statusFilter.value || userFilter.value || yearFilter.value);
          renderTableState(
            body,
            7,
            hasFilters
              ? 'Nuk u gjet asnjë kërkesë që përputhet me filtrat.'
              : 'Nuk u gjet asnjë kërkesë për pushim.'
          );
          return;
        }
        requests.forEach((request) => {
          const row = document.createElement('tr');
          addCell(row, `#${request.id}`);
          addCell(row, request.employee_name);
          const selectedDates = Array.isArray(request.selected_dates) && request.selected_dates.length
            ? request.selected_dates
            : [request.start_date, request.end_date].filter((value, index, values) => value && values.indexOf(value) === index);
          const requestDates = selectedDates.map(dayNumber).join(', ');
          const dateCell = addCell(row, `${leaveTypeLabel(request.leave_type)}\n${requestDates}\n${Math.round(Number(request.requested_units) || 0)} ditë`);
          dateCell.style.whiteSpace = 'pre-line';
          const reasonCell = addCell(row, '');
          appendReasonsNotice(reasonCell, request);
          const statusCell = addCell(row, '');
          const badge = document.createElement('span');
          badge.className = `elm-admin__status elm-admin__status--${request.status}`;
          badge.textContent = statusLabel(request.status);
          statusCell.appendChild(badge);
          addCell(row, request.submitted_at);
          const actions = addCell(row, '');
          actions.className = 'elm-admin__actions';
          if (request.status === 'pending') {
            const edit = document.createElement('button');
            edit.className = 'button button-small';
            edit.type = 'button';
            edit.textContent = cfg.i18n.editLabel;
            edit.addEventListener('click', () => openEditRequest(request));
            actions.appendChild(edit);

            const approve = document.createElement('button');
            approve.className = 'button button-small button-primary';
            approve.type = 'button';
            approve.textContent = 'Mirato';
            approve.addEventListener('click', () => decide(request.id, 'approved'));
            actions.appendChild(approve);
            const reject = document.createElement('button');
            reject.className = 'button button-small';
            reject.type = 'button';
            reject.textContent = 'Refuzo';
            reject.addEventListener('click', () => decide(request.id, 'rejected'));
            actions.appendChild(reject);
          }
          if (['pending', 'approved'].includes(request.status)) {
            const cancel = document.createElement('button');
            cancel.className = 'button button-small';
            cancel.type = 'button';
            cancel.textContent = 'Anulo';
            cancel.addEventListener('click', () => cancelRequest(request.id));
            actions.appendChild(cancel);
          }
          if (cfg.canDeleteRequests) {
            const remove = document.createElement('button');
            remove.className = 'button button-small button-link-delete';
            remove.type = 'button';
            remove.textContent = 'Fshi';
            remove.addEventListener('click', () => openDeleteRequest(request));
            actions.appendChild(remove);
          }
          const pdf = document.createElement('a');
          pdf.className = 'button button-small';
          pdf.textContent = 'PDF';
          pdf.href = `${cfg.exportRequestBase}${request.id}&_wpnonce=${encodeURIComponent(cfg.exportRequestNonce)}`;
          actions.appendChild(pdf);
          if (cfg.canViewMedical && request.medical_document_id) {
            const documentLink = document.createElement('a');
            documentLink.className = 'button button-small';
            documentLink.textContent = 'Dokumenti mjekësor';
            documentLink.href = `${cfg.downloadMedicalBase}${request.medical_document_id}&_wpnonce=${encodeURIComponent(cfg.downloadMedicalNonce)}`;
            actions.appendChild(documentLink);
          }
          body.appendChild(row);
        });
      } catch (error) {
        renderTableState(body, 7, error.message || cfg.i18n.genericError, 'error');
      }
    };

    deleteConfirmInput?.addEventListener('input', () => {
      if (deleteSubmit) deleteSubmit.disabled = deleteConfirmInput.value !== 'DELETE';
      setDeleteFeedback('');
    });
    ['copy', 'cut', 'paste', 'drop', 'contextmenu'].forEach((eventName) => {
      deleteConfirmInput?.addEventListener(eventName, (event) => event.preventDefault());
    });
    deleteSubmit?.addEventListener('click', deleteRequest);
    root.querySelectorAll('[data-admin-delete-close]').forEach((button) => button.addEventListener('click', closeDeleteRequest));
    deleteDialog?.addEventListener('click', (event) => {
      if (event.target === deleteDialog) closeDeleteRequest();
    });

    getUsers().then((users) => fillUsers([userFilter], users)).catch(() => {});
    root.querySelector('[data-apply-filters]').addEventListener('click', loadRequests);
    loadRequests();

    const auditStatus = root.querySelector('[data-audit-status]');
    api('audit/verify').then((result) => {
      auditStatus.textContent = result.valid ? `Regjistri i auditimit u verifikua - ${result.entries} regjistrime` : `Verifikimi i regjistrit të auditimit dështoi - ndërprerje te regjistrimi nr. ${result.broken_at}`;
      auditStatus.classList.add(result.valid ? 'is-valid' : 'is-invalid');
    }).catch(() => {
      auditStatus.textContent = 'Verifikimi i auditimit lejohet vetëm për rolin e udhëheqësit.';
    });
  });

  document.querySelectorAll('[data-elm-admin="adjustments"]').forEach((root) => {
    const form = root.querySelector('[data-adjustment-form]');
    const body = root.querySelector('[data-adjustments-body]');
    const entitlementForm = root.querySelector('[data-entitlement-admin-form]');
    const entitlementBody = root.querySelector('[data-entitlement-admin-body]');
    getUsers().then((users) => fillUsers([root.querySelector('[data-user-select]'), root.querySelector('[data-entitlement-user-select]')], users)).catch((error) => showNotice(root, error.message, 'error'));

    const loadEntitlements = async () => {
      if (!entitlementBody) return;
      renderTableState(entitlementBody, 6, 'Po ngarkohet...', 'loading');
      let changes;
      try {
        changes = await api('entitlement-changes');
      } catch (error) {
        renderTableState(entitlementBody, 6, error.message || cfg.i18n.genericError, 'error');
        throw error;
      }
      entitlementBody.textContent = '';
      if (!changes.length) {
        renderTableState(entitlementBody, 6, 'Nuk u gjet asnjë kërkesë për +1 ditë.');
        return;
      }
      changes.forEach((item) => {
        const row = document.createElement('tr');
        addCell(row, item.employee_name || item.employee_username || `Përdoruesi nr. ${item.user_id}`);
        addCell(row, `${Math.round(Number(item.current_entitlement) || 0)} → ${Math.round(Number(item.requested_entitlement) || 0)} ditë`);
        addCell(row, item.effective_year);
        const reason = addCell(row, `${item.reason || ''}
Paraqitur nga ${item.requested_by_username || item.requested_by_name || ''}`);
        reason.style.whiteSpace = 'pre-line';
        const status = addCell(row, statusLabel(item.status || 'pending'));
        status.className = `elm-admin__entitlement-status elm-admin__entitlement-status--${item.status || 'pending'}`;
        if (item.decision_note) {
          const decision = document.createElement('div');
          decision.textContent = `${item.decided_by_username || item.decided_by_name || ''}: ${item.decision_note}`;
          status.appendChild(decision);
        }
        const actions = addCell(row, '');
        if (item.status === 'pending') {
          const approve = document.createElement('button');
          approve.type = 'button';
          approve.className = 'button button-small button-primary';
          approve.textContent = 'Mirato';
          approve.addEventListener('click', async () => {
            const note = window.prompt('Arsyetim për miratimin (opsional):', '');
            if (note === null) return;
            approve.disabled = true;
            try {
              await api(`entitlement-changes/${item.id}/decision`, { method: 'POST', body: JSON.stringify({ decision: 'approved', note: note.trim() }) });
              showNotice(root, 'Ndryshimi u miratua.');
              await loadEntitlements();
            } catch (error) { showNotice(root, error.message, 'error'); approve.disabled = false; }
          });
          const reject = document.createElement('button');
          reject.type = 'button';
          reject.className = 'button button-small';
          reject.textContent = 'Refuzo';
          reject.addEventListener('click', async () => {
            const note = window.prompt('Arsyetimi i refuzimit:', '');
            if (note === null) return;
            if (!note.trim()) { showNotice(root, 'Shkruani arsyetimin e refuzimit.', 'error'); return; }
            reject.disabled = true;
            try {
              await api(`entitlement-changes/${item.id}/decision`, { method: 'POST', body: JSON.stringify({ decision: 'rejected', note: note.trim() }) });
              showNotice(root, 'Kërkesa u refuzua.');
              await loadEntitlements();
            } catch (error) { showNotice(root, error.message, 'error'); reject.disabled = false; }
          });
          actions.append(approve, document.createTextNode(' '), reject);
        }
        entitlementBody.appendChild(row);
      });
    };

    entitlementForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(entitlementForm);
      const submit = entitlementForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        await api('entitlement-changes', {
          method: 'POST',
          body: JSON.stringify({
            user_id: Number(data.get('user_id') || 0),
            requested_entitlement: Number(data.get('requested_entitlement') || 0),
            effective_year: Number(data.get('effective_year') || 0),
            reason: String(data.get('reason') || '').trim(),
          }),
        });
        entitlementForm.querySelector('[name="reason"]').value = '';
        showNotice(root, 'Kërkesa u paraqit dhe është në pritje të miratimit.');
        await loadEntitlements();
      } catch (error) {
        showNotice(root, error.message, 'error');
      } finally {
        if (submit) submit.disabled = false;
      }
    });

    const loadAdjustments = async () => {
      renderTableState(body, 5, 'Po ngarkohet...', 'loading');
      let adjustments;
      try {
        adjustments = await api('adjustments');
      } catch (error) {
        renderTableState(body, 5, error.message || cfg.i18n.genericError, 'error');
        throw error;
      }
      body.textContent = '';
      if (!adjustments.length) {
        renderTableState(body, 5, 'Nuk u gjet asnjë regjistrim për ditë shtesë.');
        return;
      }
      adjustments.forEach((item) => {
        const row = document.createElement('tr');
        addCell(row, item.employee_name);
        addCell(row, item.leave_year);
        addCell(row, `+${Math.round(Number(item.amount) || 0)}`);
        addCell(row, item.note);
        addCell(row, `${item.created_at}\n${item.created_by_name}`).style.whiteSpace = 'pre-line';
        body.appendChild(row);
      });
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      try {
        const result = await api('adjustments', {
          method: 'POST',
          body: JSON.stringify({ user_id: Number(data.get('user_id')), year: Number(data.get('year')), amount: Number(data.get('amount')), note: data.get('note') }),
        });
        showNotice(root, `U shtuan ${Math.round(Number(result.amount) || 0)} ditë. Gjithsej ditë pushimi pas ndryshimit: ${Math.round(Number(result.balance.total_entitlement) || 0)} ditë.`);
        form.querySelector('[name="amount"]').value = '';
        form.querySelector('[name="note"]').value = '';
        await loadAdjustments();
      } catch (error) {
        showNotice(root, error.message, 'error');
      } finally {
        submit.disabled = false;
      }
    });
    loadEntitlements().catch((error) => showNotice(root, error.message, 'error'));
    loadAdjustments().catch((error) => showNotice(root, error.message, 'error'));
  });


  document.querySelectorAll('[data-elm-admin="reports"]').forEach((root) => {
    const body = root.querySelector('[data-report-employees-body]');
    const yearInput = root.querySelector('[data-report-employees-year]');
    const refresh = root.querySelector('[data-report-employees-refresh]');
    const canManageProfiles = Boolean(cfg.canManageEmployeeProfiles);
    if (!body || !yearInput) return;

    const wholeDays = (value) => String(Math.max(0, Math.round(Number(value) || 0)));
    const reportHref = (userId, year) => {
      if (!cfg.exportEmployeeBase || !cfg.exportEmployeeNonce) return '';
      return `${cfg.exportEmployeeBase}${encodeURIComponent(String(userId))}&year=${encodeURIComponent(String(year))}&_wpnonce=${encodeURIComponent(cfg.exportEmployeeNonce)}`;
    };

    const loadEmployees = async () => {
      const year = Math.max(2000, Math.min(2100, Number(yearInput.value) || new Date().getFullYear()));
      yearInput.value = String(year);
      renderTableState(body, 5, 'Po ngarkohet...', 'loading');
      if (refresh) refresh.disabled = true;
      try {
        const users = await api(`users?year=${encodeURIComponent(String(year))}`);
        body.textContent = '';
        if (!Array.isArray(users) || !users.length) {
          renderTableState(body, 5, 'Nuk u gjet asnjë punonjës.');
          return;
        }
        users.forEach((user) => {
          const row = document.createElement('tr');

          const employee = document.createElement('td');
          const name = document.createElement('strong');
          name.textContent = user.name || `Përdoruesi nr. ${user.id}`;
          const email = document.createElement('small');
          email.textContent = user.email || '';
          employee.append(name, document.createElement('br'), email);
          row.appendChild(employee);

          const positionCell = document.createElement('td');
          const position = document.createElement('input');
          position.type = 'text';
          position.className = 'regular-text';
          position.maxLength = 255;
          position.value = user.position || '';
          position.disabled = !canManageProfiles;
          position.setAttribute('aria-label', `Pozita e punonjësit ${user.name || ''}`.trim());
          positionCell.appendChild(position);
          row.appendChild(positionCell);

          const sectorCell = document.createElement('td');
          const sector = document.createElement('input');
          sector.type = 'text';
          sector.className = 'regular-text';
          sector.maxLength = 255;
          sector.value = user.sector || '';
          sector.disabled = !canManageProfiles;
          sector.setAttribute('aria-label', `Sektori ose njësia e punonjësit ${user.name || ''}`.trim());
          sectorCell.appendChild(sector);
          row.appendChild(sectorCell);

          const balance = user.balance || {};
          const balanceCell = document.createElement('td');
          const remaining = document.createElement('strong');
          remaining.textContent = `${wholeDays(balance.remaining)} të mbetura`;
          const details = document.createElement('small');
          details.textContent = `${wholeDays(balance.used)} të shfrytëzuara - ${wholeDays(balance.pending)} në pritje - ${wholeDays(balance.total)} gjithsej`;
          balanceCell.append(remaining, document.createElement('br'), details);
          row.appendChild(balanceCell);

          const actions = document.createElement('td');
          actions.className = 'elm-admin__actions';
          if (canManageProfiles) {
            const save = document.createElement('button');
            save.type = 'button';
            save.className = 'button button-small button-primary';
            save.textContent = 'Ruaj ndryshimet';
            save.addEventListener('click', async () => {
              save.disabled = true;
              try {
                const updated = await api(`users/${encodeURIComponent(String(user.id))}/profile`, {
                  method: 'POST',
                  body: JSON.stringify({
                    position: position.value.trim(),
                    sector: sector.value.trim(),
                  }),
                });
                position.value = updated.position || '';
                sector.value = updated.sector || '';
                showNotice(root, `Të dhënat e punonjësit ${updated.name || user.name || ''} u ruajtën.`);
              } catch (error) {
                showNotice(root, error.message, 'error');
              } finally {
                save.disabled = false;
              }
            });
            actions.appendChild(save);
          }

          const href = reportHref(user.id, year);
          if (href) {
            const report = document.createElement('a');
            report.className = 'button button-small';
            report.target = '_blank';
            report.rel = 'noopener';
            report.href = href;
            report.textContent = 'Krijo PDF';
            actions.appendChild(report);
          }
          row.appendChild(actions);
          body.appendChild(row);
        });
      } catch (error) {
        renderTableState(body, 5, error.message || cfg.i18n.genericError, 'error');
        showNotice(root, error.message, 'error');
      } finally {
        if (refresh) refresh.disabled = false;
      }
    };

    refresh?.addEventListener('click', loadEmployees);
    yearInput.addEventListener('change', loadEmployees);
    loadEmployees();
  });

})();
