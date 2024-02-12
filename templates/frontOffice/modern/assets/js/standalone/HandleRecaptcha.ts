export default function HandleRecaptcha(form: any) {
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
      return null;
    });
  });

  form.addEventListener('validRecaptcha', function (e: CustomEvent) {
    if (!document.getElementById('ScriptRecaptcha')) return null;
    form.querySelector('#g-recaptcha-response').value = e.detail.token;
    form.submit();
    return null;
  });

  return null;
}
