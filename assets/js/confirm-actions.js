const confirmScript = document.currentScript;
if (confirmScript && confirmScript.src) {
    const confirmStyles = document.createElement('link');
    confirmStyles.rel = 'stylesheet';
    confirmStyles.href = new URL('../css/confirm-dialog.css', confirmScript.src).href;
    document.head.append(confirmStyles);
}

const confirmationDialog = document.createElement('dialog');
confirmationDialog.className = 'confirm-dialog';
confirmationDialog.setAttribute('aria-labelledby', 'confirm-dialog-title');
confirmationDialog.setAttribute('aria-describedby', 'confirm-dialog-message');

const dialogTitle = document.createElement('h2');
dialogTitle.className = 'confirm-dialog__title';
dialogTitle.id = 'confirm-dialog-title';

const dialogMessage = document.createElement('p');
dialogMessage.className = 'confirm-dialog__message';
dialogMessage.id = 'confirm-dialog-message';

const rememberLabel = document.createElement('label');
rememberLabel.className = 'confirm-dialog__remember';

const rememberCheckbox = document.createElement('input');
rememberCheckbox.type = 'checkbox';
rememberLabel.append(rememberCheckbox, document.createTextNode('Jangan tampilkan lagi untuk tindakan ini'));

const dialogActions = document.createElement('div');
dialogActions.className = 'confirm-dialog__actions';

const cancelButton = document.createElement('button');
cancelButton.type = 'button';
cancelButton.className = 'confirm-dialog__cancel';
cancelButton.textContent = 'Batal';

const confirmButton = document.createElement('button');
confirmButton.type = 'button';
confirmButton.className = 'confirm-dialog__confirm';

dialogActions.append(cancelButton, confirmButton);
confirmationDialog.append(dialogTitle, dialogMessage, rememberLabel, dialogActions);
document.body.append(confirmationDialog);

let pendingConfirmation = null;
const bypassNextSubmit = new WeakSet();

function isValidConfirmationType(type) {
    return type === 'logout' || type === 'delete';
}

function shouldSkipConfirmation(type) {
    try {
        return window.localStorage.getItem('kostku:skip-confirmation:' + type) === '1';
    } catch (error) {
        console.error('Could not read the confirmation preference.', error);
        return false;
    }
}

function rememberConfirmationPreference(type) {
    try {
        window.localStorage.setItem('kostku:skip-confirmation:' + type, '1');
        return true;
    } catch (error) {
        console.error('Could not save the confirmation preference.', error);
        return false;
    }
}

function runConfirmedAction(type, message, action) {
    if (!isValidConfirmationType(type)) {
        return;
    }
    if (shouldSkipConfirmation(type)) {
        action();
        return;
    }
    if (confirmationDialog.open) {
        return;
    }

    pendingConfirmation = { type, action };
    confirmationDialog.returnValue = 'cancel';
    dialogTitle.textContent = type === 'logout' ? 'Keluar dari akun?' : 'Konfirmasi penghapusan';
    dialogMessage.textContent = message;
    confirmButton.textContent = type === 'logout' ? 'Ya, log out' : 'Ya, hapus';
    rememberCheckbox.checked = false;
    confirmationDialog.showModal();
    cancelButton.focus();
}

function getFormConfirmation(form) {
    const type = form.dataset.confirmAction;
    const message = form.dataset.confirmMessage;
    if (isValidConfirmationType(type) && message) {
        return { type, message };
    }

    const selectedImages = form.querySelectorAll('input[name="delete_images[]"]:checked');
    if (selectedImages.length > 0) {
        return {
            type: 'delete',
            message: `Hapus ${selectedImages.length} foto galeri yang dipilih?`,
        };
    }

    return null;
}

cancelButton.addEventListener('click', () => confirmationDialog.close('cancel'));
confirmButton.addEventListener('click', () => confirmationDialog.close('confirm'));

confirmationDialog.addEventListener('click', (event) => {
    if (event.target === confirmationDialog) {
        confirmationDialog.close('cancel');
    }
});

confirmationDialog.addEventListener('close', () => {
    const pending = pendingConfirmation;
    pendingConfirmation = null;

    if (!pending || confirmationDialog.returnValue !== 'confirm') {
        return;
    }

    if (rememberCheckbox.checked && !rememberConfirmationPreference(pending.type)) {
        window.alert('Pilihan ini tidak dapat disimpan. Konfirmasi akan tetap muncul berikutnya.');
    }
    pending.action();
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (bypassNextSubmit.has(form)) {
        bypassNextSubmit.delete(form);
        return;
    }

    const confirmation = getFormConfirmation(form);
    if (!confirmation) {
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    runConfirmedAction(confirmation.type, confirmation.message, () => {
        if (typeof form.requestSubmit !== 'function') {
            console.error('This browser does not support confirmed form submission.');
            window.alert('Form tidak dapat dikirim oleh browser ini. Perbarui browser lalu coba lagi.');
            return;
        }

        bypassNextSubmit.add(form);
        try {
            if (submitter && submitter.form === form) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
        } finally {
            bypassNextSubmit.delete(form);
        }
    });
}, true);

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('a[data-confirm-action]');
    if (!link || (link.target && link.target !== '_self')) {
        return;
    }

    const type = link.dataset.confirmAction;
    const message = link.dataset.confirmMessage;
    if (!isValidConfirmationType(type) || !message) {
        return;
    }

    event.preventDefault();
    runConfirmedAction(type, message, () => window.location.assign(link.href));
});
