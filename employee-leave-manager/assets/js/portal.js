(() => {
  'use strict';

  const locale = 'sq-XK';
  const albanianWeekdaysShort = ['HËN', 'MAR', 'MËR', 'ENJ', 'PRE', 'SHT', 'DIE'];
  const albanianMonths = ['Janar', 'Shkurt', 'Mars', 'Prill', 'Maj', 'Qershor', 'Korrik', 'Gusht', 'Shtator', 'Tetor', 'Nëntor', 'Dhjetor'];

  const parseResponse = async (response) => {
    const text = await response.text();
    if (!text) return {};
    try {
      return JSON.parse(text);
    } catch (error) {
      throw new Error(`Serveri ktheu një përgjigje të pavlefshme (${response.status}).`);
    }
  };

  const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const monthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  const parseIso = (value) => {
    const parts = String(value || '').split('-').map(Number);
    return new Date(parts[0], parts[1] - 1, parts[2]);
  };
  const prettyDate = (value) => parseIso(value).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
  const prettyDateTime = (value) => {
    const normalized = String(value || '').trim().replace(' ', 'T');
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? String(value || '') : parsed.toLocaleString(locale);
  };
  const dayNumber = (value) => String(parseIso(value).getDate());
  const compactDate = (value) => {
    const [year, month, day] = String(value).split('-');
    return `${day}/${month}/${year}`;
  };
  const groupedDateList = (values = []) => {
    const groups = new Map();
    [...new Set((Array.isArray(values) ? values : []).filter(Boolean))]
      .sort()
      .forEach((value) => {
        const [year, month, day] = String(value).split('-');
        if (!year || !month || !day) return;
        const key = `${year}-${month}`;
        if (!groups.has(key)) groups.set(key, { year, month, days: [] });
        groups.get(key).days.push(String(Number(day)));
      });

    return [...groups.values()]
      .map((group) => `${group.days.join(', ')} | ${group.month} | ${group.year}`)
      .join(' ; ');
  };
  const monthLabel = (date) => `${albanianMonths[date.getMonth()]} ${date.getFullYear()}`;
  const addMonths = (date, count) => new Date(date.getFullYear(), date.getMonth() + count, 1);
  const addDays = (date, count) => {
    const result = new Date(date);
    result.setDate(result.getDate() + count);
    return result;
  };
  const wholeDays = (value) => String(Math.max(0, Math.round(Number(value) || 0)));

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

  const renderListState = (container, message, kind = 'empty') => {
    if (!container) return;
    container.textContent = '';
    const state = document.createElement('p');
    state.className = `elm-empty-state elm-empty-state--${kind}`;
    state.textContent = String(message || '');
    container.appendChild(state);
  };
  const statusLabel = (status) => ({
    pending: 'Në pritje',
    approved: 'Miratuar',
    rejected: 'Refuzuar',
    cancelled: 'Anuluar',
  }[String(status || '')] || String(status || ''));
  const activeOwnStatuses = new Set(['pending', 'approved']);
  const allOwnStatuses = new Set(['pending', 'approved', 'rejected', 'cancelled']);

  const syncFileInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
    const field = input.closest('.elm-file-field');
    const output = field?.querySelector('[data-file-name]');
    if (!output) return;
    const emptyLabel = output.dataset.emptyLabel || 'Nuk është zgjedhur asnjë skedar';
    output.textContent = input.files && input.files.length ? input.files[0].name : emptyLabel;
  };

  const initFileInputs = (scope) => {
    scope.querySelectorAll('[data-file-input]').forEach((input) => {
      syncFileInput(input);
      input.addEventListener('change', () => syncFileInput(input));
    });
  };


  const bootEmployeePortalTabs = (root) => {
    if (root.dataset.elmChief === '1' || root.dataset.elmEmployeeTabsInitialized === '1') return;
    root.dataset.elmEmployeeTabsInitialized = '1';
    const buttons = [...root.querySelectorAll('[data-portal-tab]')];
    const panels = [...root.querySelectorAll('[data-portal-panel]')];
    const activate = (name) => {
      buttons.forEach((button) => {
        const active = button.dataset.portalTab === name;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach((panel) => {
        const active = panel.dataset.portalPanel === name;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });
    };
    buttons.forEach((button) => button.addEventListener('click', () => activate(button.dataset.portalTab || 'my')));
  };

  const bootChiefPortal = (root, cfg, i18n) => {
    if (root.dataset.elmChief !== '1' || root.dataset.elmChiefInitialized === '1') return;
    root.dataset.elmChiefInitialized = '1';

    const tabButtons = [...root.querySelectorAll('[data-portal-tab]')];
    const tabPanels = [...root.querySelectorAll('[data-portal-panel]')];
    const requestsBody = root.querySelector('[data-chief-requests-body]');
    const statusFilter = root.querySelector('[data-chief-filter-status]');
    const userFilter = root.querySelector('[data-chief-filter-user]');
    const yearFilter = root.querySelector('[data-chief-filter-year]');
    const applyFilters = root.querySelector('[data-chief-apply-filters]');
    const refreshButton = root.querySelector('[data-chief-refresh]');
    const chiefNotice = root.querySelector('[data-chief-notice]');
    const settingsForm = root.querySelector('[data-chief-settings-form]');
    const settingsNotice = root.querySelector('[data-settings-notice]');
    const chiefEntitlementsSection = root.querySelector('[data-chief-entitlements]');
    const chiefEntitlementsPending = root.querySelector('[data-chief-entitlements-pending]');
    const chiefEntitlementsPendingSection = root.querySelector('.elm-chief-plus-one__pending');
    const chiefEntitlementsHistory = root.querySelector('[data-chief-entitlements-history]');
    const chiefEntitlementNotice = root.querySelector('[data-chief-entitlement-notice]');
    const employeesBody = root.querySelector('[data-chief-employees-body]');
    const employeesYear = root.querySelector('[data-chief-employees-year]');
    const employeesRefresh = root.querySelector('[data-chief-employees-refresh]');
    const employeesNotice = root.querySelector('[data-chief-employees-notice]');
    const adjustmentForm = root.querySelector('[data-chief-adjustment-form]');
    const adjustmentUser = root.querySelector('[data-chief-adjustment-user]');
    const adjustmentsBody = root.querySelector('[data-chief-adjustments-body]');
    const adjustmentsNotice = root.querySelector('[data-chief-adjustments-notice]');
    const adjustmentsRefresh = root.querySelector('[data-chief-adjustments-refresh]');
    const auditVerify = root.querySelector('[data-chief-audit-verify]');
    const auditStatus = root.querySelector('[data-chief-audit-status]');
    const historyUserFilter = root.querySelector('[data-chief-history-user]');
    const historyStatusFilter = root.querySelector('[data-chief-history-status]');
    const historyYearFilter = root.querySelector('[data-chief-history-year]');
    const historyApply = root.querySelector('[data-chief-history-apply]');
    const historyRefresh = root.querySelector('[data-chief-history-refresh]');
    const historyNotice = root.querySelector('[data-chief-history-notice]');
    const historyReport = root.querySelector('[data-chief-history-report]');
    const historyDialog = root.querySelector('[data-chief-history-dialog]');
    const historyDialogTitle = root.querySelector('[data-chief-history-dialog-title]');
    const historyDialogStatus = root.querySelector('[data-chief-history-dialog-status]');
    const historyDialogYear = root.querySelector('[data-chief-history-dialog-year]');
    const historyDialogApply = root.querySelector('[data-chief-history-dialog-apply]');
    const historyDialogNotice = root.querySelector('[data-chief-history-dialog-notice]');
    const historyDialogReport = root.querySelector('[data-chief-history-dialog-report]');
    const currentUserId = Number(cfg.currentUserId || root.dataset.elmCurrentUserId || 0);
    const canApproveExceptions = Boolean(cfg.canApproveExceptions) || root.dataset.elmCanApproveExceptions === '1';
    const canViewMedical = Boolean(cfg.canViewMedical) || root.dataset.elmCanViewMedical === '1';
    const canDeleteRequests = Boolean(cfg.canDeleteRequests) || root.dataset.elmCanDeleteRequests === '1';
    const deleteDialog = root.querySelector('[data-chief-delete-dialog]');
    const deleteSummary = root.querySelector('[data-chief-delete-summary]');
    const deleteConfirmInput = root.querySelector('[data-chief-delete-confirm]');
    const deleteSubmit = root.querySelector('[data-chief-delete-submit]');
    const deleteFeedback = root.querySelector('[data-chief-delete-feedback]');
    let deleteRequestState = null;
    let employerRequestsLoaded = false;
    let historyLoaded = false;
    let historyDialogEmployeeId = 0;
    let settingsLoaded = false;
    let usersLoaded = false;
    let employeeDirectoryLoaded = false;
    let directoryUsers = [];
    let employerRequests = [];

    const chiefApi = async (operation, payload = {}) => {
      const ajaxUrl = cfg.ajaxUrl || root.dataset.elmAjaxUrl || '';
      const nonce = cfg.portalNonce || cfg.ajaxNonce || root.dataset.elmPortalNonce || '';
      if (!ajaxUrl || !nonce) throw new Error(i18n.genericError);
      const body = new URLSearchParams();
      body.set('action', 'elm_chief_portal');
      body.set('nonce', nonce);
      body.set('operation', String(operation || ''));
      body.set('payload', JSON.stringify(payload || {}));
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
      });
      const result = await parseResponse(response);
      if (!response.ok || !result?.success) {
        throw new Error(result?.data?.message || result?.message || i18n.genericError);
      }
      return result.data;
    };

    const showBoxNotice = (node, message, kind = 'success') => {
      if (!node) return;
      const globalClass = node.matches('[data-settings-notice]') ? ' elm-chief-notice--global' : '';
      const fallbackMessage = kind === 'error'
        ? (i18n.genericError || 'Ndodhi një gabim. Provoni përsëri.')
        : (node.matches('[data-settings-notice]')
          ? (i18n.settingsSaved || 'Cilësimet u ruajtën me sukses.')
          : (i18n.operationSaved || 'Veprimi u krye me sukses.'));
      const visibleMessage = typeof message === 'string' && message.trim() ? message.trim() : fallbackMessage;
      node.textContent = visibleMessage;
      node.className = `elm-chief-notice${globalClass} elm-chief-notice--${kind}`;
      node.hidden = false;
    };

    const clearBoxNotice = (node) => {
      if (!node) return;
      const globalClass = node.matches('[data-settings-notice]') ? ' elm-chief-notice--global' : '';
      node.hidden = true;
      node.textContent = '';
      node.className = `elm-chief-notice${globalClass}`;
    };

    const portalPageInput = settingsForm?.querySelector('[name="portal_page_id"]');
    const frontendOnlyInput = settingsForm?.querySelector('[name="frontend_only_enabled"]');
    const plusOneAlwaysVisibleInput = root.querySelector('[data-plus-one-visibility-toggle]');
    const plusOneVisibilityStatus = root.querySelector('[data-plus-one-visibility-status]');
    let plusOneAlwaysVisible = root.dataset.elmPlusOneAlwaysVisible === '1';
    let lastPlusOnePendingCount = 0;
    let entitlementChangesLoaded = false;

    const refreshSelfPortal = () => {
      const button = root.querySelector('[data-refresh-requests]');
      if (button && !button.disabled) button.click();
    };

    const applyPlusOneVisibility = (pendingCount = lastPlusOnePendingCount) => {
      if (!chiefEntitlementsSection) return;
      lastPlusOnePendingCount = Math.max(0, Number(pendingCount) || 0);
      const shouldShow = lastPlusOnePendingCount > 0 || plusOneAlwaysVisible;
      chiefEntitlementsSection.hidden = !shouldShow;
      chiefEntitlementsSection.classList.toggle('elm-chief-plus-one--hidden', !shouldShow);
      chiefEntitlementsSection.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
      if (!shouldShow) {
        const history = chiefEntitlementsSection.querySelector('.elm-chief-plus-one__history');
        if (history) history.open = false;
      }
    };

    const showPlusOneVisibilityStatus = (message, kind = 'success') => {
      if (!plusOneVisibilityStatus) return;
      plusOneVisibilityStatus.textContent = String(message || '');
      plusOneVisibilityStatus.className = `elm-plus-one-display-settings__status elm-plus-one-display-settings__status--${kind}`;
      plusOneVisibilityStatus.hidden = !message;
    };

    const activateTab = (name) => {
      tabButtons.forEach((button) => {
        const active = button.dataset.portalTab === name;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      tabPanels.forEach((panel) => {
        const active = panel.dataset.portalPanel === name;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });
      if (name === 'employers' && !employerRequestsLoaded) {
        employerRequestsLoaded = true;
        Promise.all([loadEmployerUsers(), loadEmployerRequests(), loadEntitlementChanges()]).catch((error) => showBoxNotice(chiefNotice, error.message, 'error'));
      }
      if (name === 'history' && !historyLoaded) {
        historyLoaded = true;
        Promise.all([loadEmployerUsers(), loadHistoryReport()]).catch((error) => showBoxNotice(historyNotice, error.message, 'error'));
      }
      if (name === 'settings' && !settingsLoaded) {
        settingsLoaded = true;
        if (settingsForm) {
          employeeDirectoryLoaded = true;
          Promise.all([loadEmployerUsers(), verifyAuditLedger()]).catch((error) => showBoxNotice(settingsNotice, error.message, 'error'));
        }
      }
    };

    tabButtons.forEach((button) => button.addEventListener('click', () => activateTab(button.dataset.portalTab || 'my')));

    const appendReasonHistory = (cell, request) => {
      const history = Array.isArray(request.reason_history) ? request.reason_history : [];
      if (!history.length) return;
      const notice = document.createElement('div');
      notice.className = 'elm-request-reasons-notice';
      history.forEach((reason, index) => {
        const item = document.createElement('div');
        item.className = `elm-request-reasons-item elm-request-reasons-item--${reason.status || 'pending'}`;
        const number = document.createElement('span');
        number.className = 'elm-request-reasons-number';
        number.textContent = `#${index + 1}`;
        const content = document.createElement('span');
        content.className = 'elm-request-reasons-content';
        const actor = document.createElement('strong');
        actor.className = 'elm-request-reasons-actor';
        actor.textContent = String(reason.actor_name || reason.actorName || '');
        content.append(actor, document.createTextNode(`: ${String(reason.text || '')}`));
        item.append(number, content);
        notice.appendChild(item);
      });
      cell.appendChild(notice);
    };

    const warningLabel = () => '';

    const addCell = (row, text = '') => {
      const cell = document.createElement('td');
      cell.textContent = String(text ?? '');
      row.appendChild(cell);
      return cell;
    };

    const makeAction = (label, handler, primary = false) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `elm-link-button${primary ? ' elm-link-button--primary' : ''}`;
      button.textContent = label;
      button.addEventListener('click', handler);
      return button;
    };

    const makeRequestAction = (label, fallbackLabel, handler, primary = false, actionName = '') => {
      const button = document.createElement('button');
      const visibleLabel = String(label || '').trim() || String(fallbackLabel || '').trim() || 'Veprim';
      button.type = 'button';
      button.className = `button button-small elm-chief-action${primary ? ' button-primary' : ''}${actionName ? ` elm-chief-action--${actionName}` : ''}`;
      button.textContent = visibleLabel;
      button.setAttribute('aria-label', visibleLabel);
      button.title = visibleLabel;
      button.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (button.disabled) return;
        try {
          await handler(event);
        } catch (error) {
          showBoxNotice(chiefNotice, error?.message || i18n.genericError, 'error');
        }
      });
      return button;
    };

    // The review table uses the same visible action system as "Pushimi im".
    // Separate event handlers are kept so supervisor actions still call the scoped chief API.
    const makeReviewAction = (label, fallbackLabel, handler, actionName = '') => {
      const button = document.createElement('button');
      const visibleLabel = String(label || '').trim() || String(fallbackLabel || '').trim() || 'Veprim';
      button.type = 'button';
      button.className = `button button-small elm-self-action${actionName ? ` elm-self-action--${actionName}` : ''}${actionName === 'delete' ? ' button-link-delete' : ''}`;
      button.textContent = visibleLabel;
      button.setAttribute('aria-label', visibleLabel);
      button.title = visibleLabel;
      button.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (button.disabled) return;
        try {
          await handler(event);
        } catch (error) {
          showBoxNotice(chiefNotice, error?.message || i18n.genericError, 'error');
        }
      });
      return button;
    };

    const reviewRequestPdfUrl = (request) => {
      const serverUrl = String(request?.pdf_url || '').trim();
      if (serverUrl) return serverUrl;
      const base = String(cfg.exportRequestBase || root.dataset.elmExportRequestBase || '').trim();
      const nonce = String(cfg.exportRequestNonce || root.dataset.elmExportRequestNonce || '').trim();
      const requestId = Number(request?.id || 0);
      if (!base || !nonce || !requestId) return '';
      return `${base}${encodeURIComponent(String(requestId))}&_wpnonce=${encodeURIComponent(nonce)}`;
    };

    const decisionRequest = async (request, decision) => {
      const shortWarningDates = Array.isArray(request.short_notice_warning_dates) ? request.short_notice_warning_dates : [];
      const periodWarningDates = Array.isArray(request.period_one_warning_dates) ? request.period_one_warning_dates : [];
      if (decision === 'approved' && shortWarningDates.length) {
        const warningText = String(i18n.chiefShortNoticeConfirm || 'Kjo kërkesë përfshin data brenda afatit minimal 15-ditor: %s. Shqyrtojeni këtë vërejtje para miratimit.')
          .replace('%s', groupedDateList(shortWarningDates));
        if (!window.confirm(warningText)) return;
      }
      if (decision === 'approved' && periodWarningDates.length) {
        const warningText = String(i18n.chiefPeriodOneConfirm || 'Kjo kërkesë kalon pragun informues 10-ditor për periudhën janar-qershor. Ditët mbi prag: %s. Vazhdoni me miratimin vetëm pasi ta keni shqyrtuar këtë tejkalim.')
          .replace('%s', groupedDateList(periodWarningDates));
        if (!window.confirm(warningText)) return;
      }
      const promptText = decision === 'approved'
        ? (String(i18n.approvePrompt || '').trim() || 'Arsyetim për miratimin (opsional):')
        : (String(i18n.rejectPrompt || '').trim() || 'Arsyetimi i refuzimit:');
      const note = window.prompt(promptText, '');
      if (note === null) return;
      if (decision === 'rejected' && !note.trim()) {
        showBoxNotice(chiefNotice, String(i18n.rejectPrompt || '').trim() || 'Arsyetimi i refuzimit është i detyrueshëm.', 'error');
        return;
      }
      clearBoxNotice(chiefNotice);
      try {
        await chiefApi('decision', { request_id: request.id, decision, note: note.trim() });
        showBoxNotice(chiefNotice, String(i18n.operationSaved || '').trim() || 'Kërkesa u përditësua me sukses.');
        await loadEmployerRequests();
        refreshSelfPortal();
      } catch (error) {
        showBoxNotice(chiefNotice, error.message, 'error');
      }
    };

    const cancelEmployerRequest = async (request) => {
      const reason = window.prompt(String(i18n.cancelManagementPrompt || '').trim() || 'Arsyetimi i anulimit:', '');
      if (reason === null) return;
      if (!reason.trim()) {
        showBoxNotice(chiefNotice, String(i18n.cancelReasonRequired || '').trim() || 'Shkruani arsyetimin e anulimit.', 'error');
        return;
      }
      clearBoxNotice(chiefNotice);
      try {
        await chiefApi('cancel', { request_id: request.id, note: reason.trim() });
        showBoxNotice(chiefNotice, String(i18n.cancelledMessage || '').trim() || 'Kërkesa u anulua. Veprimi u regjistrua në regjistrin e auditimit.');
        await loadEmployerRequests();
        refreshSelfPortal();
      } catch (error) {
        showBoxNotice(chiefNotice, error.message, 'error');
      }
    };

    const clearDeleteFeedback = () => {
      if (!deleteFeedback) return;
      deleteFeedback.hidden = true;
      deleteFeedback.textContent = '';
      deleteFeedback.className = 'elm-chief-edit-feedback';
    };

    const showDeleteFeedback = (message) => {
      if (!deleteFeedback) return;
      deleteFeedback.textContent = message;
      deleteFeedback.className = 'elm-chief-edit-feedback elm-chief-edit-feedback--error';
      deleteFeedback.hidden = false;
    };

    const closeDeleteDialog = () => {
      if (!deleteDialog) return;
      deleteDialog.hidden = true;
      deleteRequestState = null;
      if (deleteConfirmInput) deleteConfirmInput.value = '';
      if (deleteSubmit) deleteSubmit.disabled = true;
      clearDeleteFeedback();
      document.body.classList.remove('elm-chief-delete-open');
    };

    const openDeleteDialog = (request) => {
      if (!canDeleteRequests || !deleteDialog || !deleteConfirmInput || !deleteSubmit) return;
      deleteRequestState = request;
      deleteConfirmInput.value = '';
      deleteSubmit.disabled = true;
      clearDeleteFeedback();
      if (deleteSummary) {
        const dates = Array.isArray(request.selected_dates) ? groupedDateList(request.selected_dates) : '';
        deleteSummary.textContent = `#${request.id} - ${request.employee_name || ''}${dates ? ` - ${dates}` : ''}`;
      }
      deleteDialog.hidden = false;
      document.body.classList.add('elm-chief-delete-open');
      window.setTimeout(() => deleteConfirmInput.focus(), 0);
    };

    const renderEntitlementChanges = (changes = []) => {
      if (!chiefEntitlementsPending || !chiefEntitlementsHistory) return;
      chiefEntitlementsPending.textContent = '';
      chiefEntitlementsHistory.textContent = '';

      const rows = Array.isArray(changes) ? changes : [];
      const pending = rows.filter((change) => change.status === 'pending' && Number(change.user_id) !== currentUserId);
      const history = rows.filter((change) => change.status !== 'pending' && Number(change.user_id) !== currentUserId);

      entitlementChangesLoaded = true;
      if (chiefEntitlementsPendingSection) chiefEntitlementsPendingSection.hidden = pending.length === 0;
      applyPlusOneVisibility(pending.length);

      const renderCard = (change, target, interactive = false) => {
        const item = document.createElement('article');
        item.className = `elm-plus-one-request elm-plus-one-request--${change.status || 'pending'}`;

        const head = document.createElement('div');
        head.className = 'elm-plus-one-request__head';

        const identity = document.createElement('div');
        const employee = document.createElement('strong');
        employee.textContent = change.employee_name || change.employee_username || `Përdoruesi nr. ${change.user_id}`;
        const changeLine = document.createElement('small');
        const delta = Math.round(Number(change.requested_entitlement) || 0) - Math.round(Number(change.current_entitlement) || 0);
        const deltaLabel = `${delta > 0 ? '+' : ''}${delta} ${Math.abs(delta) === 1 ? 'ditë' : 'ditë'}`;
        changeLine.textContent = `${deltaLabel} • ${wholeDays(change.current_entitlement)} → ${wholeDays(change.requested_entitlement)} • ${change.effective_year}`;
        identity.append(employee, changeLine);

        const badge = document.createElement('span');
        badge.className = `elm-status elm-status--${change.status || 'pending'}`;
        badge.textContent = statusLabel(change.status || 'pending');
        head.append(identity, badge);
        item.appendChild(head);

        if (change.reason) {
          const reason = document.createElement('p');
          reason.className = 'elm-plus-one-request__reason';
          reason.textContent = change.reason;
          item.appendChild(reason);
        }

        if (change.decision_note) {
          const decision = document.createElement('p');
          decision.className = `elm-plus-one-request__decision elm-entitlement-decision--${change.status}`;
          const actor = change.decided_by_username || change.decided_by_name || '';
          decision.textContent = actor ? `${actor}: ${change.decision_note}` : change.decision_note;
          item.appendChild(decision);
        }

        if (interactive && change.status === 'pending') {
          const actions = document.createElement('div');
          actions.className = 'elm-plus-one-request__actions elm-chief-actions';

          const approve = makeRequestAction(i18n.approve || 'Mirato', 'Mirato', async () => {
            const note = window.prompt(i18n.entitlementApprovePrompt || 'Shënim për miratimin (opsional):', '');
            if (note === null) return;
            try {
              await chiefApi('decide_entitlement_change', { id: change.id, decision: 'approved', note: note.trim() });
              const message = i18n.entitlementApproved || 'Kërkesa për +1 ditë u miratua.';
              showBoxNotice(chiefEntitlementNotice, message);
              await loadEntitlementChanges();
              if (chiefEntitlementsSection?.hidden) showBoxNotice(chiefNotice, message);
              refreshSelfPortal();
            } catch (error) {
              showBoxNotice(chiefEntitlementNotice, error.message, 'error');
            }
          }, true, 'approve');

          const reject = makeRequestAction(i18n.reject || 'Refuzo', 'Refuzo', async () => {
            const note = window.prompt(i18n.entitlementRejectPrompt || 'Arsyetimi i refuzimit:', '');
            if (note === null) return;
            if (!note.trim()) {
              showBoxNotice(chiefEntitlementNotice, i18n.entitlementRejectPrompt || 'Shkruani arsyetimin e refuzimit.', 'error');
              return;
            }
            try {
              await chiefApi('decide_entitlement_change', { id: change.id, decision: 'rejected', note: note.trim() });
              const message = i18n.entitlementRejected || 'Kërkesa për +1 ditë u refuzua.';
              showBoxNotice(chiefEntitlementNotice, message);
              await loadEntitlementChanges();
              if (chiefEntitlementsSection?.hidden) showBoxNotice(chiefNotice, message);
            } catch (error) {
              showBoxNotice(chiefEntitlementNotice, error.message, 'error');
            }
          }, false, 'reject');

          actions.append(approve, reject);
          item.appendChild(actions);
        }

        target.appendChild(item);
      };

      if (!pending.length) {
        renderListState(chiefEntitlementsPending, i18n.entitlementNoPending || 'Nuk u gjet asnjë kërkesë +1 ditë në pritje.');
      } else {
        pending.forEach((change) => renderCard(change, chiefEntitlementsPending, true));
      }

      if (!history.length) {
        renderListState(chiefEntitlementsHistory, i18n.entitlementNoDecisionHistory || 'Nuk u gjet asnjë kërkesë +1 ditë në historik.');
      } else {
        history.forEach((change) => renderCard(change, chiefEntitlementsHistory, false));
      }
    };

    const loadEntitlementChanges = async () => {
      if (!chiefEntitlementsPending || !chiefEntitlementsHistory) return [];
      if (chiefEntitlementsSection && !entitlementChangesLoaded) {
        chiefEntitlementsSection.hidden = true;
        chiefEntitlementsSection.classList.add('elm-chief-plus-one--hidden');
        chiefEntitlementsSection.setAttribute('aria-hidden', 'true');
      }
      renderListState(chiefEntitlementsPending, i18n.loading || 'Po ngarkohet...', 'loading');
      renderListState(chiefEntitlementsHistory, i18n.loading || 'Po ngarkohet...', 'loading');
      const changes = await chiefApi('entitlement_changes', {});
      renderEntitlementChanges(Array.isArray(changes) ? changes : []);
      return changes;
    };

    const setHistoryLoading = (target) => {
      if (!target) return;
      target.textContent = '';
      const state = document.createElement('p');
      state.className = 'elm-empty-state elm-empty-state--loading';
      state.textContent = i18n.loading || 'Po ngarkohet...';
      target.appendChild(state);
    };

    const historyMetric = (label, value, primary = false) => {
      const item = document.createElement('div');
      item.className = `elm-metric elm-history-metric${primary ? ' elm-metric--primary' : ''}`;
      const caption = document.createElement('span');
      caption.textContent = label;
      const total = document.createElement('strong');
      total.textContent = wholeDays(value);
      item.append(caption, total);
      return item;
    };

    const renderHistoryReport = (target, report) => {
      if (!target) return;
      target.textContent = '';
      const employees = Array.isArray(report?.employees) ? report.employees : [];
      const requests = Array.isArray(report?.requests) ? report.requests : [];
      const year = Number(report?.year || historyYearFilter?.value || root.dataset.elmYear || new Date().getFullYear());

      if (!employees.length) {
        renderListState(target, i18n.noEmployees || 'Nuk u gjet asnjë punonjës.');
        return;
      }

      const totalBalance = employees.reduce((sum, employee) => {
        const balance = employee.balance || {};
        sum.total += Number(balance.total || 0);
        sum.used += Number(balance.used || 0);
        sum.pending += Number(balance.pending || 0);
        sum.remaining += Number(balance.remaining || 0);
        return sum;
      }, { total: 0, used: 0, pending: 0, remaining: 0 });

      const overview = document.createElement('section');
      overview.className = 'elm-history-overview';
      const overviewHead = document.createElement('div');
      overviewHead.className = 'elm-history-section-head';
      const overviewTitle = document.createElement('div');
      const title = document.createElement('h4');
      title.textContent = employees.length === 1 ? (employees[0].name || i18n.historyTitle || 'Historiku i pushimeve') : (i18n.historyEmployeeSummary || 'Përmbledhja e punonjësve');
      const subtitle = document.createElement('p');
      subtitle.textContent = `${year} · ${employees.length === 1 ? (i18n.historyAnnualBalance || 'Gjendja e pushimit vjetor') : `${employees.length} punonjës`}`;
      overviewTitle.append(title, subtitle);
      overviewHead.appendChild(overviewTitle);
      overview.appendChild(overviewHead);

      const metrics = document.createElement('div');
      metrics.className = 'elm-balance-grid elm-history-summary-grid';
      metrics.append(
        historyMetric(i18n.historyTotalDays || 'Gjithsej ditë pushimi', totalBalance.total),
        historyMetric(i18n.historyUsedDays || 'Të shfrytëzuara', totalBalance.used),
        historyMetric(i18n.historyPendingDays || 'Në pritje', totalBalance.pending),
        historyMetric(i18n.historyRemainingDays || 'Të mbetura', totalBalance.remaining, true)
      );
      overview.appendChild(metrics);

      // Professional history statistics summary
      const stats = document.createElement('div');
      stats.className = 'elm-history-statistics';
      const statValues = {
        total: requests.length,
        approved: requests.filter((r) => r.status === 'approved').length,
        pending: requests.filter((r) => r.status === 'pending').length,
        rejected: requests.filter((r) => r.status === 'rejected').length,
        cancelled: requests.filter((r) => r.status === 'cancelled').length,
      };
      [
        [i18n.historyTotalRequests || 'Gjithsej kërkesa', statValues.total],
        [statusLabel('approved'), statValues.approved],
        [statusLabel('pending'), statValues.pending],
        [statusLabel('rejected'), statValues.rejected],
        [statusLabel('cancelled'), statValues.cancelled],
      ].forEach(([label, value]) => {
        stats.appendChild(historyMetric(label, value));
      });
      overview.appendChild(stats);

      if (employees.length > 1) {
        const wrap = document.createElement('div');
        wrap.className = 'elm-table-wrap elm-history-employee-table-wrap';
        const table = document.createElement('table');
        table.className = 'elm-table elm-history-employee-table';
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        ['Punonjësi', i18n.historyTotalDays || 'Gjithsej', i18n.historyUsedDays || 'Të shfrytëzuara', i18n.historyPendingDays || 'Në pritje', i18n.historyRemainingDays || 'Të mbetura'].forEach((text) => {
          const th = document.createElement('th');
          th.textContent = text;
          headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        const tbody = document.createElement('tbody');
        employees.forEach((employee) => {
          const row = document.createElement('tr');
          const identity = document.createElement('td');
          const strong = document.createElement('strong');
          strong.textContent = employee.name || `Përdoruesi nr. ${employee.id}`;
          identity.appendChild(strong);
          const meta = [employee.position, employee.sector].filter(Boolean).join(' · ');
          if (meta) {
            const small = document.createElement('small');
            small.textContent = meta;
            identity.append(document.createElement('br'), small);
          }
          row.appendChild(identity);
          const balance = employee.balance || {};
          [balance.total, balance.used, balance.pending, balance.remaining].forEach((value) => addCell(row, wholeDays(value)));
          tbody.appendChild(row);
        });
        table.append(thead, tbody);
        wrap.appendChild(table);
        overview.appendChild(wrap);
      } else {
        const employee = employees[0];
        const meta = [employee.position, employee.sector, employee.email].filter(Boolean).join(' · ');
        if (meta) {
          const employeeMeta = document.createElement('p');
          employeeMeta.className = 'elm-history-employee-meta';
          employeeMeta.textContent = meta;
          overview.appendChild(employeeMeta);
        }
      }
      target.appendChild(overview);

      const details = document.createElement('section');
      details.className = 'elm-history-details';
      const detailsHead = document.createElement('div');
      detailsHead.className = 'elm-history-section-head';
      const detailsCopy = document.createElement('div');
      const detailsTitle = document.createElement('h4');
      detailsTitle.textContent = i18n.historyRequestDetails || 'Historiku i kërkesave';
      const detailsSubtitle = document.createElement('p');
      detailsSubtitle.textContent = `${requests.length} kërkesa · ${year}`;
      detailsCopy.append(detailsTitle, detailsSubtitle);
      detailsHead.appendChild(detailsCopy);
      details.appendChild(detailsHead);

      if (!requests.length) {
        const emptyState = document.createElement('p');
        emptyState.className = 'elm-empty-state';
        emptyState.textContent = i18n.historyNoRequests || i18n.noFilteredRequests || 'Nuk u gjet asnjë kërkesë që përputhet me filtrat.';
        details.appendChild(emptyState);
        target.appendChild(details);
        return;
      }

      const list = document.createElement('div');
      list.className = 'elm-history-request-list';
      requests.forEach((request) => {
        const item = document.createElement('article');
        item.className = `elm-history-request elm-history-request--${request.status || 'pending'}`;

        const head = document.createElement('div');
        head.className = 'elm-history-request__head';
        const heading = document.createElement('div');
        const employeeName = document.createElement('strong');
        employeeName.textContent = request.employee_name || '';
        const requestMeta = document.createElement('span');
        const typeLabel = request.leave_type === 'medical' ? (i18n.medical || 'Pushim mjekësor') : (i18n.annual || 'Pushim vjetor');
        requestMeta.textContent = `#${request.id} · ${typeLabel} · ${wholeDays(request.requested_units)} ditë`;
        heading.append(employeeName, requestMeta);
        const status = document.createElement('span');
        status.className = `elm-status elm-status--${request.status || 'pending'}`;
        status.textContent = statusLabel(request.status || '');
        head.append(heading, status);
        item.appendChild(head);

        const selectedDates = Array.isArray(request.selected_dates) && request.selected_dates.length
          ? request.selected_dates
          : [request.start_date, request.end_date].filter((value, index, values) => value && values.indexOf(value) === index);
        const grid = document.createElement('div');
        grid.className = 'elm-history-request__grid';
        const addDetail = (label, value) => {
          const box = document.createElement('div');
          const caption = document.createElement('span');
          caption.textContent = label;
          const content = document.createElement('strong');
          content.textContent = value || '—';
          box.append(caption, content);
          grid.appendChild(box);
        };
        addDetail(i18n.historyRequestedAt || 'Paraqitur më', prettyDateTime(request.submitted_at));
        const periodText = request.start_date && request.end_date
          ? `${prettyDate(request.start_date)} – ${prettyDate(request.end_date)}`
          : groupedDateList(selectedDates);
        addDetail(i18n.historyLeavePeriod || 'Periudha e zgjedhur', periodText);
        addDetail(i18n.historySelectedDates || 'Datat e zgjedhura', groupedDateList(selectedDates));
        let eventText = 'Në pritje';
        if (request.status === 'cancelled') eventText = request.cancelled_at ? `Anuluar më ${prettyDateTime(request.cancelled_at)}` : 'Anuluar';
        if (request.status === 'approved') eventText = request.decided_at ? `Miratuar më ${prettyDateTime(request.decided_at)}` : 'Miratuar';
        if (request.status === 'rejected') eventText = request.decided_at ? `Refuzuar më ${prettyDateTime(request.decided_at)}` : 'Refuzuar';
        addDetail(i18n.historyDecision || 'Statusi', eventText);
        item.appendChild(grid);

        const reasonHistory = Array.isArray(request.reason_history) ? request.reason_history : [];
        if (reasonHistory.length) {
          const reasons = document.createElement('div');
          reasons.className = 'elm-history-request__reasons';
          const reasonsTitle = document.createElement('span');
          reasonsTitle.className = 'elm-history-request__reasons-title';
          reasonsTitle.textContent = i18n.historyReason || 'Arsyetimi';
          reasons.appendChild(reasonsTitle);
          appendReasonHistory(reasons, request);
          item.appendChild(reasons);
        }
        list.appendChild(item);
      });
      details.appendChild(list);
      target.appendChild(details);
    };

    const fetchHistoryReport = async ({ employeeId = 0, status = '', year = 0, target = historyReport, notice = historyNotice } = {}) => {
      if (!target) return null;
      clearBoxNotice(notice);
      setHistoryLoading(target);
      try {
        const report = await chiefApi('history', {
          employee_id: Number(employeeId || 0),
          status: String(status || ''),
          year: Number(year || root.dataset.elmYear || new Date().getFullYear()),
        });
        renderHistoryReport(target, report || {});
        return report;
      } catch (error) {
        renderListState(target, error.message || i18n.genericError, 'error');
        showBoxNotice(notice, error.message || i18n.genericError, 'error');
        throw error;
      }
    };

    const loadHistoryReport = async () => fetchHistoryReport({
      employeeId: historyUserFilter?.value || 0,
      status: historyStatusFilter?.value || '',
      year: historyYearFilter?.value || root.dataset.elmYear || new Date().getFullYear(),
      target: historyReport,
      notice: historyNotice,
    });

    const loadHistoryDialogReport = async () => fetchHistoryReport({
      employeeId: historyDialogEmployeeId,
      status: historyDialogStatus?.value || '',
      year: historyDialogYear?.value || root.dataset.elmYear || new Date().getFullYear(),
      target: historyDialogReport,
      notice: historyDialogNotice,
    });

    const closeHistoryDialog = () => {
      if (!historyDialog) return;
      historyDialog.hidden = true;
      historyDialogEmployeeId = 0;
      document.body.classList.remove('elm-chief-history-open');
    };

    const openHistoryDialog = async (request) => {
      if (!historyDialog || !historyDialogReport) return;
      historyDialogEmployeeId = Number(request?.employee_id || 0);
      if (!historyDialogEmployeeId) return;
      const requestYear = Number(String(request?.start_date || request?.selected_dates?.[0] || '').slice(0, 4));
      if (historyDialogYear) historyDialogYear.value = String(requestYear || historyYearFilter?.value || root.dataset.elmYear || new Date().getFullYear());
      if (historyDialogStatus) historyDialogStatus.value = '';
      if (historyDialogTitle) historyDialogTitle.textContent = `${i18n.history || 'Historiku'} · ${request?.employee_name || ''}`;
      historyDialog.hidden = false;
      document.body.classList.add('elm-chief-history-open');
      await loadHistoryDialogReport();
    };

    historyApply?.addEventListener('click', () => {
      historyLoaded = true;
      loadHistoryReport().catch(() => {});
    });
    historyRefresh?.addEventListener('click', async () => {
      historyRefresh.disabled = true;
      try {
        await Promise.all([loadEmployerUsers(true), loadHistoryReport()]);
        historyLoaded = true;
      } finally {
        historyRefresh.disabled = false;
      }
    });
    historyDialogApply?.addEventListener('click', () => loadHistoryDialogReport().catch(() => {}));
    root.querySelectorAll('[data-chief-history-close]').forEach((button) => button.addEventListener('click', closeHistoryDialog));
    historyDialog?.addEventListener('click', (event) => {
      if (event.target === historyDialog) closeHistoryDialog();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && historyDialog && !historyDialog.hidden) closeHistoryDialog();
    });

    const renderEmployerRequests = (requests) => {
      if (!requestsBody) return;
      requestsBody.textContent = '';
      if (!requests.length) {
        const hasFilters = Boolean(statusFilter?.value || userFilter?.value || yearFilter?.value);
        renderTableState(
          requestsBody,
          7,
          hasFilters
            ? (i18n.noFilteredRequests || 'Nuk u gjet asnjë kërkesë që përputhet me filtrat.')
            : (i18n.noReviewRequests || 'Nuk u gjet asnjë kërkesë për shqyrtim.')
        );
        return;
      }

      requests.forEach((request) => {
        const row = document.createElement('tr');
        addCell(row, `#${request.id}`);
        addCell(row, request.employee_name || '');
        const selectedDates = Array.isArray(request.selected_dates) && request.selected_dates.length
          ? request.selected_dates
          : [request.start_date, request.end_date].filter((value, index, values) => value && values.indexOf(value) === index);
        const typeLabel = request.leave_type === 'medical' ? i18n.medical : i18n.annual;
        const daysCountLabel = String(i18n.daysCount || '%d ditë').replace('%d', wholeDays(request.requested_units));
        const dateCell = addCell(row, `${typeLabel}\n${groupedDateList(selectedDates)}\n${daysCountLabel}`);
        dateCell.className = 'elm-request-dates';
        dateCell.style.whiteSpace = 'pre-line';
        const shortWarningDates = Array.isArray(request.short_notice_warning_dates) ? request.short_notice_warning_dates : [];
        if (shortWarningDates.length) {
          const warning = document.createElement('div');
          warning.className = `elm-request-policy-warning elm-request-policy-warning--${request.status || 'pending'}`;
          warning.textContent = String(i18n.chiefShortNoticeWarning || 'Vërejtje: kjo kërkesë përfshin data që nuk e plotësojnë afatin minimal 15-ditor. Datat: %s.')
            .replace('%s', groupedDateList(shortWarningDates));
          dateCell.appendChild(warning);
        }
        const periodWarningDates = Array.isArray(request.period_one_warning_dates) ? request.period_one_warning_dates : [];
        if (periodWarningDates.length) {
          const warning = document.createElement('div');
          warning.className = `elm-request-policy-warning elm-request-policy-warning--${request.status || 'pending'}`;
          warning.textContent = String(i18n.chiefPeriodOneWarning || 'Vërejtje: kjo kërkesë kalon pragun informues prej 10 ditësh pune për periudhën janar-qershor. Ditët mbi prag: %s.')
            .replace('%s', groupedDateList(periodWarningDates));
          dateCell.appendChild(warning);
        }
        const reasonCell = addCell(row, '');
        appendReasonHistory(reasonCell, request);
        const statusCell = addCell(row, '');
        const status = document.createElement('span');
        status.className = `elm-status elm-status--${request.status}`;
        status.textContent = statusLabel(request.status || '');
        statusCell.appendChild(status);
        addCell(row, prettyDateTime(request.submitted_at));
        const actions = addCell(row, '');
        actions.className = 'elm-table-actions';
        const actionInner = document.createElement('div');
        actionInner.className = 'elm-table-actions-inner elm-my-actions elm-review-actions';
        const requestStatus = String(request.status || '').toLowerCase();
        actionInner.appendChild(makeReviewAction(i18n.history, 'Historiku', () => openHistoryDialog(request), 'history'));
        if (requestStatus === 'pending') {
          actionInner.appendChild(makeReviewAction(i18n.edit, 'Redakto', () => openEmployerEdit(request), 'edit'));
          actionInner.appendChild(makeReviewAction(i18n.approve, 'Mirato', () => decisionRequest(request, 'approved'), 'approve'));
          actionInner.appendChild(makeReviewAction(i18n.reject, 'Refuzo', () => decisionRequest(request, 'rejected'), 'reject'));
        }
        if (['pending', 'approved'].includes(requestStatus)) {
          actionInner.appendChild(makeReviewAction(i18n.cancel, 'Anulo', () => cancelEmployerRequest(request), 'cancel'));
        }
        if (canDeleteRequests) {
          actionInner.appendChild(makeReviewAction(i18n.delete, 'Fshi', () => openDeleteDialog(request), 'delete'));
        }
        const pdfUrl = reviewRequestPdfUrl(request);
        if (pdfUrl) {
          const pdf = document.createElement('a');
          pdf.className = 'button button-small elm-self-action elm-self-action--pdf elm-review-action--pdf';
          pdf.textContent = 'PDF';
          pdf.setAttribute('aria-label', 'PDF');
          pdf.title = 'PDF';
          pdf.target = '_blank';
          pdf.rel = 'noopener';
          pdf.href = pdfUrl;
          actionInner.appendChild(pdf);
        }
        if (canViewMedical && request.medical_document_id && cfg.downloadMedicalBase && cfg.downloadMedicalNonce) {
          const file = document.createElement('a');
          file.className = 'button button-small';
          file.textContent = i18n.medicalFile;
          file.href = `${cfg.downloadMedicalBase}${request.medical_document_id}&_wpnonce=${encodeURIComponent(cfg.downloadMedicalNonce)}`;
          actionInner.appendChild(file);
        }
        actions.appendChild(actionInner);
        requestsBody.appendChild(row);
      });
    };

    const employeeReportHref = (userId, year) => {
      if (!cfg.exportEmployeeBase || !cfg.exportEmployeeNonce) return '';
      return `${cfg.exportEmployeeBase}${encodeURIComponent(String(userId))}&year=${encodeURIComponent(String(year))}&_wpnonce=${encodeURIComponent(cfg.exportEmployeeNonce)}`;
    };

    const renderEmployeeDirectory = (users) => {
      if (!employeesBody) return;
      employeesBody.textContent = '';
      const visibleUsers = Array.isArray(users) ? users : [];
      if (!visibleUsers.length) {
        renderTableState(employeesBody, 5, i18n.noEmployees || 'Nuk u gjet asnjë punonjës.');
        return;
      }
      visibleUsers.forEach((user) => {
        const row = document.createElement('tr');
        const employeeCell = document.createElement('td');
        const name = document.createElement('strong');
        name.textContent = user.name || `Përdoruesi nr. ${user.id}`;
        const email = document.createElement('small');
        email.textContent = user.email || '';
        employeeCell.append(name, document.createElement('br'), email);
        row.appendChild(employeeCell);

        const positionCell = document.createElement('td');
        const position = document.createElement('input');
        position.type = 'text';
        position.maxLength = 255;
        position.value = user.position || '';
        position.setAttribute('aria-label', `Pozita e punonjësit ${user.name || ''}`.trim());
        positionCell.appendChild(position);
        row.appendChild(positionCell);

        const sectorCell = document.createElement('td');
        const sector = document.createElement('input');
        sector.type = 'text';
        sector.maxLength = 255;
        sector.value = user.sector || '';
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
        const actionInner = document.createElement('div');
        actionInner.className = 'elm-table-actions-inner elm-chief-actions';
        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'button button-small button-primary';
        save.textContent = i18n.saveChanges || 'Ruaj ndryshimet';
        save.addEventListener('click', async () => {
          clearBoxNotice(employeesNotice);
          save.disabled = true;
          try {
            const updated = await chiefApi('save_employee_profile', {
              user_id: Number(user.id),
              position: position.value.trim(),
              sector: sector.value.trim(),
            });
            user.position = updated.position || '';
            user.sector = updated.sector || '';
            position.value = user.position;
            sector.value = user.sector;
            showBoxNotice(employeesNotice, i18n.employeeDetailsSaved || 'Të dhënat e punonjësit u ruajtën.');
          } catch (error) {
            showBoxNotice(employeesNotice, error.message, 'error');
          } finally {
            save.disabled = false;
          }
        });
        actionInner.appendChild(save);
        const reportYear = Number(user.year || employeesYear?.value || root.dataset.elmYear || new Date().getFullYear());
        const href = employeeReportHref(user.id, reportYear);
        if (href) {
          const report = document.createElement('a');
          report.className = 'button button-small';
          report.target = '_blank';
          report.rel = 'noopener';
          report.href = href;
          report.textContent = i18n.generateReport || 'Krijo PDF';
          actionInner.appendChild(report);
        }
        actions.appendChild(actionInner);
        row.appendChild(actions);
        employeesBody.appendChild(row);
      });
    };

    const loadEmployerUsers = async (force = false) => {
      if (usersLoaded && !force) {
        renderEmployeeDirectory(directoryUsers);
        return directoryUsers;
      }
      if (!userFilter && !historyUserFilter && !employeesBody && !adjustmentUser) return [];
      const year = Number(employeesYear?.value || root.dataset.elmYear || new Date().getFullYear());
      const users = await chiefApi('users', { year });
      directoryUsers = Array.isArray(users) ? users : [];
      usersLoaded = true;
      directoryUsers
        .filter((user) => Number(user.id) !== currentUserId)
        .forEach((user) => {
          [userFilter, historyUserFilter, adjustmentUser].filter(Boolean).forEach((select) => {
            if (select.querySelector(`option[value="${user.id}"]`)) return;
            const option = document.createElement('option');
            option.value = String(user.id);
            option.textContent = user.name || user.email || `Përdoruesi nr. ${user.id}`;
            select.appendChild(option);
          });
        });
      renderEmployeeDirectory(directoryUsers);
      return directoryUsers;
    };

    const loadEmployerRequests = async () => {
      if (!requestsBody) return [];
      historyLoaded = false;
      clearBoxNotice(chiefNotice);
      renderTableState(requestsBody, 7, i18n.loading || 'Po ngarkohet...', 'loading');
      const filters = {
        status: statusFilter?.value || '',
        employee_id: userFilter?.value || 0,
        year: yearFilter?.value || 0,
      };
      try {
        const requests = await chiefApi('requests', filters);
        employerRequests = (Array.isArray(requests) ? requests : []).filter((request) => Number(request.employee_id) !== currentUserId);
        renderEmployerRequests(employerRequests);
        return employerRequests;
      } catch (error) {
        renderTableState(requestsBody, 7, error.message || i18n.genericError, 'error');
        throw error;
      }
    };

    applyFilters?.addEventListener('click', () => loadEmployerRequests().catch((error) => showBoxNotice(chiefNotice, error.message, 'error')));
    refreshButton?.addEventListener('click', async () => {
      refreshButton.disabled = true;
      try {
        await Promise.all([loadEmployerUsers(), loadEmployerRequests(), loadEntitlementChanges()]);
      } catch (error) {
        showBoxNotice(chiefNotice, error.message, 'error');
      } finally {
        refreshButton.disabled = false;
      }
    });


    const refreshEmployeeDirectory = async () => {
      if (!employeesBody) return;
      clearBoxNotice(employeesNotice);
      renderTableState(employeesBody, 5, i18n.loading || 'Po ngarkohet...', 'loading');
      try {
        await loadEmployerUsers(true);
      } catch (error) {
        renderTableState(employeesBody, 5, error.message || i18n.genericError, 'error');
        throw error;
      }
    };


    const renderAdjustments = (adjustments) => {
      if (!adjustmentsBody) return;
      adjustmentsBody.textContent = '';
      const rows = Array.isArray(adjustments) ? adjustments : [];
      if (!rows.length) {
        renderTableState(adjustmentsBody, 5, i18n.noAdjustments || 'Nuk u gjet asnjë regjistrim për ditë shtesë.');
        return;
      }
      rows.forEach((item) => {
        const row = document.createElement('tr');
        addCell(row, item.employee_name || `Përdoruesi nr. ${item.user_id}`);
        addCell(row, item.leave_year || item.year || '');
        addCell(row, wholeDays(item.amount));
        const note = addCell(row, item.note || '');
        if (item.created_by_name) {
          const actor = document.createElement('small');
          actor.textContent = `Shtuar nga ${item.created_by_name}`;
          note.append(document.createElement('br'), actor);
        }
        addCell(row, prettyDateTime(item.created_at || ''));
        adjustmentsBody.appendChild(row);
      });
    };

    const showAdjustmentEmployeePrompt = () => {
      if (!adjustmentsBody) return;
      renderTableState(
        adjustmentsBody,
        5,
        i18n.adjustmentSelectEmployee || 'Zgjidhni një punonjës për të shfaqur ditët shtesë.',
        'info'
      );
    };

    async function loadAdjustments() {
      if (!adjustmentsBody) return [];
      const formData = adjustmentForm ? new FormData(adjustmentForm) : null;
      const userId = Number(formData?.get('user_id') || 0);
      if (!userId) {
        showAdjustmentEmployeePrompt();
        return [];
      }
      renderTableState(adjustmentsBody, 5, i18n.loading || 'Po ngarkohet...', 'loading');
      const rows = await chiefApi('adjustments', {
        user_id: userId,
        year: Number(formData?.get('year') || 0),
      });
      renderAdjustments(rows);
      return rows;
    }

    async function verifyAuditLedger() {
      if (!auditStatus) return null;
      auditStatus.textContent = i18n.loading || 'Po ngarkohet...';
      try {
        const result = await chiefApi('verify_audit');
        const valid = Boolean(result?.valid);
        auditStatus.className = `elm-chief-audit-status elm-chief-audit-status--${valid ? 'valid' : 'invalid'}`;
        auditStatus.textContent = valid
          ? `${i18n.auditValid || 'Regjistri i auditimit u verifikua me sukses.'} ${Number(result.entries || 0)} regjistrime.`
          : `${i18n.auditInvalid || 'Verifikimi i regjistrit të auditimit dështoi.'} Ndërprerje te regjistrimi nr. ${Number(result.broken_at || 0)}.`;
        return result;
      } catch (error) {
        auditStatus.className = 'elm-chief-audit-status elm-chief-audit-status--invalid';
        auditStatus.textContent = error.message;
        throw error;
      }
    }

    employeesRefresh?.addEventListener('click', async () => {
      employeesRefresh.disabled = true;
      try {
        await refreshEmployeeDirectory();
      } catch (error) {
        showBoxNotice(employeesNotice, error.message, 'error');
      } finally {
        employeesRefresh.disabled = false;
      }
    });
    employeesYear?.addEventListener('change', () => {
      usersLoaded = false;
      refreshEmployeeDirectory().catch((error) => showBoxNotice(employeesNotice, error.message, 'error'));
    });


    adjustmentForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearBoxNotice(adjustmentsNotice);
      const data = new FormData(adjustmentForm);
      const submit = adjustmentForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        await chiefApi('create_adjustment', {
          user_id: Number(data.get('user_id') || 0),
          year: Number(data.get('year') || 0),
          amount: Number(data.get('amount') || 0),
          note: String(data.get('note') || '').trim(),
        });
        adjustmentForm.elements.note.value = '';
        adjustmentForm.elements.amount.value = '1';
        showBoxNotice(adjustmentsNotice, i18n.adjustmentSaved || 'Ditët shtesë u shtuan për vitin e zgjedhur.');
        usersLoaded = false;
        await Promise.all([loadAdjustments(), loadEmployerUsers(true)]);
      } catch (error) {
        showBoxNotice(adjustmentsNotice, error.message, 'error');
      } finally {
        if (submit) submit.disabled = false;
      }
    });

    adjustmentsRefresh?.addEventListener('click', async () => {
      adjustmentsRefresh.disabled = true;
      clearBoxNotice(adjustmentsNotice);
      try {
        await loadAdjustments();
      } catch (error) {
        showBoxNotice(adjustmentsNotice, error.message, 'error');
      } finally {
        adjustmentsRefresh.disabled = false;
      }
    });
    adjustmentUser?.addEventListener('change', () => loadAdjustments().catch((error) => showBoxNotice(adjustmentsNotice, error.message, 'error')));
    adjustmentForm?.elements.year?.addEventListener('change', () => loadAdjustments().catch((error) => showBoxNotice(adjustmentsNotice, error.message, 'error')));
    auditVerify?.addEventListener('click', async () => {
      auditVerify.disabled = true;
      try {
        await verifyAuditLedger();
      } finally {
        auditVerify.disabled = false;
      }
    });

    const editDialog = root.querySelector('[data-chief-edit-dialog]');
    const editForm = root.querySelector('[data-chief-edit-form]');
    const editEmployee = root.querySelector('[data-chief-edit-employee]');
    const editCalendars = root.querySelector('[data-chief-edit-calendars]');
    const editSelected = root.querySelector('[data-chief-edit-selected]');
    const editFeedback = root.querySelector('[data-chief-edit-feedback]');
    const editMedical = root.querySelector('[data-chief-edit-medical]');
    const editType = editForm?.querySelector('[name="leave_type"]');
    const editReason = editForm?.querySelector('[name="reason"]');
    const editMedicalAck = editForm?.querySelector('[name="medical_ack"]');
    let editState = null;
    let editBaseMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    let editCalendarDays = {};
    const editDates = new Set();
    const chiefToday = cfg.today || root.dataset.elmToday || iso(new Date());
    const chiefNoticeEnd = cfg.noticeEnd || root.dataset.elmNoticeEnd || iso(addDays(parseIso(chiefToday), 14));

    const editPeriodOneExcessDates = () => {
      if ((editType?.value || editState?.originalType || 'annual') !== 'annual') return new Set();
      const counts = new Map();
      const excess = new Set();
      [...editDates].sort().forEach((date) => {
        const day = editCalendarDays[date] || fallbackEditDay(date);
        if (!day.working_day || day.holiday || Number(date.slice(5, 7)) > 6) return;
        const year = Number(date.slice(0, 4));
        const count = (counts.get(year) || 0) + 1;
        counts.set(year, count);
        if (count > 10) excess.add(date);
      });
      return excess;
    };

    const showEditFeedback = (message, kind = 'error') => {
      if (!editFeedback) return;
      editFeedback.textContent = message;
      editFeedback.className = `elm-chief-edit-feedback elm-chief-edit-feedback--${kind}`;
      editFeedback.hidden = false;
    };

    const clearEditFeedback = () => {
      if (!editFeedback) return;
      editFeedback.textContent = '';
      editFeedback.hidden = true;
      editFeedback.className = 'elm-chief-edit-feedback';
    };

    const closeEmployerEdit = () => {
      editState = null;
      editDates.clear();
      editCalendarDays = {};
      editForm?.reset();
      if (editDialog) editDialog.hidden = true;
      document.body.classList.remove('elm-chief-edit-open');
      clearEditFeedback();
    };

    const editDayIsBlocked = (key, day = {}) => {
      if (editState?.originalDates.has(key)) return false;
      if (!day.working_day || day.holiday) return true;
      const capacityApplies = (editType?.value || editState?.originalType || 'annual') === 'annual';
      if (capacityApplies && (day.capacity_blocked || day.disabled)) return true;
      return false;
    };

    const renderEditSelected = () => {
      if (!editSelected) return;
      editSelected.textContent = '';
      const dates = [...editDates].sort();
      if (!dates.length) {
        const empty = document.createElement('span');
        empty.className = 'elm-chief-edit-selected-empty';
        empty.textContent = i18n.chooseDates;
        editSelected.appendChild(empty);
        return;
      }
      dates.forEach((date) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'elm-chief-edit-date-chip';
        chip.dataset.removeChiefEditDate = date;
        chip.textContent = `${compactDate(date)} ×`;
        editSelected.appendChild(chip);
      });
    };

    const fallbackEditDay = (key) => {
      const parsed = parseIso(key);
      const weekend = [0, 6].includes(parsed.getDay());
      return { working_day: !weekend, holiday: false, holiday_name: '', capacity_blocked: false, disabled: false };
    };

    const renderEditMonth = (month) => {
      const section = document.createElement('section');
      section.className = 'elm-chief-edit-month';
      const title = document.createElement('h4');
      title.textContent = monthLabel(month);
      section.appendChild(title);
      const table = document.createElement('table');
      table.className = 'elm-chief-edit-calendar';
      const thead = document.createElement('thead');
      const headRow = document.createElement('tr');
      ['Hë', 'Ma', 'Më', 'En', 'Pr', 'Sh', 'Di'].forEach((label) => {
        const th = document.createElement('th');
        th.textContent = label;
        headRow.appendChild(th);
      });
      thead.appendChild(headRow);
      table.appendChild(thead);
      const tbody = document.createElement('tbody');
      const periodOneExcess = editPeriodOneExcessDates();
      const first = new Date(month.getFullYear(), month.getMonth(), 1);
      const offset = (first.getDay() + 6) % 7;
      const lastDay = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
      let day = 1;
      for (let rowIndex = 0; rowIndex < Math.ceil((offset + lastDay) / 7); rowIndex += 1) {
        const row = document.createElement('tr');
        for (let column = 0; column < 7; column += 1) {
          const cell = document.createElement('td');
          if ((rowIndex === 0 && column < offset) || day > lastDay) {
            cell.className = 'is-empty';
          } else {
            const key = iso(new Date(month.getFullYear(), month.getMonth(), day));
            const dayData = editCalendarDays[key] || fallbackEditDay(key);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'elm-chief-edit-day';
            button.dataset.chiefEditDate = key;
            const dayNumber = document.createElement('span');
            dayNumber.textContent = String(day);
            button.appendChild(dayNumber);
            if (dayData.holiday) {
              const holidayHelp = document.createElement('span');
              holidayHelp.className = 'elm-chief-edit-holiday-help';
              holidayHelp.textContent = '?';
              holidayHelp.dataset.holidayName = dayData.holiday_name || dayData.day_note || 'Festë e institucionit';
              holidayHelp.setAttribute('aria-hidden', 'true');
              button.appendChild(holidayHelp);
            }
            const selected = editDates.has(key);
            const blocked = editDayIsBlocked(key, dayData);
            const isAnnualEdit = (editType?.value || editState?.originalType || 'annual') === 'annual';
            const isOriginal = Boolean(editState?.originalDates.has(key));
            const persistedShortNotice = Boolean(editState?.shortNoticeWarningDates?.has(key));
            const shortNoticeWarning = isAnnualEdit && (persistedShortNotice || (!isOriginal && key >= chiefToday && (Boolean(dayData.short_notice) || key <= chiefNoticeEnd)));
            const periodOneExcessWarning = selected && periodOneExcess.has(key);
            button.classList.toggle('is-selected', selected);
            if (isOriginal) button.classList.add('is-original');
            if (!dayData.working_day || dayData.holiday) button.classList.add('is-nonworking');
            if (isAnnualEdit && (dayData.capacity_blocked || dayData.disabled)) button.classList.add('is-blocked');
            if (shortNoticeWarning) button.classList.add('is-short-notice-warning');
            if (periodOneExcessWarning) button.classList.add('is-period-one-excess-warning');
            const warningTitle = [
              shortNoticeWarning ? 'Vërejtje: data është brenda afatit minimal 15-ditor.' : '',
              periodOneExcessWarning ? 'Vërejtje: kjo ditë është mbi pragun informues prej 10 ditësh.' : '',
            ].filter(Boolean).join(' ');
            if (warningTitle) button.title = warningTitle;
            button.disabled = blocked;
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            cell.appendChild(button);
            day += 1;
          }
          row.appendChild(cell);
        }
        tbody.appendChild(row);
      }
      table.appendChild(tbody);
      section.appendChild(table);
      return section;
    };

    const renderEditCalendars = () => {
      if (!editCalendars) return;
      editCalendars.textContent = '';
      editCalendars.append(renderEditMonth(editBaseMonth), renderEditMonth(addMonths(editBaseMonth, 1)));
      renderEditSelected();
    };

    const loadEditCalendars = async () => {
      if (!editState || !editCalendars) return;
      editCalendars.textContent = i18n.loading;
      const second = addMonths(editBaseMonth, 1);
      const employeeId = Number(editState.request.employee_id || 0);
      const [firstData, secondData] = await Promise.all([
        chiefApi('calendar', { month: monthKey(editBaseMonth), employee_id: employeeId }),
        chiefApi('calendar', { month: monthKey(second), employee_id: employeeId }),
      ]);
      editCalendarDays = { ...editCalendarDays, ...(firstData.days || {}), ...(secondData.days || {}) };
      renderEditCalendars();
    };

    const openEmployerEdit = async (request) => {
      if (!request || request.status !== 'pending') {
        showBoxNotice(chiefNotice, i18n.editPendingOnly, 'error');
        return;
      }
      if (!editDialog || !editForm || !editType || !editReason) {
        showBoxNotice(chiefNotice, i18n.genericError, 'error');
        return;
      }
      const existingDates = Array.isArray(request.selected_dates) && request.selected_dates.length
        ? request.selected_dates
        : [request.start_date, request.end_date].filter(Boolean);
      editState = { request, originalDates: new Set(existingDates), shortNoticeWarningDates: new Set(Array.isArray(request.short_notice_warning_dates) ? request.short_notice_warning_dates : []) };
      editDates.clear();
      existingDates.forEach((date) => editDates.add(date));
      editCalendarDays = {};
      const firstDate = existingDates.length ? parseIso([...existingDates].sort()[0]) : new Date();
      editBaseMonth = new Date(firstDate.getFullYear(), firstDate.getMonth(), 1);
      if (editEmployee) editEmployee.value = String(request.employee_name || '');
      editType.value = request.leave_type === 'medical' ? 'medical' : 'annual';
      editReason.value = String(request.reason || '');
      if (editMedicalAck) editMedicalAck.checked = Boolean(request.medical_ack);
      if (editMedical) editMedical.hidden = editType.value !== 'medical';
      clearEditFeedback();
      editDialog.hidden = false;
      document.body.classList.add('elm-chief-edit-open');
      renderEditCalendars();
      try {
        await loadEditCalendars();
      } catch (error) {
        showEditFeedback(error.message);
      }
      editReason.focus();
    };

    if (editCalendars) {
      editCalendars.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-chief-edit-date]') : null;
        if (!button || button.disabled || !editState) return;
        const key = button.dataset.chiefEditDate || '';
        if (editDates.has(key)) {
          editDates.delete(key);
        } else {
          editDates.add(key);
        }
        clearEditFeedback();
        renderEditCalendars();
      });
    }

    editSelected?.addEventListener('click', (event) => {
      const button = event.target instanceof Element ? event.target.closest('[data-remove-chief-edit-date]') : null;
      if (!button) return;
      editDates.delete(button.dataset.removeChiefEditDate || '');
      renderEditCalendars();
    });

    root.querySelectorAll('[data-chief-edit-nav]').forEach((button) => button.addEventListener('click', async () => {
      if (!editState) return;
      editBaseMonth = addMonths(editBaseMonth, Number(button.dataset.chiefEditNav || 0));
      try {
        await loadEditCalendars();
      } catch (error) {
        showEditFeedback(error.message);
      }
    }));

    root.querySelectorAll('[data-chief-edit-close]').forEach((button) => button.addEventListener('click', closeEmployerEdit));
    editDialog?.addEventListener('click', (event) => {
      if (event.target === editDialog) closeEmployerEdit();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && editDialog && !editDialog.hidden) closeEmployerEdit();
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
        showEditFeedback(i18n.chooseDates);
        return;
      }
      if (!reason) {
        showEditFeedback(i18n.reasonRequired);
        editReason.focus();
        return;
      }
      if (editType.value === 'medical' && !editMedicalAck?.checked && !editState.request.medical_document_id) {
        showEditFeedback(i18n.medicalRequired);
        return;
      }
      const submit = editForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        await chiefApi('update_request', {
          request_id: editState.request.id,
          data: {
            leave_type: editType.value,
            selected_dates: selectedDates,
            start_date: selectedDates[0],
            end_date: selectedDates[selectedDates.length - 1],
            reason,
            medical_ack_present: 1,
            medical_ack: editMedicalAck?.checked ? 1 : 0,
            medical_document_id: editState.request.medical_document_id || 0,
          },
        });
        closeEmployerEdit();
        showBoxNotice(chiefNotice, i18n.operationSaved);
        await loadEmployerRequests();
        refreshSelfPortal();
      } catch (error) {
        showEditFeedback(error.message);
      } finally {
        if (submit) submit.disabled = false;
      }
    });

    root.querySelectorAll('[data-chief-delete-close]').forEach((button) => button.addEventListener('click', closeDeleteDialog));
    deleteDialog?.addEventListener('click', (event) => {
      if (event.target === deleteDialog) closeDeleteDialog();
    });
    deleteConfirmInput?.addEventListener('input', () => {
      clearDeleteFeedback();
      if (deleteSubmit) deleteSubmit.disabled = deleteConfirmInput.value !== 'DELETE';
    });
    ['copy', 'cut', 'paste', 'drop', 'contextmenu'].forEach((eventName) => {
      deleteConfirmInput?.addEventListener(eventName, (event) => event.preventDefault());
    });
    deleteConfirmInput?.addEventListener('beforeinput', (event) => {
      if (['insertFromPaste', 'insertFromDrop'].includes(event.inputType)) event.preventDefault();
    });
    deleteConfirmInput?.addEventListener('keydown', (event) => {
      const key = String(event.key || '').toLowerCase();
      if ((event.ctrlKey || event.metaKey) && ['c', 'v', 'x'].includes(key)) event.preventDefault();
      if ((event.shiftKey && key === 'insert') || (event.ctrlKey && key === 'insert')) event.preventDefault();
    });
    deleteSubmit?.addEventListener('click', async () => {
      if (!deleteRequestState || !deleteConfirmInput || deleteConfirmInput.value !== 'DELETE') {
        showDeleteFeedback(i18n.deleteRequestMismatch || 'Shkruani saktësisht DELETE për të aktivizuar fshirjen.');
        return;
      }
      deleteSubmit.disabled = true;
      try {
        await chiefApi('delete_request', { request_id: deleteRequestState.id, confirmation: deleteConfirmInput.value });
        closeDeleteDialog();
        await loadEmployerRequests();
        refreshSelfPortal();
        showBoxNotice(chiefNotice, i18n.deleteRequestSuccess || 'Kërkesa u fshi përgjithmonë.');
      } catch (error) {
        showDeleteFeedback(error.message);
        deleteSubmit.disabled = deleteConfirmInput.value !== 'DELETE';
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && deleteDialog && !deleteDialog.hidden) closeDeleteDialog();
    });

    plusOneAlwaysVisibleInput?.addEventListener('change', async () => {
      const requestedValue = Boolean(plusOneAlwaysVisibleInput.checked);
      const previousValue = plusOneAlwaysVisible;
      plusOneAlwaysVisibleInput.disabled = true;
      showPlusOneVisibilityStatus('Duke ruajtur...', 'saving');
      try {
        const result = await chiefApi('save_plus_one_visibility', { enabled: requestedValue });
        plusOneAlwaysVisible = Boolean(result?.show_plus_one_when_empty);
        root.dataset.elmPlusOneAlwaysVisible = plusOneAlwaysVisible ? '1' : '0';
        plusOneAlwaysVisibleInput.checked = plusOneAlwaysVisible;
        try {
          if (!entitlementChangesLoaded && chiefEntitlementsPending && chiefEntitlementsHistory) {
            await loadEntitlementChanges();
          } else {
            applyPlusOneVisibility(lastPlusOnePendingCount);
          }
          showPlusOneVisibilityStatus('U ruajt', 'success');
        } catch (refreshError) {
          applyPlusOneVisibility(lastPlusOnePendingCount);
          showPlusOneVisibilityStatus('U ruajt; rifreskoni faqen për ta verifikuar paraqitjen.', 'error');
        }
      } catch (error) {
        plusOneAlwaysVisible = previousValue;
        plusOneAlwaysVisibleInput.checked = previousValue;
        root.dataset.elmPlusOneAlwaysVisible = previousValue ? '1' : '0';
        applyPlusOneVisibility(lastPlusOnePendingCount);
        showPlusOneVisibilityStatus(error?.message || i18n.genericError, 'error');
      } finally {
        plusOneAlwaysVisibleInput.disabled = false;
      }
    });

    settingsForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearBoxNotice(settingsNotice);
      if (frontendOnlyInput?.checked && Number(portalPageInput?.value || 0) < 1) {
        showBoxNotice(settingsNotice, i18n.invalidPortalPage || 'Zgjidhni një faqe të publikuar të portalit para aktivizimit të qasjes vetëm përmes portalit.', 'error');
        portalPageInput?.focus();
        return;
      }
      const formData = new FormData(settingsForm);
      const workingWeekdays = formData.getAll('working_weekdays[]').map(Number);
      const submit = settingsForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        const result = await chiefApi('save_settings', {
          data: {
            concurrency_limit: Number(formData.get('concurrency_limit') || 2),
            working_weekdays: workingWeekdays,
            movable_holidays: {
              bajrami_i_madh: String(formData.get('movable_bajrami_i_madh') || ''),
              bajrami_i_vogel: String(formData.get('movable_bajrami_i_vogel') || ''),
            },
            max_upload_mb: Number(formData.get('max_upload_mb') || 10),
            ...(portalPageInput ? { portal_page_id: Number(portalPageInput.value || 0) } : {}),
            ...(frontendOnlyInput ? { frontend_only_enabled: Boolean(frontendOnlyInput.checked) } : {}),
          },
        });
        settingsForm.elements.concurrency_limit.value = String(result.concurrency_limit || 2);
        settingsForm.elements.max_upload_mb.value = String(result.max_upload_mb || 10);
        const movableEditDates = result.movable_holiday_edit_dates || {};
        if (settingsForm.elements.movable_bajrami_i_madh && movableEditDates.bajrami_i_madh) {
          settingsForm.elements.movable_bajrami_i_madh.value = String(movableEditDates.bajrami_i_madh);
        }
        if (settingsForm.elements.movable_bajrami_i_vogel && movableEditDates.bajrami_i_vogel) {
          settingsForm.elements.movable_bajrami_i_vogel.value = String(movableEditDates.bajrami_i_vogel);
        }
        settingsForm.querySelectorAll('[name="working_weekdays[]"]').forEach((input) => {
          input.checked = Array.isArray(result.working_weekdays) && result.working_weekdays.map(Number).includes(Number(input.value));
        });
        if (portalPageInput) portalPageInput.value = String(result.portal_page_id || 0);
        if (frontendOnlyInput) frontendOnlyInput.checked = Boolean(result.frontend_only_enabled);
        showBoxNotice(settingsNotice, i18n.settingsSaved || 'Cilësimet u ruajtën me sukses.');
        settingsNotice?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        employerRequestsLoaded = false;
        refreshSelfPortal();
      } catch (error) {
        showBoxNotice(settingsNotice, error.message, 'error');
        settingsNotice?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  };

  const bootPortal = (root) => {
    if (root.dataset.elmInitialized === '1') return;
    root.dataset.elmInitialized = '1';

    const cfg = window.ELMPortal || {};
    const i18n = {
      loading: 'Po ngarkohet...',
      genericError: 'Ndodhi një gabim. Provoni përsëri.',
      cancelConfirm: 'Ta anuloni këtë kërkesë?',
      cancelReasonPrompt: 'Shkruani arsyetimin e anulimit:',
      cancelReasonRequired: 'Shkruani arsyetimin e anulimit.',
      cancelledMessage: 'Kërkesa u anulua.',
      deleteOwnConfirm: 'Kërkesa do të fshihet përgjithmonë. Ky veprim nuk mund të zhbëhet.',
      deleteOwnPrompt: 'Shkruani saktësisht DELETE për të konfirmuar fshirjen:',
      deleteOwnSuccess: 'Kërkesa u fshi përgjithmonë.',
      noRequests: 'Nuk u gjet asnjë kërkesë për pushim.',
      noReviewRequests: 'Nuk u gjet asnjë kërkesë për shqyrtim.',
      noFilteredRequests: 'Nuk u gjet asnjë kërkesë që përputhet me filtrat.',
      noEmployees: 'Nuk u gjet asnjë punonjës.',
      noAdjustments: 'Nuk u gjet asnjë regjistrim për ditë shtesë.',
      adjustmentSelectEmployee: 'Zgjidhni një punonjës për të shfaqur ditët shtesë.',
      submitRequest: 'Paraqit kërkesën',
      annual: 'Pushim vjetor',
      medical: 'Pushim mjekësor',
      cancel: 'Anulo',
      edit: 'Redakto',
      saveChanges: 'Ruaj ndryshimet',
      cancelEdit: 'Anulo redaktimin',
      updated: 'Kërkesa nr. %d u përditësua me sukses.',
      editingRequest: 'Po redaktohet kërkesa nr. %d',
      newRequest: 'Kërkesë e re',
      requestLeave: 'Kërko pushim',
      editPendingOnly: 'Mund të redaktohen vetëm kërkesat në pritje.',
      pdfReport: 'Shkarko PDF',
      noneSelected: 'asnjë',
      chooseDates: 'Zgjidhni një periudhë me së paku një ditë pune të disponueshme.',
      reasonRequired: 'Shkruani arsyetimin para paraqitjes.',
      medicalRequired: 'Konfirmoni dokumentacionin mjekësor ose bashkëngjitni dokumentin.',
      saved: 'Kërkesa nr. %d u paraqit me sukses.',
      pendingWarning: 'Ka kërkesa në pritje për datat: %s.',
      unavailable: 'Kjo datë nuk është e disponueshme.',
      workingDays: 'Do të llogariten %d ditë pune.',
      calendarDays: '%d ditë kalendarike të zgjedhura.',
      skippedDates: 'Datat e padisponueshme u anashkaluan: %s.',
      ownRequest: 'Tashmë keni një kërkesë me statusin %s për këtë ditë.',
      periodOneAvailable: 'Kërkesa për pushim vjetor paraqitet së paku 15 ditë para fillimit.',
      shortNoticeWarning: 'Afati minimal 15-ditor nuk plotësohet për këto data: %2$s. Kërkesa mund të vazhdojë dhe vërejtja do t\'i shfaqet mbikëqyrësit gjatë shqyrtimit.',
      periodOneWarning: 'Periudha janar-qershor ka një prag informues prej %1$d ditësh pune. Përzgjedhja aktuale e kalon këtë prag me %2$d ditë (%3$s). Kërkesa mund të vazhdojë; mbikëqyrësi do ta shohë këtë vërejtje gjatë shqyrtimit.',
      chiefShortNoticeWarning: 'Vërejtje: kjo kërkesë përfshin data që nuk e plotësojnë afatin minimal 15-ditor. Datat: %s.',
      chiefShortNoticeConfirm: 'Kjo kërkesë përfshin data brenda afatit minimal 15-ditor: %s. Shqyrtojeni këtë vërejtje para miratimit.',
      chiefPeriodOneWarning: 'Vërejtje: kjo kërkesë kalon pragun informues prej 10 ditësh pune për periudhën janar-qershor. Ditët mbi prag: %s.',
      chiefPeriodOneConfirm: 'Kjo kërkesë kalon pragun informues 10-ditor për periudhën janar-qershor. Ditët mbi prag: %s. Vazhdoni me miratimin vetëm pasi ta keni shqyrtuar këtë tejkalim.',
      policyWarningSaved: '',
      chiefApprovalRequired: '',
      warningPendingDay: '',
      policyExceptionDay: '',
      employeeReason: 'Arsyetimi i punonjësit',
      chiefRequestReason: 'Arsyetimi i kërkesës nga mbikëqyrësi',
      chiefDecisionReason: 'Arsyetimi i vendimit nga mbikëqyrësi',
      employeeCancelReason: 'Arsyetimi i anulimit nga punonjësi',
      chiefCancelReason: 'Arsyetimi i anulimit nga mbikëqyrësi',
      employerReasons: 'Punonjësi',
      chiefReasons: 'Mbikëqyrësi',
      totalExceeded: 'Pas llogaritjes së kërkesave në pritje mbeten %d ditë pune.',
      entitlementChange: 'Kërko +1 ditë për përvojë',
      entitlementRequested: 'Kërkesa për +1 ditë për përvojë pune iu dërgua mbikëqyrësit për verifikim dhe vendim.',
      entitlementCancelled: 'Kërkesa në pritje u anulua.',
      entitlementCancelConfirm: 'Ta anuloni këtë kërkesë në pritje?',
      entitlementNoHistory: 'Nuk u gjet asnjë kërkesë për +1 ditë.',
      entitlementNoDecisionHistory: 'Nuk u gjet asnjë kërkesë +1 ditë në historik.',
      entitlementNoPending: 'Nuk u gjet asnjë kërkesë +1 ditë në pritje.',
      entitlementSelectEmployee: 'Zgjidhni një punonjës për ta parë historikun e ndryshimeve.',
      entitlementApprovePrompt: 'Arsyetim për miratimin (opsional):',
      entitlementRejectPrompt: 'Arsyetimi i refuzimit:',
      entitlementSaved: 'Vendimi u ruajt.',
      entitlementApproved: 'Kërkesa për +1 ditë për përvojë pune u miratua.',
      entitlementRejected: 'Kërkesa për +1 ditë për përvojë pune u refuzua.',
      entitlementView: 'Shiko kërkesën +1',
      entitlementAccessSaved: 'Cilësimi i kërkesave për ndryshim u ruajt.',
      entitlementEnabled: 'Aktivizuar',
      entitlementDisabled: 'Çaktivizuar',
      ...(cfg.i18n || {}),
    };

    initFileInputs(root);
    bootEmployeePortalTabs(root);
    bootChiefPortal(root, cfg, i18n);

    const calendar = root.querySelector('[data-calendar]');
    const monthContainers = [...root.querySelectorAll('[data-ot-month-index]')];
    const selectedSummary = root.querySelector('[data-ot-selected-list]');
    const form = root.querySelector('[data-request-form]');
    if (!calendar || monthContainers.length < 2 || !selectedSummary || !form) return;

    const notice = root.querySelector('.elm-notice');
    const submitStatus = root.querySelector('[data-submit-status]');
    const waitlist = root.querySelector('[data-waitlist]');
    const medical = root.querySelector('[data-medical]');
    const typeSelect = form.querySelector('[name="leave_type"]');
    const selectedInput = form.querySelector('[name="selected_dates"]');
    const startInput = form.querySelector('[name="start_date"]');
    const endInput = form.querySelector('[name="end_date"]');
    const reasonInput = form.querySelector('[name="reason"]');
    const formActionInput = form.querySelector('[data-request-form-action]');
    const editRequestIdInput = form.querySelector('[data-edit-request-id]');
    const submitButton = form.querySelector('[data-request-submit]');
    const cancelEditButton = form.querySelector('[data-cancel-edit]');
    const requestCard = root.querySelector('[data-request-card]');
    const requestFormEyebrow = root.querySelector('[data-request-form-eyebrow]');
    const requestFormTitle = root.querySelector('[data-request-form-title]');
    const ackInput = form.querySelector('[name="medical_ack"]');
    const fileInput = form.querySelector('[name="medical_document"]');
    const requestDayCount = root.querySelector('[data-request-day-count]');
    const requestsBody = root.querySelector('[data-requests-body]');
    const refreshRequests = root.querySelector('[data-refresh-requests]');
    const clearDates = root.querySelector('[data-ot-clear-dates]');
    const periodOneAllowance = root.querySelector('[data-period-one-allowance]');
    const policyWarnings = root.querySelector('[data-policy-warnings]');
    const calendarGuide = root.querySelector('[data-calendar-guide]');
    const entitlementPanel = root.querySelector('[data-entitlement-panel]');
    const entitlementToggle = root.querySelector('[data-entitlement-toggle]');
    const entitlementForm = root.querySelector('[data-entitlement-form]');
    const entitlementNotice = root.querySelector('[data-entitlement-notice]');
    const entitlementHistory = root.querySelector('[data-entitlement-history]');
    const entitlementHistoryDetails = entitlementHistory?.closest('details');
    const entitlementFrom = root.querySelector('[data-entitlement-from]');
    const entitlementTo = root.querySelector('[data-entitlement-to]');
    if (!typeSelect || !selectedInput || !startInput || !endInput || !reasonInput || !requestDayCount) return;

    const today = cfg.today || root.dataset.elmToday || iso(new Date());
    const noticeEnd = cfg.noticeEnd || root.dataset.elmNoticeEnd || iso(addDays(parseIso(today), 14));
    let currentYear = Number(cfg.year || root.dataset.elmYear || new Date().getFullYear());
    const yearSelect = root.querySelector('[data-elm-year-select]');
    const canDeleteOwnRequests = Boolean(cfg.canDeleteRequests) || root.dataset.elmCanDeleteRequests === '1';
    const canManageOwnRequests = Boolean(cfg.canManageLeave) || root.dataset.elmChief === '1';
    let entitlementCanStart = root.dataset.elmEntitlementCanStart === '1';
    const balanceState = {
      totalRemaining: Number(root.dataset.elmTotalRemaining || 0),
      periodOneRemaining: Number(root.dataset.elmPeriodOneRemaining || 0),
      periodOneLimit: Number(root.dataset.elmPeriodOneLimit || 0),
      standardEntitlement: Number(root.dataset.elmStandardEntitlement || 20),
    };
    const syncPlusOnePreview = () => {
      const current = Math.max(1, Math.round(Number(balanceState.standardEntitlement) || 20));
      const requested = current + 1;
      if (entitlementFrom) entitlementFrom.textContent = String(current);
      if (entitlementTo) entitlementTo.textContent = String(requested);
      if (entitlementForm?.elements?.requested_entitlement) entitlementForm.elements.requested_entitlement.value = String(requested);
      if (entitlementForm?.elements?.effective_year) entitlementForm.elements.effective_year.value = String(currentYear);
    };
    syncPlusOnePreview();
    let baseMonth = parseIso(today);
    baseMonth = new Date(baseMonth.getFullYear(), baseMonth.getMonth(), 1);
    let rangeAnchor = null;
    const initialRequestsNode = root.querySelector('[data-elm-initial-requests]');
    let requestsCache = [];
    if (initialRequestsNode) {
      try {
        const initialRequests = JSON.parse(initialRequestsNode.textContent || '[]');
        requestsCache = Array.isArray(initialRequests) ? initialRequests : [];
      } catch (error) {
        requestsCache = [];
      }
    }
    let editContext = null;
    const selectedDates = new Set(
      String(selectedInput.value || '')
        .split(',')
        .map((date) => date.trim())
        .filter(Boolean),
    );

    const fallbackDay = (key) => {
      const parsed = parseIso(key);
      const weekend = [0, 6].includes(parsed.getDay());
      return {
        approved: 0,
        pending: 0,
        disabled: false,
        capacity_blocked: false,
        working_day: !weekend,
        holiday: false,
        holiday_name: '',
        weekend,
        short_notice: key >= today && key <= noticeEnd,
        own_status: '',
        own_leave_type: '',
        own_policy_warning: false,
        disabled_reason: '',
        day_note: weekend ? 'Ditë jopune; nuk llogaritet si ditë pushimi.' : '',
      };
    };

    const calendarData = { days: {}, capacity_limit: 2, notice_end: noticeEnd };
    root.querySelectorAll('[data-ot-date]').forEach((button) => {
      const key = button.dataset.otDate;
      if (!key) return;
      calendarData.days[key] = {
        approved: Number(button.dataset.approved || 0),
        pending: Number(button.dataset.pending || 0),
        disabled: button.dataset.capacityBlocked === '1',
        capacity_blocked: button.dataset.capacityBlocked === '1',
        working_day: button.dataset.workingDay !== '0',
        holiday: button.dataset.holiday === '1',
        holiday_name: button.dataset.holidayName || '',
        weekend: button.dataset.weekend === '1',
        short_notice: button.dataset.shortNotice === '1',
        own_status: button.dataset.ownStatus || '',
        own_leave_type: button.dataset.ownLeaveType || '',
        own_policy_warning: button.dataset.ownPolicyWarning === '1',
        disabled_reason: button.dataset.capacityBlocked === '1' ? 'Është arritur kapaciteti ditor i miratimeve.' : '',
        day_note: button.dataset.dayNote || '',
      };
    });

    // Prefer localized values, but always fall back to the server-rendered
    // portal attributes. This keeps AJAX actions working on cached pages and
    // when the shortcode is rendered after the normal script-localization phase.
    const portalAjaxUrl = cfg.ajaxUrl || root.dataset.elmAjaxUrl || '';
    const portalActionNonce = cfg.portalNonce || cfg.ajaxNonce || root.dataset.elmPortalNonce || '';
    const submitActionNonce = cfg.ajaxNonce || portalActionNonce;
    const canAjax = Boolean(portalAjaxUrl && portalActionNonce);
    const ajax = async (action, fields = {}, options = {}) => {
      if (!portalAjaxUrl || !portalActionNonce) throw new Error(i18n.genericError);
      const body = options.body instanceof FormData ? options.body : new FormData();
      body.set('action', action);
      body.set('nonce', options.submit ? submitActionNonce : portalActionNonce);
      Object.entries(fields).forEach(([key, value]) => body.set(key, String(value)));
      const response = await fetch(portalAjaxUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const envelope = await parseResponse(response);
      if (!response.ok || !envelope.success) {
        const details = envelope && envelope.data ? envelope.data : {};
        const error = new Error(details.message || envelope.message || i18n.genericError);
        error.data = details.data || {};
        error.status = response.status;
        throw error;
      }
      return envelope.data;
    };

    const showFeedback = (message, kind = 'success') => {
      if (notice) {
        notice.textContent = message;
        notice.className = `elm-notice elm-notice--${kind}`;
        notice.hidden = false;
      }
      if (submitStatus) {
        submitStatus.textContent = message;
        submitStatus.className = `elm-submit-status elm-submit-status--${kind}`;
        submitStatus.hidden = false;
      }
    };

    const clearFeedback = () => {
      if (notice) {
        notice.hidden = true;
        notice.textContent = '';
      }
      if (submitStatus) {
        submitStatus.hidden = true;
        submitStatus.textContent = '';
      }
    };

    const reportUrl = (requestId) => `${cfg.exportRequestBase}${encodeURIComponent(requestId)}&_wpnonce=${encodeURIComponent(cfg.exportRequestNonce)}`;
    const calendarDay = (key) => calendarData.days[key] || fallbackDay(key);
    const isPastForCurrentType = (key) => typeSelect.value === 'annual' && key < today;
    const isShortNoticeForCurrentType = (key, day = calendarDay(key)) => typeSelect.value === 'annual'
      && key >= today
      && (Boolean(day.short_notice) || key <= (calendarData.notice_end || noticeEnd));
    const isEditingOriginalDate = (key) => Boolean(
      editContext
      && (editContext.originalRangeDates || editContext.originalDates).has(key)
    );
    const dayIsBlocked = (key, day = calendarDay(key)) => {
      const editingOriginal = isEditingOriginalDate(key);
      return Boolean(
        (isPastForCurrentType(key) && !editingOriginal)
        || (typeSelect.value === 'annual' && day.disabled && !editingOriginal)
        || (typeSelect.value === 'annual' && day.capacity_blocked && !editingOriginal)
        || (activeOwnStatuses.has(day.own_status) && !editingOriginal && !(typeSelect.value === 'medical' && day.own_leave_type === 'annual'))
      );
    };
    const isDeductedWorkingDay = (key) => {
      const day = calendarDay(key);
      return Boolean(day.working_day && !day.holiday && !dayIsBlocked(key, day));
    };

    const formatPeriodOneAllowance = () => i18n.periodOneAvailable
      .replace('%1$d', String(Math.max(0, Math.round(balanceState.periodOneRemaining))))
      .replace('%2$d', String(Math.max(0, Math.round(balanceState.periodOneLimit))));

    const updateAllowanceText = () => {
      if (periodOneAllowance) periodOneAllowance.textContent = formatPeriodOneAllowance();
    };

    const setBalance = (balance = {}) => {
      const values = {
        total: balance.total_entitlement,
        used: balance.approved_used,
        pending: balance.pending_units,
        remaining: balance.remaining_after_pending,
      };
      Object.entries(values).forEach(([key, value]) => {
        const element = root.querySelector(`[data-balance="${key}"]`);
        if (element) element.textContent = wholeDays(value);
      });
      const ringTotal = Number(balance.total_entitlement);
      const ringRemaining = Math.max(0, Number(balance.remaining_after_pending) || 0);
      if (Number.isFinite(ringTotal) && ringTotal > 0) {
        const ringPct = Math.max(0, Math.min(100, Math.round((ringRemaining / ringTotal) * 100)));
        const ringEl = root.querySelector('[data-balance-ring]');
        if (ringEl) ringEl.style.setProperty('--elm-ring-pct', String(ringPct));
        const ringValueEl = root.querySelector('[data-balance-ring-value]');
        if (ringValueEl) ringValueEl.textContent = `${ringPct}%`;
      }
      if (Number.isFinite(Number(balance.remaining_after_pending))) {
        balanceState.totalRemaining = Number(balance.remaining_after_pending);
      }
      if (Number.isFinite(Number(balance.period_one_remaining))) {
        balanceState.periodOneRemaining = Number(balance.period_one_remaining);
      }
      if (Number.isFinite(Number(balance.period_one_limit))) {
        balanceState.periodOneLimit = Number(balance.period_one_limit);
      }
      if (Number.isFinite(Number(balance.standard_entitlement))) {
        balanceState.standardEntitlement = Number(balance.standard_entitlement);
        root.dataset.elmStandardEntitlement = String(Math.round(balanceState.standardEntitlement));
        syncPlusOnePreview();
      }
      updateAllowanceText();
    };

    const entitlementApi = async (operation, payload = {}) => ajax('elm_entitlement_change', {
      operation,
      payload: JSON.stringify(payload || {}),
    });

    const showEntitlementNotice = (message, kind = 'success') => {
      if (!entitlementNotice) return;
      entitlementNotice.textContent = message;
      entitlementNotice.className = `elm-chief-notice elm-chief-notice--${kind}`;
      entitlementNotice.hidden = false;
    };

    const updateEntitlementRequestUi = (changes = []) => {
      const currentYearChanges = (Array.isArray(changes) ? changes : []).filter((change) => Number(change.effective_year) === currentYear);
      const blockingChange = currentYearChanges.find((change) => (change.status || '') === 'pending');
      const currentChange = currentYearChanges[0] || null;
      entitlementCanStart = !blockingChange;
      root.dataset.elmEntitlementCanStart = entitlementCanStart ? '1' : '0';
      if (entitlementForm) entitlementForm.hidden = !entitlementCanStart;
      if (entitlementToggle) {
        entitlementToggle.hidden = false;
        entitlementToggle.textContent = entitlementCanStart ? i18n.entitlementChange : i18n.entitlementView;
      }
      return currentChange;
    };

    const renderEntitlementHistory = (changes = []) => {
      if (!entitlementHistory) return;
      entitlementHistory.textContent = '';
      updateEntitlementRequestUi(changes);
      if (!changes.length) {
        renderListState(entitlementHistory, i18n.entitlementNoHistory || 'Nuk u gjet asnjë kërkesë për +1 ditë.');
        return;
      }
      changes.forEach((change) => {
        const item = document.createElement('div');
        item.className = `elm-entitlement-history__item elm-entitlement-history__item--${change.status || 'pending'}`;
        const summary = document.createElement('div');
        summary.className = 'elm-entitlement-history__summary';
        const title = document.createElement('strong');
        const delta = Math.round(Number(change.requested_entitlement) || 0) - Math.round(Number(change.current_entitlement) || 0);
        title.textContent = `${delta > 0 ? '+' : ''}${delta} ditë • ${wholeDays(change.current_entitlement)} → ${wholeDays(change.requested_entitlement)} • ${change.effective_year}`;
        const badge = document.createElement('span');
        badge.className = `elm-status elm-status--${change.status || 'pending'}`;
        badge.textContent = statusLabel(change.status || 'pending');
        summary.append(title, badge);
        const reason = document.createElement('p');
        reason.textContent = change.reason || '';
        item.append(summary, reason);
        if (change.decision_note) {
          const decision = document.createElement('p');
          decision.className = 'elm-entitlement-history__decision';
          decision.textContent = `${change.decided_by_username || change.decided_by_name || ''}: ${change.decision_note}`;
          item.appendChild(decision);
        }
        if (change.status === 'pending') {
          const cancel = document.createElement('button');
          cancel.type = 'button';
          cancel.className = 'elm-link-button';
          cancel.dataset.cancelEntitlement = String(change.id);
          cancel.textContent = i18n.cancel;
          item.appendChild(cancel);
        }
        entitlementHistory.appendChild(item);
      });
    };

    const loadEntitlementHistory = async () => {
      if (!entitlementHistory) return [];
      renderListState(entitlementHistory, i18n.loading || 'Po ngarkohet...', 'loading');
      try {
        const changes = await entitlementApi('list');
        renderEntitlementHistory(Array.isArray(changes) ? changes : []);
        return changes;
      } catch (error) {
        renderListState(entitlementHistory, error.message || i18n.genericError, 'error');
        throw error;
      }
    };

    const openEntitlementPanel = async () => {
      if (!entitlementPanel) return;
      entitlementPanel.hidden = false;
      syncPlusOnePreview();
      try {
        await loadEntitlementHistory();
        if (entitlementHistoryDetails && !entitlementCanStart) entitlementHistoryDetails.open = true;
      } catch (error) {
        showEntitlementNotice(error.message, 'error');
      }
      entitlementPanel.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' });
    };

    entitlementToggle?.addEventListener('click', openEntitlementPanel);
    root.querySelectorAll('[data-entitlement-close]').forEach((button) => button.addEventListener('click', () => {
      if (entitlementPanel) entitlementPanel.hidden = true;
    }));
    entitlementForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(entitlementForm);
      const submit = entitlementForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        await entitlementApi('create', {
          requested_entitlement: Number(data.get('requested_entitlement') || 0),
          effective_year: Number(data.get('effective_year') || 0),
          reason: String(data.get('reason') || '').trim(),
        });
        entitlementCanStart = false;
        root.dataset.elmEntitlementCanStart = '0';
        entitlementForm.elements.reason.value = '';
        showEntitlementNotice(i18n.entitlementRequested);
        await loadEntitlementHistory();
        if (entitlementHistoryDetails) entitlementHistoryDetails.open = true;
      } catch (error) {
        showEntitlementNotice(error.message, 'error');
      } finally {
        if (submit) submit.disabled = false;
      }
    });
    entitlementHistory?.addEventListener('click', async (event) => {
      const button = event.target instanceof Element ? event.target.closest('[data-cancel-entitlement]') : null;
      if (!button || !window.confirm(i18n.entitlementCancelConfirm)) return;
      button.disabled = true;
      try {
        await entitlementApi('cancel', { id: Number(button.dataset.cancelEntitlement || 0) });
        showEntitlementNotice(i18n.entitlementCancelled);
        await loadEntitlementHistory();
      } catch (error) {
        showEntitlementNotice(error.message, 'error');
        button.disabled = false;
      }
    });

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

    const appendRequestReasonsNotice = (cell, request) => {
      const reasons = collectRequestReasons(request);
      if (!reasons.length) return;
      const notice = document.createElement('div');
      notice.className = 'elm-request-reasons-notice';
      reasons.forEach((reason, index) => {
        const item = document.createElement('div');
        item.className = `elm-request-reasons-item elm-request-reasons-item--${reason.status}`;
        const number = document.createElement('span');
        number.className = 'elm-request-reasons-number';
        number.textContent = `#${index + 1}`;
        const content = document.createElement('span');
        content.className = 'elm-request-reasons-content';
        const actor = document.createElement('strong');
        actor.className = 'elm-request-reasons-actor';
        actor.textContent = reason.actorName;
        content.append(actor, document.createTextNode(`: ${reason.text}`));
        item.append(number, content);
        notice.appendChild(item);
      });
      cell.appendChild(notice);
    };


    const resetEditState = (render = true) => {
      editContext = null;
      if (requestCard) requestCard.classList.remove('is-editing');
      form.reset();
      if (formActionInput) formActionInput.value = 'elm_submit_request_form';
      if (editRequestIdInput) editRequestIdInput.value = '';
      if (submitButton) submitButton.textContent = i18n.submitRequest;
      if (cancelEditButton) cancelEditButton.hidden = true;
      if (requestFormEyebrow) requestFormEyebrow.textContent = i18n.newRequest;
      if (requestFormTitle) requestFormTitle.textContent = i18n.requestLeave;
      if (medical) medical.hidden = true;
      selectedDates.clear();
      rangeAnchor = null;
      clearFeedback();
      if (render) renderCalendars();
    };

    const beginEdit = async (request) => {
      if (!request || request.status !== 'pending') {
        showFeedback(i18n.editPendingOnly, 'error');
        return;
      }
      const requestDates = Array.isArray(request.selected_dates) && request.selected_dates.length
        ? request.selected_dates
        : [request.start_date, request.end_date].filter((value, index, values) => value && values.indexOf(value) === index);
      const originalDates = new Set(requestDates);
      const originalRangeDates = new Set(requestDates);
      const rangeStart = String(request.start_date || '');
      const rangeEnd = String(request.end_date || '');
      if (/^\d{4}-\d{2}-\d{2}$/.test(rangeStart) && /^\d{4}-\d{2}-\d{2}$/.test(rangeEnd) && rangeStart <= rangeEnd) {
        const cursor = parseIso(rangeStart);
        const rangeTo = parseIso(rangeEnd);
        let guard = 0;
        while (cursor <= rangeTo && guard <= 366) {
          originalRangeDates.add(iso(cursor));
          cursor.setDate(cursor.getDate() + 1);
          guard += 1;
        }
      }
      if (requestCard) requestCard.classList.add('is-editing');
      editContext = {
        id: Number(request.id),
        originalType: String(request.leave_type || 'annual'),
        originalDates,
        originalRangeDates,
        shortNoticeWarningDates: new Set(Array.isArray(request.short_notice_warning_dates) ? request.short_notice_warning_dates : []),
        originalPeriodOneDays: [...originalDates].filter((date) => Number(String(date).slice(5, 7)) <= 6).length,
        year: Number(String(request.start_date || '').slice(0, 4)) || currentYear,
      };
      selectedDates.clear();
      originalRangeDates.forEach((date) => selectedDates.add(date));
      rangeAnchor = null;
      typeSelect.value = request.leave_type === 'medical' ? 'medical' : 'annual';
      reasonInput.value = String(request.reason || '');
      if (ackInput) ackInput.checked = Boolean(request.medical_ack);
      if (fileInput) fileInput.value = '';
      if (medical) medical.hidden = typeSelect.value !== 'medical';
      if (formActionInput) formActionInput.value = 'elm_update_request_form';
      if (editRequestIdInput) editRequestIdInput.value = String(request.id);
      if (submitButton) submitButton.textContent = i18n.saveChanges;
      if (cancelEditButton) cancelEditButton.hidden = false;
      if (requestFormEyebrow) requestFormEyebrow.textContent = i18n.editingRequest.replace('%d', String(request.id));
      if (requestFormTitle) requestFormTitle.textContent = i18n.saveChanges;
      const firstDate = [...originalDates].sort()[0];
      if (firstDate) {
        const parsed = parseIso(firstDate);
        baseMonth = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
      }
      renderCalendars();
      try {
        await loadCalendar();
      } catch (error) {
        // Keep editing available with the server-rendered calendar when a cached
        // nonce prevents the optional live calendar refresh.
        renderCalendars();
      }
      if (requestCard && typeof requestCard.scrollIntoView === 'function') requestCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const renderRequests = (requests = []) => {
      requestsCache = Array.isArray(requests) ? requests : [];
      if (!requestsBody) return;
      requestsBody.textContent = '';
      if (!requests.length) {
        renderTableState(requestsBody, 6, i18n.noRequests || 'Nuk u gjet asnjë kërkesë për pushim.');
        return;
      }

      requests.forEach((request) => {
        const row = document.createElement('tr');
        const selected = Array.isArray(request.selected_dates) ? request.selected_dates : [];
        const fallbackDates = [request.start_date, request.end_date].filter((value, index, values) => value && values.indexOf(value) === index);
        const dates = groupedDateList(selected.length ? selected : fallbackDates);
        const values = [
          request.leave_type === 'annual' ? i18n.annual : i18n.medical,
          dates,
          wholeDays(request.requested_units),
          statusLabel(request.status),
          new Date(String(request.submitted_at).replace(' ', 'T')).toLocaleString(locale),
        ];
        values.forEach((value, index) => {
          const cell = document.createElement('td');
          if (index === 1) cell.className = 'elm-request-dates';
          if (index === 3) {
            const badge = document.createElement('span');
            badge.className = `elm-status elm-status--${request.status}`;
            badge.textContent = String(value || '');
            cell.appendChild(badge);
          } else {
            cell.textContent = String(value == null ? '' : value);
            if (index === 1) {
              appendRequestReasonsNotice(cell, request);
              const shortWarningDates = Array.isArray(request.short_notice_warning_dates) ? request.short_notice_warning_dates : [];
              if (shortWarningDates.length) {
                const warning = document.createElement('div');
                warning.className = `elm-request-policy-warning elm-request-policy-warning--${request.status || 'pending'}`;
                warning.textContent = String(i18n.chiefShortNoticeWarning || 'Vërejtje: kjo kërkesë përfshin data që nuk e plotësojnë afatin minimal 15-ditor. Datat: %s.')
                  .replace('%s', groupedDateList(shortWarningDates));
                cell.appendChild(warning);
              }
              const periodWarningDates = Array.isArray(request.period_one_warning_dates) ? request.period_one_warning_dates : [];
              if (periodWarningDates.length) {
                const warning = document.createElement('div');
                warning.className = `elm-request-policy-warning elm-request-policy-warning--${request.status || 'pending'}`;
                warning.textContent = String(i18n.chiefPeriodOneWarning || 'Vërejtje: kjo kërkesë kalon pragun informues prej 10 ditësh pune për periudhën janar-qershor. Ditët mbi prag: %s.')
                  .replace('%s', groupedDateList(periodWarningDates));
                cell.appendChild(warning);
              }
            }
          }
          row.appendChild(cell);
        });

        const actions = document.createElement('td');
        actions.className = 'elm-table-actions';
        const actionsInner = document.createElement('div');
        actionsInner.className = 'elm-table-actions-inner elm-my-actions';
        if (request.status === 'pending') {
          const edit = document.createElement('button');
          edit.type = 'button';
          edit.className = 'button button-small elm-self-action elm-self-action--edit';
          edit.dataset.editRequest = String(request.id);
          edit.textContent = i18n.edit || 'Redakto';
          edit.setAttribute('aria-label', i18n.edit || 'Redakto');
          edit.title = i18n.edit || 'Redakto';
          actionsInner.appendChild(edit);

          if (canManageOwnRequests) {
            const approve = document.createElement('button');
            approve.type = 'button';
            approve.className = 'button button-small elm-self-action elm-self-action--approve';
            approve.dataset.ownDecision = 'approved';
            approve.dataset.ownDecisionRequest = String(request.id);
            approve.textContent = i18n.approve || 'Mirato';
            approve.setAttribute('aria-label', i18n.approve || 'Mirato');
            approve.title = i18n.approve || 'Mirato';
            actionsInner.appendChild(approve);

            const reject = document.createElement('button');
            reject.type = 'button';
            reject.className = 'button button-small elm-self-action elm-self-action--reject';
            reject.dataset.ownDecision = 'rejected';
            reject.dataset.ownDecisionRequest = String(request.id);
            reject.textContent = i18n.reject || 'Refuzo';
            reject.setAttribute('aria-label', i18n.reject || 'Refuzo');
            reject.title = i18n.reject || 'Refuzo';
            actionsInner.appendChild(reject);
          }

        }
        if (['pending', 'approved'].includes(request.status)) {
          const cancel = document.createElement('button');
          cancel.type = 'button';
          cancel.className = 'button button-small elm-self-action elm-self-action--cancel';
          cancel.dataset.cancelRequest = String(request.id);
          cancel.textContent = i18n.cancel || 'Anulo';
          cancel.setAttribute('aria-label', i18n.cancel || 'Anulo');
          cancel.title = i18n.cancel || 'Anulo';
          actionsInner.appendChild(cancel);
        }
        if (canDeleteOwnRequests) {
          const remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'button button-small elm-self-action elm-self-action--delete button-link-delete';
          remove.dataset.deleteOwnRequest = String(request.id);
          remove.textContent = i18n.delete || 'Fshi';
          remove.setAttribute('aria-label', i18n.delete || 'Fshi');
          remove.title = i18n.delete || 'Fshi';
          actionsInner.appendChild(remove);
        }
        if (cfg.exportRequestBase && cfg.exportRequestNonce) {
          const pdf = document.createElement('a');
          pdf.className = 'button button-small elm-self-action elm-self-action--pdf';
          pdf.href = reportUrl(request.id);
          pdf.target = '_blank';
          pdf.rel = 'noopener';
          pdf.textContent = 'PDF';
          pdf.setAttribute('aria-label', 'PDF');
          pdf.title = 'PDF';
          actionsInner.appendChild(pdf);
        }
        actions.appendChild(actionsInner);
        row.appendChild(actions);
        requestsBody.appendChild(row);
      });
    };

    const loadSnapshot = async () => {
      const snapshot = await ajax('elm_portal_snapshot', { year: currentYear });
      setBalance(snapshot.balance);
      renderRequests(snapshot.requests || []);
      return snapshot;
    };

    const sortedSelectedDates = () => [...selectedDates].sort();
    const selectedWorkingDates = () => sortedSelectedDates().filter(isDeductedWorkingDay);
    const workingDatesFor = (dates) => [...dates].sort().filter(isDeductedWorkingDay);
    const annualSelectionError = (dates) => {
      if (typeSelect.value !== 'annual') return '';
      const yearKey = String(currentYear);
      const workingDates = workingDatesFor(dates).filter((date) => date.slice(0, 4) === yearKey);
      const editCredit = editContext && editContext.originalType === 'annual'
        ? [...editContext.originalDates].filter((date) => date.slice(0, 4) === yearKey).length
        : 0;
      const available = balanceState.totalRemaining + editCredit;
      if (workingDates.length > available) {
        return i18n.totalExceeded.replace('%d', String(Math.max(0, Math.round(available))));
      }
      return '';
    };

    const selectedPolicyWarnings = (dates) => {
      const result = {
        shortNotice: [],
        periodOneExcess: [],
        periodOneLimit: 10,
      };
      if (typeSelect.value !== 'annual') return result;

      const workingDates = workingDatesFor(dates);
      workingDates.forEach((date) => {
        const persistedShortNotice = Boolean(editContext?.shortNoticeWarningDates?.has(date));
        const isOriginalDate = Boolean(editContext?.originalDates?.has(date));
        if (persistedShortNotice || (!isOriginalDate && isShortNoticeForCurrentType(date, calendarDay(date))) || (!editContext && isShortNoticeForCurrentType(date, calendarDay(date)))) {
          result.shortNotice.push(date);
        }
      });

      const periodOneByYear = new Map();
      workingDates.filter((date) => Number(date.slice(5, 7)) <= 6).forEach((date) => {
        const year = Number(date.slice(0, 4));
        const count = (periodOneByYear.get(year) || 0) + 1;
        periodOneByYear.set(year, count);
        if (count > result.periodOneLimit) result.periodOneExcess.push(date);
      });
      return result;
    };

    const formatSelectionPolicyWarnings = (dates) => {
      const warnings = selectedPolicyWarnings(dates);
      const messages = [];
      if (warnings.shortNotice.length) {
        messages.push(String(i18n.shortNoticeWarning || '')
          .replace('%1$d', '15')
          .replace('%2$s', groupedDateList(warnings.shortNotice)));
      }
      if (warnings.periodOneExcess.length) {
        messages.push(String(i18n.periodOneWarning || '')
          .replace('%1$d', String(warnings.periodOneLimit))
          .replace('%2$d', String(warnings.periodOneExcess.length))
          .replace('%3$s', groupedDateList(warnings.periodOneExcess)));
      }
      return messages.filter(Boolean);
    };

    const syncSelectedFields = () => {
      const dates = sortedSelectedDates();
      const workingDates = selectedWorkingDates();
      selectedInput.value = workingDates.join(',');
      startInput.value = dates[0] || '';
      endInput.value = dates[dates.length - 1] || '';

      if (!dates.length) {
        selectedSummary.textContent = i18n.noneSelected;
      } else {
        const calendarText = groupedDateList(dates);
        selectedSummary.textContent = `${calendarText} - ${i18n.workingDays.replace('%d', workingDates.length)}`;
      }
      requestDayCount.textContent = String(workingDates.length);

      if (policyWarnings) {
        const messages = formatSelectionPolicyWarnings(selectedDates);
        policyWarnings.textContent = messages.join(' ');
        policyWarnings.hidden = messages.length === 0;
      }

      if (waitlist) {
        const pending = workingDates.filter((date) => calendarDay(date).pending);
        if (pending.length) {
          waitlist.textContent = i18n.pendingWarning.replace('%s', pending.join(', '));
          waitlist.className = 'elm-waitlist elm-waitlist--pending';
          waitlist.hidden = false;
        } else {
          waitlist.hidden = true;
          waitlist.textContent = '';
        }
      }
    };

    const weekdayLabels = () => [...albanianWeekdaysShort];

    const dayMessage = (key, day) => {
      if (activeOwnStatuses.has(day.own_status) && !(typeSelect.value === 'medical' && day.own_leave_type === 'annual')) return i18n.ownRequest.replace('%s', day.own_status);
      if (isPastForCurrentType(key)) return 'Pushimi vjetor nuk mund të përfshijë data të kaluara.';
      if (isShortNoticeForCurrentType(key, day) && !isEditingOriginalDate(key)) return `Vërejtje: kjo datë është brenda afatit minimal 15-ditor dhe mund të përzgjidhet. ${day.day_note || ''}`.trim();
      if (day.disabled_reason) return day.disabled_reason;
      return day.day_note || '';
    };

    const renderMonth = (container, visibleMonth) => {
      container.textContent = '';
      const heading = document.createElement('div');
      heading.className = 'ot-month-title';
      heading.textContent = monthLabel(visibleMonth);
      container.appendChild(heading);

      const table = document.createElement('table');
      table.className = 'ot-cal-table';
      const thead = document.createElement('thead');
      const headingRow = document.createElement('tr');
      weekdayLabels().forEach((weekday) => {
        const th = document.createElement('th');
        th.textContent = weekday;
        headingRow.appendChild(th);
      });
      thead.appendChild(headingRow);
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      const selectionWarnings = selectedPolicyWarnings(selectedDates);
      const periodOneExcessSet = new Set(selectionWarnings.periodOneExcess);
      const year = visibleMonth.getFullYear();
      const monthNumber = visibleMonth.getMonth();
      const first = new Date(year, monthNumber, 1);
      const offset = (first.getDay() + 6) % 7;
      const daysInMonth = new Date(year, monthNumber + 1, 0).getDate();
      const previousMonthDays = new Date(year, monthNumber, 0).getDate();
      const cells = [];
      for (let index = 0; index < offset; index += 1) cells.push({ day: previousMonthDays - offset + 1 + index, other: true });
      for (let day = 1; day <= daysInMonth; day += 1) cells.push({ day, other: false });
      while (cells.length % 7 !== 0) cells.push({ day: cells.length - (offset + daysInMonth) + 1, other: true });

      cells.forEach((cell, index) => {
        if (index % 7 === 0) tbody.appendChild(document.createElement('tr'));
        const td = document.createElement('td');
        let element;
        if (cell.other) {
          element = document.createElement('span');
          element.className = 'ot-day ot-other-month';
          element.setAttribute('aria-hidden', 'true');
        } else {
          const key = iso(new Date(year, monthNumber, cell.day));
          const day = calendarDay(key);
          const selected = selectedDates.has(key);
          const shortNoticeWarning = isShortNoticeForCurrentType(key, day) && !isEditingOriginalDate(key);
          const periodOneExcessWarning = selected && periodOneExcessSet.has(key);
          const baseMessage = dayMessage(key, day);
          const excessMessage = periodOneExcessWarning ? 'Vërejtje: kjo është një ditë e zgjedhur mbi pragun informues prej 10 ditësh në periudhën janar-qershor.' : '';
          const message = [baseMessage, excessMessage].filter(Boolean).join(' ');
          element = document.createElement('button');
          element.type = 'button';
          element.className = 'ot-day';
          element.dataset.otDate = key;
          element.setAttribute('aria-pressed', selected ? 'true' : 'false');
          element.setAttribute('aria-label', `${prettyDate(key)}. ${day.working_day ? 'Ditë pune.' : 'Ditë jopune; nuk llogaritet.'} ${day.own_status ? `Statusi i kërkesës suaj: ${statusLabel(day.own_status)}.` : ''} ${message}`.trim());
          element.title = message || (typeSelect.value === 'annual' ? `${day.approved || 0}/${calendarData.capacity_limit} të miratuara${day.pending ? `, ${day.pending} në pritje` : ''}` : prettyDate(key));
          if (selected) element.classList.add('ot-selected');
          if (key === rangeAnchor) element.classList.add('ot-range-anchor');
          if (!day.working_day || day.holiday) element.classList.add('ot-nonworking');
          if (day.weekend || [0, 6].includes(parseIso(key).getDay())) element.classList.add('ot-weekend');
          if (day.pending) element.classList.add('ot-pending');
          if (typeSelect.value === 'annual' && day.capacity_blocked) element.classList.add('ot-capacity-full');
          if (day.holiday) element.classList.add('ot-holiday');
          if (allOwnStatuses.has(day.own_status)) {
            element.classList.add('ot-own-status', `ot-own-status--${day.own_status}`);
          }
          if (day.own_policy_warning) {
            element.classList.add('ot-own-policy-warning');
          }
          if (shortNoticeWarning) element.classList.add('ot-short-notice-warning');
          if (periodOneExcessWarning) element.classList.add('ot-period-one-excess-warning');
          if (dayIsBlocked(key, day)) {
            element.classList.add('ot-disabled');
            element.setAttribute('aria-disabled', 'true');
          }
          const dayNumber = document.createElement('span');
          dayNumber.className = 'ot-day-number';
          dayNumber.textContent = String(cell.day);
          element.appendChild(dayNumber);
          if (day.holiday) {
            const holidayHelp = document.createElement('span');
            holidayHelp.className = 'ot-holiday-help';
            holidayHelp.textContent = '?';
            holidayHelp.dataset.holidayName = day.holiday_name || day.day_note || 'Festë e institucionit';
            holidayHelp.setAttribute('aria-hidden', 'true');
            element.appendChild(holidayHelp);
          }
        }
        if (cell.other) element.textContent = String(cell.day);
        td.appendChild(element);
        tbody.lastElementChild.appendChild(td);
      });
      table.appendChild(tbody);
      container.appendChild(table);
    };

    const renderCalendars = () => {
      renderMonth(monthContainers[0], baseMonth);
      renderMonth(monthContainers[1], addMonths(baseMonth, 1));
      calendar.setAttribute('aria-busy', 'false');
      syncSelectedFields();
    };

    const loadCalendar = async () => {
      if (!canAjax) {
        renderCalendars();
        return;
      }
      calendar.setAttribute('aria-busy', 'true');
      const secondMonth = addMonths(baseMonth, 1);
      const [first, second] = await Promise.all([
        ajax('elm_calendar_data', { month: monthKey(baseMonth) }),
        ajax('elm_calendar_data', { month: monthKey(secondMonth) }),
      ]);
      calendarData.capacity_limit = first.capacity_limit || second.capacity_limit || calendarData.capacity_limit;
      calendarData.notice_end = first.notice_end || second.notice_end || calendarData.notice_end;
      calendarData.days = { ...calendarData.days, ...first.days, ...second.days };
      selectedDates.forEach((date) => {
        if (dayIsBlocked(date, calendarDay(date))) selectedDates.delete(date);
      });
      if (rangeAnchor && !selectedDates.has(rangeAnchor)) rangeAnchor = null;
      renderCalendars();
    };

    const inclusiveRangeCandidate = (from, to) => {
      const candidate = new Set(selectedDates);
      const fromDate = parseIso(from);
      const toDate = parseIso(to);
      const direction = fromDate <= toDate ? 1 : -1;
      const cursor = new Date(fromDate);
      const skipped = [];
      while ((direction === 1 && cursor <= toDate) || (direction === -1 && cursor >= toDate)) {
        const key = iso(cursor);
        const day = calendarDay(key);
        if (dayIsBlocked(key, day)) skipped.push(key);
        else candidate.add(key);
        cursor.setDate(cursor.getDate() + direction);
      }
      return { candidate, skipped };
    };

    const applyCandidateSelection = (candidate) => {
      selectedDates.clear();
      candidate.forEach((date) => selectedDates.add(date));
    };

    const toggleDate = (element) => {
      const key = element.dataset.otDate;
      const day = calendarDay(key);
      if (!key || dayIsBlocked(key, day)) {
        showFeedback(dayMessage(key, day) || i18n.unavailable, 'error');
        return;
      }
      clearFeedback();
      if (selectedDates.has(key)) {
        selectedDates.delete(key);
        rangeAnchor = null;
        renderCalendars();
        return;
      }

      if (rangeAnchor && rangeAnchor !== key) {
        const { candidate, skipped } = inclusiveRangeCandidate(rangeAnchor, key);
        const limitError = annualSelectionError(candidate);
        if (limitError) {
          showFeedback(limitError, 'error');
          return;
        }
        applyCandidateSelection(candidate);
        rangeAnchor = null;
        renderCalendars();
        if (skipped.length) showFeedback(i18n.skippedDates.replace('%s', skipped.map(compactDate).join(', ')), 'error');
        return;
      }

      const candidate = new Set(selectedDates);
      candidate.add(key);
      const limitError = annualSelectionError(candidate);
      if (limitError) {
        showFeedback(limitError, 'error');
        return;
      }
      applyCandidateSelection(candidate);
      rangeAnchor = key;
      renderCalendars();
    };

    const validationError = () => {
      if (!selectedWorkingDates().length) return { message: i18n.chooseDates, field: root.querySelector('[data-ot-nav="-1"]') };
      if (typeSelect.value === 'annual' && sortedSelectedDates().some((date) => date < today && !(editContext && editContext.originalDates.has(date)))) return { message: 'Pushimit vjetor nuk mund t\'i shtohen data të kaluara.', field: root.querySelector('[data-ot-nav="-1"]') };
      const limitError = annualSelectionError(selectedDates);
      if (limitError) return { message: limitError, field: root.querySelector('[data-ot-nav="-1"]') };
      if (!reasonInput.value.trim()) return { message: i18n.reasonRequired, field: reasonInput };
      const hasMedicalFile = Boolean(fileInput && fileInput.files && fileInput.files[0]);
      if (typeSelect.value === 'medical' && !(ackInput && ackInput.checked) && !hasMedicalFile) {
        return { message: i18n.medicalRequired, field: ackInput || reasonInput };
      }
      return null;
    };

    calendar.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target.closest('[data-ot-date]') : null;
      if (target) toggleDate(target);
    });

    root.querySelectorAll('[data-ot-nav]').forEach((button) => button.addEventListener('click', () => {
      baseMonth = addMonths(baseMonth, Number(button.dataset.otNav));
      loadCalendar().catch((error) => showFeedback(error.message, 'error'));
    }));

    if (yearSelect) {
      yearSelect.addEventListener('change', async () => {
        const selectedYear = Math.min(2060, Math.max(2024, Number(yearSelect.value) || currentYear));
        yearSelect.value = String(selectedYear);
        if (selectedYear === currentYear) return;

        currentYear = selectedYear;
        root.dataset.elmYear = String(currentYear);
        baseMonth = new Date(currentYear, baseMonth.getMonth(), 1);
        selectedDates.clear();
        rangeAnchor = null;
        clearFeedback();
        syncPlusOnePreview();
        renderCalendars();

        try {
          await Promise.all([loadSnapshot(), loadCalendar()]);
          if (entitlementPanel && !entitlementPanel.hidden) await loadEntitlementHistory();
        } catch (error) {
          showFeedback(error.message, 'error');
        }
      });
    }

    if (clearDates) {
      clearDates.addEventListener('click', () => {
        selectedDates.clear();
        rangeAnchor = null;
        clearFeedback();
        renderCalendars();
      });
    }

    typeSelect.addEventListener('change', () => {
      if (medical) medical.hidden = typeSelect.value !== 'medical';
      selectedDates.forEach((date) => {
        if (dayIsBlocked(date, calendarDay(date))) selectedDates.delete(date);
      });
      if (rangeAnchor && !selectedDates.has(rangeAnchor)) rangeAnchor = null;
      const limitError = annualSelectionError(selectedDates);
      if (limitError) showFeedback(limitError, 'error');
      renderCalendars();
    });

    if (requestsBody) {
      requestsBody.addEventListener('click', async (event) => {
        const editButton = event.target instanceof Element ? event.target.closest('[data-edit-request]') : null;
        if (editButton) {
          event.preventDefault();
          const requestId = Number(editButton.dataset.editRequest || 0);
          if (!requestId) {
            showFeedback(i18n.genericError, 'error');
            return;
          }
          editButton.disabled = true;
          try {
            let request = requestsCache.find((item) => Number(item.id) === requestId);
            if (!request && canAjax) {
              const snapshot = await loadSnapshot();
              request = (snapshot.requests || []).find((item) => Number(item.id) === requestId);
            }
            if (!request) throw new Error(i18n.genericError);
            await beginEdit(request);
          } catch (error) {
            showFeedback(error.message, 'error');
          } finally {
            if (editButton.isConnected) editButton.disabled = false;
          }
          return;
        }

        const ownDecisionButton = event.target instanceof Element ? event.target.closest('[data-own-decision]') : null;
        if (ownDecisionButton) {
          event.preventDefault();
          const requestId = Number(ownDecisionButton.dataset.ownDecisionRequest || 0);
          const decision = String(ownDecisionButton.dataset.ownDecision || '');
          if (!requestId || !canManageOwnRequests || !['approved', 'rejected'].includes(decision)) {
            showFeedback(i18n.genericError, 'error');
            return;
          }
          const currentRequest = requestsCache.find((item) => Number(item.id) === requestId);
          const shortWarningDates = Array.isArray(currentRequest?.short_notice_warning_dates) ? currentRequest.short_notice_warning_dates : [];
          const periodWarningDates = Array.isArray(currentRequest?.period_one_warning_dates) ? currentRequest.period_one_warning_dates : [];
          if (decision === 'approved' && shortWarningDates.length) {
            const warningText = String(i18n.chiefShortNoticeConfirm || 'Kjo kërkesë përfshin data brenda afatit minimal 15-ditor: %s. Shqyrtojeni këtë vërejtje para miratimit.')
              .replace('%s', groupedDateList(shortWarningDates));
            if (!window.confirm(warningText)) return;
          }
          if (decision === 'approved' && periodWarningDates.length) {
            const warningText = String(i18n.chiefPeriodOneConfirm || 'Kjo kërkesë kalon pragun informues 10-ditor për periudhën janar-qershor. Ditët mbi prag: %s. Vazhdoni me miratimin vetëm pasi ta keni shqyrtuar këtë tejkalim.')
              .replace('%s', groupedDateList(periodWarningDates));
            if (!window.confirm(warningText)) return;
          }
          const promptText = decision === 'approved'
            ? (i18n.approvePrompt || 'Arsyetim për miratimin (opsional):')
            : (i18n.rejectPrompt || 'Arsyetimi i refuzimit:');
          const note = window.prompt(promptText, '');
          if (note === null) return;
          if (decision === 'rejected' && !note.trim()) {
            showFeedback(i18n.rejectPrompt || 'Arsyetimi i refuzimit është i detyrueshëm.', 'error');
            return;
          }
          ownDecisionButton.disabled = true;
          try {
            await ajax('elm_chief_portal', {
              operation: 'decision_own',
              payload: JSON.stringify({ request_id: requestId, decision, note: note.trim() }),
            });
            await Promise.all([loadSnapshot(), loadCalendar()]);
            showFeedback(i18n.operationSaved || 'Vendimi u ruajt me sukses.');
          } catch (error) {
            showFeedback(error.message, 'error');
            if (ownDecisionButton.isConnected) ownDecisionButton.disabled = false;
          }
          return;
        }

        const deleteOwnButton = event.target instanceof Element ? event.target.closest('[data-delete-own-request]') : null;
        if (deleteOwnButton) {
          event.preventDefault();
          const requestId = Number(deleteOwnButton.dataset.deleteOwnRequest || 0);
          if (!requestId || !canDeleteOwnRequests) {
            showFeedback(i18n.genericError, 'error');
            return;
          }
          if (!window.confirm(i18n.deleteOwnConfirm)) return;
          const confirmation = window.prompt(i18n.deleteOwnPrompt, '') ?? null;
          if (confirmation === null) return;
          if (confirmation !== 'DELETE') {
            showFeedback(i18n.deleteRequestMismatch || 'Shkruani saktësisht DELETE për të aktivizuar fshirjen.', 'error');
            return;
          }
          deleteOwnButton.disabled = true;
          try {
            await ajax('elm_delete_own_request', { request_id: requestId, confirmation });
            await Promise.all([loadSnapshot(), loadCalendar()]);
            showFeedback(i18n.deleteOwnSuccess);
          } catch (error) {
            showFeedback(error.message, 'error');
            if (deleteOwnButton.isConnected) deleteOwnButton.disabled = false;
          }
          return;
        }
        const button = event.target instanceof Element ? event.target.closest('[data-cancel-request]') : null;
        if (!button) return;
        if (!window.confirm(i18n.cancelConfirm)) {
          event.preventDefault();
          return;
        }
        const cancelReason = window.prompt(i18n.cancelReasonPrompt, '') ?? null;
        if (cancelReason === null) {
          event.preventDefault();
          return;
        }
        if (!cancelReason.trim()) {
          event.preventDefault();
          showFeedback(i18n.cancelReasonRequired, 'error');
          return;
        }
        const cancelForm = button.closest('form');
        const reasonField = cancelForm ? cancelForm.querySelector('[name="cancel_reason"]') : null;
        if (reasonField) reasonField.value = cancelReason.trim();
        if (!canAjax) return;
        event.preventDefault();
        button.disabled = true;
        try {
          await ajax('elm_cancel_request', { request_id: button.dataset.cancelRequest, cancel_reason: cancelReason.trim() });
          await Promise.all([loadSnapshot(), loadCalendar()]);
          showFeedback(i18n.cancelledMessage);
        } catch (error) {
          showFeedback(error.message, 'error');
          button.disabled = false;
        }
      });
    }


    if (cancelEditButton) {
      cancelEditButton.addEventListener('click', () => resetEditState());
    }

    if (refreshRequests && canAjax) {
      refreshRequests.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          await Promise.all([loadSnapshot(), loadCalendar()]);
        } catch (error) {
          showFeedback(error.message, 'error');
        } finally {
          button.disabled = false;
        }
      });
    }

    form.addEventListener('submit', async (event) => {
      clearFeedback();
      syncSelectedFields();
      const invalid = validationError();
      if (invalid) {
        event.preventDefault();
        showFeedback(invalid.message, 'error');
        if (invalid.field && typeof invalid.field.focus === 'function') invalid.field.focus();
        return;
      }

      if (!cfg.ajaxUrl || !cfg.ajaxNonce) return;
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.textContent = i18n.loading;
      }
      try {
        const body = new FormData(form);
        const wasEditing = Boolean(editContext);
        // Use the established submit action for both creation and pending edits.
        // The server switches to update mode whenever request_id is present.
        const request = await ajax('elm_submit_request', {}, { body, submit: true });
        const conflicts = request.pending_conflict_dates || [];
        form.reset();
        syncFileInput(fileInput);
        editContext = null;
        if (requestCard) requestCard.classList.remove('is-editing');
        if (formActionInput) formActionInput.value = 'elm_submit_request_form';
        if (editRequestIdInput) editRequestIdInput.value = '';
        if (cancelEditButton) cancelEditButton.hidden = true;
        if (requestFormEyebrow) requestFormEyebrow.textContent = i18n.newRequest;
        if (requestFormTitle) requestFormTitle.textContent = i18n.requestLeave;
        if (medical) medical.hidden = true;
        selectedDates.clear();
        rangeAnchor = null;
        await Promise.all([loadSnapshot(), loadCalendar()]);
        const saved = (wasEditing ? i18n.updated : i18n.saved).replace('%d', request.id);
        const warning = conflicts.length ? ` ${i18n.pendingWarning.replace('%s', conflicts.join(', '))}` : '';
        const policyWarning = '';
        showFeedback(`${saved}${warning}${policyWarning}`);
        const historyCard = root.querySelector('.elm-history-card');
        if (historyCard && typeof historyCard.scrollIntoView === 'function') historyCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        showFeedback(error.message, 'error');
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.textContent = editContext ? i18n.saveChanges : i18n.submitRequest;
        }
      }
    });

    updateAllowanceText();
    renderCalendars();
    loadCalendar().catch((error) => {
      renderCalendars();
      showFeedback(error.message, 'error');
    });
  };

  const boot = () => document.querySelectorAll('.elm-portal').forEach(bootPortal);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
