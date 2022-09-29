export default function ProductGallery() {
  const main = document.getElementById('MainImage');

  const thumbs = document.querySelectorAll('.ProductGallery-thumbnail');

  if (!main || thumbs.length === 0) return null;

  [...thumbs].forEach((img) => {
    img.addEventListener('click', (e) => {
      resetFocus();
      main.src =
        '/legacy-image-library/product_image_' +
        img.dataset.imageId +
        '/full/!525,/0/default.webp';

      img.classList.add('is-active');
    });
  });

  function resetFocus() {
    [...thumbs].forEach((el) => el.classList.remove('is-active'));
  }
}
