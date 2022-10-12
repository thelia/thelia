export default function Selection() {

  const observer = new IntersectionObserver((observables) => {
    observables.forEach((observable) => {
      if (observable.intersectionRatio > 0.4) {
        observable.target.classList.remove('is-hide')
        observer.unobserve(observable.target);
      }
    })
  }, {
    threshold: [0.4]
  });

  const selections = document.querySelectorAll('.Selection--animated');

  [...selections].forEach((selection) => {
    selection.classList.add('is-hide');
    observer.observe(selection);
  })
}
