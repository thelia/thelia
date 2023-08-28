export default function HandleRecaptcha(form) {
  if (!form) return null;

  const inputs = form.querySelectorAll('input, textarea');

  [...inputs].forEach((input) => {
    input.addEventListener('focus', () => {
      if (document.getElementById('ScriptRecaptcha') || !window.SCRIPTRECAPTCHA)
        return null;

      const scriptTag = document.createElement('script');
      scriptTag.setAttribute('src', window.SCRIPTRECAPTCHA);
      scriptTag.setAttribute('id', 'ScriptRecaptcha');
      form.appendChild(scriptTag);
    });
  });

  form.addEventListener('validRecaptcha', function (e) {
    if (!document.getElementById('ScriptRecaptcha')) return null;
    form.querySelector('#g-recaptcha-response').value = e.detail.token;
    form.submit();
  });
}
