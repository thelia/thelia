import axios from 'axios';

export default function Newsletter() {
  const forms = document.querySelectorAll('.Newsletter-form');

  [...forms].forEach((form) => {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      const formUrl = form.getAttribute('action');
      const formData = new FormData(form);

      const inputEmail = form.querySelector('input[type="email"]');
      inputEmail.classList.remove('is-valid');
      inputEmail.classList.remove('is-invalid');

      const invalidFeedback = form.querySelector('.invalid-feedback');
      const validFeedback = form.querySelector('.valid-feedback');
      invalidFeedback.classList.add('hidden');
      validFeedback.classList.add('hidden');

      try {
        const { data } = await axios({
          method: 'POST',
          url: formUrl,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          data: formData
        });

        if (data.success) {
          inputEmail.classList.add('is-valid');
          const btnSubmit = form.querySelector('button[type="submit"]');
          btnSubmit.parentNode.removeChild(btnSubmit);
          validFeedback.classList.remove('hidden');
        } else {
          form.querySelector('.invalid-feedback').innerText = data.message;
          inputEmail.classList.add('is-invalid');
          invalidFeedback.classList.remove('hidden');
        }
      } catch (error) {
        console.error('error', error.response.data);
        inputEmail.classList.add('is-invalid');
        if (error.response.data && error.response.data.message) {
          form.querySelector('.invalid-feedback').innerText =
            error.response.data.message;
          invalidFeedback.classList.remove('hidden');
        }
      }
    });
  });
}
