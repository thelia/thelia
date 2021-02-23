import MicroModal from 'micromodal';
import axios from 'axios';

export default async () => {
	const togglers = document.querySelectorAll('[data-toggle-modal]');
	const parser = new DOMParser();
	let route = null;

	[...togglers].forEach((toggler, index) => {
		toggler.addEventListener('click', async (e) => {
			if (e.target.classList.contains('is-loading')) {
				return;
			}
			e.target.classList.add('is-loading');
			const nonCurrent = [...togglers].filter((p, i) => i !== index);
			nonCurrent.forEach((btn) => btn.classList.add('pointer-events-none'));
			route = e.target.getAttribute('data-toggle-modal');

			try {
				const { data } = await axios.get(route);

				const html = parser.parseFromString(data, 'text/html');

				modalLoad(html.querySelector('form'), e.target, nonCurrent);
			} catch (error) {
				console.error(error);
				nonCurrent.forEach((btn) => btn.classList.add('pointer-events-none'));
			}
		});
	});

	const modalLoad = (form, btn, btnNonCUrrent) => {
		const content = document.getElementById('modalContent');
		const id = form.id;
		if (content.querySelector(id)) {
			content.querySelector(id).remove();
		}

		MicroModal.show('AjaxModal', {
			onShow: (modal) => {
				btn.classList.remove('is-loading');
				btnNonCUrrent.forEach((btn) =>
					btn.classList.remove('pointer-events-none')
				);
				content.append(form);
				manageForm(form);
			},
			onClose: (modal) => {
				content.removeChild(form);
			},
			awaitOpenAnimation: true
		});
	};

	const manageForm = (form) => {
		form.addEventListener('submit', async function (e) {
			e.preventDefault();

			const formUrl = form.getAttribute('action');
			const formData = new FormData(form);

			const fieldset = form.querySelector('#modal-fieldset');
			const info = form.querySelector('#modal-info');
			const alert = form.querySelector('#modal-alert');

			form.classList.add('is-loading');

			try {
				const { data } = await axios({
					method: 'POST',
					url: formUrl,
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
					data: formData
				});
				form.classList.remove('is-loading');

				if (data.success) {
					fieldset.remove();
					const message = info.appendChild(document.createElement('p'));
					message.classList.add('font-heading');
					message.innerHTML = data.message;
				} else {
					alert.classList.remove('hidden');
					alert.innerHTML = data.message;
				}
			} catch (error) {
				alert.classList.remove('hidden');
				alert.innerHTML = error.message;
				form.classList.remove('is-loading');
			}
		});
	};
};
