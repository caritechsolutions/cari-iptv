/**
 * CARI-IPTV Client-Side Router
 * History API based routing for the SPA
 */
const CariRouter = (function() {
    const routes = [];
    let currentPath = null;
    let beforeEachHook = null;

    function addRoute(path, handler) {
        // Convert path pattern to regex: /movies/:id → /movies/([^/]+)
        const paramNames = [];
        const pattern = path.replace(/:([a-zA-Z_]+)/g, (_, name) => {
            paramNames.push(name);
            return '([^/]+)';
        });
        routes.push({
            path,
            pattern: new RegExp('^' + pattern + '$'),
            paramNames,
            handler,
        });
    }

    function navigate(path, replace) {
        if (path === currentPath) return;

        if (replace) {
            history.replaceState(null, '', path);
        } else {
            history.pushState(null, '', path);
        }
        handleRoute();
    }

    function handleRoute() {
        const path = window.location.pathname;
        if (path === currentPath) return;
        currentPath = path;

        if (beforeEachHook) {
            const proceed = beforeEachHook(path);
            if (proceed === false) return;
        }

        for (const route of routes) {
            const match = path.match(route.pattern);
            if (match) {
                const params = {};
                route.paramNames.forEach((name, i) => {
                    params[name] = decodeURIComponent(match[i + 1]);
                });
                route.handler(params);
                return;
            }
        }

        // 404 fallback
        const content = document.getElementById('appContent');
        if (content) {
            content.innerHTML = CariUI.emptyState('lucide-map-pin-off', 'Page Not Found', 'The page you are looking for does not exist.');
        }
    }

    function beforeEach(fn) {
        beforeEachHook = fn;
    }

    function start() {
        window.addEventListener('popstate', handleRoute);

        // Intercept link clicks
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('http') || href.startsWith('#') || href.startsWith('/admin') || href.startsWith('/api')) return;

            e.preventDefault();
            navigate(href);
        });

        handleRoute();
    }

    function getCurrentPath() {
        return window.location.pathname;
    }

    function refresh() {
        currentPath = null;
        handleRoute();
    }

    return { addRoute, navigate, beforeEach, start, getCurrentPath, handleRoute, refresh };
})();
