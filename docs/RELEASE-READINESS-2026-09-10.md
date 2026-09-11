# ROUH ERP — Release Readiness (2026-09-10)

## Scope
This package is the cleaned testing release after the accounting, inventory, payment-idempotency, order-preparation, and invoice-printing hardening pass.

## Important decisions
- `.env` and `backend/.env` are intentionally retained for local testing, per the project owner’s request.
- The ROUH blending rule is intentionally preserved: residual alcohol in ml is calculated numerically as bottle size in ml minus oil quantity in grams. No density conversion is applied.
- The rule is centralized so recipe, costing, stock validation, and order-item cost logic use the same calculation.

## Main fixes in this release
- Centralized the bottle-minus-oil residual alcohol calculation.
- Fixed custom-blend order-item alcohol costing so it charges only the residual alcohol quantity.
- Fixed inventory weighted-average recalculation when reversing consumption.
- Fixed weighted-average/current-value recalculation for approved consumption quantity corrections.
- Fixed fixed-asset disposal to include opening accumulated depreciation.
- Added payment Idempotency-Key protection for order, purchase, and direct-sale payments.
- Changed purchase-void inventory reversal to use the correction event date and rebuilt the remaining material valuation from the canonical ledger, excluding the voided purchase receipt.
- Separated purchase invoice posting (inventory/AP) from purchase payment posting (AP/cash) so payment records and supplier balances reconcile correctly.
- Prevented voiding a purchase that has recorded cash payments until the supplier refund is separately reconciled.
- Removed the unused legacy `/loyalty/redeem` 410 compatibility route and its controller.
- Removed runtime log output from the release package.
- Reworked admin invoice printing to fetch fresh order data at print time and normalize item fields before rendering.
- Added a controlled `php artisan rouh:prepare-go-live-reset --force` command for the final test-to-production reset. The command is not run during normal testing.

## Verification
- PHP source lint: **0 errors** across the backend PHP tree.
- TypeScript/TSX syntax parsing: **0 parse errors** across 104 source files.
- Business-rule runtime sanity: residual alcohol returns 34 for 50-16 and clamps to 0 when oil exceeds bottle volume.
- Full TypeScript type-check/build was attempted but cannot complete here because the local dependency tree is unavailable and the environment cannot download the locked packages.
- Full Laravel runtime/integration tests cannot complete here because Composer/vendor and a MySQL/SQLite runtime are unavailable.
- ZIP integrity: verified after packaging with `unzip -t`.

## Known verification boundary
The delivery environment used for this packaging pass does not have Composer installed and has no Laravel `vendor/` tree. Therefore a full Laravel runtime/database integration test cannot honestly be claimed from this environment alone. The project should be run locally with the real database and dependencies before final production cutover.

## Go-live sequence
1. Finish functional/accounting tests on the current test database.
2. Take a full database backup and confirm the backup can be restored.
3. Do not reset the database until testing is complete.
4. Run `php artisan rouh:prepare-go-live-reset --force` on the test/prod-prep database.
5. Re-enter the final physical inventory from the final count sheet.
6. Enter opening cash/bank/receivables/payables/fixed assets and the opening journal using the chosen start date.
7. Enter real opening/current-period expenses according to the accounting policy.
8. Run accounting, inventory, receivables, payables, and cash/bank reconciliation checks.
9. Take the final pre-production backup.
10. Deploy the application to production with a production `.env`, `APP_DEBUG=false`, real database credentials, HTTPS, and the production frontend API URL.

## Reset command safety
The go-live reset command clears transactional/test data and monetary/current stock balances, but intentionally keeps master data such as products, variants, recipes, materials, chart of accounts, roles/permissions, and store/tax configuration. Customer master data is kept unless the optional `--customers` flag is explicitly supplied.
