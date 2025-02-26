<script>
function deleteSize(ukuran) {
    const sizeInput = document.querySelector('input[name="size"]');
    let currentSizes = [];
    try {
        currentSizes = JSON.parse(sizeInput.value);
    } catch (e) {
        currentSizes = sizeInput.value ? sizeInput.value.split(',') : [];
    }
    currentSizes = currentSizes.filter(size => size !== ukuran);
    sizeInput.value = JSON.stringify(currentSizes);
    sizeInput.dispatchEvent(new Event('change', { bubbles: true }));
    // Hapus elemen dari DOM
    const sizeElement = document.querySelector(`[data-size="${ukuran}"]`);
    if (sizeElement) {
        sizeElement.remove();
    }
}
</script>