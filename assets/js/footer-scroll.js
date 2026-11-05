const footerLinks = document.querySelectorAll('.site-footer a[href*="#"]');
const homePath = new URL('index.php', window.location.href).pathname;
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const scrollToSection = (section) => {
    if (!section) {
        return;
    }

    section.scrollIntoView({
        behavior: reduceMotion ? 'auto' : 'smooth',
        block: 'center',
    });
};

footerLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
        const destination = new URL(link.href);
        const sectionId = destination.hash.slice(1);

        if (destination.pathname !== homePath || !sectionId) {
            return;
        }

        event.preventDefault();

        if (window.location.pathname === homePath) {
            scrollToSection(document.getElementById(sectionId));
            return;
        }

        destination.hash = '';
        destination.searchParams.set('scroll_to', sectionId);
        window.location.assign(destination);
    });
});

if (window.location.pathname === homePath) {
    const sectionId = new URLSearchParams(window.location.search).get('scroll_to');
    if (sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            window.history.scrollRestoration = 'manual';
            window.scrollTo({ top: 0, behavior: 'auto' });

            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('scroll_to');
            cleanUrl.hash = '';
            window.history.replaceState(null, '', cleanUrl);
            requestAnimationFrame(() => requestAnimationFrame(() => scrollToSection(section)));
        }
    }
}
