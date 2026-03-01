/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './templates/**/*.twig',
    './src/**/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      keyframes: {
        'fade-in-up': {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        'fade-in-up': 'fade-in-up 0.5s ease-out forwards',
      },
      colors: {
        pastel: {
          bg: '#FDF8F5',
          cream: '#FFF8F0',
          mint: '#A8BBA3',
          'mint-soft': '#D4E5D0',
          lavender: '#CDB4DB',
          'lavender-soft': '#E8DCF0',
          peach: '#F0D5C8',
          'peach-soft': '#FAEDE8',
          sky: '#B5D4E8',
          'sky-soft': '#DFEEF5',
          apricot: '#F5D0C5',
          text: '#5C5C5C',
          'text-strong': '#3D3D3D',
          border: '#E8DED5',
        },
      },
      boxShadow: {
        pastel: '0 2px 8px rgb(0 0 0 / 0.04)',
        'pastel-md': '0 4px 16px rgb(0 0 0 / 0.06)',
      },
    },
  },
  plugins: [],
};
