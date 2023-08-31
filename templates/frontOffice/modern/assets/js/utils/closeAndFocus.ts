export default function closeAndFocus(
  dispatchAction = () => {},
  focusSelector: string
) {
  dispatchAction();
  const refocus = Array.from(document.querySelectorAll(focusSelector));
  let elementToRefocus = refocus[refocus.length - 1];
  if (elementToRefocus) {
    const e = new Event('focus');
    refocus[refocus.length - 1]?.dispatchEvent(e);
  }
}
