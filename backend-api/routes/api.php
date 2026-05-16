<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\SiteSettingController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\OutletUserController;
use App\Http\Controllers\Api\Outlet\KategoriBahanBakuController;
use App\Http\Controllers\Api\Outlet\SatuanController;
use App\Http\Controllers\Api\Outlet\SupplierController;
use App\Http\Controllers\Api\Outlet\BahanBakuController;
use App\Http\Controllers\Api\Outlet\KategoriMenuController;
use App\Http\Controllers\Api\Outlet\MenuOutletController;
use Illuminate\Support\Facades\Route;

// ── Public order tracking (no auth required) ──────────────────────────────
Route::get('/track/{outletId}/{orderCode}', [\App\Http\Controllers\Api\OrderTrackingController::class, 'show']);

// ── Public membership registration (no auth required) ─────────────────────
Route::get('/public/membership/{outletSlug}',          [\App\Http\Controllers\Api\Public\MembershipController::class, 'show']);
Route::post('/public/membership/{outletSlug}/register', [\App\Http\Controllers\Api\Public\MembershipController::class, 'register']);

// ── Public table-ordering (no auth required, rate-limited) ───────────────
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/public/outlet/{outletSlug}/table/{token}',         [\App\Http\Controllers\Api\Public\TableOrderController::class, 'show']);
    Route::post('/public/outlet/{outletSlug}/table/{token}/order',  [\App\Http\Controllers\Api\Public\TableOrderController::class, 'store']);
    Route::get('/public/outlet/{outletSlug}/order/{orderCode}',     [\App\Http\Controllers\Api\Public\TableOrderController::class, 'status']);
    Route::put('/public/outlet/{outletSlug}/order/{orderCode}',     [\App\Http\Controllers\Api\Public\TableOrderController::class, 'update']);
    // Replace / upload payment proof for a still-pending public order.
    Route::post('/public/outlet/{outletSlug}/order/{orderCode}/proof', [\App\Http\Controllers\Api\Public\TableOrderController::class, 'uploadProof']);

    // Public takeaway-ordering: one URL per outlet, no table token.
    Route::get('/public/outlet/{outletSlug}/takeaway',        [\App\Http\Controllers\Api\Public\TakeawayOrderController::class, 'show']);
    Route::post('/public/outlet/{outletSlug}/takeaway/order', [\App\Http\Controllers\Api\Public\TakeawayOrderController::class, 'store']);
});

// ── Site Settings — public read so frontend can load branding ────────────
Route::get('/site-settings', [SiteSettingController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/site-settings', [SiteSettingController::class, 'update']);
    Route::post('/site-settings/upload', [SiteSettingController::class, 'uploadImage']);
});


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// User menus - accessible by authenticated users
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/menus/user', [MenuController::class, 'userMenus']);
});

// Admin routes - superadmin has access to everything
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // User Management
    Route::apiResource('users', UserController::class);
    
    // Role Management
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
    
    // Permission Management
    Route::apiResource('permissions', PermissionController::class);
    
    // Menu Management
    Route::apiResource('menus', MenuController::class);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Outlet Management
    Route::apiResource('outlets', OutletController::class);
    
    // Outlet Users Management
    Route::get('outlets/{outlet}/users', [OutletUserController::class, 'index']);
    Route::post('outlets/{outlet}/users', [OutletUserController::class, 'store']);
    Route::get('outlets/{outlet}/users/{id}', [OutletUserController::class, 'show']);
    Route::put('outlets/{outlet}/users/{id}', [OutletUserController::class, 'update']);
    Route::delete('outlets/{outlet}/users/{id}', [OutletUserController::class, 'destroy']);
    
    // Roles & Permissions Management
    Route::get('outlets/{outlet}/roles', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'getRoles']);
    Route::post('outlets/{outlet}/roles', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'createRole']);
    Route::put('outlets/{outlet}/roles/{roleId}', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'updateRole']);
    Route::delete('outlets/{outlet}/roles/{roleId}', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'deleteRole']);
    Route::get('outlets/{outlet}/permissions', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'getPermissions']);
    Route::post('outlets/{outlet}/assign-role', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'assignRole']);
    Route::get('outlets/{outlet}/roles/{roleId}/permissions', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'getRolePermissions']);
    Route::put('outlets/{outlet}/roles/{roleId}/permissions', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'assignPermissionsToRole']);
    Route::get('outlets/{outlet}/users/{userId}/permissions', [\App\Http\Controllers\Api\Outlet\RolePermissionController::class, 'getUserPermissions']);
    
    // Bahan Baku Module - Kategori
    Route::get('outlets/{outlet}/kategori-bahan-baku', [KategoriBahanBakuController::class, 'index']);
    Route::post('outlets/{outlet}/kategori-bahan-baku', [KategoriBahanBakuController::class, 'store']);
    Route::get('outlets/{outlet}/kategori-bahan-baku/{id}', [KategoriBahanBakuController::class, 'show']);
    Route::put('outlets/{outlet}/kategori-bahan-baku/{id}', [KategoriBahanBakuController::class, 'update']);
    Route::delete('outlets/{outlet}/kategori-bahan-baku/{id}', [KategoriBahanBakuController::class, 'destroy']);
    
    // Bahan Baku Module - Satuan
    Route::get('outlets/{outlet}/satuan', [SatuanController::class, 'index']);
    Route::post('outlets/{outlet}/satuan', [SatuanController::class, 'store']);
    Route::get('outlets/{outlet}/satuan/{id}', [SatuanController::class, 'show']);
    Route::put('outlets/{outlet}/satuan/{id}', [SatuanController::class, 'update']);
    Route::delete('outlets/{outlet}/satuan/{id}', [SatuanController::class, 'destroy']);
    
    // Bahan Baku Module - Supplier
    Route::get('outlets/{outlet}/supplier', [SupplierController::class, 'index']);
    Route::post('outlets/{outlet}/supplier', [SupplierController::class, 'store']);
    Route::get('outlets/{outlet}/supplier/{id}', [SupplierController::class, 'show']);
    Route::put('outlets/{outlet}/supplier/{id}', [SupplierController::class, 'update']);
    Route::delete('outlets/{outlet}/supplier/{id}', [SupplierController::class, 'destroy']);
    
    // Bahan Baku Module - Bahan Baku
    Route::get('outlets/{outlet}/bahan-baku', [BahanBakuController::class, 'index']);
    Route::post('outlets/{outlet}/bahan-baku', [BahanBakuController::class, 'store']);
    Route::get('outlets/{outlet}/bahan-baku/{id}', [BahanBakuController::class, 'show']);
    Route::put('outlets/{outlet}/bahan-baku/{id}', [BahanBakuController::class, 'update']);
    Route::delete('outlets/{outlet}/bahan-baku/{id}', [BahanBakuController::class, 'destroy']);
    
    // Bahan Baku Stock Operations
    Route::post('outlets/{outlet}/bahan-baku/{id}/add-stock', [BahanBakuController::class, 'addStock']);
    Route::post('outlets/{outlet}/bahan-baku/{id}/reduce-stock', [BahanBakuController::class, 'reduceStock']);
    Route::post('outlets/{outlet}/bahan-baku/{id}/adjust-stock', [BahanBakuController::class, 'adjustStock']);
    Route::get('outlets/{outlet}/bahan-baku/{id}/stock-history', [BahanBakuController::class, 'stockHistory']);

    // Stock Opname
    Route::get('outlets/{outlet}/stock-opname/pic-options', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'picOptions']);
    Route::get('outlets/{outlet}/stock-opname', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'index']);
    Route::post('outlets/{outlet}/stock-opname', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'store']);
    Route::get('outlets/{outlet}/stock-opname/{id}', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'show']);
    Route::put('outlets/{outlet}/stock-opname/{id}', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'update']);
    Route::post('outlets/{outlet}/stock-opname/{id}/submit', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'submit']);
    Route::post('outlets/{outlet}/stock-opname/{id}/approve', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'approve']);
    Route::post('outlets/{outlet}/stock-opname/{id}/reject', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'reject']);
    Route::get('outlets/{outlet}/stock-opname/{id}/report', [\App\Http\Controllers\Api\Outlet\StockOpnameController::class, 'report']);

    // Menu Module - Kategori Menu
    Route::get('outlets/{outlet}/kategori-menu', [KategoriMenuController::class, 'index']);
    Route::post('outlets/{outlet}/kategori-menu', [KategoriMenuController::class, 'store']);
    Route::get('outlets/{outlet}/kategori-menu/{id}', [KategoriMenuController::class, 'show']);
    Route::put('outlets/{outlet}/kategori-menu/{id}', [KategoriMenuController::class, 'update']);
    Route::delete('outlets/{outlet}/kategori-menu/{id}', [KategoriMenuController::class, 'destroy']);

    // Menu Module - Menu
    Route::get('outlets/{outlet}/menu', [MenuOutletController::class, 'index']);
    Route::post('outlets/{outlet}/menu', [MenuOutletController::class, 'store']);
    Route::get('outlets/{outlet}/menu/{id}', [MenuOutletController::class, 'show']);
    Route::get('outlets/{outlet}/menu/{id}/check-availability', [MenuOutletController::class, 'checkAvailability']);
    Route::put('outlets/{outlet}/menu/{id}', [MenuOutletController::class, 'update']);
    Route::delete('outlets/{outlet}/menu/{id}', [MenuOutletController::class, 'destroy']);
    
    // Menu Image Upload
    Route::post('outlets/{outlet}/menu/upload-image', [\App\Http\Controllers\Api\Outlet\MenuImageController::class, 'upload']);
    Route::post('outlets/{outlet}/menu/delete-image', [\App\Http\Controllers\Api\Outlet\MenuImageController::class, 'delete']);

    // Generic Image Upload (logos, etc.)
    Route::post('outlets/{outlet}/upload/image', [\App\Http\Controllers\Api\Outlet\UploadController::class, 'uploadImage']);

    // Tables Management
    Route::get('outlets/{outlet}/tables', [\App\Http\Controllers\Api\Outlet\TableController::class, 'index']);
    Route::post('outlets/{outlet}/tables', [\App\Http\Controllers\Api\Outlet\TableController::class, 'store']);
    Route::get('outlets/{outlet}/tables/{id}', [\App\Http\Controllers\Api\Outlet\TableController::class, 'show']);
    Route::put('outlets/{outlet}/tables/{id}', [\App\Http\Controllers\Api\Outlet\TableController::class, 'update']);
    Route::delete('outlets/{outlet}/tables/{id}', [\App\Http\Controllers\Api\Outlet\TableController::class, 'destroy']);
    Route::post('outlets/{outlet}/tables/{id}/cleanup', [\App\Http\Controllers\Api\Outlet\TableController::class, 'cleanup']);
    Route::post('outlets/{outlet}/tables/{id}/regenerate-token', [\App\Http\Controllers\Api\Outlet\TableController::class, 'regenerateToken']);

    // Public table orders (cashier approval)
    Route::get('outlets/{outlet}/public-orders/pending',       [\App\Http\Controllers\Api\Outlet\OrderController::class, 'pendingPublic']);
    Route::post('outlets/{outlet}/public-orders/{id}/approve', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'approvePublic']);
    Route::post('outlets/{outlet}/public-orders/{id}/reject',  [\App\Http\Controllers\Api\Outlet\OrderController::class, 'rejectPublic']);

    // Payment Methods
    Route::get('outlets/{outlet}/payment-methods', [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'index']);
    Route::post('outlets/{outlet}/payment-methods', [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'store']);
    Route::put('outlets/{outlet}/payment-methods/{id}', [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'update']);
    Route::delete('outlets/{outlet}/payment-methods/{id}', [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'destroy']);
    Route::post('outlets/{outlet}/payment-methods/{id}/qr',   [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'uploadQr']);
    Route::delete('outlets/{outlet}/payment-methods/{id}/qr', [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'deleteQr']);
    Route::get('outlets/{outlet}/bon', [\App\Http\Controllers\Api\Outlet\PaymentMethodController::class, 'getBonList']);
    Route::post('outlets/{outlet}/orders/{id}/settle-bon', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'settleBon']);

    // Promos
    Route::get('outlets/{outlet}/promos', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'index']);
    Route::get('outlets/{outlet}/promos/available', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'available']);
    Route::post('outlets/{outlet}/promos/applicable', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'applicable']);
    Route::post('outlets/{outlet}/promos/validate', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'validate']);
    Route::post('outlets/{outlet}/promos', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'store']);
    Route::get('outlets/{outlet}/promos/{id}', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'show']);
    Route::put('outlets/{outlet}/promos/{id}', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'update']);
    Route::delete('outlets/{outlet}/promos/{id}', [\App\Http\Controllers\Api\Outlet\PromoController::class, 'destroy']);

    // Members
    Route::get('outlets/{outlet}/members', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'index']);
    Route::get('outlets/{outlet}/members/search', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'search']);
    Route::post('outlets/{outlet}/members', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'store']);
    Route::get('outlets/{outlet}/members/{id}', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'show']);
    Route::put('outlets/{outlet}/members/{id}', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'update']);
    Route::delete('outlets/{outlet}/members/{id}', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'destroy']);
    Route::post('outlets/{outlet}/members/{id}/adjust-points', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'adjustPoints']);
    Route::get('outlets/{outlet}/members/{id}/transactions', [\App\Http\Controllers\Api\Outlet\MemberController::class, 'transactions']);

    // Membership Settings
    Route::get('outlets/{outlet}/membership-settings', [\App\Http\Controllers\Api\Outlet\MembershipSettingController::class, 'index']);
    Route::put('outlets/{outlet}/membership-settings', [\App\Http\Controllers\Api\Outlet\MembershipSettingController::class, 'update']);

    // Transaction Settings (PPN, Service Charge, dll)
    Route::get('outlets/{outlet}/transaction-settings',  [\App\Http\Controllers\Api\Outlet\TransactionSettingController::class, 'index']);
    Route::put('outlets/{outlet}/transaction-settings',  [\App\Http\Controllers\Api\Outlet\TransactionSettingController::class, 'update']);

    // Orders/POS
    Route::get('outlets/{outlet}/orders', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'index']);
    Route::post('outlets/{outlet}/orders', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'store']);
    Route::get('outlets/{outlet}/orders/{id}', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'show']);
    Route::put('outlets/{outlet}/orders/{id}', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'update']);
    Route::post('outlets/{outlet}/orders/{id}/payment', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'payment']);
    Route::post('outlets/{outlet}/orders/{id}/cancel', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'cancel']);
    Route::get('outlets/{outlet}/orders/{id}/receipt', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'receipt']);
    Route::get('outlets/{outlet}/orders/{id}/thermal-receipt', [\App\Http\Controllers\Api\Outlet\OrderController::class, 'thermalReceipt']);

    // Stations (KDS)
    Route::get('outlets/{outlet}/stations', [\App\Http\Controllers\Api\Outlet\StationController::class, 'index']);
    Route::post('outlets/{outlet}/stations', [\App\Http\Controllers\Api\Outlet\StationController::class, 'store']);
    Route::put('outlets/{outlet}/stations/{id}', [\App\Http\Controllers\Api\Outlet\StationController::class, 'update']);
    Route::delete('outlets/{outlet}/stations/{id}', [\App\Http\Controllers\Api\Outlet\StationController::class, 'destroy']);
    Route::get('outlets/{outlet}/stations/{id}/orders', [\App\Http\Controllers\Api\Outlet\StationController::class, 'orders']);
    Route::post('outlets/{outlet}/stations/{stationId}/items/{itemId}/start', [\App\Http\Controllers\Api\Outlet\StationController::class, 'startItem']);
    Route::post('outlets/{outlet}/stations/{stationId}/items/{itemId}/confirm', [\App\Http\Controllers\Api\Outlet\StationController::class, 'confirmItem']);
    Route::post('outlets/{outlet}/stations/{stationId}/orders/{orderId}/serve', [\App\Http\Controllers\Api\Outlet\StationController::class, 'serveOrder']);

    // HR Management - Attendance
    Route::get('outlets/{outlet}/attendances', [\App\Http\Controllers\Api\Outlet\AttendanceController::class, 'index']);
    Route::post('outlets/{outlet}/attendances/clock-in', [\App\Http\Controllers\Api\Outlet\AttendanceController::class, 'clockIn']);
    Route::post('outlets/{outlet}/attendances/clock-out', [\App\Http\Controllers\Api\Outlet\AttendanceController::class, 'clockOut']);
    Route::get('outlets/{outlet}/attendances/today/{userId}', [\App\Http\Controllers\Api\Outlet\AttendanceController::class, 'getTodayStatus']);
    Route::get('outlets/{outlet}/attendances/summary', [\App\Http\Controllers\Api\Outlet\AttendanceController::class, 'getSummary']);

    // HR Management - Leave Requests
    Route::get('outlets/{outlet}/leave-requests', [\App\Http\Controllers\Api\Outlet\LeaveRequestController::class, 'index']);
    Route::post('outlets/{outlet}/leave-requests', [\App\Http\Controllers\Api\Outlet\LeaveRequestController::class, 'store']);
    Route::put('outlets/{outlet}/leave-requests/{id}/status', [\App\Http\Controllers\Api\Outlet\LeaveRequestController::class, 'updateStatus']);
    Route::get('outlets/{outlet}/leave-requests/balance/{userId}', [\App\Http\Controllers\Api\Outlet\LeaveRequestController::class, 'getBalance']);
    Route::post('outlets/{outlet}/leave-requests/initialize-balance', [\App\Http\Controllers\Api\Outlet\LeaveRequestController::class, 'initializeBalance']);

    // HR Management - Payroll
    Route::get('outlets/{outlet}/payrolls', [\App\Http\Controllers\Api\Outlet\PayrollController::class, 'index']);
    Route::get('outlets/{outlet}/payrolls/{id}', [\App\Http\Controllers\Api\Outlet\PayrollController::class, 'show']);
    Route::post('outlets/{outlet}/payrolls/generate', [\App\Http\Controllers\Api\Outlet\PayrollController::class, 'generate']);
    Route::put('outlets/{outlet}/payrolls/{id}', [\App\Http\Controllers\Api\Outlet\PayrollController::class, 'update']);
    Route::post('outlets/{outlet}/payrolls/{id}/approve', [\App\Http\Controllers\Api\Outlet\PayrollController::class, 'approve']);
    Route::post('outlets/{outlet}/payrolls/{id}/mark-paid', [\App\Http\Controllers\Api\Outlet\PayrollController::class, 'markAsPaid']);

    // Weather Data
    Route::get('outlets/{outlet}/weather', [\App\Http\Controllers\Api\Outlet\WeatherController::class, 'index']);
    Route::get('outlets/{outlet}/weather/latest', [\App\Http\Controllers\Api\Outlet\WeatherController::class, 'latest']);
    Route::get('outlets/{outlet}/weather/statistics', [\App\Http\Controllers\Api\Outlet\WeatherController::class, 'statistics']);
    Route::get('outlets/{outlet}/weather/sales-correlation', [\App\Http\Controllers\Api\Outlet\WeatherController::class, 'salesCorrelation']);

    // HR Management - Employee Info
    Route::get('outlets/{outlet}/employees', [\App\Http\Controllers\Api\Outlet\EmployeeController::class, 'index']);
    Route::get('outlets/{outlet}/employees/{userId}', [\App\Http\Controllers\Api\Outlet\EmployeeController::class, 'show']);
    Route::put('outlets/{outlet}/employees/{userId}/info', [\App\Http\Controllers\Api\Outlet\EmployeeController::class, 'updateInfo']);
    Route::get('outlets/{outlet}/payroll-settings', [\App\Http\Controllers\Api\Outlet\EmployeeController::class, 'getPayrollSettings']);

    // Employee Beverage Allowance
    Route::get('outlets/{outlet}/employee-beverages/settings', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'getSettings']);
    Route::put('outlets/{outlet}/employee-beverages/settings', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'updateSettings']);
    Route::get('outlets/{outlet}/employee-beverages/allowed', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'getAllowedBeverages']);
    Route::post('outlets/{outlet}/employee-beverages/allowed', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'addAllowedBeverage']);
    Route::delete('outlets/{outlet}/employee-beverages/allowed/{id}', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'removeAllowedBeverage']);
    Route::get('outlets/{outlet}/employee-beverages/claims', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'getEmployeeClaims']);
    Route::post('outlets/{outlet}/employee-beverages/claim', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'claimBeverage']);
    Route::get('outlets/{outlet}/employee-beverages/my-quota', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'getMyQuotaStatus']);
    Route::get('outlets/{outlet}/employee-beverages/quota/{userId}', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'getEmployeeQuotaStatus']);
    Route::get('outlets/{outlet}/employee-beverages/statistics', [\App\Http\Controllers\Api\Outlet\EmployeeBeverageController::class, 'getStatistics']);
    Route::put('outlets/{outlet}/payroll-settings', [\App\Http\Controllers\Api\Outlet\EmployeeController::class, 'updatePayrollSettings']);

    // HR Management - Shift Management
    Route::get('outlets/{outlet}/shifts', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'index']);
    Route::post('outlets/{outlet}/shifts', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'storeShift']);
    // Sub-resource routes MUST come BEFORE the catch-all /shifts/{id} routes,
    // otherwise Laravel will route e.g. "assignments" into the {id} parameter.
    Route::get('outlets/{outlet}/shifts/assignments', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'getAssignments']);
    Route::get('outlets/{outlet}/shifts/calendar', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'getCalendar']);
    Route::post('outlets/{outlet}/shifts/assign', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'assignShift']);
    Route::post('outlets/{outlet}/shifts/bulk-assign', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'bulkAssign']);
    Route::post('outlets/{outlet}/shifts/auto-schedule', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'autoSchedule']);
    Route::get('outlets/{outlet}/shifts/day-off-schedule', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'getDayOffSchedule']);
    Route::post('outlets/{outlet}/shifts/day-offs', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'createDayOff']);
    Route::delete('outlets/{outlet}/shifts/day-offs/{id}', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'deleteDayOff']);
    Route::post('outlets/{outlet}/shifts/copy-day', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'copyDaySchedule']);
    Route::put('outlets/{outlet}/shifts/assignments/{id}', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'updateAssignment']);
    Route::delete('outlets/{outlet}/shifts/assignments/{id}', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'deleteAssignment']);
    // Catch-all numeric-id routes for shift configuration CRUD.
    Route::put('outlets/{outlet}/shifts/{id}', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'updateShift'])->whereNumber('id');
    Route::delete('outlets/{outlet}/shifts/{id}', [\App\Http\Controllers\Api\Outlet\ShiftController::class, 'destroyShift'])->whereNumber('id');

    // Purchase Management
    Route::get('outlets/{outlet}/purchases', [\App\Http\Controllers\Api\Outlet\PurchaseController::class, 'index']);
    Route::post('outlets/{outlet}/purchases', [\App\Http\Controllers\Api\Outlet\PurchaseController::class, 'store']);
    Route::get('outlets/{outlet}/purchases/{id}', [\App\Http\Controllers\Api\Outlet\PurchaseController::class, 'show']);
    Route::delete('outlets/{outlet}/purchases/{id}', [\App\Http\Controllers\Api\Outlet\PurchaseController::class, 'destroy']);

    // Expense Management
    Route::get('outlets/{outlet}/expenses', [\App\Http\Controllers\Api\Outlet\ExpenseController::class, 'index']);
    Route::get('outlets/{outlet}/expenses/categories', [\App\Http\Controllers\Api\Outlet\ExpenseController::class, 'getCategories']);
    Route::post('outlets/{outlet}/expenses', [\App\Http\Controllers\Api\Outlet\ExpenseController::class, 'store']);
    Route::get('outlets/{outlet}/expenses/{id}', [\App\Http\Controllers\Api\Outlet\ExpenseController::class, 'show']);
    Route::put('outlets/{outlet}/expenses/{id}', [\App\Http\Controllers\Api\Outlet\ExpenseController::class, 'update']);
    Route::delete('outlets/{outlet}/expenses/{id}', [\App\Http\Controllers\Api\Outlet\ExpenseController::class, 'destroy']);

    // Kasbon Management
    Route::get('outlets/{outlet}/kasbon', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'index']);
    Route::post('outlets/{outlet}/kasbon', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'store']);
    Route::post('outlets/{outlet}/kasbon/{id}/approve', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'approve']);
    Route::post('outlets/{outlet}/kasbon/{id}/reject', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'reject']);
    Route::post('outlets/{outlet}/kasbon/{id}/mark-paid', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'markAsPaid']);
    Route::get('outlets/{outlet}/kasbon/settings', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'getSettings']);
    Route::put('outlets/{outlet}/kasbon/settings', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'updateSettings']);
    Route::get('outlets/{outlet}/kasbon/user/{userId}/summary', [\App\Http\Controllers\Api\Outlet\KasbonController::class, 'getUserSummary']);

    // Locations (Gudang / Unit Produksi)
    Route::get('outlets/{outlet}/locations', [\App\Http\Controllers\Api\Outlet\LocationController::class, 'index']);
    Route::post('outlets/{outlet}/locations', [\App\Http\Controllers\Api\Outlet\LocationController::class, 'store']);
    Route::put('outlets/{outlet}/locations/{id}', [\App\Http\Controllers\Api\Outlet\LocationController::class, 'update']);
    Route::delete('outlets/{outlet}/locations/{id}', [\App\Http\Controllers\Api\Outlet\LocationController::class, 'destroy']);
    Route::get('outlets/{outlet}/locations/{id}/stock', [\App\Http\Controllers\Api\Outlet\LocationController::class, 'getStock']);

    // Production Units
    Route::get('outlets/{outlet}/production/units', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'indexUnits']);
    Route::post('outlets/{outlet}/production/units', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'storeUnit']);
    Route::put('outlets/{outlet}/production/units/{unitId}', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'updateUnit']);
    Route::delete('outlets/{outlet}/production/units/{unitId}', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'destroyUnit']);

    // Production Orders
    Route::get('outlets/{outlet}/production/orders', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'indexOrders']);
    Route::post('outlets/{outlet}/production/orders', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'storeOrder']);
    Route::put('outlets/{outlet}/production/orders/{orderId}/status', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'updateOrderStatus']);
    Route::post('outlets/{outlet}/production/orders/{orderId}/complete', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'completeOrder']);

    // Production Stock History Report
    Route::get('outlets/{outlet}/production/stock-history', [\App\Http\Controllers\Api\Outlet\ProductionController::class, 'stockHistory']);

    // Stock Movements
    Route::get('outlets/{outlet}/stock-movements', [\App\Http\Controllers\Api\Outlet\StockMovementController::class, 'index']);
    Route::get('outlets/{outlet}/stock-movements/summary', [\App\Http\Controllers\Api\Outlet\StockMovementController::class, 'summary']);
    Route::post('outlets/{outlet}/stock-movements/in', [\App\Http\Controllers\Api\Outlet\StockMovementController::class, 'stockIn']);
    Route::post('outlets/{outlet}/stock-movements/out', [\App\Http\Controllers\Api\Outlet\StockMovementController::class, 'stockOut']);
    Route::post('outlets/{outlet}/stock-movements/transfer', [\App\Http\Controllers\Api\Outlet\StockMovementController::class, 'transfer']);

    // WhatsApp (WAHA) Integration
    Route::get('outlets/{outlet}/whatsapp/status', [\App\Http\Controllers\Api\Outlet\WhatsAppController::class, 'status']);
    Route::get('outlets/{outlet}/whatsapp/settings', [\App\Http\Controllers\Api\Outlet\WhatsAppController::class, 'getSettings']);
    Route::put('outlets/{outlet}/whatsapp/settings', [\App\Http\Controllers\Api\Outlet\WhatsAppController::class, 'updateSettings']);
    Route::post('outlets/{outlet}/whatsapp/test', [\App\Http\Controllers\Api\Outlet\WhatsAppController::class, 'sendTest']);
    Route::get('whatsapp/qr', [\App\Http\Controllers\Api\Outlet\WhatsAppController::class, 'qrCode']);
    Route::post('whatsapp/start-session', [\App\Http\Controllers\Api\Outlet\WhatsAppController::class, 'startSession']);
});
