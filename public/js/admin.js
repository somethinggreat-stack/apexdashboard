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
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const target = tab.dataset.target;
                document.querySelectorAll('.tab-panel').forEach(panel => {
                    panel.classList.toggle('active', panel.id === target);
                });
            });
        });
    });

    const chatScroll = document.querySelector('[data-chat-scroll]');
    if (chatScroll) chatScroll.scrollTop = chatScroll.scrollHeight;
});

window.openModal = openModal;
window.closeModal = closeModal;
