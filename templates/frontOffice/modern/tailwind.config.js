const colors = require('tailwindcss/colors');

module.exports = {
  content: [
    './**/*.html',
    './**/*.tpl',
    './components/**/*.css',
    '!(node_modules)/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        main: colors.orange[400],
        'main-dark': colors.orange[600]
      }
    }
  },
  variants: {
    extend: {}
  },
  plugins: [require('@tailwindcss/forms')]
};
