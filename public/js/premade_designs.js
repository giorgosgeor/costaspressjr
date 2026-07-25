let isEditMode = false;

function openAddModal() {
    isEditMode = false;
    document.getElementById('modalTitle').textContent = 'Add New Design';
    document.getElementById('submitBtn').textContent = 'Add Design';
    document.getElementById('designForm').reset();
    document.getElementById('designId').value = '';
    document.getElementById('designForm').action = '/admin/premade/add';
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('activeGroup').style.display = 'none';
    document.querySelectorAll('#productCheckboxes input[type="checkbox"]').forEach(cb => cb.checked = false);
    document.getElementById('designModal').classList.add('active');
}

function openEditModal(designId) {
    isEditMode = true;
    document.getElementById('modalTitle').textContent = 'Edit Design';
    document.getElementById('submitBtn').textContent = 'Save Changes';
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('designForm').style.display = 'none';
    document.getElementById('designForm').action = '/admin/premade/edit/' + designId;
    document.getElementById('activeGroup').style.display = 'block';
    document.getElementById('designModal').classList.add('active');

    fetch('/admin/api/premade/' + designId, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            document.getElementById('designId').value = data.design.id;
            document.getElementById('section_id').value = data.design.section_id;
            document.getElementById('designName').value = data.design.name;
            document.getElementById('designDescription').value = data.design.description || '';
            document.getElementById('designPrice').value = data.design.price;
            document.getElementById('designActive').checked = data.design.active == 1;

            if (data.design.image_path) {
                document.getElementById('previewImg').src = '/' + data.design.image_path;
                document.getElementById('currentImagePreview').style.display = 'block';
                document.getElementById('removeImage').checked = false;
            } else {
                document.getElementById('currentImagePreview').style.display = 'none';
            }

            document.querySelectorAll('#productCheckboxes input[type="checkbox"]').forEach(cb => {
                cb.checked = data.product_ids.includes(parseInt(cb.value));
            });

            document.getElementById('modalLoading').style.display = 'none';
            document.getElementById('designForm').style.display = 'block';
        })
        .catch(() => { alert('Failed to load design data'); closeModal(); });
}

function closeModal() {
    document.getElementById('designModal').classList.remove('active');
}

function filterBySection() {
    const section = document.getElementById('sectionFilter').value;
    document.querySelectorAll('#designsTable tr[data-section]').forEach(row => {
        row.style.display = (!section || row.dataset.section === section) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-confirm').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this design?')) {
                e.preventDefault();
            }
        });
    });

    document.getElementById('designModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
});
