<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventPaymentMethod;
use App\Models\SavedPaymentOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentOptionController extends Controller
{
    public function index(Request $request): View
    {
        $options = SavedPaymentOption::availableTo($request->user())->orderByDesc('is_enabled')
            ->orderBy('label')->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.payments.options.index', compact('options'));
    }

    public function create(): View
    {
        return view('admin.payments.options.form', ['option' => new SavedPaymentOption(['is_enabled' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        SavedPaymentOption::create([...$data, 'created_by' => $request->user()->id,
            'is_shared' => $request->user()->isSuperAdmin(), 'is_enabled' => true, 'version' => 1]);

        return to_route('admin.payment-options.index')->with('success', 'Payment option saved. Event Managers can select available options in their event drafts.');
    }

    public function edit(Request $request, SavedPaymentOption $paymentOption): View
    {
        abort_unless($paymentOption->canManage($request->user()), 404);

        return view('admin.payments.options.form', ['option' => $paymentOption]);
    }

    public function update(Request $request, SavedPaymentOption $paymentOption): RedirectResponse
    {
        abort_unless($paymentOption->canManage($request->user()), 404);
        $data = $this->validated($request);
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        DB::transaction(function () use ($request, $paymentOption, $data, $version) {
            $option = SavedPaymentOption::lockForUpdate()->findOrFail($paymentOption->id);
            abort_unless($option->canManage($request->user()), 404);
            if ($option->version !== (int) $version) {
                throw ValidationException::withMessages(['version' => 'This option changed. Reload the saved version before editing it again.']);
            }
            $option->update([...$data, 'version' => $option->version + 1]);
        });

        return to_route('admin.payment-options.index')->with('success', 'Saved option updated. Existing event copies and payment records are unchanged.');
    }

    public function availability(Request $request, SavedPaymentOption $paymentOption): RedirectResponse
    {
        abort_unless($paymentOption->canManage($request->user()), 404);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'is_enabled' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $paymentOption, $data) {
            $option = SavedPaymentOption::lockForUpdate()->findOrFail($paymentOption->id);
            abort_unless($option->canManage($request->user()), 404);
            if ($option->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'This option changed. Reload Payment Options before enabling or disabling it.']);
            }
            // Set the requested state, never blindly flip it on retries/double clicks.
            $enabled = (bool) $data['is_enabled'];
            if ($option->is_enabled !== $enabled) {
                $option->update(['is_enabled' => $enabled, 'version' => $option->version + 1]);
            }
        });

        return to_route('admin.payment-options.index')->with('success',
            $data['is_enabled'] ? 'Payment option enabled for new event selections. Existing event copies are unchanged.'
                : 'Payment option disabled for new event selections. Existing event copies are unchanged.');
    }

    private function validated(Request $request): array
    {
        $wallet = in_array($request->input('provider'), ['GCash', 'Maya'], true);
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(EventPaymentMethod::providers()))],
            'account_name' => ['exclude_if:provider,PayMongo', 'required', 'string', 'max:255'],
            'account_number' => ['exclude_if:provider,PayMongo', 'bail', 'required', 'string', 'max:255',
                ...($wallet ? ['regex:/\A09[0-9]{9}\z/'] : [])],
            'instructions' => ['nullable', 'required_if:provider,Bank', 'string', 'max:5000'],
        ], [
            'account_number.required' => $wallet ? 'Enter the registered mobile number for this wallet.' : 'Enter the bank account number.',
            'account_number.regex' => 'Enter an 11-digit registered mobile number starting with 09 (for example, 09171234567).',
            'instructions.required_if' => 'Identify the bank in the payment instructions so participants know where to transfer.',
        ]);
        if ($data['provider'] === 'PayMongo') {
            $data['account_name'] = $data['account_number'] = null;
        }
        // Labels are display metadata, never trusted from submitted form fields.
        // Keep the existing 100-character column without shortening account data.
        $data['label'] = mb_substr(EventPaymentMethod::providers()[$data['provider']]
            .($data['provider'] === 'PayMongo' ? '' : ' — '.$data['account_name']), 0, 100);

        return $data;
    }
}
