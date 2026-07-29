<?php

use App\Models\User;
use Sendportal\Base\Facades\Sendportal;
use Sendportal\Base\Repositories\Subscribers\MySqlSubscriberTenantRepository;


it('keeps users in sync with subscribers', function () {
    //new user
    $user = User::factory()->single_student()->can_login()->create();
    expect($user)->toBeObject();

    $r = new MySqlSubscriberTenantRepository();
    $sub = $r->findBy(Sendportal::currentWorkspaceId(), "email", $user->email);
    expect($sub)->toBeObject();
    $found_id = $sub->id;

    //change email
    $newemail = 'test' . time() . '@test.com';
    $user->email =  $newemail;
    $user->save();

    $sub = $r->findBy(Sendportal::currentWorkspaceId(), "email", $user->email);
    expect($sub)->toBeObject();
    expect($sub->id)->toEqual($found_id);

    //alter mailings
    $user->mailings = false;
    $user->save();
    $sub = $r->findBy(Sendportal::currentWorkspaceId(), "email", $user->email);
    expect($sub)->toBeNull();

    //alter it back
    $user->mailings = true;
    $user->save();
    $sub = $r->findBy(Sendportal::currentWorkspaceId(), "email", $user->email);
    expect($sub)->toBeObject();

    //delete
    $user->delete();
    $sub = $r->findBy(Sendportal::currentWorkspaceId(), "email", $user->email);
    expect($sub)->toBeNull();
});

