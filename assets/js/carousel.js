// Hero carousel on the Home page: auto-advances and can be changed with the dots.
document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = carousel.querySelectorAll('.hero__slide');
    const dots = carousel.querySelectorAll('.hero__dot');

    if (slides.length < 2) {
        return;
    }

    let current = 0;
    let timer = null;

    const show = (index) => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
    };

    const start = () => {
        stop();
        timer = setInterval(() => show(current + 1), 5000);
    };

    const stop = () => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    };

    dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
            show(i);
            start();
        });
    });

    // Do not auto-play for people who asked their system to reduce motion
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        start();
    }
});

document.querySelectorAll('[data-house-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('.house-carousel__track');
    const slides = Array.from(track.children);

    if (slides.length < 2) {
        return;
    }

    slides.forEach((slide) => {
        const clone = slide.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.inert = true;
        track.append(clone);
    });

    let current = 0;

    const update = (animate = true) => {
        track.style.transition = animate ? '' : 'none';
        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        const step = slides[0].getBoundingClientRect().width + gap;
        track.style.transform = `translateX(-${current * step}px)`;
    };

    const advance = () => {
        current += 1;
        update();

        if (current === slides.length) {
            track.addEventListener('transitionend', () => {
                current = 0;
                update(false);
                requestAnimationFrame(() => {
                    track.style.transition = '';
                });
            }, { once: true });
        }
    };

    window.addEventListener('resize', () => update(false));

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.setInterval(advance, 5000);
    }
});
