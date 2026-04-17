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

            new bootstrap.Modal(document.getElementById('tagModal')).show();
        });
    });

    document.getElementById('btnDeleteUnique')?.addEventListener('click', function () {
        const n = this.title.match(/\d+/) ? this.title.match(/\d+/)[0] : '?';
        if (confirm('Supprimer ' + n + ' tag(s) utilisés par un seul favori ?\n\nCette action est irréversible.')) {
            document.getElementById('deleteUniqueForm').submit();
        }
    });

    document.getElementById('btnTagDelete')?.addEventListener('click', function () {
        const tag = document.getElementById('tagOld').value;
        if (confirm('Supprimer le tag « ' + tag + ' » de tous les favoris ?\n\nCette action est irréversible.')) {
            bootstrap.Modal.getInstance(document.getElementById('tagModal'))?.hide();
            document.getElementById('tagDeleteForm').submit();
        }
    });

})();
