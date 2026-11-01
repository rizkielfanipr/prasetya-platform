import { execSync } from 'node:child_process';

export default function globalSetup(): void {
    execSync('php artisan --env=e2e migrate:fresh --seed --force --no-interaction', { stdio: 'inherit' });
    execSync('php artisan --env=e2e cache:clear --no-interaction', { stdio: 'inherit' });
}
