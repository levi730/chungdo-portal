<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coordinator
    |--------------------------------------------------------------------------
    |
    | The association's coordinator: the top of the organisation chart, and
    | deliberately not the top of the technical one. What the position may do is
    | the `coordinator` role's permission list in PermissionSeeder; WHO holds it
    | is this setting, and nothing else.
    |
    | It is a user id rather than an email so that changing his email address,
    | or his name, does not silently detach the position. The trade is that the
    | value says nothing about who it is — keep the comment in .env current.
    |
    | Deliberately NOT a role assignment. There is no row in model_has_roles for
    | the coordinator, so there is no state to drift and no path to grant it:
    | not the admin user form, not a committee, not anything short of editing
    | this value and deploying. RoleAssignment refuses to offer it either way.
    |
    | Unset or unmatched means nobody holds the position — it fails closed, and
    | App\Services\Coordinator logs a warning when the id matches no user.
    |
    */

    'coordinator_user_id' => env('COORDINATOR_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Coordinator abilities
    |--------------------------------------------------------------------------
    |
    | What the position may do. This lives here rather than on the `coordinator`
    | role's grants for the same reason the holder does: a Spatie role with real
    | permissions is grantable by anyone who can write to model_has_roles, and
    | Spatie's own gate hook would honour it without ever consulting this file.
    | The role is therefore kept deliberately EMPTY (see PermissionSeeder), and
    | this array is the only thing that confers anything.
    |
    | It is an explicit allowlist, not "everything but the technical bits":
    | super.admin's Gate::before bypass is what hands out future abilities
    | automatically, and the coordinator deliberately does not get that. Add a
    | new permission here consciously, or the coordinator will not have it.
    |
    */

    'coordinator_permissions' => [
        'event.viewAllSchoolRegistrants',
        'event.reorganizeDivisions',
        'event.manageAddons',
        'event.approveRefunds',
        'event.manage',
        'store.manage',
        'school.manage',
        'users.manage',
    ],

];
