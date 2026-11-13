import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/checkpoint-history.js', import.meta.url), 'utf8');

function formFor(eventId = '', categoryId = '', checkpointId = '') {
    const listeners = [];
    const categoryListeners = [];
    const groups = ['1', '2'].map(id => ({ dataset: { historyEvent: id }, hidden: false, disabled: false }));
    const checkpointGroups = ['101', '102', '201'].map(id => ({ dataset: { historyCategory: id }, hidden: false, disabled: false }));
    const event = { value: eventId, addEventListener: (name, callback) => { if (name === 'change') listeners.push(callback); } };
    const category = { value: categoryId, querySelectorAll: () => groups,
        addEventListener: (name, callback) => { if (name === 'change') categoryListeners.push(callback); } };
    const checkpoint = { value: checkpointId, disabled: false, options: [{ value: '', textContent: 'All checkpoints' }],
        querySelectorAll: () => checkpointGroups };
    return {
        dataset: {}, event, category, checkpoint, groups, checkpointGroups, listeners, categoryListeners,
        elements: { namedItem: name => ({ event_id: event, category_id: category, checkpoint_id: checkpoint })[name] ?? null },
        changeEvent(value) { event.value = value; listeners.forEach(callback => callback()); },
        changeCategory(value) { category.value = value; categoryListeners.forEach(callback => callback()); },
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
    const form = formFor('2', '201', '900');
    const env = environment([form], 'loading');
    assert.equal(form.listeners.length, 0);
    env.dispatch('DOMContentLoaded');
    assert.equal(form.listeners.length, 1);
    assert.equal(form.category.value, '201');
    assert.equal(form.checkpoint.value, '900');
    assert.equal(form.checkpoint.disabled, false);
});

test('Livewire navigation initializes replacement forms and does not duplicate existing handlers', () => {
    const first = formFor();
    const env = environment([first]);
    env.dispatch('livewire:navigated');
    assert.equal(first.listeners.length, 1);
    const replacement = formFor('1', '101', '800');
    env.document.forms = [replacement];
    env.dispatch('livewire:navigated');
    assert.equal(replacement.listeners.length, 1);
    assert.equal(replacement.category.value, '101');
    assert.equal(replacement.checkpoint.value, '800');
    assert.equal(replacement.categoryListeners.length, 1);
    env.run();
    assert.equal(replacement.listeners.length, 1);
    assert.equal(replacement.categoryListeners.length, 1);
    assert.equal(env.events.get('livewire:navigated').length, 1);
});

test('pages without history filters are safe to navigate through', () => {
    const env = environment([]);
    env.dispatch('livewire:navigated');
    env.run();
    assert.equal(env.events.get('livewire:navigated').length, 1);
});

test('server-restored checkpoint selection is preserved and only its category group is available', () => {
    const form = formFor('1', '101', '800');
    environment([form]);
    assert.equal(form.checkpoint.value, '800');
    assert.equal(form.checkpoint.disabled, false);
    assert.equal(form.checkpoint.options[0].textContent, 'All checkpoints');
    assert.equal(form.checkpointGroups[0].hidden, false);
    assert.equal(form.checkpointGroups[0].disabled, false);
    assert.ok(form.checkpointGroups.slice(1).every(group => group.hidden && group.disabled));
});

test('checkpoint selection is disabled and stale values cleared until a category is selected', () => {
    const form = formFor('1', '', '800');
    environment([form]);
    assert.equal(form.checkpoint.disabled, true);
    assert.equal(form.checkpoint.value, '');
    assert.equal(form.checkpoint.options[0].textContent, 'Select a category first');
    assert.ok(form.checkpointGroups.every(group => group.hidden && group.disabled));
});

test('changing categories clears the old checkpoint and enables only the newly selected category', () => {
    const form = formFor('1', '101', '800');
    environment([form]);
    form.changeCategory('102');
    assert.equal(form.checkpoint.value, '');
    assert.equal(form.checkpoint.disabled, false);
    assert.equal(form.checkpointGroups[0].hidden, true);
    assert.equal(form.checkpointGroups[0].disabled, true);
    assert.equal(form.checkpointGroups[1].hidden, false);
    assert.equal(form.checkpointGroups[1].disabled, false);
    assert.equal(form.checkpointGroups[2].hidden, true);
    assert.equal(form.checkpointGroups[2].disabled, true);
});

test('changing events clears both dependent selections and disables checkpoint options until reselection', () => {
    const form = formFor('1', '101', '800');
    environment([form]);
    form.changeEvent('2');
    assert.equal(form.category.value, '');
    assert.equal(form.checkpoint.value, '');
    assert.equal(form.checkpoint.disabled, true);
    assert.ok(form.checkpointGroups.every(group => group.hidden && group.disabled));
    form.changeCategory('201');
    assert.equal(form.checkpoint.value, '');
    assert.equal(form.checkpoint.disabled, false);
    assert.equal(form.checkpointGroups[2].hidden, false);
    assert.equal(form.checkpointGroups[2].disabled, false);
});

test('clearing the category restores the disabled select-category state', () => {
    const form = formFor('1', '101', '800');
    environment([form]);
    form.changeCategory('');
    assert.equal(form.checkpoint.value, '');
    assert.equal(form.checkpoint.disabled, true);
    assert.equal(form.checkpoint.options[0].textContent, 'Select a category first');
    assert.ok(form.checkpointGroups.every(group => group.hidden && group.disabled));
});

test('categories without recorded checkpoints have an explicit disabled state', () => {
    const form = formFor('1', '101', '800');
    environment([form]);
    form.changeCategory('999');
    assert.equal(form.checkpoint.value, '');
    assert.equal(form.checkpoint.disabled, true);
    assert.equal(form.checkpoint.options[0].textContent, 'No recorded checkpoints');
    assert.ok(form.checkpointGroups.every(group => group.hidden && group.disabled));
    form.changeCategory('101');
    assert.equal(form.checkpoint.disabled, false);
    assert.equal(form.checkpoint.options[0].textContent, 'All checkpoints');
});

test('incomplete forms do not bind partial listeners or crash navigation', () => {
    const form = formFor('1', '101');
    const original = form.elements.namedItem;
    form.elements.namedItem = name => name === 'checkpoint_id' ? null : original(name);
    const env = environment([form]);
    env.dispatch('livewire:navigated');
    assert.equal(form.listeners.length, 0);
    assert.equal(form.categoryListeners.length, 0);
    assert.notEqual(form.dataset.historyBound, 'true');
});
