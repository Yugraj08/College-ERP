/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./views/**/*.{php,html,js}",
    "./public/**/*.{php,html,js}",
    "./src/**/*.{php,html,js}"
  ],
  theme: {
    extend: {
      colors: {
        college: {
          maroon: '#800000',
          'maroon-dark': '#5C0000',
          navy: '#002B5C',
          'navy-dark': '#001836',
          gold: '#D4AF37',
          'gold-light': '#F5E6BE'
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif']
      }
    },
  },
  plugins: [],
}
