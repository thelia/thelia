export default function closeAndFocus(
  dispatchAction = () => {},
  focusSelector: any
) {
  dispatchAction();
  const refocus = Array.from(document.querySelectorAll(focusSelector));
  refocus[refocus.length - 1]?.focus();
}
