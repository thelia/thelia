export default function getLocale() {
  return (
    document.documentElement.lang ||
    (navigator.languages && navigator.languages[0]) ||
    navigator.language ||
    navigator.userLanguage ||
    'en-US'
  );
}
