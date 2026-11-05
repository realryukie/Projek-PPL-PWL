const revealElements = document.querySelectorAll('.home-page .scroll-reveal');
const homePage = document.querySelector('.home-page');

if (!homePage || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
    revealElements.forEach((element) => element.classList.add('is-visible'));
} else {
    homePage.classList.add('reveal-ready');

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.15,
        rootMargin: '0px 0px -32px 0px',
    });

    revealElements.forEach((element) => revealObserver.observe(element));
}
