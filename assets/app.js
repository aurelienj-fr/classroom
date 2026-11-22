/*
 * monStudio front entry point (AssetMapper).
 * Bootstrap's JS bundle is loaded separately in base.html.twig.
 */
import './styles/app.css';

// Confirmation prompt for destructive actions: <form data-confirm="Message">.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});
