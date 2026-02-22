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
            const orientation = document.querySelector('input[name="ppm_orientation"]:checked');
            const isPortrait  = orientation && orientation.value === 'portrait';
            const reqW = isPortrait ? 1080 : 1920;
            const reqH = isPortrait ? 1920 : 1080;

            attachments.forEach(function (att) {
                const w = att.width, h = att.height;
                if ( w && h && ( w !== reqW || h !== reqH ) ) {
                    const ok = window.confirm(
                        'Warning: "' + att.filename + '" is ' + w + '×' + h +
                        ' but the playlist is set to ' + reqW + '×' + reqH +
                        '.\n\nAdd it anyway?'
                    );
                    if ( !ok ) return;
                }
                const row = buildRow({
                    attachment_id: att.id,
                    thumb: att.sizes?.thumbnail?.url || att.url,
                    duration: '',
                    frequency: ( window.ppmData && window.ppmData.globalFrequency ) ? window.ppmData.globalFrequency : 1,
                });
                list.appendChild(row);
                reindexItems();
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
        const idx = document.querySelectorAll('.ppm-item').length;
        li.innerHTML = `
            <span class="ppm-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
            <img src="${escHtml(item.thumb)}" class="ppm-thumb" alt="">
            <input type="hidden" name="ppm_items[${idx}][attachment_id]" value="${escHtml(String(item.attachment_id))}">
            <div class="ppm-item-fields">
                <label>Duration (s)<input type="number" name="ppm_items[${idx}][duration]" min="1" placeholder="Global" value="${escHtml(String(item.duration))}"></label>
                <label>Frequency<input type="number" name="ppm_items[${idx}][frequency]" min="1" value="${escHtml(String(item.frequency))}"></label>
            </div>
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
            reindexItems();
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
                reindexItems();
            }
            li.classList.remove('ppm-drag-over');
        });
    }

    /* ── Reindex input names after reorder ────────────────────── */
    function reindexItems() {
        document.querySelectorAll('#ppm-items-list .ppm-item').forEach(function (li, idx) {
            li.querySelectorAll('input').forEach(function (input) {
                if (input.name) {
                    input.name = input.name.replace(/ppm_items\[\d+\]/, 'ppm_items[' + idx + ']');
                }
            });
        });
    }

    /* ── Orientation toggle ───────────────────────────────────── */
    document.querySelectorAll('.ppm-orientation-toggle input[type=radio]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.ppm-orient-btn').forEach(function (btn) {
                btn.classList.remove('active');
            });
            radio.closest('.ppm-orient-btn').classList.add('active');
        });
    });

    /* ── Force Reload ─────────────────────────────────────────── */
    const forceBtn = document.getElementById('ppm-force-reload');
    if ( forceBtn ) {
        forceBtn.addEventListener('click', function () {
            const postId = forceBtn.dataset.postId;
            forceBtn.disabled = true;
            forceBtn.textContent = 'Sending…';
            fetch( ppmData.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=ppm_force_reload&post_id=' + postId + '&nonce=' + ppmData.forceNonce,
            })
            .then( function (r) { return r.json(); } )
            .then( function (data) {
                if ( data.success ) {
                    forceBtn.textContent = '✓ Reload triggered';
                    setTimeout( function () {
                        forceBtn.disabled = false;
                        forceBtn.textContent = '↻ Force Reload';
                    }, 3000 );
                } else {
                    forceBtn.textContent = '✗ Failed';
                    forceBtn.disabled = false;
                }
            })
            .catch( function () {
                forceBtn.textContent = '✗ Error';
                forceBtn.disabled = false;
            });
        });
    }

})();
