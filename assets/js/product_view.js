document.querySelectorAll('[data-choose-room]').forEach((button) => {
    button.addEventListener('click', () => {
        const select = document.getElementById('room_id');
        if (!select) {
            return;
        }

        select.value = button.dataset.chooseRoom;
        select.dispatchEvent(new Event('change')); // refresh the estimated total

        const booking = document.getElementById('booking');
        if (booking) {
            booking.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        select.focus({ preventScroll: true });
    });
});

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const slides = gallery.querySelectorAll('.gallery__slide');
    const dots = gallery.querySelectorAll('.gallery__dot');
    let current = 0;

    const show = (index) => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
    };

    const prev = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');
    if (prev) {
        prev.addEventListener('click', () => show(current - 1));
    }
    if (next) {
        next.addEventListener('click', () => show(current + 1));
    }
    dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
});

// A billing month is counted when its matching calendar date is reached;
// any additional started month is charged in full, with a minimum of one.
(() => {
    const roomSelect = document.getElementById('room_id');
    const checkIn = document.getElementById('check_in_date');
    const checkOut = document.getElementById('check_out_date');
    const estimate = document.getElementById('booking-estimate');

    if (!roomSelect || !checkIn || !checkOut || !estimate) {
        return;
    }

    const formatRupiah = (amount) => 'Rp' + amount.toLocaleString('id-ID');

    const countMonths = (from, to) => {
        const [inYear, inMonth, inDay] = from.split('-').map(Number);
        const [outYear, outMonth, outDay] = to.split('-').map(Number);
        let months = (outYear - inYear) * 12 + (outMonth - inMonth);
        if (outDay > inDay) {
            months += 1;
        }
        return Math.max(1, months);
    };

    const update = () => {
        const option = roomSelect.options[roomSelect.selectedIndex];
        const price = option ? Number(option.dataset.price) : 0;

        if (!price || !checkIn.value || !checkOut.value || checkOut.value <= checkIn.value) {
            estimate.textContent = '';
            return;
        }

        const months = countMonths(checkIn.value, checkOut.value);
        estimate.textContent =
            'Estimasi total: ' + formatRupiah(price * months) +
            ' (' + months + ' bulan x ' + formatRupiah(price) + ')';
    };

    [roomSelect, checkIn, checkOut].forEach((field) => field.addEventListener('change', update));
    update();
})();
