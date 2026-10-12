import type { Auth } from './index';
import '@inertiajs/core';
declare module '@inertiajs/core' {
    interface PageProps {
        auth: Auth;
        name: string;
        quote: { message: string; author: string };
    }
}
