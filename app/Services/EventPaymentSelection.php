<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\SavedPaymentOption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventPaymentSelection
{
    /** Display choices only: never rewrite saved accounts or submitted tokens. */
    public static function formChoices(iterable $eventCopies, iterable $libraryOptions, array $selectedTokens): Collection
    {
        $copies = collect($eventCopies)->map(fn ($copy) => [
            'choice' => $copy, 'token' => self::eventToken($copy), 'isEventCopy' => true,
        ]);
        $options = collect($libraryOptions)->map(fn ($option) => [
            'choice' => $option, 'token' => $option->selectionToken(), 'isEventCopy' => false,
        ]);

        return $copies->concat($options)->groupBy(function ($entry) {
            $choice = $entry['choice'];

            // Compare every participant-facing account detail exactly, not just
            // its name/provider. Changed instructions must remain selectable.
            return hash('sha256', json_encode([
                $choice->provider, $choice->account_name, $choice->account_number, $choice->instructions,
            ], JSON_THROW_ON_ERROR));
        })->flatMap(function ($duplicates) use ($selectedTokens) {
            $selected = $duplicates->filter(fn ($entry) => in_array($entry['token'], $selectedTokens, true));

            // Prefer the saved event copy by default, but retain the exact choice
            // restored after validation. Keep conflicting old selections visible
            // so the user can resolve an error; never silently change their tokens.
            return $selected->isNotEmpty() ? $selected : $duplicates->take(1);
        })->values();
    }

    public static function eventToken(EventPaymentMethod $method): string
    {
        return 'event:'.$method->id.':'.hash('sha256', json_encode($method->only([
            'provider', 'account_name', 'account_number', 'instructions', 'is_enabled', 'sort_order',
        ]), JSON_THROW_ON_ERROR));
    }

    public function prepare(Request $request, ?Event $event = null): ?array
    {
        // Compatibility for previously opened event forms/integrations. New forms
        // always send this marker and cannot override copies with raw account fields.
        if (! $request->exists('payment_selection_submitted')) {
            return null;
        }
        $data = $request->validate([
            'payment_selection_submitted' => ['required', 'accepted'],
            'payment_choices' => ['sometimes', 'array', 'max:20'],
            'payment_choices.*' => ['required', 'string', 'max:110', 'distinct', 'regex:/^(option:[1-9][0-9]*:[1-9][0-9]*|event:[1-9][0-9]*:[a-f0-9]{64})$/'],
        ]);
        $choices = $data['payment_choices'] ?? [];
        $this->resolve($choices, $request->user(), $event, false);

        return $choices;
    }

    private function resolve(array $choices, User $user, ?Event $event, bool $lock): array
    {
        $rows = [];
        $providers = [];
        // Consistent lock order for options selected in different browser orders.
        $tokens = $choices;
        sort($tokens, SORT_STRING);
        foreach ($tokens as $token) {
            [$kind, $id, $version] = explode(':', $token);
            if ($kind === 'option') {
                $query = SavedPaymentOption::availableTo($user)->where('is_enabled', true);
                $option = ($lock ? $query->lockForUpdate() : $query)->find($id);
                if (! $option || $option->selectionToken() !== $token) {
                    throw ValidationException::withMessages(['payment_choices' => 'A selected payment option is unavailable or changed. Reload the event form and choose again.']);
                }
                $details = $option->eventDetails();
            } else {
                $query = $event?->paymentMethods();
                $method = $query ? ($lock ? $query->lockForUpdate() : $query)->find($id) : null;
                if (! $method || self::eventToken($method) !== $token) {
                    throw ValidationException::withMessages(['payment_choices' => 'A saved event payment option changed or does not belong to this event. Reload before saving.']);
                }
                $details = $method->only(['provider', 'account_name', 'account_number', 'instructions']);
                $details['is_enabled'] = true;
            }
            if (in_array($details['provider'], $providers, true)) {
                throw ValidationException::withMessages(['payment_choices' => 'Choose only one payment account per provider for this event.']);
            }
            if (! array_key_exists($details['provider'], EventPaymentMethod::providers())) {
                throw ValidationException::withMessages(['payment_choices' => 'Choose a supported payment provider.']);
            }
            if ($details['provider'] !== 'PayMongo' && (blank($details['account_name'])
                || (blank($details['account_number']) && blank($details['instructions'])))) {
                throw ValidationException::withMessages(['payment_choices' => 'A selected payment account is incomplete. Choose a complete saved option.']);
            }
            $providers[] = $details['provider'];
            $rows[$token] = $details;
        }

        return array_map(fn ($token) => $rows[$token], $choices);
    }

    public function sync(Event $event, array $choices, User $user): void
    {
        // Called inside the event's existing setup transaction/ownership lock.
        $rows = $this->resolve($choices, $user, $event, true);
        $existing = $event->paymentMethods()->get()->keyBy('provider');
        if ($rows === [] && $existing->isEmpty()) {
            // An explicit empty selection must not reactivate legacy category accounts.
            foreach ($event->categories()->whereNotNull('payment_provider')->get()->unique('payment_provider') as $category) {
                if (filled($category->payment_provider)) {
                    $event->paymentMethods()->create(['provider' => $category->payment_provider,
                        'account_name' => $category->payment_account_name, 'account_number' => $category->payment_account_number,
                        'instructions' => $category->payment_instructions, 'is_enabled' => false, 'sort_order' => 0]);
                }
            }
        }
        $selected = array_column($rows, 'provider');
        foreach ($existing as $method) {
            if (! in_array($method->provider, $selected, true) && $method->is_enabled) {
                $method->update(['is_enabled' => false]);
            }
        }
        $nextOrder = (int) $existing->max('sort_order') + 1;
        foreach ($rows as $row) {
            if ($method = $existing->get($row['provider'])) {
                $method->fill($row);
                if ($method->isDirty()) {
                    $method->save();
                }
            } else {
                $event->paymentMethods()->create([...$row, 'sort_order' => $nextOrder++]);
            }
        }
        if ($rows !== [] && $event->payment_setup_needs_review) {
            $event->forceFill(['payment_setup_needs_review' => false])->save();
        }
        $event->unsetRelation('paymentMethods');
    }
}
