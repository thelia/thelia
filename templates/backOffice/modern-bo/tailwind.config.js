module.exports = {
  purge: [
    './**/*.html',
    '.**/*.tpl',
    '/components/**/*.css',
    '!(node_modules)/**/*.js'
  ],
  //mode: "jit",
  darkMode: false,

  theme: {
    extend: {
      colors: {
        main: '#ec6352',
        darkGray: '#333333',
        thelia: '#f39922'
      }
    }
  },
  variants: {
    extend: {}
  },
  plugins: [require('@tailwindcss/forms')]
};
