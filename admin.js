// Small helper for admin actions that call the JSON endpoints (approve, reject, delete).
function adminPost(url, data) {
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var body = new FormData();
    Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
    return fetch(url, { method: 'POST', body: body, headers: { 'X-CSRF-Token': token } })
        .then(function (res) {
            return res.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; });
        })
        .then(function (json) {
            if (!json.ok) { throw new Error(json.error || 'Something went wrong.'); }
            return json;
        });
}

function showPageMessage(text, type) {
    var box = document.getElementById('page-message');
    if (!box) { alert(text); return; }
    box.className = 'ap-alert ap-alert-' + (type || 'success');
    box.textContent = text;
    box.hidden = false;
}
