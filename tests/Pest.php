<?php

use App\Models\Event;
use App\Models\User;
use App\Services\EventReviewWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// Public-event feature fixtures go through real review transitions.
// Pre-workflow fixtures explicitly set review_status to NULL instead.
function approveEventSetupForTest(Event $event): Event
{
    $event = $event->fresh();
    // These fixtures exercise publication, not incomplete submission rejection.
    // Complete only the newly required setup before taking the real transitions.
    if ($event->end_time === null) {
        $event->update(['end_time' => '23:59']);
    }
    foreach ($event->categories()->get() as $category) {
        $setup = [];
        if ($category->slot_limit === null) {
            $setup['slot_limit'] = 100;
        }
        if (blank($category->checkpoint_map_image) && blank($category->course_map)) {
            $setup['checkpoint_map_image'] = 'categories/checkpoint-maps/synthetic-review.png';
        }
        if ($setup !== []) {
            $category->update($setup);
        }
    }
    $workflow = app(EventReviewWorkflow::class);
    $event = $workflow->transition($event, $event->manager, 'submit', $event->review_version);
    $reviewer = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    return $workflow->transition($event, $reviewer, 'approve', $event->review_version);
}
