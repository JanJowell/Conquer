import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/checkpoint-history.js', import.meta.url), 'utf8');

function formFor(eventId = '', categoryId = '') {
    const listeners = [];
    const groups = ['1', '2'].map(id => ({ dataset: { historyEvent: id }, hidden: false, disabled: false }));
    const event = { value: eventId, addEventListener: (name, callback) => { if (name === 'change') listeners.push(callback); } };
    const category = { value: categoryId, querySelectorAll: () => groups };
    return {
        dataset: {}, event, category, groups, listeners,
        elements: { namedItem: name => name === 'event_id' ? event : category },
        changeEvent(value) { event.value = value; listeners.forEach(callback => callback()); },
    };
}

function environment(forms, readyState = 'complete') {
    const events = new Map();
    const document = {
        readyState, forms,
        querySelectorAll: () => document.forms,
        addEventListener: (name, callback) => events.set(name, [...(events.get(name) ?? []), callback]),
    };
    const context = vm.createContext({ document, window: {}, fetch: () => { throw new Error('History filters must not make requests'); } });
    const run = () => vm.runInContext(source, context);
    const dispatch = name => (events.get(name) ?? []).forEach(callback => callback());
    run();
    return { context, document, events, run, dispatch };
}

test('initial server-restored selections remain selected and unrelated category groups are hidden and disabled', () => {
    const form = formFor('1', '101');
    environment([form]);
    assert.equal(form.category.value, '101');
    assert.equal(form.groups[0].hidden, false);
    assert.equal(form.groups[0].disabled, false);
    assert.equal(form.groups[1].hidden, true);
    assert.equal(form.groups[1].disabled, true);
});

test('switching events clears a stale category and enables only the new event categories', () => {
    const form = formFor('1', '101');
    environment([form]);
    form.changeEvent('2');
    assert.equal(form.category.value, '');
    assert.equal(form.groups[0].hidden, true);
    assert.equal(form.groups[0].disabled, true);
    assert.equal(form.groups[1].hidden, false);
    assert.equal(form.groups[1].disabled, false);
});

test('all events restores every authorized category group without selecting a category automatically', () => {
    const form = formFor('2', '201');
    environment([form]);
    form.changeEvent('');
    assert.equal(form.category.value, '');
    assert.ok(form.groups.every(group => !group.hidden && !group.disabled));
});

test('DOMContentLoaded initializes filters without clearing restored values', () => {
    const form = formFor('2', '201');
    const env = environment([form], 'loading');
    assert.equal(form.listeners.length, 0);
    env.dispatch('DOMContentLoaded');
    assert.equal(form.listeners.length, 1);
    assert.equal(form.category.value, '201');
});

test('Livewire navigation initializes replacement forms and does not duplicate existing handlers', () => {
    const first = formFor();
    const env = environment([first]);
    env.dispatch('livewire:navigated');
    assert.equal(first.listeners.length, 1);
    const replacement = formFor('1', '101');
    env.document.forms = [replacement];
    env.dispatch('livewire:navigated');
    assert.equal(replacement.listeners.length, 1);
    assert.equal(replacement.category.value, '101');
    env.run();
    assert.equal(replacement.listeners.length, 1);
    assert.equal(env.events.get('livewire:navigated').length, 1);
});

test('pages without history filters are safe to navigate through', () => {
    const env = environment([]);
    env.dispatch('livewire:navigated');
    env.run();
    assert.equal(env.events.get('livewire:navigated').length, 1);
});
