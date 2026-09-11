# ROUH — Testing and Go-Live Reset

## Current testing phase

Do **not** run the go-live reset command while testing. Keep the current test database until all functional and accounting scenarios are complete.

Run normal testing against the current local `.env`. Keep regular database backups before destructive tests.

## Final reset before production

1. Finish all testing and export any test evidence you need.
2. Take a full database backup and verify that it can be restored.
3. Run `php artisan rouh:prepare-go-live-reset --force` locally against the final test database.
4. Add `--customers` only when test customer records/reviews/favorites must also be cleared.
5. Verify that products, variants, recipes, materials, chart of accounts, permissions, tax/store configuration remain.
6. Enter the final physical inventory from a new physical count.
7. Enter opening cash/bank/receivables/payables/fixed assets and the approved opening journal dated to the configured accounting start date.
8. Enter opening operating expenses only when they belong to the opening/current period according to the accounting policy; do not backfill test expenses.
9. Run the accounting and inventory control checks and reconcile subledgers to the general ledger.
10. Take the final pre-production backup, then deploy the code and verified data to production.

The reset command is intentionally manual and requires `--force`; it is never executed by migrations or normal deployment.
