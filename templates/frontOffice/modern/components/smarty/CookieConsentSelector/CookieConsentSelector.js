import { setCookie } from '@utils/cookies';

export default function CookieConsentSelector() {
	const $inputs = [
		...document.querySelectorAll('.CookieConsentSelector-input input')
	];
	const $saved = document.querySelector('.CookieConsentSelector-saved');

	if (!$inputs) return;

	$inputs.forEach((elem) => {
		elem.addEventListener('change', (e) => {
			e.preventDefault();
			if (elem.checked) {
				setCookie('cookie_consent', elem.value, 30);
				$saved.classList.add('is-active');
				setTimeout(() => {
					$saved.classList.remove('is-active');
				}, 2000);
			}
		});
	});
}
