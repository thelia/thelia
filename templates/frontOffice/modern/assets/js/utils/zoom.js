export default function zoom() {
  const images = document.querySelectorAll('[data-image-zoom]');

  if (images.length < 1) return null;

  images.forEach((image) => {
    const zoomer = document.createElement('div');

    zoomer.setAttribute('class', 'Zoomer is-visible');

    image.addEventListener('mousemove', (e) => {
      const backgroundImage = 'url(' + image.src + ')';

      image.parentElement?.insertBefore(zoomer, image);

      zoomer.classList.add('is-visible');

      const x = (e.offsetX / image.offsetWidth) * 100;
      const y = (e.offsetY / image.offsetHeight) * 100;
      const backgroundPosition = x + '% ' + y + '%';

      zoomer.style.left = e.offsetX + 'px';
      zoomer.style.top = e.offsetY + 'px';

      setZoom(
        zoomer,
        backgroundImage,
        backgroundPosition,
        e.offsetX + 'px',
        e.offsetY + 'px'
      );
    });

    image.addEventListener('mouseout', (e) => {
      zoomer.classList.remove('is-visible');
      setZoom(zoomer, '', '', 0, 0);
    });
  });
}

function setZoom(z, bgImg, bgPosition, zLeft, zTop) {
  z.style.backgroundImage = bgImg;
  z.style.backgroundPosition = bgPosition;
  z.style.left = zLeft + 'px';
  z.style.top = zTop + 'px';
}
