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
    maxWidth: {
      80: '80%'
    },
    minWidth: {
      80: '80%'
    },
    extend: {
      colors: {
        main: '#EC6351',
        darkMain: '#E25441',
        lightCharbon: '#444444',
        mediumCharbon: '#333333',
        darkCharbon: '#222222',
        grey: '#707070',
        mediumGrey: '#A7A7A7',
        lightGrey: '#EBEBEB',
        lightPearl: '#F7F7F7',
        mediumPearl: '#F5F5F5',
        carot: '#FC9722',
        green: '#73CE6F',
        blue: '#1E9AE8',
        error: '#D10000',
        danger: '#FDE2E2',
        success: '#D1FAE5',
        info: '#E6F1FA'
      }
    }
  },
  variants: {
    extend: {
      backgroundColor: ['odd', 'even'],
      display: ['group-hover', 'group-focus']
    }
  },
  plugins: [require('@tailwindcss/forms')]
};
