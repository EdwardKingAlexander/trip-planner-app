$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force --path=database/migrations/2026_05_03_000000_backfill_imports_migration_record.php
$FORGE_PHP artisan migrate --force

npm ci || npm install
npm run build

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
