export default () => {
  for (const form of document.querySelectorAll('.Cart-form')) {
    form
      ?.querySelector('select[name=quantity]')
      ?.addEventListener('change', function () {
        (form as HTMLFormElement).submit();
      });
    form
      ?.querySelector('input[name=quantity]')
      ?.addEventListener('change', function () {
        (form as HTMLFormElement).submit();
      });
    form
      ?.querySelector('.Button-change-country')
      ?.addEventListener('click', function (e) {
        e.preventDefault();
        (form as HTMLFormElement).submit();
      });
  }
};
