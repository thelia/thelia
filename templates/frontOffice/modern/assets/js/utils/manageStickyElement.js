const VW_TO_DISABLE = 769;
const INTERSECTION_RATIO_LIMIT = 1;

const createIntersectionObserver = (classToToggle, callback) =>
  new IntersectionObserver(
    ([e]) => {
      if (
        window.innerWidth < VW_TO_DISABLE ||
        (e.boundingClientRect.width === 0 && e.boundingClientRect.height === 0)
      )
        return;

      const IS_STUCK =
        e.intersectionRatio < INTERSECTION_RATIO_LIMIT &&
        e.boundingClientRect.y <= 0;

      document.body.classList.toggle(classToToggle, IS_STUCK);

      if (callback) {
        callback(e, IS_STUCK);
      }
    },
    { threshold: [1] }
  );

const observeStickyElement = (element, classToToggle, callback) => {
  const observer = createIntersectionObserver(classToToggle, callback);

  observer.observe(element);
};

export default observeStickyElement;
