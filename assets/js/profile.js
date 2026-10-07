/* StudentFlow - Profile page */
(function () {
  'use strict';
  const SF = window.SF;

  function showError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('hidden', !msg);
  }

  function applyUser(u) {
    const nameEls = document.querySelectorAll('[data-user-name]');
    nameEls.forEach(function (el) { el.textContent = u.name; });
    const email = document.querySelector('[data-user-email]');
    if (email) email.textContent = u.email;

    // chips
    const chipMap = { college: '[data-user-college]', department: '[data-user-department]', year: '[data-user-year]' };
    Object.keys(chipMap).forEach(function (key) {
      const chip = document.querySelector(chipMap[key]);
      if (!chip) return;
      if (u[key]) {
        chip.textContent = u[key];
        chip.classList.remove('hidden');
      } else {
        chip.classList.add('hidden');
      }
    });

    // avatar
    const img = document.getElementById('avatarPreview');
    const text = document.getElementById('avatarPreviewText');
    if (u.profile_image && img) {
      img.src = 'assets/images/uploads/' + u.profile_image + '?t=' + Date.now();
      img.classList.remove('hidden');
      if (text) text.classList.add('hidden');
    }
  }

  /* ------------- profile form ------------- */
  const profileForm = document.getElementById('profileForm');
  if (profileForm) {
    profileForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showError('pfError', '');
      const fd = new FormData(profileForm);
      fd.append('action', 'profile');
      SF.postForm('actions/profile_actions.php', fd, true).then(function (json) {
        if (!json.ok) { showError('pfError', json.message || 'Could not save profile.'); return; }
        if (json.data && json.data.user) applyUser(json.data.user);
        SF.toast(json.message, 'success');
      });
    });
  }

  /* ------------- avatar upload ------------- */
  const avatarInput = document.getElementById('avatarInput');
  if (avatarInput) {
    avatarInput.addEventListener('change', function () {
      const file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      showError('pfError', '');

      // instant local preview
      const img = document.getElementById('avatarPreview');
      const text = document.getElementById('avatarPreviewText');
      if (img) {
        img.src = URL.createObjectURL(file);
        img.classList.remove('hidden');
        if (text) text.classList.add('hidden');
      }

      const fd = new FormData(profileForm);
      fd.append('action', 'profile');
      fd.set('profile_image', file);
      SF.postForm('actions/profile_actions.php', fd, true).then(function (json) {
        if (!json.ok) { showError('pfError', json.message || 'Upload failed.'); return; }
        if (json.data && json.data.user) applyUser(json.data.user);
        SF.toast('Profile photo updated.', 'success');
      });
    });
  }

  /* ------------- password form ------------- */
  const passwordForm = document.getElementById('passwordForm');
  if (passwordForm) {
    passwordForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showError('pwError', '');
      const fd = new FormData(passwordForm);
      fd.append('action', 'password');
      SF.postForm('actions/profile_actions.php', fd, true).then(function (json) {
        if (!json.ok) { showError('pwError', json.message || 'Could not change password.'); return; }
        passwordForm.reset();
        SF.toast(json.message, 'success');
      });
    });
  }
})();
