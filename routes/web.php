<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sendportal\Base\Facades\Sendportal;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (Request $request) {
    if (! $request->hasCookie('firstVisitMade')) {
        return redirect('first-visit');
    }

    return redirect('dashboard');
});
Route::get('_health', function(Request $request) {
    return "OK";
});

Route::get('/glide/public/{path}', [\App\Http\Controllers\ImageController::class, 'showpublic'])->where('path', '.*');
Route::get('/glide/{path}', [\App\Http\Controllers\ImageController::class, 'show'])->where('path', '.*');



Route::get('/first-visit', [\App\Http\Controllers\GeneralController::class, 'firstVisit'])->name('first-visit');

// The public school directory — a link that can be handed to anyone, so it sits
// outside the auth group alongside the storefront. Shows live schools only;
// archived ones are excluded by the model's soft deletes.
Route::get('/schools', [\App\Http\Controllers\SchoolController::class, 'publicDirectory'])->name('schools.public');

// The public storefront. These are deliberately OUTSIDE the auth group — the
// store sells to guests as well as members (docs/store-design.md), which makes
// them the only member-facing pages in the portal a signed-out visitor can
// reach. Browsing and the cart are public; who may check out is decided at
// checkout, not here. Nothing below touches money.
Route::get('/store', [\App\Http\Controllers\StoreController::class, 'index'])->name('store.index');
Route::get('/store/cart', [\App\Http\Controllers\StoreController::class, 'cart'])->name('store.cart');
Route::post('/store/cart', [\App\Http\Controllers\StoreController::class, 'addToCart'])->name('store.cart.add');
Route::patch('/store/cart', [\App\Http\Controllers\StoreController::class, 'updateCart'])->name('store.cart.update');
Route::delete('/store/cart/{variant}', [\App\Http\Controllers\StoreController::class, 'removeFromCart'])->name('store.cart.remove');
// Checkout. Also public: members pay on-page with Elements, guests are sent to
// Stripe Hosted Checkout. Both write the order row here first.
Route::get('/store/checkout', [\App\Http\Controllers\StoreCheckoutController::class, 'show'])->name('store.checkout');
Route::post('/store/checkout', [\App\Http\Controllers\StoreCheckoutController::class, 'store'])->name('store.checkout.store');
Route::post('/store/checkout/finalize', [\App\Http\Controllers\StoreCheckoutController::class, 'finalize'])->name('store.checkout.finalize');
Route::get('/store/complete/{reference}', [\App\Http\Controllers\StoreCheckoutController::class, 'complete'])->name('store.complete');

// Last, so /store/cart and /store/checkout aren't swallowed by the slug.
Route::get('/store/{slug}', [\App\Http\Controllers\StoreController::class, 'show'])->name('store.show');

// Project United (donations / t-shirts / hoodies) was retired in 2026. The
// purchase routes are gone; the admin reports below remain because the
// transactions are financial records. See docs/project-united-retirement.md.

Route::middleware(['auth', 'verified'])->group(function () {

    // Starting an impersonation is restricted to super.admins (manage-users).
    // The leave-impersonate route below stays open so an impersonated
    // (possibly non-admin) user can always return to their own account.
    Route::get('/user/{user}/impersonate', [\App\Http\Controllers\UserController::class, 'impersonate'])
        ->middleware('can:manage-users')
        ->name('user.impersonate');

    Route::get('dashboard', [\App\Http\Controllers\GeneralController::class, 'dashboard'])
        ->name('dashboard');

    // User administration (super.admin only)
    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])
            ->name('users.index');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])
            ->name('users.edit');
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])
            ->name('users.update');
        Route::post('/users/{user}/password-reset', [\App\Http\Controllers\Admin\UserController::class, 'sendPasswordReset'])
            ->name('users.password-reset');
        Route::post('/zulip/sync', [\App\Http\Controllers\Admin\UserController::class, 'syncZulip'])
            ->name('zulip.sync');

        Route::get('/committees', [\App\Http\Controllers\Admin\CommitteeController::class, 'index'])
            ->name('committees.index');
        Route::get('/committees/create', [\App\Http\Controllers\Admin\CommitteeController::class, 'create'])
            ->name('committees.create');
        Route::get('/committees/{committee}/edit', [\App\Http\Controllers\Admin\CommitteeController::class, 'edit'])
            ->name('committees.edit');
    });

    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'editProfile'])
        ->name('profile.edit');
    Route::post('/profile/avatar/{id?}', [\App\Http\Controllers\ProfileController::class, 'updateAvatar'])
        ->name('profile.avatar');
    Route::delete('/profile/avatar/{id?}', [\App\Http\Controllers\ProfileController::class, 'deleteAvatar'])
        ->name('profile.deleteavatar');
    Route::delete('/profile/device/{id}', [\App\Http\Controllers\ProfileController::class, 'removeDevice'])
        ->name('profile.deletedevice');
    Route::post('/profile/family/{id}/edit', [\App\Http\Controllers\ProfileController::class, 'updateFamilyMember'])
        ->name('profile-family.update');
    Route::post('/profile/family/add', [\App\Http\Controllers\ProfileController::class, 'createFamilyMember'])
        ->name('profile-family.create');
    Route::delete('/profile/family/{id}', [\App\Http\Controllers\ProfileController::class, 'deleteFamilyMember'])
        ->name('profile-family.delete');
    Route::post('/profile/family/{id}/invite', [\App\Http\Controllers\ProfileController::class, 'inviteFamilyMember'])
        ->name('profile-family.invite');
    Route::post('/profile/link-account', [\App\Http\Controllers\ProfileController::class, 'requestAccountLink'])
        ->name('account-link.request');
    Route::post('/profile/link-account/{id}/accept', [\App\Http\Controllers\ProfileController::class, 'acceptAccountLink'])
        ->name('account-link.accept');
    Route::post('/profile/link-account/{id}/decline', [\App\Http\Controllers\ProfileController::class, 'declineAccountLink'])
        ->name('account-link.decline');
    Route::post('/profile/link-account/{id}/cancel', [\App\Http\Controllers\ProfileController::class, 'cancelAccountLink'])
        ->name('account-link.cancel');

    // Event admin CRUD
    Route::get('/admin/events', [\App\Http\Controllers\EventAdminController::class, 'index'])->name('events.index');
    Route::get('/admin/events/create', [\App\Http\Controllers\EventAdminController::class, 'create'])->name('events.create');
    Route::post('/admin/events', [\App\Http\Controllers\EventAdminController::class, 'store'])->name('events.store');
    Route::get('/admin/events/{event}/edit', [\App\Http\Controllers\EventAdminController::class, 'edit'])->name('events.edit');
    Route::put('/admin/events/{event}', [\App\Http\Controllers\EventAdminController::class, 'update'])->name('events.update');
    Route::delete('/admin/events/{event}', [\App\Http\Controllers\EventAdminController::class, 'destroy'])->name('events.destroy');
    Route::post('/admin/events/{id}/restore', [\App\Http\Controllers\EventAdminController::class, 'restore'])->name('events.restore');
    Route::delete('/admin/events/{event}/media/{media}', [\App\Http\Controllers\EventAdminController::class, 'deleteMedia'])->name('events.media.delete');
    Route::post('/admin/events/{event}/media/{media}/focus', [\App\Http\Controllers\EventAdminController::class, 'setMediaFocus'])->name('events.media.focus');

    // Store admin CRUD (permission enforced in the controller, as with events)
    Route::get('/admin/products', [\App\Http\Controllers\ProductAdminController::class, 'index'])->name('products.index');
    Route::get('/admin/products/create', [\App\Http\Controllers\ProductAdminController::class, 'create'])->name('products.create');
    Route::post('/admin/products', [\App\Http\Controllers\ProductAdminController::class, 'store'])->name('products.store');
    Route::get('/admin/products/{product}/edit', [\App\Http\Controllers\ProductAdminController::class, 'edit'])->name('products.edit');
    Route::put('/admin/products/{product}', [\App\Http\Controllers\ProductAdminController::class, 'update'])->name('products.update');
    Route::delete('/admin/products/{product}', [\App\Http\Controllers\ProductAdminController::class, 'destroy'])->name('products.destroy');
    Route::post('/admin/products/{id}/restore', [\App\Http\Controllers\ProductAdminController::class, 'restore'])->name('products.restore');
    Route::delete('/admin/products/{product}/media/{media}', [\App\Http\Controllers\ProductAdminController::class, 'deleteMedia'])->name('products.media.delete');
    Route::post('/admin/products/{product}/media/{media}/focus', [\App\Http\Controllers\ProductAdminController::class, 'setMediaFocus'])->name('products.media.focus');

    // Print runs, and the variants on sale during each
    Route::get('/admin/products/{product}/runs/create', [\App\Http\Controllers\ProductRunController::class, 'create'])->name('products.runs.create');
    Route::post('/admin/products/{product}/runs', [\App\Http\Controllers\ProductRunController::class, 'store'])->name('products.runs.store');
    Route::get('/admin/products/{product}/runs/{run}/edit', [\App\Http\Controllers\ProductRunController::class, 'edit'])->name('products.runs.edit');
    Route::put('/admin/products/{product}/runs/{run}', [\App\Http\Controllers\ProductRunController::class, 'update'])->name('products.runs.update');
    Route::delete('/admin/products/{product}/runs/{run}', [\App\Http\Controllers\ProductRunController::class, 'destroy'])->name('products.runs.destroy');

    Route::get('/event/latest', [\App\Http\Controllers\EventController::class, 'latest'])->name('event.latest');
    Route::middleware('can:event.reorganizeDivisions')->group(function () {
        Route::get('/event/{slug}/arrange-divisions', [\App\Http\Controllers\EventController::class, 'arrangeDivisions'])->name('event.arrange-divisions');
        Route::post('/event/{slug}/arrange-divisions/auto', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsAuto'])->name('event.arrange-divisions.auto');
        Route::post('/event/{slug}/arrange-divisions/save', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsSave'])->name('event.arrange-divisions.save');
        Route::get('/event/{slug}/arrange-divisions/versions', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsVersions'])->name('event.arrange-divisions.versions');
        Route::get('/event/{slug}/arrange-divisions/versions/{versionId}', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsRestore'])->name('event.arrange-divisions.restore');
        Route::post('/event/{slug}/arrange-divisions/versions/{versionId}/star', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsStar'])->name('event.arrange-divisions.star');
        Route::post('/event/{slug}/arrange-divisions/versions/{versionId}/note', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsNote'])->name('event.arrange-divisions.note');
        Route::post('/event/{slug}/arrange-divisions/versions/{versionId}/publish', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsPublish'])->name('event.arrange-divisions.publish');
        Route::post('/event/{slug}/arrange-divisions/unpublish', [\App\Http\Controllers\EventController::class, 'arrangeDivisionsUnpublish'])->name('event.arrange-divisions.unpublish');
    });
    Route::get('/event/{slug}/print-registrations', [\App\Http\Controllers\EventController::class, 'printRegistrationCards'])->name('event.print-regs');
    Route::get('/event/{slug}/print-division-cards', [\App\Http\Controllers\EventController::class, 'printDivisionCards'])->name('event.print-division-cards');
    Route::get('/event/{slug}/print-tournament-cards', [\App\Http\Controllers\EventController::class, 'printTournamentCards'])->name('event.print-tournament-cards');
    Route::get('/event/{slug}/registrants/by-div', [\App\Http\Controllers\EventController::class, 'viewRegistrantsByDivision'])->name('event.registrants-by-div');
    Route::get('/event/{slug}/registrants/by-nat-div', [\App\Http\Controllers\EventController::class, 'viewRegistrantsByNatDiv'])->name('event.registrants-by-nat-div');
    Route::get('/event/{slug}/registrants/by-tshirt', [\App\Http\Controllers\EventController::class, 'viewRegistrantsByTshirt'])->name('event.registrants-by-tshirt');
    Route::get('/event/{slug}/registrants/by-volunteer', [\App\Http\Controllers\EventController::class, 'viewRegistrantsByVolunteer'])->name('event.registrants-by-volunteer');
    Route::get('/event/{slug}/registrants/by-potluck', [\App\Http\Controllers\EventController::class, 'viewRegistrantsByPotluckItem'])->name('event.registrants-by-potluck');
    Route::get('/event/{slug}/registrants/by-addon/{type}', [\App\Http\Controllers\EventController::class, 'viewRegistrantsByAddon'])->name('event.registrants-by-addon');
    Route::get('/event/{slug}/registrants/spreadsheet', [\App\Http\Controllers\EventController::class, 'downloadRegistrantSpreadsheet'])->name('event.reg-spreadsheet');
    Route::get('/event/{slug}/financials/spreadsheet', [\App\Http\Controllers\EventController::class, 'downloadFinancialsSpreadsheet'])->name('event.financials-spreadsheet');
    Route::get('/event/{slug}/registrants', [\App\Http\Controllers\EventController::class, 'viewRegistrants'])->name('event.registrants');
    Route::get('/event/{slug}/addons', [\App\Http\Controllers\EventController::class, 'manageAddons'])->name('event.addons');
    Route::post('/event/{slug}/addons', [\App\Http\Controllers\EventController::class, 'saveAddons'])->name('event.addons-save');
    Route::get('/event/{slug}/refund-requests', [\App\Http\Controllers\EventController::class, 'refundRequests'])->name('event.refund-requests');
    Route::post('/event/{slug}/refund-requests/{id}/approve', [\App\Http\Controllers\EventController::class, 'approveRefund'])->name('event.refund-approve');
    Route::post('/event/{slug}/refund-requests/{id}/deny', [\App\Http\Controllers\EventController::class, 'denyRefund'])->name('event.refund-deny');
    Route::get('/event/{slug}/register', [\App\Http\Controllers\EventController::class, 'registerForm'])->name('event.register');
    Route::post('/event/{slug}/register', [\App\Http\Controllers\EventController::class, 'registerProcess'])->name('event.register-process');
    Route::post('/event/{slug}/register/finalize', [\App\Http\Controllers\EventController::class, 'registerFinalize'])->name('event.register-finalize');
    Route::post('/event/{slug}/register/adjust', [\App\Http\Controllers\EventController::class, 'registerAdjust'])->name('event.register-adjust');
    Route::post('/event/{slug}/register/adjust/finalize', [\App\Http\Controllers\EventController::class, 'registerAdjustFinalize'])->name('event.register-adjust-finalize');
    Route::get('/event/{slug}/register/complete', [\App\Http\Controllers\EventController::class, 'completeRegForm'])->name('event.complete-reg');
    Route::get('/event/{slug}/download/{id}', [\App\Http\Controllers\EventController::class, 'downloadMedia'])->name('event.download');
    Route::get('/event/{slug}/check-in/{users}', [\App\Http\Controllers\EventController::class, 'eventCheckInDetails'])->name('event.check-in-details');
    Route::get('/event/{slug}/check-in', [\App\Http\Controllers\EventController::class, 'eventCheckIn'])->name('event.check-in');

    Route::get('/event/{slug}/pass/apple', [\App\Http\Controllers\EventController::class, 'appleWalletPass'])->name('event.pass.apple');
    Route::get('/event/{slug}/pass/google', [\App\Http\Controllers\EventController::class, 'googleWalletPass'])->name('event.pass.google');
    Route::get('/event/{slug}/meal-voucher', [\App\Http\Controllers\EventController::class, 'mealVoucher'])->name('event.meal-voucher');

    Route::get('/users', function () {
        return view('users.index');
    });

    Route::post('/user/{user}/add-event-note', [\App\Http\Controllers\UserController::class, 'addUserEventNote']);

    Route::get('/test/{path}', [\App\Http\Controllers\GeneralController::class, 'test'])->where('path', '.*');
    /*Route::get('/test', function() {
        return new App\Mail\Winter2023\TournamentLetter();
    });*/

    Route::get('/school/{id}/event/{slug}', [\App\Http\Controllers\SchoolController::class, 'event'])->name('event.by.school');
    // School CRUD. Editing a school belongs to its own instructors
    // (SchoolPolicy reads school_instructors); creating and archiving need the
    // school.manage permission. Authorization is in the policy, not here.
    Route::get('/school/create', [\App\Http\Controllers\SchoolController::class, 'create'])->name('school.create');
    Route::post('/school', [\App\Http\Controllers\SchoolController::class, 'store'])->name('school.store');
    Route::get('/school/{id}/edit', [\App\Http\Controllers\SchoolController::class, 'edit'])->name('school.edit');
    Route::put('/school/{id}', [\App\Http\Controllers\SchoolController::class, 'update'])->name('school.update');
    Route::delete('/school/{id}', [\App\Http\Controllers\SchoolController::class, 'destroy'])->name('school.destroy');
    Route::post('/school/{id}/restore', [\App\Http\Controllers\SchoolController::class, 'restore'])->name('school.restore');
    Route::delete('/school/{id}/photo', [\App\Http\Controllers\SchoolController::class, 'deletePhoto'])->name('school.photo.delete');
    // Last, so /school/create isn't swallowed by the id.
    Route::get('/school/{id}', [\App\Http\Controllers\SchoolController::class, 'view'])->name('school.view');
    Route::get('/school', [\App\Http\Controllers\SchoolController::class, 'index'])->name('school.index');

    Route::get('/2024-passport-challenge', function () {
        return view('2024-passport-challenge');
    });
    Route::get('/passport', function() {
        return redirect('/2024-passport-challenge');
    });

    Route::get('/user/leave-impersonate', [\App\Http\Controllers\UserController::class, 'leaveImpersonate'])->name('user.leave-impersonate');

    Route::middleware(['auth'])->prefix('sendportal')->group(function () {
        Sendportal::webRoutes();
    });

    Route::get('/project-united/report', [\App\Http\Controllers\ProjectUnitedController::class, 'report'])->name('project-united-report');
    Route::get('/project-united/final-report', [\App\Http\Controllers\ProjectUnitedController::class, 'finalReport'])->name('project-united-final-report');

});

Sendportal::publicWebRoutes();

Route::get('/perm', function () {

    /* $mike = \App\Models\User::find(1);
     $kennedy = \App\Models\User::find(5);
     $rob = \App\Models\User::find(6);
     $tammy = \App\Models\User::find(9);
     $jane = \App\Models\User::find(10);
     $sean = \App\Models\User::find(11);
     $masterb = \App\Models\User::find(38);

     //$saRole = Role::create(['name' => 'super.admin']);
     $mike->assignRole('super.admin');


     dump($kennedy->can('event.viewAllSchoolRegistrants'));

     dump($jane->can('event.viewAllSchoolRegistrants'));

     dump($mike->can('event.viewAllSchoolRegistrants'));
     $eaRole = Role::create(['name' => 'event.admin']);

     $permission = Permission::create(['name' => 'event.viewAllSchoolRegistrants']);
     $eaRole->givePermissionTo('event.viewAllSchoolRegistrants');

     $permission = Permission::create(['name' => 'event.reorganizeDivisions']);
     $eaRole->givePermissionTo('event.reorganizeDivisions');

     $kennedy->assignRole('event.admin');
     $rob->assignRole('event.admin');
     $tammy->assignRole('event.admin');
     $sean->assignRole('event.admin');
     $masterb->assignRole('event.admin');

*/
    return 'OK';
});

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    session()->flash('email-verification', 'Your account has been verified! Please fill out your profile.');

    return redirect('/profile');
})->middleware(['auth', 'signed'])->name('verification.verify');

