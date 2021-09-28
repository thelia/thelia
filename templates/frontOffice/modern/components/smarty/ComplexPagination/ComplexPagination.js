import InfiniteScroll from 'infinite-scroll';

const ComplexPagination = () => {
  const grid = document.getElementById('GridProduct');

  if (!grid) return false;

  const total = +grid.dataset.total;
  const unit = grid.dataset.unit;

  if (!document.querySelector('.pagination__next')) {
    return;
  }

  const infScroll = new InfiniteScroll(grid, {
    path: '.pagination__next',
    checkLastPage: false,
    append: unit,
    history: 'replace',
    responseType: 'document',
    button: '.ComplexPaginationButton',
    status: '.LoadStatus',
    scrollThreshold: false
  });

  infScroll.on('request', function (response, path) {
    document.getElementById('ScrollController').style.display = 'none';
  });

  infScroll.on('append', function (response, path) {
    if (total === grid.querySelectorAll(unit).length) {
      document.getElementById('ScrollController').style.display = 'none';
    } else {
      document.getElementById('ScrollController').style.display = 'flex';
    }
  });

  infScroll.on('history', function (title, path) {
    console.log(title, path);
  });
};

export default ComplexPagination;
