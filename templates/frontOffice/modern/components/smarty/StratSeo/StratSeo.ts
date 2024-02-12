export default function StratSeo() {
  const observer = new IntersectionObserver(
    (observables) => {
      observables.forEach((observable) => {
        if (observable.intersectionRatio > 0.4) {
          observable.target.classList.remove('is-hide');
          observer.unobserve(observable.target);
        }
      });
    },
    {
      threshold: [0.4]
    }
  );

  const strat = document.getElementById('StratSeo');

  observer.observe(strat as Element);
}
