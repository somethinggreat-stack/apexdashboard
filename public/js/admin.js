function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('open');
    }
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.tabs').forEach(group => {
        const tabs = group.querySelectorAll('.tab');

        function activate(tab) {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.target;
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.toggle('active', panel.id === target);
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                activate(tab);
                // Remember the open tab in the URL (no scroll, no history spam) so
                // a reload — e.g. after uploading a document — returns to the same
                // tab instead of resetting to the first one.
                if (tab.dataset.target) {
                    try { history.replaceState(null, '', '#' + tab.dataset.target); } catch (e) {}
                }
            });
        });

        // On load, honour a #tab-… hash so reloads land on the right tab.
        const want = (location.hash || '').slice(1);
        if (want) tabs.forEach(tab => { if (tab.dataset.target === want) activate(tab); });
    });

    const chatScroll = document.querySelector('[data-chat-scroll]');
    if (chatScroll) chatScroll.scrollTop = chatScroll.scrollHeight;
});

window.openModal = openModal;
window.closeModal = closeModal;

/* "Referred by" boxes on the Add/Edit Business Owner form are single-choice:
   ticking one referrer unticks the others (one referrer per business owner). */
document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.classList && el.classList.contains('ref-check') && el.checked) {
        document.querySelectorAll('input.ref-check[name="' + el.name + '"]').forEach(function (other) {
            if (other !== el) other.checked = false;
        });
    }
});
