<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\SavedPaymentOption;
use App\Models\User;
use App\Services\EventPaymentSelection;
use App\Services\EventReviewWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function libraryOption(User $owner, array $attributes = []): SavedPaymentOption
{
    return SavedPaymentOption::create(array_merge(['created_by' => $owner->id, 'is_shared' => $owner->isSuperAdmin(),
        'label' => 'Saved GCash', 'provider' => 'GCash', 'account_name' => 'Synthetic account',
        'account_number' => '09170000000', 'instructions' => 'Use the registration reference.',
        'is_enabled' => true, 'version' => 1], $attributes));
}

function libraryPayload(array $attributes = []): array
{
    return array_merge(['title' => 'Library event', 'description' => 'A complete cycling event', 'venue' => 'Local assembly point',
        'event_date' => today()->addMonth()->toDateString(), 'event_end_date' => today()->addMonth()->toDateString(),
        'start_time' => '06:00', 'end_time' => '12:00', 'registration_deadline' => today()->addWeek()->toDateString(),
        'banner_image' => 'events/synthetic.jpg', 'interest_type' => 'Cycling',
        'type_details' => ['Cycling' => ['route_distance_km' => '50', 'surface_type' => 'Road', 'bike_type' => 'Road Bike', 'helmet_required' => '1']],
        'payment_selection_submitted' => '1', 'payment_choices' => []], $attributes);
}

function libraryEvent(User $manager): Event
{
    $payload = libraryPayload();
    $event = Event::forceCreate([...collect($payload)->except(['payment_selection_submitted', 'payment_choices'])->all(),
        'slug' => 'saved-options-'.uniqid(), 'manager_id' => $manager->id,
        'type_details' => ['route_distance_km' => '50', 'surface_type' => 'Road', 'elevation_gain_m' => null,
            'bike_type' => 'Road Bike', 'helmet_required' => true], 'status' => 'draft', 'review_status' => 'draft']);
    Category::create(['event_id' => $event->id, 'name' => '50K Open', 'distance_km' => 50,
        'price_cents' => 75000, 'price_currency' => 'PHP', 'status' => 'open', 'slot_limit' => 100]);

    return $event;
}

test('payment library visibility separates shared and personal account details', function () {
    $admin = User::factory()->create();
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    libraryOption($admin, ['account_name' => 'SHARED-SENTINEL']);
    libraryOption($manager, ['account_name' => 'MY-SENTINEL']);
    libraryOption($other, ['account_name' => 'PRIVATE-SENTINEL', 'instructions' => 'PRIVATE-ACCOUNT-DETAILS']);

    $this->actingAs($manager)->get(route('admin.payment-options.index'))->assertOk()
        ->assertSee('Payment Records')->assertSee('SHARED-SENTINEL')->assertSee('MY-SENTINEL')
        ->assertDontSee('PRIVATE-SENTINEL')->assertDontSee('PRIVATE-ACCOUNT-DETAILS');
    $this->actingAs($admin)->get(route('admin.payment-options.index'))->assertOk()
        ->assertSee('SHARED-SENTINEL')->assertDontSee('MY-SENTINEL')->assertDontSee('PRIVATE-SENTINEL');
});

test('payment cards keep key details and actions visible while instructions and help start collapsed', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager, ['label' => 'OLD-CUSTOM-LABEL', 'account_name' => 'Synthetic holder',
        'instructions' => "<script>unsafe()</script>\nSecond instruction line"]);
    $before = $option->fresh()->getAttributes();
    $response = $this->actingAs($manager)->get(route('admin.payment-options.index'))->assertOk()
        ->assertSee('Manage payment accounts for your events.')->assertSee('GCash — Synthetic holder')
        ->assertSee('Personal')->assertSee('Enabled')->assertSee('Edit details')->assertSee('Disable')
        ->assertDontSee('OLD-CUSTOM-LABEL')->assertDontSee('Available for new selections')
        ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    $card = '//section[@aria-labelledby="payment-option-title-'.$option->id.'"]';
    expect(trim($xpath->query($card.'//h2')->item(0)->textContent))->toBe('GCash — Synthetic holder');
    expect($xpath->query($card.'//details[@data-payment-instructions and not(@open)]')->length)->toBe(1)
        ->and($xpath->query($card.'//details//p')->item(0)->textContent)->toContain('<script>unsafe()</script>', 'Second instruction line')
        ->and($xpath->query($card.'//p[not(ancestor::details)]')->item(0)->textContent)->toContain('09170000000')
        ->and($xpath->query($card.'//dl | '.$card.'//dt')->length)->toBe(0)
        ->and($xpath->query($card.'//details//form | '.$card.'//details//button')->length)->toBe(0)
        ->and($xpath->query('//details[@data-payment-options-help and not(@open)]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-payment-options-help]')->item(0)->textContent)->toContain('does not change existing event payment details or payment records.');
    expect($option->fresh()->getAttributes())->toBe($before);
});

test('compact cards handle other providers missing instructions and legacy accounts without changing stored data', function (string $provider, bool $enabled) {
    $admin = User::factory()->create();
    $option = libraryOption($admin, ['provider' => $provider, 'is_enabled' => $enabled, 'account_name' => 'Synthetic holder',
        'account_number' => '001234567890123456', 'instructions' => null]);
    $response = $this->actingAs($admin)->get(route('admin.payment-options.index'))->assertOk()
        ->assertSee('Shared')->assertSee($enabled ? 'Enabled' : 'Disabled')
        ->assertSee($enabled ? 'Disable' : 'Enable')->assertDontSee('data-payment-instructions', false);
    if ($provider === 'PayMongo') {
        $response->assertSee('PayMongo Online Checkout')->assertSee('Online checkout')
            ->assertDontSee('Synthetic holder')->assertDontSee('001234567890123456');
    } else {
        $response->assertSee(EventPaymentMethod::providers()[$provider].' — Synthetic holder')
            ->assertSee('001234567890123456');
        $option->update(['account_number' => null]);
        $this->get(route('admin.payment-options.index'))->assertOk()->assertSee('No account number saved.');
        $option->update(['instructions' => 'Synthetic bank details']);
        $this->get(route('admin.payment-options.index'))->assertOk()->assertSee('See instructions for account details.')->assertSee('View instructions');
    }
})->with(['Maya', 'Bank', 'PayMongo'])->with([true, false]);

test('empty payment library keeps its add action and concise guidance', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.payment-options.index'))->assertOk()
        ->assertSee('Manage payment accounts for your events.')->assertSee('Add Payment Option')
        ->assertSee('No saved payment options yet.')->assertDontSee('data-payment-instructions', false);
});

test('payment option creation fixes ownership and sharing from the signed in role', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $other = User::factory()->create();
    $this->actingAs($user)->get(route('admin.payment-options.create'))->assertOk();
    $this->post(route('admin.payment-options.store'), ['label' => 'New option', 'provider' => 'GCash',
        'account_name' => 'Synthetic owner', 'account_number' => '09170000000', 'is_enabled' => '0',
        'is_shared' => ! $user->isSuperAdmin(), 'created_by' => $other->id, 'version' => 99])
        ->assertRedirect(route('admin.payment-options.index'))->assertSessionHasNoErrors();

    $option = SavedPaymentOption::sole();
    expect($option->created_by)->toBe($user->id)->and($option->is_shared)->toBe($user->isSuperAdmin())
        ->and($option->version)->toBe(1)->and($option->is_enabled)->toBeTrue();
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN]);

test('managers cannot edit shared or another managers options and admin cannot edit private options', function () {
    $admin = User::factory()->create();
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $other = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $shared = libraryOption($admin);
    $private = libraryOption($other);
    foreach ([[$manager, $shared], [$manager, $private], [$admin, $private]] as [$actor, $option]) {
        $this->actingAs($actor)->get(route('admin.payment-options.edit', $option))->assertNotFound();
        $this->put(route('admin.payment-options.update', $option), ['account_number' => 'tampered'])->assertNotFound();
        $this->patch(route('admin.payment-options.availability', $option), ['version' => 1, 'is_enabled' => '0'])->assertNotFound();
        expect($option->fresh()->account_number)->toBe('09170000000');
        expect($option->fresh()->is_enabled)->toBeTrue()->and($option->fresh()->version)->toBe(1);
    }
});

test('payment options generate labels without an option name for every provider', function (string $provider, string $label) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($manager)->get(route('admin.payment-options.create'))->assertOk()
        ->assertDontSee('name="label"', false)->assertDontSee('Option name')->assertDontSee('name="is_enabled"', false);
    $this->post(route('admin.payment-options.store'), ['provider' => $provider, 'account_name' => 'Synthetic owner',
        'account_number' => '09171234567', 'instructions' => 'Synthetic Bank payment steps'])
        ->assertSessionHasNoErrors();
    $option = SavedPaymentOption::sole();
    expect($option->label)->toBe($label)->and($option->version)->toBe(1)
        ->and($option->created_by)->toBe($manager->id)->and($option->is_shared)->toBeFalse()->and($option->is_enabled)->toBeTrue();
    $this->get(route('admin.payment-options.edit', $option))->assertOk()
        ->assertDontSee('name="label"', false)->assertDontSee('Option name')->assertDontSee('name="is_enabled"', false);
})->with([
    ['GCash', 'GCash — Synthetic owner'], ['Maya', 'Maya — Synthetic owner'],
    ['Bank', 'Bank Transfer — Synthetic owner'], ['PayMongo', 'PayMongo Online Checkout'],
]);

test('submitted labels are ignored and generated labels safely fit the existing column', function () {
    $this->actingAs(User::factory()->create());
    $accountName = str_repeat('界', 255);
    $this->post(route('admin.payment-options.store'), ['label' => ['forged label'], 'provider' => 'GCash',
        'account_name' => $accountName, 'account_number' => '09171234567', 'is_enabled' => '1'])
        ->assertSessionHasNoErrors();
    $option = SavedPaymentOption::sole();
    expect($option->label)->toBe(mb_substr('GCash — '.$accountName, 0, 100))
        ->and(mb_strlen($option->label))->toBe(100)->and(mb_check_encoding($option->label, 'UTF-8'))->toBeTrue()
        ->and($option->account_name)->toBe($accountName);
    $this->get(route('admin.payment-options.index'))->assertOk()->assertSee($option->label);
});

test('existing labels change only on explicit saves without changing event copies', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager, ['label' => 'Existing custom label']);
    $event = libraryEvent($manager);
    DB::transaction(fn () => app(EventPaymentSelection::class)->sync($event, [$option->selectionToken()], $manager));
    $snapshot = app(EventReviewWorkflow::class)->snapshot($event);
    $this->actingAs($manager)->get(route('admin.payment-options.edit', $option))->assertOk();
    expect($option->fresh()->label)->toBe('Existing custom label');
    $this->put(route('admin.payment-options.update', $option), ['provider' => 'Maya', 'account_name' => 'New owner',
        'account_number' => '09991234567', 'is_enabled' => '1', 'version' => 1])->assertSessionHasNoErrors();
    expect($option->fresh()->label)->toBe('Maya — New owner')->and($option->fresh()->version)->toBe(2)
        ->and(app(EventReviewWorkflow::class)->snapshot($event))->toBe($snapshot);
    $this->put(route('admin.payment-options.update', $option), ['label' => 'Forged', 'provider' => 'GCash',
        'account_name' => 'Stale owner', 'account_number' => '09171234567', 'is_enabled' => '1', 'version' => 1])
        ->assertSessionHasErrors('version');
    expect($option->fresh()->label)->toBe('Maya — New owner')->and($option->fresh()->version)->toBe(2);
});

test('account validation requires bank details and supports online checkout without account fields', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('admin.payment-options.store'), ['label' => 'Bad', 'provider' => 'GCash', 'is_enabled' => '1'])
        ->assertSessionHasErrors(['account_name', 'account_number']);
    $this->post(route('admin.payment-options.store'), ['label' => 'Bad', 'provider' => 'Other', 'is_enabled' => '1'])
        ->assertSessionHasErrors('provider');
    expect(SavedPaymentOption::count())->toBe(0);
    $this->post(route('admin.payment-options.store'), ['label' => 'Instructions', 'provider' => 'Bank',
        'account_name' => 'Synthetic bank owner', 'instructions' => 'Bank and account details', 'is_enabled' => '1'])->assertSessionHasErrors('account_number');
    $this->post(route('admin.payment-options.store'), ['label' => 'Bank option', 'provider' => 'Bank',
        'account_name' => 'Synthetic bank owner', 'account_number' => '001234567890', 'instructions' => 'Synthetic Bank: send proof.', 'is_enabled' => '1'])->assertSessionHasNoErrors();
    $this->post(route('admin.payment-options.store'), ['label' => 'Online', 'provider' => 'PayMongo',
        'account_name' => 'ignored', 'account_number' => 'ignored', 'is_enabled' => '1'])->assertSessionHasNoErrors();
    expect(SavedPaymentOption::where('provider', 'PayMongo')->sole()->account_number)->toBeNull();
});

test('wallet library validation rejects invalid numbers even when instructions are provided', function (string $provider, mixed $number) {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]));
    $this->post(route('admin.payment-options.store'), ['label' => 'Wallet', 'provider' => $provider,
        'account_name' => 'Synthetic wallet', 'account_number' => $number, 'instructions' => 'Do not bypass number validation.', 'is_enabled' => '1'])
        ->assertSessionHasErrors('account_number');
    expect(SavedPaymentOption::count())->toBe(0);
})->with(['GCash', 'Maya'])->with(['', '0917123456', '091712345678', '08171234567', '+639171234567', '0917 123 4567', '0917ABC4567', [['09171234567']], 9171234567]);

test('wallet creation and edits keep leading zeros while invalid edits leave versions and event copies unchanged', function (string $provider) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $input = ['label' => 'Wallet', 'provider' => $provider, 'account_name' => 'Synthetic wallet', 'account_number' => '09171234567', 'is_enabled' => '1'];
    $this->actingAs($manager)->post(route('admin.payment-options.store'), $input)->assertSessionHasNoErrors();
    $option = SavedPaymentOption::sole();
    expect($option->account_number)->toBe('09171234567');
    $event = libraryEvent($manager);
    DB::transaction(fn () => app(EventPaymentSelection::class)->sync($event, [$option->selectionToken()], $manager));
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $this->put(route('admin.payment-options.update', $option), [...$input, 'account_number' => 'bad', 'version' => 1])->assertSessionHasErrors('account_number');
    expect($option->fresh()->version)->toBe(1)->and($option->fresh()->account_number)->toBe('09171234567');
    $this->put(route('admin.payment-options.update', $option), [...$input, 'account_number' => '09991234567', 'version' => 1])->assertSessionHasNoErrors();
    expect($option->fresh()->account_number)->toBe('09991234567')->and($option->fresh()->version)->toBe(2)
        ->and(app(EventReviewWorkflow::class)->snapshot($event))->toBe($before);
})->with(['GCash', 'Maya']);

test('bank library numbers have no wallet length restriction and instructions identify the bank', function () {
    $this->actingAs(User::factory()->create());
    $input = ['label' => 'Bank', 'provider' => 'Bank', 'account_name' => 'Synthetic owner', 'account_number' => '000123456789012345', 'is_enabled' => '1'];
    $this->post(route('admin.payment-options.store'), $input)->assertSessionHasErrors('instructions');
    $this->post(route('admin.payment-options.store'), [...$input, 'instructions' => 'Synthetic Bank, Main Branch. Send proof.'])->assertSessionHasNoErrors();
    expect(SavedPaymentOption::sole()->account_number)->toBe('000123456789012345');
});

test('PayMongo ignores forged manual account fields including non string values', function () {
    $this->actingAs(User::factory()->create());
    $input = ['label' => 'Online', 'provider' => 'PayMongo', 'is_enabled' => '1', 'account_name' => ['ignored'], 'account_number' => ['ignored']];
    $this->post(route('admin.payment-options.store'), $input)->assertSessionHasNoErrors();
    $option = SavedPaymentOption::sole();
    expect($option->account_name)->toBeNull()->and($option->account_number)->toBeNull();
    $this->put(route('admin.payment-options.update', $option), [...$input, 'version' => 1])->assertSessionHasNoErrors();
    expect($option->fresh()->account_name)->toBeNull()->and($option->fresh()->account_number)->toBeNull();
});

test('payment option forms restore the submitted provider and escaped values after validation errors', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager, ['provider' => 'PayMongo', 'account_name' => null, 'account_number' => null]);
    $response = $this->actingAs($manager)->withSession(['_old_input' => ['provider' => 'Maya',
        'account_name' => '<script>bad()</script>', 'account_number' => '09BAD']])
        ->get(route('admin.payment-options.edit', $option))->assertOk()
        ->assertSee('Registered mobile number')->assertSee('pattern="09[0-9]{9}"', false)
        ->assertSee('inputmode="numeric" maxlength="255"', false)->assertSee('value="09BAD"', false)
        ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false)
        ->assertSee('js/payment-option-fields.js');
    expect($response->getContent())->not->toContain('type="number" name="account_number"');
});

test('library updates require the saved version and cannot change ownership or sharing', function () {
    $owner = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($owner);
    $input = $option->only(['label', 'provider', 'account_name', 'account_number', 'instructions', 'is_enabled', 'version']);
    $this->actingAs($owner)->get(route('admin.payment-options.edit', $option))->assertOk();
    $this->put(route('admin.payment-options.update', $option), [...$input, 'account_number' => '09220000000',
        'is_shared' => true, 'created_by' => 999])->assertSessionHasNoErrors();
    expect($option->fresh()->version)->toBe(2)->and($option->fresh()->is_shared)->toBeFalse()->and($option->fresh()->created_by)->toBe($owner->id);
    $this->put(route('admin.payment-options.update', $option), [...$input, 'account_number' => '09330000000'])->assertSessionHasErrors('version');
    expect($option->fresh()->account_number)->toBe('09220000000');
});

test('participant and guest requests cannot manage the staff payment library', function () {
    $this->get(route('admin.payment-options.index'))->assertRedirect(route('login'));
    $option = libraryOption(User::factory()->create());
    $this->patch(route('admin.payment-options.availability', $option), ['version' => 1, 'is_enabled' => '0'])->assertRedirect(route('login'));
    $participant = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $this->actingAs($participant)->get(route('admin.payment-options.index'))->assertForbidden();
    $this->get(route('admin.payment-options.create'))->assertForbidden();
    $this->post(route('admin.payment-options.store'), [])->assertForbidden();
    $this->get(route('admin.payment-options.edit', $option))->assertForbidden();
    $this->put(route('admin.payment-options.update', $option), [])->assertForbidden();
    $this->patch(route('admin.payment-options.availability', $option), ['version' => 1, 'is_enabled' => '0'])->assertForbidden();
    expect($option->fresh()->is_enabled)->toBeTrue()->and($option->fresh()->version)->toBe(1);
});

test('editing payment details preserves availability even with forged or old checkbox input', function (bool $enabled, ?string $submitted) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager, ['is_enabled' => $enabled]);
    $input = ['provider' => 'GCash', 'account_name' => 'Updated owner', 'account_number' => '09171234567', 'version' => 1];
    if ($submitted !== null) {
        $input['is_enabled'] = $submitted;
    }
    $this->actingAs($manager)->put(route('admin.payment-options.update', $option), $input)->assertSessionHasNoErrors();
    expect($option->fresh()->is_enabled)->toBe($enabled)->and($option->fresh()->account_name)->toBe('Updated owner')
        ->and($option->fresh()->version)->toBe(2);
})->with([true, false])->with([null, '0', '1']);

test('owners can set availability without changing saved details or scope', function (string $role) {
    $owner = User::factory()->create(['role' => $role]);
    $option = libraryOption($owner);
    $before = $option->only(['created_by', 'is_shared', 'label', 'provider', 'account_name', 'account_number', 'instructions']);
    $this->actingAs($owner)->get(route('admin.payment-options.index'))->assertOk()
        ->assertSee(route('admin.payment-options.availability', $option), false)->assertSee('Disable')->assertSee('Edit details');
    foreach ([false, true] as $index => $enabled) {
        $this->patch(route('admin.payment-options.availability', $option), ['version' => $index + 1, 'is_enabled' => $enabled ? '1' : '0',
            'account_number' => 'forged', 'label' => 'forged', 'created_by' => 99999, 'is_shared' => ! $owner->isSuperAdmin()])
            ->assertRedirect(route('admin.payment-options.index'))->assertSessionHasNoErrors();
        expect($option->fresh()->is_enabled)->toBe($enabled)->and($option->fresh()->version)->toBe($index + 2)
            ->and($option->fresh()->only(array_keys($before)))->toBe($before);
        $this->get(route('admin.payment-options.index'))->assertOk()->assertSee($enabled ? 'Disable' : 'Enable');
    }
})->with([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN]);

test('availability controls are offered only for manageable cards with explicit desired states and versions', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $active = libraryOption($manager);
    $inactive = libraryOption($manager, ['is_enabled' => false]);
    $shared = libraryOption(User::factory()->create());
    $response = $this->actingAs($manager)->get(route('admin.payment-options.index'))->assertOk()
        ->assertDontSee(route('admin.payment-options.availability', $shared), false);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($response->getContent());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    foreach ([[$active, '0', 'Disable'], [$inactive, '1', 'Enable']] as [$option, $state, $button]) {
        $form = '//form[@action="'.route('admin.payment-options.availability', $option).'" and @method="POST"]';
        expect($xpath->query($form)->length)->toBe(1)
            ->and($xpath->query($form.'//input[@name="_token"]')->length)->toBe(1)
            ->and($xpath->query($form.'//input[@name="_method" and @value="PATCH"]')->length)->toBe(1)
            ->and($xpath->query($form.'//input[@name="version" and @value="1"]')->length)->toBe(1)
            ->and($xpath->query($form.'//input[@name="is_enabled" and @value="'.$state.'"]')->length)->toBe(1)
            ->and(trim($xpath->query($form.'//button')->item(0)->textContent))->toBe($button);
    }
});

test('stale repeated availability clicks cannot undo newer changes or overwrite detail edits', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager);
    $url = route('admin.payment-options.availability', $option);
    $index = route('admin.payment-options.index');
    $this->actingAs($manager)->from($index)->patch($url, ['version' => 1, 'is_enabled' => '0'])->assertSessionHasNoErrors();
    foreach (['0', '1'] as $state) {
        $response = $this->from($index)->patch($url, ['version' => 1, 'is_enabled' => $state])->assertRedirect($index);
        expect($option->fresh()->is_enabled)->toBeFalse()->and($option->fresh()->version)->toBe(2);
        $this->followRedirects($response)->assertOk()->assertSee('Reload Payment Options')->assertSee('This option changed.');
    }
    $details = ['provider' => 'GCash', 'account_name' => 'New owner', 'account_number' => '09171234567'];
    $this->put(route('admin.payment-options.update', $option), [...$details, 'version' => 1])->assertSessionHasErrors('version');
    $this->put(route('admin.payment-options.update', $option), [...$details, 'version' => 2])->assertSessionHasNoErrors();
    $this->from($index)->patch($url, ['version' => 2, 'is_enabled' => '1'])->assertSessionHasErrors('version');
    expect($option->fresh()->account_name)->toBe('New owner')->and($option->fresh()->is_enabled)->toBeFalse()->and($option->fresh()->version)->toBe(3);
});

test('requesting the current availability state does not blindly flip it or change its version', function (bool $enabled) {
    $owner = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($owner, ['is_enabled' => $enabled]);
    $before = $option->fresh()->getAttributes();
    $this->actingAs($owner)->patch(route('admin.payment-options.availability', $option), ['version' => 1, 'is_enabled' => $enabled ? '1' : '0'])
        ->assertSessionHasNoErrors();
    expect($option->fresh()->getAttributes())->toBe($before);
})->with([true, false]);

test('availability requests validate desired state and version and cannot mutate on GET', function (array $input) {
    $owner = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($owner);
    $url = route('admin.payment-options.availability', $option);
    $this->actingAs($owner)->patchJson($url, $input)->assertUnprocessable();
    $this->get($url)->assertStatus(405);
    expect($option->fresh()->is_enabled)->toBeTrue()->and($option->fresh()->version)->toBe(1);
})->with([
    [[]], [['version' => 1]], [['is_enabled' => '0']], [['version' => 0, 'is_enabled' => '0']],
    [['version' => 1, 'is_enabled' => 'toggle']], [['version' => [1], 'is_enabled' => ['0']]],
]);

test('availability changes affect new selections only and invalidate old tokens even after re enabling', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager);
    $token = $option->selectionToken();
    $event = libraryEvent($manager);
    DB::transaction(fn () => app(EventPaymentSelection::class)->sync($event, [$token], $manager));
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $copy = $event->paymentMethods()->sole();
    $this->actingAs($manager)->patch(route('admin.payment-options.availability', $option), ['version' => 1, 'is_enabled' => '0'])->assertSessionHasNoErrors();
    $this->get(route('admin.events.create'))->assertOk()->assertDontSee($option->fresh()->selectionToken());
    $this->post(route('admin.events.store'), libraryPayload(['payment_choices' => [$token]]))->assertSessionHasErrors('payment_choices');
    $this->put(route('admin.events.update', $event), libraryPayload(['payment_choices' => [EventPaymentSelection::eventToken($copy)]]))->assertSessionHasNoErrors();
    expect(app(EventReviewWorkflow::class)->snapshot($event))->toBe($before);
    $this->patch(route('admin.payment-options.availability', $option), ['version' => 2, 'is_enabled' => '1'])->assertSessionHasNoErrors();
    $this->get(route('admin.events.create'))->assertOk()->assertSee($option->fresh()->selectionToken());
    $this->post(route('admin.events.store'), libraryPayload(['payment_choices' => [$token]]))->assertSessionHasErrors('payment_choices');
    expect(app(EventReviewWorkflow::class)->snapshot($event))->toBe($before)->and(Event::count())->toBe(1);
});

test('new event forms offer scoped checkboxes rather than editable account fields and escape option content', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager, ['label' => '<script>bad()</script>']);
    $other = libraryOption(User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]), ['account_number' => 'HIDDEN-ACCOUNT']);
    $this->actingAs($manager)->get(route('admin.events.create'))->assertOk()
        ->assertSee('name="payment_choices[]"', false)->assertSee($option->selectionToken())
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>bad()</script>', false)
        ->assertDontSee($other->selectionToken())->assertDontSee('HIDDEN-ACCOUNT')
        ->assertDontSee('payment_methods[0][provider]', false);
});

test('selected library accounts are copied when creating a draft and raw account overrides are ignored', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager);
    $shared = libraryOption(User::factory()->create(), ['label' => 'Shared Maya', 'provider' => 'Maya']);
    $this->actingAs($manager)->post(route('admin.events.store'), libraryPayload([
        'payment_choices' => [$option->selectionToken(), $shared->selectionToken()],
        'payment_methods_submitted' => '1', 'payment_methods' => [['provider' => 'Bank', 'account_name' => 'Injected', 'account_number' => 'wrong']],
    ]))->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::sole();
    expect($event->review_status)->toBe('draft')->and($event->paymentMethods()->count())->toBe(2)
        ->and($event->paymentMethods()->where('provider', 'GCash')->sole()->account_number)->toBe($option->account_number);
});

test('compact payment choices keep account details collapsed outside checkbox labels', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager, ['label' => '<script>bad()</script>', 'instructions' => '<b>Instructions</b>']);
    $response = $this->actingAs($manager)->get(route('admin.events.create'))->assertOk()
        ->assertSee('Required for paid events. Select one account per provider.')
        ->assertSee('Manage payment options')->assertDontSee('Available library options')
        ->assertDontSee('Selected account details are copied into this event draft')
        ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertSee('&lt;b&gt;Instructions&lt;/b&gt;', false);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    $section = '//section[@aria-label="Event Payment Options"]';
    $label = $xpath->query($section.'//label[@for="event-payment-option-'.$option->id.'"]')->item(0);
    expect($label)->not->toBeNull();
    expect($label->textContent)->toContain('GCash', 'Synthetic account')->not->toContain($option->account_number, 'Instructions', 'bad()');
    expect($xpath->query($section.'//details')->length)->toBe(1)
        ->and($xpath->query($section.'//details[@open]')->length)->toBe(0)
        ->and($xpath->query($section.'//label//details')->length)->toBe(0)
        ->and($xpath->query($section.'//details//input')->length)->toBe(0);
    $details = $xpath->query($section.'//details')->item(0);
    expect($details->textContent)->toContain('View account details', $option->account_number, '<b>Instructions</b>');
    expect($xpath->query($section.'//a[@target="_blank" and @rel="noopener noreferrer"]')->length)->toBe(1);
});

test('compact payment list preserves saved checked states and explicit empty old input', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager);
    $event = libraryEvent($manager);
    DB::transaction(fn () => app(EventPaymentSelection::class)->sync($event, [$option->selectionToken()], $manager));
    $copy = $event->paymentMethods()->sole();
    $disabled = $event->paymentMethods()->create(['provider' => 'Maya', 'account_name' => 'Disabled account', 'is_enabled' => false]);
    $this->actingAs($manager);
    foreach ([null, [], [$option->selectionToken()]] as $oldChoices) {
        if ($oldChoices !== null) {
            $this->withSession(['_old_input' => ['payment_selection_submitted' => '1', 'payment_choices' => $oldChoices]]);
        }
        $response = $this->get(route('admin.events.edit', $event))->assertOk()
            ->assertSee('Saved for this event')->assertSee('Currently disabled. Select to enable this saved copy.');
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $checked = $xpath->query('//section[@aria-label="Event Payment Options"]//input[@type="checkbox" and @checked]');
        $actual = [];
        foreach ($checked as $input) {
            $actual[] = $input->getAttribute('value');
        }
        expect($actual)->toBe($oldChoices ?? [EventPaymentSelection::eventToken($copy)])
            ->and($xpath->query('//input[@id="event-payment-copy-'.$disabled->id.'" and not(@checked)]')->length)->toBe(1);
    }
    expect($copy->fresh()->is_enabled)->toBeTrue()->and($disabled->fresh()->is_enabled)->toBeFalse();
});

test('foreign disabled missing stale and malformed selections cannot be copied into drafts', function (string $case) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $owner = $case === 'foreign' ? User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]) : $manager;
    $option = libraryOption($owner);
    $token = $option->selectionToken();
    if ($case === 'disabled') {
        $option->update(['is_enabled' => false]);
    }
    if ($case === 'stale') {
        $option->update(['version' => 2, 'account_number' => 'changed']);
    }
    if ($case === 'missing') {
        $token = 'option:999999:1';
    }
    if ($case === 'malformed') {
        $token = ['not a token'];
    }
    $this->actingAs($manager)->post(route('admin.events.store'), libraryPayload(['payment_choices' => [$token]]))
        ->assertSessionHasErrors();
    expect(Event::count())->toBe(0)->and(DB::table('event_payment_methods')->count())->toBe(0);
})->with(['foreign', 'disabled', 'missing', 'stale', 'malformed']);

test('an event cannot select two accounts for the same provider', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $a = libraryOption($manager);
    $b = libraryOption($manager, ['label' => 'Other GCash', 'account_number' => '09220000000']);
    $this->actingAs($manager)->post(route('admin.events.store'), libraryPayload(['payment_choices' => [$a->selectionToken(), $b->selectionToken()]]))
        ->assertSessionHasErrors('payment_choices');
    expect(Event::count())->toBe(0);
});

test('disabled library options do not invalidate an existing event copy or change its saved details', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $option = libraryOption($manager);
    $event = libraryEvent($manager);
    DB::transaction(fn () => app(EventPaymentSelection::class)->sync($event, [$option->selectionToken()], $manager));
    $copy = $event->paymentMethods()->sole();
    $before = app(EventReviewWorkflow::class)->snapshot($event);
    $option->update(['is_enabled' => false, 'account_number' => 'new-library-number', 'version' => 2]);
    $this->actingAs($manager)->get(route('admin.events.edit', $event))->assertOk()
        ->assertSee(EventPaymentSelection::eventToken($copy))->assertDontSee('new-library-number');
    $this->put(route('admin.events.update', $event), libraryPayload(['payment_choices' => [EventPaymentSelection::eventToken($copy)]]))
        ->assertSessionHasNoErrors();
    expect(app(EventReviewWorkflow::class)->snapshot($event))->toBe($before);
});

test('another events copy and a stale saved copy token cannot be selected', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = libraryEvent($manager);
    $other = libraryEvent($manager);
    $copy = $other->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'Other', 'account_number' => '09170000000']);
    $this->actingAs($manager)->put(route('admin.events.update', $event), libraryPayload(['payment_choices' => [EventPaymentSelection::eventToken($copy)]]))
        ->assertSessionHasErrors('payment_choices');
    $own = $event->paymentMethods()->create(['provider' => 'Maya', 'account_name' => 'Own', 'account_number' => '09170000000']);
    $oldToken = EventPaymentSelection::eventToken($own);
    $own->update(['account_number' => 'changed']);
    $this->put(route('admin.events.update', $event), libraryPayload(['payment_choices' => [$oldToken]]))->assertSessionHasErrors('payment_choices');
    expect($own->fresh()->account_number)->toBe('changed');
});

test('empty checkbox selection disables copies without deleting identifiers or falling back to legacy accounts', function (bool $hasCopy) {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = libraryEvent($manager);
    $event->categories()->first()->update(['payment_provider' => 'GCash', 'payment_account_name' => 'Legacy', 'payment_account_number' => '09170000000']);
    if ($hasCopy) {
        $copy = $event->paymentMethods()->create(['provider' => 'GCash', 'account_name' => 'Existing', 'account_number' => '09220000000']);
    }
    $this->actingAs($manager)->put(route('admin.events.update', $event), libraryPayload())->assertSessionHasNoErrors();
    expect($event->fresh()->hasUsablePaymentOptions())->toBeFalse()->and($event->paymentMethods()->sole()->is_enabled)->toBeFalse();
    if ($hasCopy) {
        expect($event->paymentMethods()->sole()->id)->toBe($copy->id);
    }
    $this->post(route('admin.events.review', [$event, 'submit']), ['review_version' => $event->fresh()->review_version])->assertSessionHasErrors('review');
})->with([true, false]);

test('catalog edits never change published or pending review hashes and payment replacements require a reviewed revision', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $admin = User::factory()->create();
    $option = libraryOption($manager);
    $event = libraryEvent($manager);
    DB::transaction(fn () => app(EventPaymentSelection::class)->sync($event, [$option->selectionToken()], $manager));
    $workflow = app(EventReviewWorkflow::class);
    $pending = $workflow->transition($event, $manager, 'submit', 0);
    $hash = $pending->submitted_setup_hash;
    $option->update(['account_number' => '09220000000', 'version' => 2]);
    expect($workflow->setupHash($pending))->toBe($hash);
    $live = $workflow->transition($pending, $admin, 'approve', $pending->review_version);
    $before = $workflow->snapshot($live);
    $originalId = $live->paymentMethods()->sole()->id;
    $this->actingAs($manager)->put(route('admin.events.update', $live), libraryPayload(['payment_choices' => [$option->selectionToken()]]))->assertForbidden();
    $revision = $workflow->startRevision($live, $manager, $live->review_version);
    $this->put(route('admin.events.update', $revision), libraryPayload(['payment_choices' => [$option->selectionToken()]]))->assertSessionHasNoErrors();
    expect($workflow->snapshot($live))->toBe($before);
    $submitted = $workflow->transition($revision->fresh(), $manager, 'submit', $revision->fresh()->review_version);
    $option->update(['account_number' => '09330000000', 'is_enabled' => false, 'version' => 3]);
    $workflow->transition($submitted, $admin, 'approve', $submitted->review_version);
    expect($live->paymentMethods()->sole()->id)->toBe($originalId)
        ->and($live->paymentMethods()->sole()->account_number)->toBe('09220000000');
});

test('schema adds an empty library without changing reviewed event payment columns', function () {
    expect(SavedPaymentOption::count())->toBe(0);
    $columns = Schema::getColumnListing('event_payment_methods');
    expect($columns)->not->toContain('saved_payment_option_id');
});
