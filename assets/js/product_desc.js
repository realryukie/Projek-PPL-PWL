// Shows a preview of the chosen photo before the form is saved.
const photoInput = document.getElementById('photo');
const photoPreview = document.getElementById('photo-preview');
const cityInput = document.getElementById('city');

if (cityInput) {
    cityInput.addEventListener('input', () => {
        cityInput.value = cityInput.value.replace(/\S+/gu, (word) => {
            const lowerCaseWord = word.toLocaleLowerCase('id-ID');
            const characters = Array.from(lowerCaseWord);
            characters[0] = characters[0].toLocaleUpperCase('id-ID');
            return characters.join('');
        });
    });
}

if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
        const file = photoInput.files[0];
        if (!file) {
            return;
        }

        // Quick check on the browser side (the server checks again when saving)
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran foto maksimal 5 MB.');
            photoInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            photoPreview.style.backgroundImage = 'url(' + event.target.result + ')';
            photoPreview.classList.add('has-image');
        };
        reader.readAsDataURL(file);
    });
}
