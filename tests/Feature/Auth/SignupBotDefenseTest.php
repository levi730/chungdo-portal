<?php

use Anhskohbo\NoCaptcha\Facades\NoCaptcha;
use App\Actions\Fortify\CreateNewUser;
use App\Models\User;

function signup(array $overrides = []): array
{
    return array_merge([
        'firstname' => 'Dana',
        'lastname' => 'Whitfield',
        'email' => 'dana.whitfield.'.uniqid().'@example.com',
        'password' => 'correct-horse-battery-staple-9',
        'password_confirmation' => 'correct-horse-battery-staple-9',
        'g-recaptcha-response' => 'token',
    ], $overrides);
}

beforeEach(function () {
    NoCaptcha::shouldReceive('verifyResponse')->andReturn(true);
    NoCaptcha::shouldReceive('renderJs', 'display')->andReturn('');
});

it('recognises the bot surname pattern from the 2026 scan', function (string $first, string $last, bool $bot) {
    expect(CreateNewUser::looksLikeBotName($first, $last))->toBe($bot);
})->with([
    ['Georgegooxy', 'GeorgegooxyVM', true],
    ['mizalexy', 'mizalexyGE', true],
    ['XRumer23grier', 'XRumer23grierYM', true],
    ['Malsaismorkыф', 'MalsaismorkыфYO', true],
    ['Samantha', 'Humphreys', false],
    ['Kwang Woo', 'Ko', false],
    ['Gina', 'Luerding-Looten', false],
    ['Ann', 'Annis', false],          // surname starting with the first name, lowercase rest
    ['Lee', 'LeeMD', true],           // the rule's known edge; no real member is spelled this way
    ['', 'VM', false],
]);

it('rejects a sign-up whose last name is the first name plus capitals', function () {
    $data = signup(['firstname' => 'Georgegooxy', 'lastname' => 'GeorgegooxyVM']);

    $this->post(route('register'), $data)->assertSessionHasErrors('lastname');

    expect(User::where('email', $data['email'])->exists())->toBeFalse();
});

it('rejects a sign-up that fills in the honeypot', function () {
    $data = signup(['website' => 'http://spam.example']);

    $this->post(route('register'), $data)->assertSessionHasErrors('website');

    expect(User::where('email', $data['email'])->exists())->toBeFalse();
});

it('still lets a real person sign up', function () {
    $data = signup(['website' => '']);

    $this->post(route('register'), $data)->assertSessionHasNoErrors();

    expect(User::where('email', $data['email'])->exists())->toBeTrue();
});

it('renders the honeypot off-screen on the sign-up form', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="website"', false)
        ->assertSee('left: -10000px', false);
});
