import axios from 'axios';
import ComplexPagination from '@components/smarty/ComplexPagination/ComplexPagination';

const updateProducts = async (url) => {
  try {
    const { data } = await axios.get(url);
    const parser = new DOMParser();
    const page = parser.parseFromString(data, 'text/html');

    document
      .querySelector('.CategoryProducts')
      .replaceWith(page.querySelector('.CategoryProducts'));

    global.history.replaceState({}, 'recherche', `${url}`);
    ComplexPagination();
  } catch (e) {
    console.error(e);
    alert('error while fetching products');
  }
};

export default function CategoryMenu() {
  document.addEventListener('click', (e) => {
    if (e.target.matches('[data-toggle-form-filters]')) {
      document.querySelector('body').classList.toggle('CategoryMenu--active');
    }
  });

  document
    .querySelector('form[data-form-filters]')
    .addEventListener('change', (e) => {
      const form = e.currentTarget;
      const formdata = form ? new FormData(form) : new FormData();
      const order = document.querySelector(
        '.CategoryProducts-nav-order-select'
      );

      if (order) {
        formdata.append('order', order?.value);
      }

      updateProducts(
        `?view=category&${new URLSearchParams(formdata).toString()}`
      );
    });
}
