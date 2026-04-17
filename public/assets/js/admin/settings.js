document.getElementById('btnCopyBookmarklet').addEventListener('click', function () {
    navigator.clipboard.writeText(document.getElementById('bookmarkletCode').value).then(() => {
        const icon = this.querySelector('i');
        icon.className = 'bi bi-check-lg text-success';
        setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
    });
});
