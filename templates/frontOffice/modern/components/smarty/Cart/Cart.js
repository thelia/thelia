export default () => {
  for (const form of document.querySelectorAll(".Cart-form")) {
    form
      .querySelector("select[name=quantity]")
      .addEventListener("change", function() {
        form.submit();
      });
    form
      .querySelector("input[name=quantity]")
      .addEventListener("change", function() {
        form.submit();
      });
    form
      .querySelector(".btn-change-country")
      .addEventListener("click", function(e) {
        e.preventDefault();
        form.submit();
      });
  }
};
