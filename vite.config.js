import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

function redirectToSlash(req, res, next) {
    if (req.url === '/beta') {
        res.writeHead(301, { Location: '/beta/' });
        res.end();

        return;
    }

    next();
}

const betaTrailingSlash = {
    name: 'beta-trailing-slash',
    configureServer(server) {
        server.middlewares.use(redirectToSlash);
    },
    configurePreviewServer(server) {
        server.middlewares.use(redirectToSlash);
    },
};

export default defineConfig({
    plugins: [vue(), betaTrailingSlash],
    build: {
        rollupOptions: {
            input: {
                home: fileURLToPath(new URL('./index.html', import.meta.url)),
                beta: fileURLToPath(new URL('./beta/index.html', import.meta.url)),
            },
        },
    },
    server: {
        allowedHosts: ['node'],
    },
    preview: {
        allowedHosts: ['node'],
    },
});
