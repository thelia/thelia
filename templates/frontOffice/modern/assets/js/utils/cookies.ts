export function getCookie(name: string) {
  const match = document.cookie.match(new RegExp(name + '=([^;]+)'));
  if (match) return match[1];
  return null;
}

export function setCookie(name: string, value: string, days?: number) {
  var expires = '';
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    expires = '; expires=' + date.toString();
  }
  document.cookie = name + '=' + value + expires + '; path=/';
}

const utils = { getCookie, setCookie };

export default utils;
