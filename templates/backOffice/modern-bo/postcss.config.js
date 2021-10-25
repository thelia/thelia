module.exports = {
  plugins: [
    require("postcss-easy-import"),
    require("tailwindcss"),
    require("postcss-nested")({ bubble: ["screen"] }),
    require("autoprefixer"),
  ],
};
