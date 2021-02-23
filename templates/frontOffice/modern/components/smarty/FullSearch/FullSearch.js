export default () => {
  document.addEventListener("click", e => {
    if (e.target.matches("[data-toggle-search]")) {
      document.querySelector(".FullSearch").classList.toggle("hidden");
    }
  });
};
