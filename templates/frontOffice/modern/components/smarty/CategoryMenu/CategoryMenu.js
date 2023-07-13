import axios from 'axios';

const updateProducts = async (url) => {
  const gridWrapper = document.getElementById('GridProductWrapper');

  gridWrapper.classList.add('is-loading');
  try {
    const { data } = await axios.get(url);
    const parser = new DOMParser();
    const page = parser.parseFromString(data, 'text/html');

    document
      .getElementById('GridProductWrapper')
      .replaceWith(page.getElementById('GridProductWrapper'));

    updateproductCount();

    global.history.replaceState({}, 'recherche', `${url}`);
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error(e);
    alert('error while fetching products');
  }
  gridWrapper.classList.remove('is-loading');
};

export default function CategoryMenu() {
  const order = document.getElementById('filterBy');

  updateproductCount();

  document.addEventListener('click', (e) => {
    if (e.target.matches('[data-toggle-filters]')) {
      document.querySelector('body').classList.toggle('CategoryMenu--active');

      document
        .querySelector('.CategoryMenu-wrapper')
        .classList.toggle('visible');

      // need to focus when opening overlay
      const targetButtonClose = document.querySelector('.CategoryMenu-close');

      if (
        !targetButtonClose ||
        !document
          .querySelector('body')
          .classList.contains('CategoryMenu--active')
      ) {
        document.getElementById('FilterBtn').focus();
        return;
      }

      targetButtonClose.focus();
    }
  });

  document
    .querySelector('form[data-form-filters]')
    ?.addEventListener('change', (e) => {
      const form = e.currentTarget;
      const formdata = form ? new FormData(form) : new FormData();
      if (order) {
        formdata.append('order', order?.value);
      }

      updateProducts(
        `?view=category&${new URLSearchParams(formdata).toString()}`
      );
    });

  order.addEventListener('change', (e) => {
    const form = e.currentTarget.form;
    const formdata = form ? new FormData(form) : new FormData();
    formdata.append('order', order?.value);

    updateProducts(
      `?view=category&${new URLSearchParams(formdata).toString()}`
    );
  });
}

const updateproductCount = () => {
  const productCount =
    document.getElementById('GridProduct')?.dataset?.total || 0;
  document.getElementById('ProductFilterCount').innerHTML = `${productCount} ${
    productCount <= 1 ? 'Article' : 'Articles'
  }`;
  document
    .getElementById('ProductSort')
    .classList.toggle('hidden', productCount <= 0);
};
