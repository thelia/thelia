module.exports = {
  purge: [
    './**/*.html',
    '.**/*.tpl',
    '/components/**/*.css',
    '!(node_modules)/**/*.js'
  ],
  //mode: "jit",
  darkMode: false,
  theme: {},
  variants: {
    extend: {}
  },
  plugins: [require('@tailwindcss/forms')]
};
