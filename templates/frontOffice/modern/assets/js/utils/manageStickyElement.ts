const VW_TO_DISABLE = 769;
const INTERSECTION_RATIO_LIMIT = 1;

const createIntersectionObserver = (
  classToToggle: string,
  callback: (e?: IntersectionObserverEntry, bool?: boolean) => void
) =>
  new IntersectionObserver(
    ([e]) => {
      if (
        window.innerWidth < VW_TO_DISABLE ||
        (e?.boundingClientRect.width === 0 &&
          e?.boundingClientRect.height === 0)
      )
        return;

      const IS_STUCK =
        e &&
        e.intersectionRatio < INTERSECTION_RATIO_LIMIT &&
        e.boundingClientRect.y <= 0;

      document.body.classList.toggle(classToToggle, IS_STUCK);

      if (callback) {
        callback(e, IS_STUCK);
      }
    },
    { threshold: [1] }
  );

const observeStickyElement = (
  element: Element,
  classToToggle: string,
  callback: (e?: IntersectionObserverEntry, bool?: boolean) => void
) => {
  const observer = createIntersectionObserver(classToToggle, callback);

  observer.observe(element);
};

export default observeStickyElement;
