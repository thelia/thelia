module.exports = {
  content: [
    './**/*.html',
    './**/*.tpl',
    './components/**/*.css',
    '!(node_modules)/**/*.js'
  ],

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
        info: '#E6F1FA',
        vermillon: '#DC3018',
        lightVermillon: '#FA533C',
        lighterVermillon: '#FF8F80',
        greyMedium: '#9B9B9B',
        pearlMedium: '#EBEBEB',
        greyDark: '#787878',
        white: '#FFFFFF',
        red: '#D21919',
        redLight: '#ED5656',
        backgroundRed: '#FFEDED',
        greenDark: '#008958',
        greenLight: '#EBFFF8',
        ocre: '#9E6700',
        mauve: '#8C5383',
        turquoise: '#0E7C7B',
        electricBlue: '#3423A6',
        darkVermillon: '#C4311C',
        ocreLight: '#FEFAF1',
        mauveLight: '#EBE2EA',
        turquoiseLight: '#ECF8F8',
        electricBlueLight: '#EEECFF',
        backgroundLightVermillon: '#F7EEED'
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
