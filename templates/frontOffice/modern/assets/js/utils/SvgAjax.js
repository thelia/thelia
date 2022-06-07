export default async function SvgAjax() {
  if (!window.SVG_SPRITE_URL) return;

  const res = await fetch(window.SVG_SPRITE_URL);
  const data = await res.text();
  const svg = new DOMParser().parseFromString(data, 'image/svg+xml');
  const div = document.createElement('div');

  div.style.display = 'none';
  div.appendChild(svg.documentElement);
  document.body.insertBefore(div, document.body.childNodes[0]);
}
