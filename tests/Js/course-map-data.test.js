import test from 'node:test';
import assert from 'node:assert/strict';
import { coordinate, validateCourse, parseCourse, emptyCourse, copyCourse } from '../../public/js/course-map-data.js';

const course = () => ({
    version: 1,
    path: [{ lat: 14.46, lng: 120.96 }, { lat: 14.47, lng: 120.97 }],
    checkpoints: [{ lat: 14.465, lng: 120.965, label: 'Water station' }],
});

test('valid saved routes round-trip and normalize precision and labels', () => {
    const draft = course();
    draft.path[0].lat = '14.46000003';
    draft.checkpoints[0].label = '  Water station  ';
    assert.deepEqual(parseCourse(JSON.stringify(draft)), course());
    assert.deepEqual(parseCourse(''), emptyCourse());
});

test('invalid coordinates never silently become zero or wrap around the map', () => {
    for (const [lat, lng] of [[null, 120], [false, 120], ['', 120], [' ', 120], [undefined, 120], [91, 120], [14, 181], [NaN, 120], [Infinity, 120]]) {
        assert.throws(() => coordinate(lat, lng));
    }
});

test('incomplete, duplicate, oversized and malformed routes cannot be applied', () => {
    assert.throws(() => parseCourse('{bad'));
    for (const invalid of [
        emptyCourse(), { ...course(), version: 2 },
        { ...course(), path: [course().path[0]] },
        { ...course(), path: Array(2).fill(course().path[0]) },
        { ...course(), path: Array(1001).fill(course().path[0]) },
        { ...course(), checkpoints: Array(51).fill(course().checkpoints[0]) },
        { ...course(), checkpoints: [{ lat: 14, lng: 120, label: ' ' }] },
        { ...course(), checkpoints: [{ lat: 14, lng: 120, label: 'x'.repeat(121) }] },
    ]) assert.throws(() => validateCourse(invalid));
});

test('closed-loop routes remain valid and checkpoint text is preserved as data', () => {
    const loop = course();
    loop.path.push(loop.path[0]);
    loop.checkpoints[0].label = '<img src=x onerror=alert(1)>';
    assert.deepEqual(validateCourse(loop), loop);
});

test('draft history is isolated from the last applied route', () => {
    const saved = course();
    const draft = copyCourse(saved);
    draft.path[0].lat = 15;
    draft.checkpoints[0].label = 'Changed';
    assert.deepEqual(saved, course());
});
