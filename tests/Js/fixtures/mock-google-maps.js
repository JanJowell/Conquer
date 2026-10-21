// Only the Maps interfaces used by the editor. No requests go to Google.
class Events {
    listeners = {};
    addListener(name, listener) { (this.listeners[name] ??= []).push(listener); }
    emit(name, ...args) { (this.listeners[name] ?? []).forEach((listener) => listener(...args)); }
}
class LatLng {
    constructor(value) { this.value = value; }
    lat() { return this.value.lat; }
    lng() { return this.value.lng; }
}
class MVCArray extends Events {
    constructor(points) { super(); this.points = points.map((point) => new LatLng(point)); }
    getAt(index) { return this.points[index]; }
    getLength() { return this.points.length; }
    push(point) { this.points.push(point); this.emit('insert_at', this.points.length - 1); }
    setAt(index, point) { this.points[index] = point; this.emit('set_at', index); }
    removeAt(index) { this.points.splice(index, 1); this.emit('remove_at', index); }
    clear() { while (this.points.length) this.removeAt(this.points.length - 1); }
}
class Map extends Events {
    constructor(element) {
        super();
        this.element = element;
        window.mockMaps.push(this);
    }
    fitBounds() {}
    setCenter() {}
    setZoom() {}
}
class Polyline extends Events {
    constructor(options) { super(); this.path = new MVCArray(options.path); window.mockLines.push(this); }
    getPath() { return this.path; }
    setEditable(value) { this.editable = value; }
    setMap() {}
}
class OverlayView {
    setMap(map) {
        if (!map) return this.onRemove();
        this.map = map;
        this.onAdd();
        this.draw();
    }
    getPanes() { return { overlayLayer: this.map.element }; }
    getProjection() { return { fromLatLngToDivPixel: () => ({ x: 100, y: 100 }) }; }
}
window.mockMaps = [];
window.mockLines = [];
window.google = { maps: {
    Map, Polyline, LatLng, OverlayView,
    LatLngBounds: class { extend() {} },
    event: { trigger: (object, name) => object.emit(name), clearInstanceListeners: (object) => { object.listeners = {}; } },
} };
window.__racetechMapsReady();
