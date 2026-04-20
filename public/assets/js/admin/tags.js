(function () {

    document.getElementById('tagFilter')?.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#tagTableBody tr').forEach(tr => {
            const tag = tr.dataset.tag ?? '';
            tr.style.display = tag.includes(q) ? '' : 'none';
        });
    });

    document.querySelectorAll('.btn-tag-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            const tag   = this.dataset.tag;
            const count = this.dataset.count;

            document.getElementById('tagOld').value        = tag;
            document.getElementById('tagNew').value        = tag;
            document.getElementById('tagDeleteName').value = tag;
            document.getElementById('tagModalInfo').textContent =
                'Tag « ' + tag + ' » utilisé dans ' + count + ' favori' + (count > 1 ? 's' : '') + '.';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('tagModal')).show();
        });
    });

    function showConfirm(title, subtitle, onConfirm) {
        document.getElementById('tagConfirmTitle').textContent    = title;
        document.getElementById('tagConfirmSubtitle').textContent = subtitle;

        const modal  = bootstrap.Modal.getOrCreateInstance(document.getElementById('tagConfirmModal'));
        const okBtn  = document.getElementById('tagConfirmOk');
        const newBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newBtn, okBtn);
        newBtn.addEventListener('click', function () {
            modal.hide();
            onConfirm();
        });
        modal.show();
    }

    document.getElementById('btnDeleteUnique')?.addEventListener('click', function () {
        const n = (this.title.match(/\d+/) ?? ['?'])[0];
        showConfirm(
            'Nettoyer ' + n + ' tag' + (n > 1 ? 's' : '') + ' solitaire' + (n > 1 ? 's' : '') + ' ?',
            'Ces tags ne sont utilisés que par un seul favori. Cette action est irréversible.',
            () => document.getElementById('deleteUniqueForm').submit()
        );
    });

    document.getElementById('btnTagDelete')?.addEventListener('click', function () {
        const tag = document.getElementById('tagOld').value;
        bootstrap.Modal.getInstance(document.getElementById('tagModal'))?.hide();
        showConfirm(
            'Supprimer le tag « ' + tag + ' » ?',
            'Ce tag sera retiré de tous les favoris. Cette action est irréversible.',
            () => document.getElementById('tagDeleteForm').submit()
        );
    });

})();
