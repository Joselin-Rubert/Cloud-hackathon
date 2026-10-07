/* StudentFlow - Settings page */
(function () {
  'use strict';
  const SF = window.SF;
  const form = document.getElementById('settingsForm');
  if (!form) return;

  const darkToggle = document.getElementById('setDark');

  // Apply dark mode instantly when the switch is flipped
  if (darkToggle) {
    darkToggle.addEventListener('change', function () {
      SF.applyTheme(darkToggle.checked);
      SF.post('actions/settings_actions.php', { dark_mode: darkToggle.checked ? 1 : 0 }, true).then(function (json) {
        if (json.ok) {
          try { localStorage.removeItem('sf_theme'); } catch (e) {}
        }
      });
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const payload = {
      dark_mode: darkToggle && darkToggle.checked ? 1 : 0,
      daily_study_target: document.getElementById('setTarget').value,
      monthly_budget: document.getElementById('setBudget').value,
      task_reminders: document.getElementById('setTaskRem').checked ? 1 : 0,
      habit_reminders: document.getElementById('setHabitRem').checked ? 1 : 0,
      email_notifications: document.getElementById('setEmail').checked ? 1 : 0,
    };
    SF.post('actions/settings_actions.php', payload).then(function (json) {
      if (!json.ok) return;
      SF.toast(json.message, 'success');
    });
  });
})();
