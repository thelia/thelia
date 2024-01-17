const colors = require('tailwindcss/colors');

module.exports = {
  content: [
    './**/*.html',
    './**/*.tpl',
    './components/**/*.css',
    '!(node_modules)/**/*.{js,tsx,ts}'
  ],
  theme: {
    extend: {
      screens: {
        xs: '350px',
        '2xl': '1440px'
      },
      colors: {
        main: colors.orange[400],
        'main-dark': colors.orange[600],
        'main-light': colors.orange[200],
        error: '#b91c1c'
      },
      container: {
        center: true
      }
    }
  },
  variants: {
    extend: {}
  },
  plugins: [
    require('@tailwindcss/forms'),
    function ({ addComponents }) {
      addComponents({
        '.container': {
          maxWidth: '100%',
          paddingLeft: '1.25rem',
          paddingRight: '1.25rem',
          '@screen sm': {
            maxWidth: '640px'
          },
          '@screen md': {
            maxWidth: '768px',
            paddingLeft: '2.5rem',
            paddingRight: '2.5rem'
          },
          '@screen lg': {
            maxWidth: '1077px'
          },
          '@screen xl': {
            maxWidth: '1076px',
            paddingLeft: '0',
            paddingRight: '0'
          },
          '@screen 2xl': {
            maxWidth: '1400px'
          }
        }
      });
    }
  ]
};
