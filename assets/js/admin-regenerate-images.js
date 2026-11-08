/**
 * Regenerates existing images from the dashboard, one batch at a time.
 */
(function () {
    var root = document.getElementById('wpb-regenerate-images');

    if (!root) {
        return;
    }

    var button = root.querySelector('button');
    var status = root.querySelector('[data-status]');

    if (!button || !status) {
        return;
    }

    button.addEventListener('click', function () {
        button.disabled = true;
        regenerate(0, 0);
    });

    function regenerate(offset, failed) {
        var body = new FormData();

        body.append('action', 'wpb_regenerate_images');
        body.append('nonce', root.getAttribute('data-nonce') || '');
        body.append('offset', String(offset));
        body.append('failed', String(failed));

        fetch(root.getAttribute('data-ajax') || '', {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            var data = payload && payload.data ? payload.data : null;

            if (!payload || !payload.success || !data) {
                fail();
                return;
            }

            status.textContent = data.message || '';

            if (data.done) {
                button.disabled = false;
                return;
            }

            regenerate(Number(data.offset) || 0, Number(data.failed) || 0);
        }).catch(function () {
            fail();
        });
    }

    function fail() {
        status.textContent = root.getAttribute('data-error') || '';
        button.disabled = false;
    }
})();
