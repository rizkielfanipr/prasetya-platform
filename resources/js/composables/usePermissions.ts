import type { Permission, SharedProps } from '@/types/backoffice';
import { usePage } from '@inertiajs/vue3';

/** For deciding what to show. It grants nothing: Laravel policies enforce every action again. */
export function usePermissions() {
    const page = usePage<SharedProps>();

    return { can: (permission: Permission): boolean => page.props.auth.permissions.includes(permission) };
}
