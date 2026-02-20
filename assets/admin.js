(function () {
    'use strict';

    /* ── Media Picker ─────────────────────────────────────────── */
    document.getElementById('ppm-add-images').addEventListener('click', function () {
        const frame = wp.media({
            title: 'Select Playlist Images',
            button: { text: 'Add to Playlist' },
            multiple: true,
        });

        frame.on('select', function () {
            const attachments = frame.state().get('selection').toJSON();
            const list = document.getElementById('ppm-items-list');
            attachments.forEach(function (att) {
                const row = buildRow({
                    attachment_id: att.id,
                    thumb: att.sizes?.thumbnail?.url || att.url,
                    duration: '',
                    frequency: 1,
                });
                list.appendChild(row);
            });
        });

        frame.open();
    });

    /* ── Row Builder ──────────────────────────────────────────── */
    function buildRow(item) {
        const li = document.createElement('li');
        li.className = 'ppm-item';
        li.draggable = true;
        li.dataset.id = item.attachment_id;
        li.innerHTML = `
            <span class="ppm-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
            <img src="${escHtml(item.thumb)}" class="ppm-thumb" alt="">
            <input type="hidden" name="ppm_items[][attachment_id]" value="${escHtml(String(item.attachment_id))}">
            <label>Duration (s): <input type="number" name="ppm_items[][duration]" min="1" placeholder="Global" value="${escHtml(String(item.duration))}"></label>
            <label>Frequency: <input type="number" name="ppm_items[][frequency]" min="1" value="${escHtml(String(item.frequency))}"></label>
            <button type="button" class="button-link ppm-remove-item">&#10005;</button>
        `;
        bindRowEvents(li);
        return li;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Remove Button ────────────────────────────────────────── */
    function bindRowEvents(li) {
        li.querySelector('.ppm-remove-item').addEventListener('click', function () {
            li.remove();
        });
        bindDragEvents(li);
    }

    // Bind remove on existing rows
    document.querySelectorAll('.ppm-item').forEach(function (li) {
        li.draggable = true;
        bindRowEvents(li);
    });

    /* ── Drag-and-Drop Reordering ─────────────────────────────── */
    let dragSrc = null;

    function bindDragEvents(li) {
        li.addEventListener('dragstart', function (e) {
            dragSrc = li;
            li.classList.add('ppm-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        li.addEventListener('dragend', function () {
            li.classList.remove('ppm-dragging');
            document.querySelectorAll('.ppm-item').forEach(function (el) {
                el.classList.remove('ppm-drag-over');
            });
        });

        li.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (li !== dragSrc) {
                document.querySelectorAll('.ppm-item').forEach(function (el) {
                    el.classList.remove('ppm-drag-over');
                });
                li.classList.add('ppm-drag-over');
            }
        });

        li.addEventListener('drop', function (e) {
            e.preventDefault();
            if (dragSrc && dragSrc !== li) {
                const list = li.parentNode;
                const items = Array.from(list.children);
                const srcIdx = items.indexOf(dragSrc);
                const tgtIdx = items.indexOf(li);
                if (srcIdx < tgtIdx) {
                    list.insertBefore(dragSrc, li.nextSibling);
                } else {
                    list.insertBefore(dragSrc, li);
                }
            }
            li.classList.remove('ppm-drag-over');
        });
    }
})();
