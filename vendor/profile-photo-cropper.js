// This script assumes Cropper.js is loaded and available as window.Cropper
let cropper = null;
let cropperModal = null;

function showCropperModal(imageSrc) {
  // Remove existing modal if any
  if (cropperModal) cropperModal.remove();

  cropperModal = document.createElement('div');
  cropperModal.className = 'cropper-modal-bg';
  cropperModal.innerHTML = `
    <div class="cropper-modal-content">
      <img id="cropperImage" src="${imageSrc}" alt="Crop Image" />
      <div class="cropper-modal-actions">
        <button id="cropBtn" class="btn">Crop & Save</button>
        <button id="cancelCropBtn" class="btn btn-cancel">Cancel</button>
      </div>
    </div>
  `;
  document.body.appendChild(cropperModal);

  const image = document.getElementById('cropperImage');
  cropper = new window.Cropper(image, {
    aspectRatio: 1,
    viewMode: 1,
    autoCropArea: 1,
    movable: true,
    zoomable: true,
    scalable: true,
    rotatable: false,
    responsive: true,
    background: false
  });

  document.getElementById('cropBtn').onclick = function() {
    const canvas = cropper.getCroppedCanvas({
      width: 400,
      height: 400,
      imageSmoothingQuality: 'high'
    });
    canvas.toBlob(function(blob) {
      // Set the blob as the file input for upload
      const fileInput = document.getElementById('profilePhotoInput');
      const dataTransfer = new DataTransfer();
      const croppedFile = new File([blob], 'cropped_profile.png', {type: 'image/png'});
      dataTransfer.items.add(croppedFile);
      fileInput.files = dataTransfer.files;
      // Update preview
      document.getElementById('profilePhotoPreview').src = canvas.toDataURL('image/png');
      // Remove modal
      cropper.destroy();
      cropperModal.remove();
    }, 'image/png', 1);
  };
  document.getElementById('cancelCropBtn').onclick = function() {
    cropper.destroy();
    cropperModal.remove();
  };
}

// Hook into the file input
window.addEventListener('DOMContentLoaded', function() {
  const fileInput = document.getElementById('profilePhotoInput');
  if (!fileInput) return;
  fileInput.addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
      alert('File is too large. Max size is 10 MB.');
      event.target.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      showCropperModal(e.target.result);
    };
    reader.readAsDataURL(file);
  });
});
