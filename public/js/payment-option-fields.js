(() => {
    document.querySelectorAll('[data-payment-option-fields]').forEach((root) => {
        const provider = root.querySelector('#option-provider');
        const name = root.querySelector('#option-account_name');
        const number = root.querySelector('#option-account_number');
        const instructions = root.querySelector('#option-instructions');
        const inputError = root.querySelector('#option-number-input-error');
        const isWallet = () => ['GCash', 'Maya'].includes(provider.value);
        const clearInputError = () => {
            number.setCustomValidity('');
            number.removeAttribute('aria-invalid');
            inputError.hidden = true;
            inputError.textContent = '';
        };
        const rejectInsertion = (event, message) => {
            event.preventDefault();
            inputError.textContent = message;
            inputError.hidden = false;
            number.setAttribute('aria-invalid', 'true');
            number.setCustomValidity(message);
        };
        const exceedsWalletLimit = (text) => number.value.length
            - ((number.selectionEnd ?? number.value.length) - (number.selectionStart ?? number.value.length))
            + text.length > 11;
        const tooLongMessage = 'Use only 11 digits for the registered mobile number. Nothing was pasted or shortened. Edit the number and try again.';
        number.addEventListener('paste', (event) => {
            if (!isWallet()) return;
            // Inspect the whole clipboard before maxlength can truncate it.
            if (!event.clipboardData) {
                rejectInsertion(event, 'Unable to read the pasted number safely. Type the 11-digit registered mobile number instead.');
            } else if (exceedsWalletLimit(event.clipboardData.getData('text'))) {
                rejectInsertion(event, tooLongMessage);
            }
        });
        number.addEventListener('drop', (event) => {
            if (!isWallet()) return;
            // A drop caret can differ from the current selection; do not risk truncation.
            rejectInsertion(event, 'Paste or type the 11-digit registered mobile number instead of dragging it into this field.');
        });
        number.addEventListener('beforeinput', (event) => {
            if (isWallet() && event.cancelable && event.inputType.startsWith('insert')
                && typeof event.data === 'string' && exceedsWalletLimit(event.data)) {
                rejectInsertion(event, 'The registered mobile number is limited to 11 digits. Edit the number before adding more.');
            }
        });
        number.addEventListener('input', clearInputError);
        const refresh = () => {
            clearInputError();
            const wallet = isWallet();
            const bank = provider.value === 'Bank';
            const manual = wallet || bank;
            root.querySelector('[data-manual-account]').hidden = !manual;
            root.querySelector('[data-online-checkout]').hidden = provider.value !== 'PayMongo';
            [name, number].forEach((field) => {
                field.disabled = !manual;
                field.required = manual;
            });
            root.querySelector('[data-account-number-label]').textContent = wallet ? 'Registered mobile number' : 'Account number';
            number.inputMode = wallet ? 'numeric' : 'text';
            number.maxLength = wallet ? 11 : 255;
            if (wallet) {
                number.pattern = '09[0-9]{9}';
                number.title = 'Enter 11 digits starting with 09.';
            } else {
                number.removeAttribute('pattern');
                number.removeAttribute('title');
            }
            number.placeholder = wallet ? 'e.g. 09171234567' : 'Enter the account number exactly as provided by the bank';
            root.querySelector('#option-number-help').textContent = wallet
                ? 'Use 11 digits starting with 09, without spaces or dashes. Format checks do not verify account ownership.'
                : 'Keep any leading zeros. Enter the bank name in the instructions below.';
            instructions.required = bank;
            root.querySelector('[data-bank-required]').hidden = !bank;
            root.querySelector('[data-bank-help]').hidden = !bank;
            // Switching providers must not silently truncate or clear entered details.
        };
        provider.addEventListener('change', refresh);
        refresh();
    });
})();
