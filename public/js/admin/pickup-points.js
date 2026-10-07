/* pickup-points.js — from views/admin/pickup_points.php, loaded where the inline script used to run. */
const pickupPointsData = JSON.parse(document.getElementById('pickup-points-data').textContent);

(function () {
    if (typeof L === 'undefined') return;
    const map = L.map('ppMap').setView([35.05, 33.2], 8);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const existing = pickupPointsData;
    existing.forEach(p => {
        L.circleMarker([p.lat, p.lng], { radius: 6, color: p.active ? '#16130F' : '#9CA3AF', fillOpacity: 0.8 })
            .bindTooltip(p.name).addTo(map);
    });

    let marker = null;
    map.on('click', e => {
        const { lat, lng } = e.latlng;
        document.getElementById('ppLat').value = lat.toFixed(6);
        document.getElementById('ppLng').value = lng.toFixed(6);
        if (marker) marker.setLatLng(e.latlng);
        else marker = L.circleMarker(e.latlng, { radius: 8, color: '#C83017', fillOpacity: 0.9 }).addTo(map);
    });
})();
