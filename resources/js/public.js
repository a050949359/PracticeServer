import PublicApp from './apps/public/PublicApp.vue';
import { publicRouter } from './apps/public/router';
import { SPA_APP_SELECTOR, createSpaApp, hasSpaMountTarget } from './createSpaApp';

async function bootstrap() {
    if (!hasSpaMountTarget()) {
        return;
    }

    const app = createSpaApp(PublicApp);

    app.use(publicRouter);

    await publicRouter.isReady();

    app.mount(SPA_APP_SELECTOR);
}

bootstrap();
