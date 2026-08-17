import '../css/app.css';
import './bootstrap';

// --- Vue / React coexistence -------------------------------------------
// During the redesign we're moving page by page from Vue to React instead
// of rewriting everything at once. Both frameworks are bundled, but only
// one is ever booted per page load: we read Inertia's initial page payload
// straight off the #app element, and if the component name starts with
// "React/" we boot the React app, otherwise we boot the existing Vue app.
//
// This only works for *full* page loads (i.e. a plain <a href="..."> link,
// or typing/refreshing a URL) — an Inertia <Link> stays within whichever
// app is currently mounted and can't hand off to the other framework mid
// SPA-navigation. So: link *within* Vue pages and *within* React pages
// with <Link>/router.visit as usual, but use a plain <a> tag for any link
// that crosses between a Vue page and a React page (see
// resources/js/ReactPages/TestPage.jsx for an example).
const el = document.getElementById('app');
const initialPage = el?.dataset?.page ? JSON.parse(el.dataset.page) : null;
const isReactPage = initialPage?.component?.startsWith('React/');

if (isReactPage) {
    import('./react-entry.jsx');
} else {
    import('./vue-entry.js');
}
