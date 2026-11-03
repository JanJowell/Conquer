<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRevision;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EventRevisionComparison
{
    public function __construct(private readonly EventReviewWorkflow $workflow) {}

    public function build(Event $draft): array
    {
        $revision = EventRevision::where('draft_event_id', $draft->id)->first();
        if (! $revision) {
            return ['available' => false, 'sections' => [], 'stale' => false];
        }

        $live = Event::findOrFail($revision->event_id);
        $current = $this->workflow->snapshot($live);
        $proposed = $this->workflow->snapshot($draft);

        return ['available' => true,
            'sections' => $this->compareSnapshots($current, $proposed, ['categories' => $revision->category_map,
                'paymentMethods' => $revision->payment_method_map, 'checkpoints' => $revision->checkpoint_map]),
            'stale' => ! $revision->applied_at && ! hash_equals($revision->base_setup_hash, $this->workflow->setupHash($live)),
            'applied' => (bool) $revision->applied_at];
    }

    public function compareSnapshots(array $current, array $proposed, array $maps): array
    {
        $currentType = $current['event']['interest_type'] ?? null;
        $proposedType = $proposed['event']['interest_type'] ?? null;
        $sections = [];
        $rows = $this->changes($current['event'], $proposed['event'], $currentType, $proposedType);
        if ($rows !== []) {
            $sections[] = ['title' => 'Event details', 'change' => 'Changed', 'rows' => $rows];
        }

        foreach (['categories', 'paymentMethods', 'checkpoints'] as $relation) {
            $map = $maps[$relation] ?? [];
            $remaining = $proposed[$relation];
            foreach ($current[$relation] as $id => $item) {
                $copyId = $map[(string) $id] ?? null;
                // Match the publication workflow: the edit form recreates payment rows.
                if (! isset($remaining[$copyId ?? '']) && $relation === 'paymentMethods') {
                    $copyId = array_find_key($remaining, fn ($copy) => $copy['provider'] === $item['provider']);
                }
                $copy = $remaining[$copyId ?? ''] ?? null;
                if ($copyId !== null) {
                    unset($remaining[$copyId]);
                }
                $rows = $this->changes($item, $copy, $currentType, $proposedType, $relation);
                if ($rows !== []) {
                    $sections[] = ['title' => $this->itemTitle($relation, $item),
                        'change' => $copy === null ? 'Removed' : 'Changed', 'rows' => $rows];
                }
            }
            foreach ($remaining as $item) {
                $sections[] = ['title' => $this->itemTitle($relation, $item), 'change' => 'Added',
                    'rows' => $this->changes(null, $item, $currentType, $proposedType, $relation)];
            }
        }

        return $sections;
    }

    /** Read-only formatting of the same complete setup used by the review page. */
    public function submittedSetup(array $snapshot): array
    {
        $type = $snapshot['event']['interest_type'] ?? null;
        $groups = [];
        foreach (['event' => 'Event details', 'categories' => 'Categories',
            'paymentMethods' => 'Payment options', 'checkpoints' => 'Checkpoints'] as $relation => $title) {
            $items = $relation === 'event' ? [$snapshot['event'] ?? []] : ($snapshot[$relation] ?? []);
            $formatted = [];
            foreach ($items as $item) {
                $rows = [];
                foreach ($item as $field => $raw) {
                    $value = $this->normalize($raw, $field);
                    if ($field === 'type_details' && is_array($value)) {
                        $schema = $relation === 'event' ? 'event_type_details' : 'event_category_type_details';
                        foreach ($value as $key => $detail) {
                            $definition = config("conquer.{$schema}.{$type}.{$key}", []);
                            $rows[] = ['field' => $definition['label'] ?? Str::headline($key),
                                'value' => $this->display($detail, $key, $item, $definition)];
                        }
                    } else {
                        $rows[] = ['field' => $this->label($field), 'value' => $this->display($value, $field, $item)];
                    }
                }
                $formatted[] = ['title' => $relation === 'event' ? $title : $this->itemTitle($relation, $item), 'rows' => $rows];
            }
            $groups[] = ['key' => $relation, 'title' => $title, 'items' => $formatted];
        }

        return $groups;
    }

    private function itemTitle(string $relation, array $item): string
    {
        return match ($relation) {
            'categories' => 'Category: '.($item['name'] ?? 'Unnamed category'),
            'paymentMethods' => 'Payment option: '.(EventPaymentMethod::providers()[$item['provider']] ?? $item['provider']),
            'checkpoints' => 'Checkpoint: '.($item['name'] ?? 'Unnamed checkpoint'),
        };
    }

    private function changes(?array $current, ?array $proposed, ?string $currentType, ?string $proposedType, string $relation = 'event'): array
    {
        $rows = [];
        foreach (array_unique([...array_keys($current ?? []), ...array_keys($proposed ?? [])]) as $field) {
            $before = $this->normalize($current[$field] ?? null, $field);
            $after = $this->normalize($proposed[$field] ?? null, $field);
            if ($field === 'type_details' && (is_array($before) || is_array($after))) {
                $before = is_array($before) ? $before : [];
                $after = is_array($after) ? $after : [];
                foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                    $old = $before[$key] ?? null;
                    $new = $after[$key] ?? null;
                    if ($old !== $new) {
                        $schema = $relation === 'event' ? 'event_type_details' : 'event_category_type_details';
                        $oldDefinition = config("conquer.{$schema}.{$currentType}.{$key}", []);
                        $newDefinition = config("conquer.{$schema}.{$proposedType}.{$key}", []);
                        $label = $newDefinition['label'] ?? $oldDefinition['label'] ?? Str::headline($key);
                        $rows[] = $this->row($label, $old, $new, $key, $current, $proposed, $oldDefinition, $newDefinition);
                    }
                }
            } elseif ($before !== $after) {
                $rows[] = $this->row($this->label($field), $before, $after, $field, $current, $proposed);
            }
        }

        return $rows;
    }

    private function row(string $label, mixed $before, mixed $after, string $field, ?array $current, ?array $proposed, array $oldDefinition = [], array $newDefinition = []): array
    {
        return ['field' => $label,
            'change' => $current === null || $before === null ? 'Added' : ($proposed === null || $after === null ? 'Removed' : 'Changed'),
            'current' => $current === null ? ['text' => 'Not present', 'details' => null] : $this->display($before, $field, $current, $oldDefinition),
            'proposed' => $proposed === null ? ['text' => 'Not present', 'details' => null] : $this->display($after, $field, $proposed, $newDefinition)];
    }

    private function normalize(mixed $value, string $field): mixed
    {
        if (in_array($field, ['type_details', 'course_map'], true) && is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($item) => $this->normalize($item, ''), $value);
        }
        if (in_array($field, ['requires_medical_certificate', 'is_enabled', 'payment_setup_needs_review'], true) && $value !== null) {
            return (bool) $value;
        }
        if (in_array($field, ['distance_km', 'latitude', 'longitude', 'price_cents', 'slot_limit', 'sort_order', 'order', 'group_min_members', 'group_max_members'], true) && is_numeric($value)) {
            return (float) $value;
        }

        return is_int($value) || is_float($value) ? (float) $value : ($value === '' ? null : $value);
    }

    private function display(mixed $value, string $field, array $item, array $definition = []): array
    {
        $details = null;
        if ($value === null) {
            $text = 'Not set';
        } elseif (is_bool($value) || ($definition['type'] ?? '') === 'boolean') {
            $text = $value ? 'Yes' : 'No';
        } elseif ($field === 'price_cents') {
            $text = ($item['price_currency'] ?? 'PHP').' '.number_format($value / 100, 2);
        } elseif ($field === 'manager_id') {
            $text = User::whereKey($value)->value('name') ?? 'Former manager';
        } elseif ($field === 'venue_id') {
            $text = Venue::whereKey($value)->value('label') ?? 'Unavailable saved venue';
        } elseif ($field === 'course_map' && is_array($value)) {
            $points = count($value['path'] ?? []);
            $checkpoints = count($value['checkpoints'] ?? []);
            $text = $points.' '.Str::plural('route point', $points).'; '.$checkpoints.' '.Str::plural('checkpoint', $checkpoints);
            $details = $this->lines($value);
        } elseif (is_array($value)) {
            $text = $this->lines($value);
        } elseif (in_array($field, ['event_date', 'event_end_date', 'registration_deadline', 'scheduled_start_date', 'scheduled_end_date'], true)) {
            $text = Carbon::parse($value)->format('M j, Y');
        } elseif (in_array($field, ['start_time', 'end_time', 'scheduled_start_time', 'scheduled_end_time'], true)) {
            $text = Carbon::parse($value)->format('g:i:s A');
        } elseif (in_array($field, ['status', 'participation_mode', 'type'], true)) {
            $text = Str::headline($value);
        } elseif ($field === 'provider' || $field === 'payment_provider') {
            $text = EventPaymentMethod::providers()[$value] ?? (string) $value;
        } else {
            $text = (string) $value;
            if (isset($definition['suffix'])) {
                $text .= ' '.$definition['suffix'];
            }
        }

        return ['text' => $text, 'details' => $details];
    }

    private function lines(array $value, string $prefix = ''): string
    {
        $lines = [];
        foreach ($value as $key => $item) {
            $label = $prefix.($prefix !== '' ? ' / ' : '').(is_int($key) ? (string) ($key + 1) : $this->label($key));
            $lines[] = is_array($item) ? ($item === [] ? $label.': None' : $this->lines($item, $label))
                : $label.': '.(is_bool($item) ? ($item ? 'Yes' : 'No') : ($item ?? 'Not set'));
        }

        return implode("\n", $lines);
    }

    private function label(string $field): string
    {
        return match ($field) {
            'title' => 'Event name', 'interest_type' => 'Event type', 'manager_id' => 'Event manager',
            'venue_id' => 'Saved venue', 'venue' => 'Venue / landmark', 'banner_image' => 'Banner image',
            'distance_km' => 'Distance (stored kilometers)', 'distance_unit' => 'Display distance unit',
            'price_cents' => 'Registration fee', 'price_currency' => 'Currency', 'slot_limit' => 'Participant limit',
            'status' => 'Registration status', 'is_enabled' => 'Enabled', 'sort_order' => 'Display order',
            'checkpoint_map_image' => 'Uploaded course map', 'course_map' => 'Drawn course map',
            'payment_setup_needs_review' => 'Payment setup needs review', 'lat' => 'Latitude', 'lng' => 'Longitude',
            'path' => 'Route point', 'version' => 'Format version', 'checkpoints' => 'Checkpoint',
            default => Str::headline($field),
        };
    }
}
