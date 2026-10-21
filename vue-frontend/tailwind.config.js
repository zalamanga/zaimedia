export default {
  content: ['./index.html', './src/**/*.{vue,js,ts}'],
  theme: {
    extend: {
      colors: {
        ink: '#17211b',
        paper: '#fbf8ff',
        leaf: '#7c3aed',
        berry: '#a855f7',
        sky: '#38bdf8',
        mint: '#34d399',
        honey: '#f59e0b',
      },
      boxShadow: {
        soft: '0 18px 60px rgba(88, 28, 135, 0.12)',
        glow: '0 18px 60px rgba(124, 58, 237, 0.24)',
      },
      keyframes: {
        floaty: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-8px)' },
        },
        popIn: {
          '0%': { opacity: '0', transform: 'translateY(12px) scale(0.98)' },
          '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
        },
      },
      animation: {
        floaty: 'floaty 5s ease-in-out infinite',
        popIn: 'popIn 420ms ease-out both',
      },
    },
  },
  plugins: [],
};
