import RegisterApp from './apps/common/RegisterApp.vue';
import { SPA_APP_SELECTOR, createSpaApp, hasSpaMountTarget } from './createSpaApp';

function bootstrap() {
    if (!hasSpaMountTarget()) {
        return;
    }

    const app = createSpaApp(RegisterApp);

    app.mount(SPA_APP_SELECTOR);
}

bootstrap();
