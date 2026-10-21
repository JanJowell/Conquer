export const MAX_POINTS = 1000;
export const MAX_CHECKPOINTS = 50;
export const MAX_LABEL_LENGTH = 120;

export const emptyCourse = () => ({ version: 1, path: [], checkpoints: [] });
export const copyCourse = (course) => JSON.parse(JSON.stringify(course));

export function coordinate(lat, lng) {
    const numeric = (value) => (typeof value === 'number' || typeof value === 'string' && value.trim() !== '') && Number.isFinite(Number(value));
    if (!numeric(lat) || !numeric(lng) ||
        Number(lat) < -90 || Number(lat) > 90 || Number(lng) < -180 || Number(lng) > 180) {
        throw new Error('Enter a valid latitude (-90 to 90) and longitude (-180 to 180).');
    }
    return { lat: Number(Number(lat).toFixed(6)), lng: Number(Number(lng).toFixed(6)) };
}

export function validateCourse(course) {
    if (!course || course.version !== 1 || !Array.isArray(course.path) || !Array.isArray(course.checkpoints)) {
        throw new Error('The saved course format is invalid. Discard or remove it before saving.');
    }
    if (course.path.length < 2 || course.path.length > MAX_POINTS) {
        throw new Error(`Draw a course with 2 to ${MAX_POINTS} route points.`);
    }
    if (course.checkpoints.length > MAX_CHECKPOINTS) {
        throw new Error(`A course supports up to ${MAX_CHECKPOINTS} checkpoints.`);
    }
    const path = course.path.map((point) => coordinate(point?.lat, point?.lng));
    if (new Set(path.map((point) => `${point.lat},${point.lng}`)).size < 2) {
        throw new Error('The course needs at least two different route points.');
    }
    const checkpoints = course.checkpoints.map((point) => {
        if (typeof point?.label !== 'string' || !point.label.trim() || [...point.label.trim()].length > MAX_LABEL_LENGTH) {
            throw new Error(`Each checkpoint needs a label of 1 to ${MAX_LABEL_LENGTH} characters.`);
        }
        return { ...coordinate(point.lat, point.lng), label: point.label.trim() };
    });
    return { version: 1, path, checkpoints };
}

export function parseCourse(value) {
    return value ? validateCourse(JSON.parse(value)) : emptyCourse();
}

export function courseSummary(course) {
    return course.path.length
        ? `${course.path.length} route points and ${course.checkpoints.length} checkpoints. Saved with this category.`
        : 'No drawn course yet. Your uploaded map image remains available separately.';
}
