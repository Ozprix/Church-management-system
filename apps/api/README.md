# API Service (Laravel)

This directory is managed by the bootstrap script in `tools/scripts/bootstrap-api.sh`. Run the script to generate the Laravel application using the official Sail Composer image:

```bash
./tools/scripts/bootstrap-api.sh
```

After installation, install Sail for local containers:

```bash
cd apps/api
php artisan sail:install --services=mysql,redis,meilisearch,mailpit
./vendor/bin/sail up -d
```

Key follow-up tasks:
- Add tenancy middleware & `TenantScopedModel` base trait.
- Configure Sanctum for SPA authentication.
- Set up module directories under `app/Modules/*`.
