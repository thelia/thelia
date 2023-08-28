export default function Search() {
  const filter = document.getElementById('filterBy');

  filter?.addEventListener('change', (e) => {
    const formdata = new FormData();
    formdata.append('query', filter.dataset.query || '');
    formdata.append('order', (filter as any).value || '');
    document.location.href = `?view=search&${new URLSearchParams(
      formdata as any
    ).toString()}`;
  });
}
Search();
