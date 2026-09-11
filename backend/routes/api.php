<?php

use App\Http\Controllers\Admin\BusinessOrdersController;
use App\Http\Controllers\OrderPreparationController;
use App\Http\Controllers\Admin\CreateCouponController;
use App\Http\Controllers\Admin\BulkImportProductsController;
use App\Http\Controllers\Admin\CouponRedemptionsController;
use App\Http\Controllers\Admin\DeleteReviewController;
use App\Http\Controllers\Admin\DeleteCouponController;
use App\Http\Controllers\Admin\DashboardStatsController;
use App\Http\Controllers\Admin\ListCouponsController;
use App\Http\Controllers\Admin\ListReviewsController;
use App\Http\Controllers\Admin\ListUsersController;
use App\Http\Controllers\Admin\ToggleCouponController;
use App\Http\Controllers\Admin\UpdateReviewStatusController;
use App\Http\Controllers\Admin\UpdateOrderStatusController;
use App\Http\Controllers\Admin\RegisterOrderPaymentController;
use App\Http\Controllers\Admin\UpdateUserRoleController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\Admin\CategoryCrudController;
use App\Http\Controllers\Admin\ProductCrudController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\VariantSizeMediaController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckAdminRoleController;
use App\Http\Controllers\CreateOrderController;
use App\Http\Controllers\GetProfileController;
use App\Http\Controllers\GetOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewsController;
use App\Http\Controllers\RedeemCouponController;
use App\Http\Controllers\SubmitQuizController;
use App\Http\Controllers\TrackOrdersController;
use App\Http\Controllers\ValidateCouponController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\StorefrontConfigController;
use App\Http\Controllers\Admin\BundleCrudController;
use App\Http\Controllers\Admin\StoreSettingsController;
use App\Http\Controllers\ManufacturingController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AppConfigController;
use App\Http\Controllers\ConsumptionAdjustmentController;
use App\Http\Controllers\OpeningBalanceController;
use Illuminate\Support\Facades\Route;

Route::post('/submit-quiz', SubmitQuizController::class)->middleware(['auth.token', 'throttle:30,1']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/validate-coupon', ValidateCouponController::class)->middleware('throttle:30,1');
Route::post('/redeem-coupon', RedeemCouponController::class);
Route::post('/orders', CreateOrderController::class)->middleware('throttle:10,1');
Route::get('/orders/{id}', GetOrderController::class);
Route::get('/profiles/{id}', GetProfileController::class)->middleware('auth.token');
Route::post('/track-orders', TrackOrdersController::class)->middleware('throttle:20,1');
Route::get('/app-config', AppConfigController::class);
Route::get('/storefront-config', StorefrontConfigController::class);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ProductReviewsController::class, 'index']);
Route::post('/products/{id}/reviews', [ProductReviewsController::class, 'store'])->middleware(['auth.token', 'throttle:10,1']);
Route::get('/bundles', [BundleController::class, 'index']);
Route::get('/bundles/{id}', [BundleController::class, 'show']);

Route::middleware('auth.token')->group(function () {
	Route::get('/admin/check/{id}', CheckAdminRoleController::class);
	// -- Customer-facing order management (ownership verified server-side) --
	// A customer can list/view/edit/cancel ONLY their own orders. Edit & cancel
	// are allowed only while the order is still pending or confirmed.
	Route::get('/customer/orders', [CustomerOrderController::class, 'index']);
	Route::get('/customer/orders/{id}', [CustomerOrderController::class, 'show']);
	Route::patch('/customer/orders/{id}', [CustomerOrderController::class, 'update']);
	Route::post('/customer/orders/{id}/cancel', [CustomerOrderController::class, 'cancel']);

	// -- DB-backed favorites (wishlist) --
	Route::get('/favorites', [FavoriteController::class, 'index']);
	Route::post('/favorites/{productId}', [FavoriteController::class, 'store']);
	Route::delete('/favorites/{productId}', [FavoriteController::class, 'destroy']);
});

Route::middleware(['auth.token', 'admin.token', 'audit.admin'])->group(function () {
	Route::get('/admin/orders', [BusinessOrdersController::class, 'index'])->middleware('permission:orders.view');
	Route::get('/admin/orders/materials', [BusinessOrdersController::class, 'listMaterials'])->middleware('permission:orders.prepare');
	Route::post('/admin/orders', [BusinessOrdersController::class, 'store'])->middleware('permission:orders.create');
	Route::patch('/admin/orders/{id}', [BusinessOrdersController::class, 'update'])->middleware('permission:orders.edit');
	Route::delete('/admin/orders/{id}', [BusinessOrdersController::class, 'destroy'])->middleware('permission:orders.delete');
	Route::get('/admin/orders/{id}', [BusinessOrdersController::class, 'show'])->middleware('permission:orders.view');
	Route::post('/admin/orders/{id}/accept', [BusinessOrdersController::class, 'accept'])->middleware('permission:orders.prepare');
	Route::get('/admin/orders/{id}/consumption-preview', [BusinessOrdersController::class, 'previewConsumption'])->middleware('permission:orders.prepare');
	Route::post('/admin/orders/{id}/consumption-confirm', [BusinessOrdersController::class, 'confirmConsumption'])->middleware('permission:orders.prepare');
	Route::patch('/admin/orders/{id}/status', UpdateOrderStatusController::class)->middleware('permission:orders.edit');
	Route::post('/admin/orders/{id}/payments', RegisterOrderPaymentController::class)->middleware('permission:orders.edit');
	Route::get('/admin/dashboard', DashboardStatsController::class)->middleware('permission:dashboard.view');
	Route::get('/admin/store-settings', [StoreSettingsController::class, 'index'])->middleware('permission:settings.manage');
	Route::put('/admin/store-settings', [StoreSettingsController::class, 'update'])->middleware('permission:settings.manage');
	Route::get('/admin/bundles', [BundleCrudController::class, 'index'])->middleware('permission:settings.manage');
	Route::post('/admin/bundles', [BundleCrudController::class, 'store'])->middleware('permission:settings.manage');
	Route::patch('/admin/bundles/{id}', [BundleCrudController::class, 'update'])->middleware('permission:settings.manage');
	Route::delete('/admin/bundles/{id}', [BundleCrudController::class, 'destroy'])->middleware('permission:settings.manage');
	Route::get('/admin/coupons', ListCouponsController::class)->middleware('permission:settings.manage');
	Route::post('/admin/coupons', CreateCouponController::class)->middleware('permission:settings.manage');
	Route::delete('/admin/coupons/{id}', DeleteCouponController::class)->middleware('permission:settings.manage');
	Route::patch('/admin/coupons/{id}/toggle', ToggleCouponController::class)->middleware('permission:settings.manage');
	Route::get('/admin/coupons/{id}/redemptions', CouponRedemptionsController::class)->middleware('permission:settings.manage');
	Route::get('/admin/categories', [CategoryCrudController::class, 'index'])->middleware('permission:products.manage');
	Route::post('/admin/categories', [CategoryCrudController::class, 'store'])->middleware('permission:products.manage');
	Route::patch('/admin/categories/{id}', [CategoryCrudController::class, 'update'])->middleware('permission:products.manage');
	Route::delete('/admin/categories/{id}', [CategoryCrudController::class, 'destroy'])->middleware('permission:products.manage');
	Route::get('/admin/products', [ProductCrudController::class, 'index'])->middleware('permission:products.manage');
	Route::post('/admin/products', [ProductCrudController::class, 'store'])->middleware('permission:products.manage');
	Route::patch('/admin/products/{id}', [ProductCrudController::class, 'update'])->middleware('permission:products.manage');
	Route::post('/admin/products/global-size', [ProductCrudController::class, 'addGlobalVariant'])->middleware('permission:products.manage');
	Route::delete('/admin/products/{id}', [ProductCrudController::class, 'destroy'])->middleware('permission:products.manage');
	Route::get('/admin/products/{productId}/variants', [ProductVariantController::class, 'index'])->middleware('permission:products.manage');
	Route::post('/admin/products/{productId}/variants', [ProductVariantController::class, 'store'])->middleware('permission:products.manage');
	Route::put('/admin/variants/{variantId}', [ProductVariantController::class, 'update'])->middleware('permission:products.manage');
	Route::post('/admin/variants/{variantId}/image', [ProductVariantController::class, 'uploadImage'])->middleware('permission:products.manage');
	Route::delete('/admin/variants/{variantId}/image', [ProductVariantController::class, 'deleteImage'])->middleware('permission:products.manage');
	Route::get('/admin/size-media', [VariantSizeMediaController::class, 'index'])->middleware('permission:products.manage');
	Route::post('/admin/size-media', [VariantSizeMediaController::class, 'upsert'])->middleware('permission:products.manage');
	Route::post('/admin/size-media/{sizeKey}/image', [VariantSizeMediaController::class, 'uploadImage'])->middleware('permission:products.manage');
	Route::delete('/admin/size-media/{sizeKey}/image', [VariantSizeMediaController::class, 'deleteImage'])->middleware('permission:products.manage');
	Route::delete('/admin/variants/{variantId}', [ProductVariantController::class, 'destroy'])->middleware('permission:products.manage');
	Route::get('/admin/recipes/materials', [RecipeController::class, 'materials'])->middleware('permission:manufacturing.manage');
	Route::get('/admin/recipes/{recipeId}', [RecipeController::class, 'show'])->middleware('permission:manufacturing.manage');
	Route::post('/admin/recipes', [RecipeController::class, 'store'])->middleware('permission:manufacturing.manage');
	Route::put('/admin/recipes/{recipeId}', [RecipeController::class, 'update'])->middleware('permission:manufacturing.manage');
	Route::post('/admin/recipes/{recipeId}/items', [RecipeController::class, 'addItem'])->middleware('permission:manufacturing.manage');
	Route::put('/admin/recipes/{recipeId}/items/{itemId}', [RecipeController::class, 'updateItem'])->middleware('permission:manufacturing.manage');
	Route::delete('/admin/recipes/{recipeId}/items/{itemId}', [RecipeController::class, 'deleteItem'])->middleware('permission:manufacturing.manage');
	Route::post('/admin/products/bulk-import', BulkImportProductsController::class)->middleware('permission:products.manage');
	Route::middleware('role:admin')->group(function () {
		Route::get('/admin/users', ListUsersController::class)->middleware('permission:users.manage');
		Route::patch('/admin/users/{id}/role', UpdateUserRoleController::class)->middleware('permission:users.manage');
		Route::post('/admin/users', [UserManagementController::class, 'store'])->middleware('permission:users.manage');
		Route::patch('/admin/users/{id}', [UserManagementController::class, 'update'])->middleware('permission:users.manage');
		Route::get('/admin/users/{id}/permissions', [UserManagementController::class, 'permissions'])->middleware('permission:users.manage');
	});
	Route::middleware('role:admin,manager')->group(function () {
		Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
	});
	// Accounting controls
	Route::get('/admin/accounting/periods', [AccountingController::class, 'periods'])->middleware('role:admin,manager');
	Route::post('/admin/accounting/periods', [AccountingController::class, 'storePeriod'])->middleware('role:admin,manager');
	Route::post('/admin/accounting/periods/{id}/close', [AccountingController::class, 'closePeriod'])->middleware('role:admin');
	Route::post('/admin/accounting/periods/{id}/reopen', [AccountingController::class, 'reopenPeriod'])->middleware('role:admin');
	Route::get('/admin/accounting/reconciliations', [AccountingController::class, 'reconciliations'])->middleware('role:admin,manager');
	Route::post('/admin/accounting/reconciliations', [AccountingController::class, 'storeReconciliation'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/reconciliation-options', [AccountingController::class, 'reconciliationOptions'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/statements', [AccountingController::class, 'statements'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/journals', [AccountingController::class, 'journals'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/health', [AccountingController::class, 'controlHealth'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/accounts', [AccountingController::class, 'accounts'])->middleware('role:admin,manager');
	Route::post('/admin/accounting/accounts', [AccountingController::class, 'storeAccount'])->middleware('role:admin,manager');
	Route::get('/admin/accounting/ledger/{accountId}', [AccountingController::class, 'ledger'])->middleware('role:admin,manager');
	Route::post('/admin/accounting/journals', [AccountingController::class, 'storeJournal'])->middleware('role:admin');
	Route::post('/admin/accounting/journals/{id}/reverse', [AccountingController::class, 'reverseJournal'])->middleware('role:admin');

	Route::get('/admin/reviews', ListReviewsController::class)->middleware('permission:products.manage');
	Route::patch('/admin/reviews/{id}', UpdateReviewStatusController::class)->middleware('permission:products.manage');
	Route::delete('/admin/reviews/{id}', DeleteReviewController::class)->middleware('permission:products.manage');
	
	// Inventory Management Routes
	Route::get('/admin/inventory/materials', [InventoryController::class, 'getMaterials'])->middleware('permission:inventory.view');
	Route::get('/admin/inventory/materials/category/{category}', [InventoryController::class, 'getMaterialsByCategory'])->middleware('permission:inventory.view');
	Route::post('/admin/inventory/materials', [InventoryController::class, 'storeMaterial'])->middleware('permission:inventory.manage');
	Route::patch('/admin/inventory/materials/{id}', [InventoryController::class, 'updateMaterial'])->middleware('permission:inventory.manage');
	Route::delete('/admin/inventory/materials/{id}', [InventoryController::class, 'deleteMaterial'])->middleware('permission:inventory.manage');
	Route::get('/admin/inventory/materials/low-stock', [InventoryController::class, 'getLowStockMaterials'])->middleware('permission:inventory.view');
	Route::get('/admin/inventory/materials/{materialId}/movements', [InventoryController::class, 'getMaterialMovements'])->middleware('permission:inventory.view');
	Route::post('/admin/inventory/valuation-write-down', [InventoryController::class, 'writeDownInventoryToNrv'])->middleware('role:admin,manager');
	Route::post('/admin/inventory/stock-counts', [InventoryController::class, 'storeStockCount'])->middleware('permission:inventory.count');
	Route::get('/admin/inventory/stock-counts', [InventoryController::class, 'getStockCounts'])->middleware('permission:inventory.view');
	Route::get('/admin/inventory/stock-requests', [InventoryController::class, 'getStockRequests'])->middleware('permission:inventory.request');
	Route::post('/admin/inventory/stock-requests', [InventoryController::class, 'storeStockRequest'])->middleware('permission:inventory.request');
	Route::patch('/admin/inventory/stock-requests/{id}', [InventoryController::class, 'updateStockRequest'])->middleware('role:admin,manager');
	Route::post('/admin/inventory/stock-requests/{id}/receive', [InventoryController::class, 'receiveStockRequest'])->middleware('role:admin,manager');
	
	
	Route::get('/admin/inventory/purchases', [InventoryController::class, 'getPurchases'])->middleware('permission:purchases.view');
	Route::post('/admin/inventory/purchases', [InventoryController::class, 'storePurchase'])->middleware('permission:purchases.manage');
	Route::patch('/admin/inventory/purchases/{id}', [InventoryController::class, 'updatePurchase'])->middleware('permission:purchases.manage');
	Route::delete('/admin/inventory/purchases/{id}', [InventoryController::class, 'deletePurchase'])->middleware('permission:purchases.manage');
	Route::post('/admin/inventory/purchases/{id}/payments', [InventoryController::class, 'recordPurchasePayment'])->middleware('permission:purchases.manage');
	
	Route::get('/admin/inventory/sales', [InventoryController::class, 'getSales'])->middleware('permission:sales.view');
	Route::post('/admin/inventory/sales/{id}/payment', [InventoryController::class, 'recordSalePayment'])->middleware('permission:sales.manage');
	Route::post('/admin/inventory/sales', [InventoryController::class, 'storeSale'])->middleware('permission:sales.manage');
	Route::patch('/admin/inventory/sales/{id}', [InventoryController::class, 'updateSale'])->middleware('permission:sales.manage');
	Route::delete('/admin/inventory/sales/{id}', [InventoryController::class, 'deleteSale'])->middleware('permission:sales.manage');
	
	Route::get('/admin/inventory/expenses', [InventoryController::class, 'getExpenses'])->middleware('permission:finance.view');
	Route::post('/admin/inventory/expenses', [InventoryController::class, 'storeExpense'])->middleware('permission:expenses.create');
	Route::patch('/admin/inventory/expenses/{id}', [InventoryController::class, 'updateExpense'])->middleware('permission:expenses.manage');
	Route::delete('/admin/inventory/expenses/{id}', [InventoryController::class, 'deleteExpense'])->middleware('permission:expenses.manage');
	
	Route::get('/admin/inventory/financial-dashboard', [InventoryController::class, 'getFinancialDashboard'])->middleware('permission:finance.view');
	
	// Fixed Assets Routes
	Route::middleware('role:admin,manager')->group(function () {
	Route::get('/admin/fixed-assets', [InventoryController::class, 'getFixedAssets']);
	Route::post('/admin/fixed-assets', [InventoryController::class, 'storeFixedAsset']);
	Route::patch('/admin/fixed-assets/{id}', [InventoryController::class, 'updateFixedAsset']);
	Route::delete('/admin/fixed-assets/{id}', [InventoryController::class, 'deleteFixedAsset']);
	Route::get('/admin/fixed-assets/depreciation/entries', [InventoryController::class, 'getDepreciationEntries']);
	Route::post('/admin/fixed-assets/depreciation/preview', [InventoryController::class, 'previewDepreciation']);
	Route::post('/admin/fixed-assets/depreciation/post', [InventoryController::class, 'postDepreciation']);
	});
	
	// Tax Configuration Routes
	Route::get('/admin/tax-configurations', [InventoryController::class, 'getTaxConfigurations'])->middleware('role:admin,manager');
	Route::post('/admin/tax-configurations', [InventoryController::class, 'storeTaxConfiguration'])->middleware('role:admin,manager');
	Route::patch('/admin/tax-configurations/{id}', [InventoryController::class, 'updateTaxConfiguration'])->middleware('role:admin,manager');
	Route::delete('/admin/tax-configurations/{id}', [InventoryController::class, 'deleteTaxConfiguration'])->middleware('role:admin,manager');
	
	// Order Preparation Routes (Protected by admin middleware)
	Route::get('/admin/order-preparation/inventory', [OrderPreparationController::class, 'getInventory'])->middleware('permission:inventory.view');
	Route::get('/admin/order-preparation/customers', [OrderPreparationController::class, 'getCustomers'])->middleware('permission:customers.view');
	Route::get('/admin/order-preparation/website-orders', [OrderPreparationController::class, 'getWebsiteOrders'])->middleware('permission:orders.view');
	Route::post('/admin/order-preparation/prepare', [OrderPreparationController::class, 'prepareOrder'])->middleware('permission:orders.prepare');
	Route::get('/admin/order-preparation/orders', [OrderPreparationController::class, 'getOrders'])->middleware('permission:orders.view');
	Route::delete('/admin/order-preparation/orders/{id}', [OrderPreparationController::class, 'deleteOrder'])->middleware('permission:orders.delete');
	
	// Unified Order Preparation Routes (Protected by admin middleware)
	Route::get('/admin/order-preparation/recipe/{orderId}', [OrderPreparationController::class, 'getOrderRecipe'])->middleware('permission:orders.prepare');
	Route::get('/admin/order-preparation/validate-stock/{orderId}', [OrderPreparationController::class, 'validateOrderStock'])->middleware('permission:orders.prepare');
	Route::post('/admin/order-preparation/validate-stock/{orderId}', [OrderPreparationController::class, 'validateOrderStock'])->middleware('permission:orders.prepare');
	Route::post('/admin/order-preparation/prepare-unified', [OrderPreparationController::class, 'prepareOrderUnified'])->middleware('permission:orders.prepare');
	Route::get('/admin/order-preparation/substitutions/{orderId}', [OrderPreparationController::class, 'getSubstitutionLog'])->middleware('permission:orders.prepare');
	Route::get('/admin/order-preparation/materials', [OrderPreparationController::class, 'getAvailableMaterials'])->middleware('permission:orders.prepare');
	Route::post('/admin/order-preparation/recipes/inline', [OrderPreparationController::class, 'createRecipeInline'])->middleware('permission:orders.prepare');
	Route::post('/admin/order-preparation/recipes/update-order-item', [OrderPreparationController::class, 'updateOrderItemRecipe'])->middleware('permission:orders.prepare');
	Route::post('/admin/order-preparation/recipes/add-material', [OrderPreparationController::class, 'addRecipeMaterialInline'])->middleware('permission:orders.prepare');
	Route::get('/admin/consumption-adjustments', [ConsumptionAdjustmentController::class, 'index'])->middleware('permission:orders.consumption.review');
	Route::post('/admin/consumption-adjustments', [ConsumptionAdjustmentController::class, 'store'])->middleware('permission:orders.consumption.adjust');
	Route::post('/admin/consumption-adjustments/{id}/review', [ConsumptionAdjustmentController::class, 'review'])->middleware('permission:orders.consumption.review');
	
	
	
	// Customer Management Routes
	Route::get('/admin/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
	Route::get('/admin/customers/phone/{phone}', [CustomerController::class, 'searchByPhone'])->middleware('permission:customers.view');
	Route::get('/admin/customers/{id}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
	Route::post('/admin/customers', [CustomerController::class, 'store'])->middleware('permission:customers.edit');
	Route::patch('/admin/customers/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.edit');
	Route::patch('/admin/customers/{id}/notes', [CustomerController::class, 'updateNotes'])->middleware('permission:customers.notes');
	Route::delete('/admin/customers/{id}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');
	
	// Opening Balance Management Routes
	Route::get('/admin/opening-balances', [OpeningBalanceController::class, 'index'])->middleware('role:admin,manager');
	Route::post('/admin/opening-balances', [OpeningBalanceController::class, 'store'])->middleware('role:admin,manager');
	Route::get('/admin/opening-balances/{id}', [OpeningBalanceController::class, 'show'])->middleware('role:admin,manager');
	Route::post('/admin/opening-balances/{id}/import-go-live', [OpeningBalanceController::class, 'importGoLiveData'])->middleware('role:admin,manager');
	Route::patch('/admin/opening-balances/{id}', [OpeningBalanceController::class, 'update'])->middleware('role:admin,manager');
	Route::delete('/admin/opening-balances/{id}', [OpeningBalanceController::class, 'destroy'])->middleware('role:admin');
	Route::post('/admin/opening-balances/{id}/confirm', [OpeningBalanceController::class, 'confirm'])->middleware('role:admin');
	Route::post('/admin/opening-balances/{id}/lock', [OpeningBalanceController::class, 'lock'])->middleware('role:admin');
	
	// Opening Balance Inventory Items
	Route::post('/admin/opening-balances/{id}/inventory', [OpeningBalanceController::class, 'addInventoryItem'])->middleware('role:admin,manager');
	Route::patch('/admin/opening-balances/{openingBalanceId}/inventory/{itemId}', [OpeningBalanceController::class, 'updateInventoryItem'])->middleware('role:admin,manager');
	Route::delete('/admin/opening-balances/{openingBalanceId}/inventory/{itemId}', [OpeningBalanceController::class, 'deleteInventoryItem'])->middleware('role:admin,manager');
	
	// Opening Balance Financial Accounts
	Route::post('/admin/opening-balances/{id}/financial-accounts', [OpeningBalanceController::class, 'addFinancialAccount'])->middleware('role:admin,manager');
	Route::patch('/admin/opening-balances/{openingBalanceId}/financial-accounts/{accountId}', [OpeningBalanceController::class, 'updateFinancialAccount'])->middleware('role:admin,manager');
	Route::delete('/admin/opening-balances/{openingBalanceId}/financial-accounts/{accountId}', [OpeningBalanceController::class, 'deleteFinancialAccount'])->middleware('role:admin,manager');
	
	// Opening Balance Payables/Receivables
	Route::post('/admin/opening-balances/{id}/payables-receivables', [OpeningBalanceController::class, 'addPayableReceivable'])->middleware('role:admin,manager');
	Route::patch('/admin/opening-balances/{openingBalanceId}/payables-receivables/{prId}', [OpeningBalanceController::class, 'updatePayableReceivable'])->middleware('role:admin,manager');
	Route::delete('/admin/opening-balances/{openingBalanceId}/payables-receivables/{prId}', [OpeningBalanceController::class, 'deletePayableReceivable'])->middleware('role:admin,manager');
	
	// Opening Balance Adjustments
	Route::post('/admin/opening-balances/{id}/adjustments', [OpeningBalanceController::class, 'requestAdjustment'])->middleware('role:admin,manager');
	Route::post('/admin/opening-balances/{openingBalanceId}/adjustments/{adjustmentId}/approve', [OpeningBalanceController::class, 'approveAdjustment'])->middleware('role:admin,manager');
	Route::post('/admin/opening-balances/{openingBalanceId}/adjustments/{adjustmentId}/reject', [OpeningBalanceController::class, 'rejectAdjustment'])->middleware('role:admin,manager');
	
	// Manufacturing Management Routes
	Route::get('/admin/manufacturing/product-mappings', [ManufacturingController::class, 'getProductMappings'])->middleware('permission:manufacturing.view');
	Route::get('/admin/manufacturing/product-mappings/product/{productId}', [ManufacturingController::class, 'getDefaultMaterial'])->middleware('permission:manufacturing.view');
	Route::post('/admin/manufacturing/product-mappings', [ManufacturingController::class, 'storeProductMapping'])->middleware('permission:manufacturing.manage');
	Route::patch('/admin/manufacturing/product-mappings/{id}', [ManufacturingController::class, 'updateProductMapping'])->middleware('permission:manufacturing.manage');
	Route::delete('/admin/manufacturing/product-mappings/{id}', [ManufacturingController::class, 'deleteProductMapping'])->middleware('permission:manufacturing.manage');
	
	Route::get('/admin/manufacturing/production-orders', [ManufacturingController::class, 'getProductionOrders'])->middleware('permission:manufacturing.view');
	Route::get('/admin/manufacturing/production-orders/{consumptionId}', [ManufacturingController::class, 'getProductionOrder'])->middleware('permission:manufacturing.view');
	Route::post('/admin/manufacturing/production-orders', [ManufacturingController::class, 'createProductionOrder'])->middleware('permission:manufacturing.manage');
	Route::post('/admin/manufacturing/production-orders/{consumptionId}/start', [ManufacturingController::class, 'startProduction'])->middleware('permission:manufacturing.manage');
	Route::post('/admin/manufacturing/production-orders/{consumptionId}/complete', [ManufacturingController::class, 'completeProduction'])->middleware('permission:manufacturing.manage');
	Route::post('/admin/manufacturing/production-orders/{consumptionId}/cancel', [ManufacturingController::class, 'cancelProduction'])->middleware('permission:manufacturing.manage');
	
	Route::get('/admin/manufacturing/materials', [ManufacturingController::class, 'getAvailableMaterials'])->middleware('permission:manufacturing.view');
	Route::get('/admin/manufacturing/materials/category/{category}', [ManufacturingController::class, 'getMaterialsByCategory'])->middleware('permission:manufacturing.view');
});
