import '@inertiajs/core';
import type { Auth } from './index';
declare module '@inertiajs/core' {
    interface PageProps {
        auth: Auth;
        name: string;
        quote: { message: string; author: string };
    }
}
