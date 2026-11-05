const profileForm = document.getElementById('profile-form');

if (profileForm) {
    const editButton = document.getElementById('edit-profile');
    const editables = profileForm.querySelectorAll('[data-editable]');
    const saveBar = profileForm.querySelector('.profile-save-bar');
    const avatarLabel = profileForm.querySelector('.avatar-edit');
    const cancelButton = profileForm.querySelector('[data-cancel-edit]');

    const setEditing = (editing) => {
        editables.forEach((field) => {
            if (field.tagName === 'SELECT') {
                field.disabled = !editing;
            } else {
                field.readOnly = !editing;
            }
        });

        profileForm.classList.toggle('is-editing', editing);
        if (saveBar) {
            saveBar.hidden = !editing;
        }
        if (avatarLabel) {
            avatarLabel.hidden = !editing;
        }
        if (editButton) {
            editButton.hidden = editing;
        }

        if (editing && editables.length > 0) {
            editables[0].focus();
        }
    };

    if (editButton) {
        editButton.addEventListener('click', () => setEditing(true));
    }

    // Cancel = reload the page, so every field goes back to the saved values
    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            window.location.assign(window.location.pathname + '?tab=account');
        });
    }

    if (profileForm.dataset.startEditing === '1') {
        setEditing(true);
    }

    profileForm.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) {
                return;
            }
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', showing ? 'false' : 'true');
            button.setAttribute('aria-label', showing ? 'Tampilkan sandi' : 'Sembunyikan sandi');
        });
    });

    const photoInput = document.getElementById('profile_photo');
    const avatarPreview = document.getElementById('avatar-preview');

    if (photoInput && avatarPreview) {
        photoInput.addEventListener('change', () => {
            const file = photoInput.files[0];
            if (!file) {
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran foto maksimal 5 MB.');
                photoInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (event) => {
                avatarPreview.style.backgroundImage = 'url(' + event.target.result + ')';
            };
            reader.readAsDataURL(file);
        });
    }
}
