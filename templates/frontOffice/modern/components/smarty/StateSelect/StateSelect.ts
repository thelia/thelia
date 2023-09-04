export default function StateSelect() {
  let originalStateInput = document.querySelector('[name$="[state]"]');

  const stateInputCopy = originalStateInput?.cloneNode(true);

  document.addEventListener(
    'change',
    (e) => {
      if ((e.target as HTMLInputElement)?.matches('[name$="[country]"]')) {
        const countryId = (e.target as HTMLInputElement).value;
        const stateInput = document.querySelector('[name$="[state]"]');
        const validStates = (stateInputCopy as Document)?.querySelectorAll(
          `[data-country="${countryId}"]`
        );

        if (validStates.length) {
          const stateInput = document.querySelector('[name$="[state]"]');
          if (stateInput) {
            stateInput.innerHTML = '';
          }
          stateInput?.closest('.StateSelect')?.classList.remove('hidden');
          for (const state of validStates) {
            stateInput?.appendChild(state);
          }
        } else {
          stateInput?.closest('.StateSelect')?.classList.add('hidden');
        }
      }
    },
    false
  );
}
