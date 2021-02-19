export default () => {
  const wrapper = document.querySelector(".NewsAndPromo");
  const btns = wrapper.querySelectorAll(".NewsAndPromo-btn");
  const contents = wrapper.querySelectorAll(".NewsAndPromo-content");

  for (const btn of btns) {
    btn.addEventListener("click", function() {
      const target = btn.dataset.target;
      for (const button of btns) {
        button.classList.add("bg-purple-300");
        button.classList.add("text-main");
        button.classList.add("py-3");
        button.classList.add("text-lg");
        button.classList.remove("py-4");
        button.classList.remove("bg-main");
        button.classList.remove("text-2xl");
        this.classList.remove("bg-purple-300");
        this.classList.remove("text-main");
        this.classList.add("bg-main");
        this.classList.add("text-white");
        this.classList.add("py-4");
        this.classList.add("text-2xl");
      }
      for (const content of contents) {
        content.classList.add("hidden");
        document.querySelector(target).classList.remove("hidden");
      }
    });
  }
};
