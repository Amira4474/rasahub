// Behaviour for the public website. The admin panel ships its own scripts.

document.documentElement.classList.add('js');

// Fade sections in as they scroll into view.
const revealed = document.querySelectorAll('[data-reveal]');

if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -10% 0px' },
    );

    revealed.forEach((element) => observer.observe(element));
} else {
    revealed.forEach((element) => element.classList.add('is-visible'));
}

// Pricing: switch every price between monthly and yearly billing.
const billingButtons = document.querySelectorAll('[data-billing]');

billingButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const billing = button.dataset.billing;

        billingButtons.forEach((other) => {
            other.setAttribute('aria-pressed', String(other === button));
        });

        document.querySelectorAll('[data-price]').forEach((price) => {
            price.textContent = price.dataset[billing];
        });

        document.querySelectorAll('[data-billing-note]').forEach((note) => {
            note.hidden = note.dataset.billingNote !== billing;
        });
    });
});

// Close the mobile menu after following one of its links.
document.querySelectorAll('[data-mobile-menu] a').forEach((link) => {
    link.addEventListener('click', () => {
        link.closest('details')?.removeAttribute('open');
    });
});
