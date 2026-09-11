# ROUH — Approved Physical Opening Count

Source: `physical-count-source-2026-09-09.pdf`

The production opening inventory dataset is stored at:

`backend/database/seeders/data/rouh_opening_inventory.csv`

Opening fixed assets are stored separately at:

`backend/database/seeders/data/rouh_opening_assets.csv`

## Approved count summary

- Inventory lines: **119**
- Perfume oil / musk lines: **99**
- Packaging lines: **17**
- Bottle-specific lines: **2**
- Alcohol lines: **1**
- Inventory opening value: **190,828.62 SYP**
- Eligible operational fixed assets: **6**
- Fixed asset opening cost: **8,935.00 SYP**
- Total opening assets before liabilities: **199,763.62 SYP**

The source count contains no opening cash or bank amount. Do not create cash/bank opening balances unless an actual counted balance exists at the accounting start date.

The two 250g empty-bottle lines from the operational-tools section are deliberately treated as packaging inventory, not fixed assets. Only the durable operating tools listed in `rouh_opening_assets.csv` are treated as PPE/fixed assets.

## Production import

1. Run `php artisan migrate`.
2. Run `php artisan rouh:validate-opening-source`.
3. Create/select the intended opening-balance date in Financial Management → Opening Balance.
4. Use the controlled **Import Inventory & Tools** action.
5. Verify the draft totals above.
6. Confirm only after the physical count is agreed with the owner/accounting records.
7. Run `php artisan rouh:accounting-audit` after confirmation.

The opening import is a snapshot; it is **not** a purchase transaction and does not create supplier payable balances. Confirmation creates the official opening inventory movement and the corresponding GL entry.

## Source hashes

- Physical-count PDF: `f41bc7cd2e93c75994467c814e1757cac7bf0d95357034f7bf40683c2063c3d5`
- Opening inventory CSV: `ddb8205632ca0090a274d0642bd3c39faf0e0e08f3d54d768fa8cfdebb466f28`
- Opening fixed-assets CSV: `8324fa6cd96bbdc2c9c53f8bd3753c6e8299b578bfc09dea12c3fe6b4a0d167e`
