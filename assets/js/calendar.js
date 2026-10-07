/* StudentFlow - Calendar page */
(function () {
  'use strict';
  const SF = window.SF;
  const modal = document.getElementById('eventModal');
  if (!modal) return;

  const detail = document.getElementById('eventDetail');
  const form = document.getElementById('eventForm');
  const formError = document.getElementById('eventFormError');
  const btnEdit = document.getElementById('ed_edit');
  const btnDelete = document.getElementById('ed_delete');
  const linkTasks = document.getElementById('ed_open_tasks');
  let currentEventId = null;

  function showDetail() {
    detail.classList.remove('hidden');
    form.classList.add('hidden');
  }

  function showForm() {
    detail.classList.add('hidden');
    form.classList.remove('hidden');
  }

  function showError(msg) {
    if (!formError) return;
    formError.textContent = msg;
    formError.classList.toggle('hidden', !msg);
  }

  function fmtDate(d) {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
  }

  /* ---------- item click -> detail ---------- */
  document.querySelectorAll('[data-cal-item]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const kind = btn.getAttribute('data-kind');
      currentEventId = kind === 'event' ? btn.getAttribute('data-event-id') : null;

      document.getElementById('ed_cat').textContent = btn.getAttribute('data-cat') || kind;
      document.getElementById('ed_title').textContent = btn.getAttribute('data-title') || '';
      document.getElementById('ed_date').textContent = fmtDate(btn.getAttribute('data-date'));
      document.getElementById('ed_time').textContent = btn.getAttribute('data-time') || '—';
      document.getElementById('ed_desc').textContent = btn.getAttribute('data-desc') || 'No description.';

      btnEdit.classList.toggle('hidden', kind !== 'event');
      btnDelete.classList.toggle('hidden', kind !== 'event');
      linkTasks.classList.toggle('hidden', kind !== 'task');

      if (kind === 'exam') linkTasks.classList.add('hidden');

      showDetail();
      SF.openModal('eventModal');
    });
  });

  /* ---------- add ---------- */
  const addBtn = document.getElementById('addEventBtn');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      form.reset();
      document.getElementById('ef_id').value = '';
      document.getElementById('eventFormTitle').textContent = 'Add Event';
      const d = new Date();
      document.getElementById('ef_date').value =
        d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      showError('');
      showForm();
      SF.openModal('eventModal');
      setTimeout(function () { document.getElementById('ef_title').focus(); }, 50);
    });
  }

  /* ---------- edit from detail ---------- */
  if (btnEdit) {
    btnEdit.addEventListener('click', function () {
      const trigger = document.querySelector('[data-cal-item][data-event-id="' + currentEventId + '"]');
      if (!trigger) return;
      document.getElementById('ef_id').value = currentEventId;
      document.getElementById('ef_title').value = trigger.getAttribute('data-title') || '';
      document.getElementById('ef_desc').value = trigger.getAttribute('data-desc') || '';
      document.getElementById('ef_date').value = trigger.getAttribute('data-date') || '';
      document.getElementById('ef_cat').value = trigger.getAttribute('data-cat') || 'Other';
      document.getElementById('ef_start').value = trigger.getAttribute('data-start') || '';
      document.getElementById('ef_end').value = trigger.getAttribute('data-end') || '';
      document.getElementById('eventFormTitle').textContent = 'Edit Event';
      showError('');
      showForm();
    });
  }

  /* ---------- delete from detail ---------- */
  if (btnDelete) {
    btnDelete.addEventListener('click', function () {
      SF.confirm('This event will be removed from your calendar.', 'Delete event').then(function (ok) {
        if (!ok) return;
        SF.post('actions/event_actions.php', { action: 'delete', id: currentEventId }).then(function (json) {
          if (!json.ok) { SF.toast(json.message || 'Delete failed.', 'error'); return; }
          SF.closeModal('eventModal');
          SF.flashAndReload(json.message || 'Event deleted.');
        });
      });
    });
  }

  /* ---------- submit ---------- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const id = document.getElementById('ef_id').value;
    const fd = new FormData(form);
    const payload = {};
    fd.forEach(function (v, k) { payload[k] = v; });
    payload.action = id ? 'update' : 'create';

    SF.post('actions/event_actions.php', payload).then(function (json) {
      if (!json.ok) {
        showError(json.message || 'Please check the form.');
        return;
      }
      SF.closeModal('eventModal');
      SF.flashAndReload(json.message || 'Event saved.');
    });
  });
})();
