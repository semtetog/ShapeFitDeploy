/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      // === CORES PRINCIPAIS ===
      colors: {
        // Background System
        background: {
          primary: '#000000',    // Preto puro
          secondary: '#111111',  // Preto suave
          tertiary: '#1a1a1a',  // Preto médio
        },
        
        // Surface System (Glassmorphism)
        surface: {
          glass: 'rgba(20, 20, 20, 0.8)',
          card: 'rgba(25, 25, 25, 0.9)',
          hover: 'rgba(35, 35, 35, 0.95)',
        },
        
        // Text System
        text: {
          light: '#FFFFFF',      // Branco puro
          primary: '#F4F4F5',   // Branco suave
          secondary: '#A1A1AA',  // Cinza claro
          muted: '#71717A',     // Cinza médio
        },
        
        // Primary Accent
        primary: {
          DEFAULT: '#FF6C00',    // Laranja vibrante
          hover: '#EA580C',      // Laranja hover
          light: 'rgba(255, 108, 0, 0.1)', // Laranja translúcido
        },
        
        // State Colors
        success: {
          DEFAULT: '#10B981',
          light: 'rgba(16, 185, 129, 0.1)',
        },
        warning: {
          DEFAULT: '#F59E0B',
          light: 'rgba(245, 158, 11, 0.1)',
        },
        danger: {
          DEFAULT: '#EF4444',
          light: 'rgba(239, 68, 68, 0.1)',
        },
        info: {
          DEFAULT: '#3B82F6',
          light: 'rgba(59, 130, 246, 0.1)',
        },
        
        // Border System
        border: {
          DEFAULT: 'rgba(255, 255, 255, 0.1)',
          hover: 'rgba(255, 255, 255, 0.2)',
          focus: '#FF6C00',
        }
      },
      
      // === TIPOGRAFIA ===
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        display: ['Sora', 'Inter', 'system-ui', 'sans-serif'],
      },
      
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],      // 12px
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],  // 14px
        'base': ['1rem', { lineHeight: '1.5rem' }],     // 16px
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],   // 18px
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],   // 20px
        '2xl': ['1.5rem', { lineHeight: '2rem' }],      // 24px
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],  // 30px
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],    // 36px
      },
      
      // === ESPAÇAMENTO ===
      spacing: {
        '1': '0.25rem',   // 4px
        '2': '0.5rem',    // 8px
        '3': '0.75rem',   // 12px
        '4': '1rem',      // 16px
        '5': '1.25rem',   // 20px
        '6': '1.5rem',    // 24px
        '8': '2rem',      // 32px
        '10': '2.5rem',   // 40px
        '12': '3rem',     // 48px
        '16': '4rem',     // 64px
      },
      
      // === BORDAS ARREDONDADAS ===
      borderRadius: {
        'sm': '0.375rem',   // 6px
        'md': '0.5rem',     // 8px
        'lg': '0.75rem',    // 12px
        'xl': '1rem',       // 16px
        '2xl': '1.5rem',    // 24px
      },
      
      // === SOMBRAS ===
      boxShadow: {
        'sm': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        'md': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
        'lg': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        'xl': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
        'glow': '0 0 20px rgba(255, 108, 0, 0.3)',
        'glow-lg': '0 0 40px rgba(255, 108, 0, 0.4)',
      },
      
      // === TRANSIÇÕES ===
      transitionDuration: {
        'fast': '150ms',
        'normal': '200ms',
        'slow': '300ms',
      },
      
      // === BACKDROP BLUR ===
      backdropBlur: {
        'xs': '2px',
        'sm': '4px',
        'md': '8px',
        'lg': '16px',
        'xl': '24px',
        '2xl': '40px',
      },
      
      // === ANIMAÇÕES ===
      animation: {
        'fade-in': 'fadeIn 0.3s ease-out',
        'slide-in': 'slideIn 0.3s ease-out',
        'scale-in': 'scaleIn 0.2s ease-out',
        'glow': 'glow 2s ease-in-out infinite alternate',
        'float': 'float 3s ease-in-out infinite',
      },
      
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideIn: {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.9)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        glow: {
          '0%': { boxShadow: '0 0 20px rgba(255, 108, 0, 0.3)' },
          '100%': { boxShadow: '0 0 40px rgba(255, 108, 0, 0.6)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
      },
      
      // === GRADIENTES ===
      backgroundImage: {
        'gradient-primary': 'linear-gradient(135deg, #FF6C00 0%, #EA580C 100%)',
        'gradient-surface': 'linear-gradient(135deg, rgba(25, 25, 25, 0.9) 0%, rgba(35, 35, 35, 0.95) 100%)',
        'gradient-glass': 'linear-gradient(135deg, rgba(20, 20, 20, 0.8) 0%, rgba(30, 30, 30, 0.9) 100%)',
      },
    },
  },
  plugins: [
    // Plugin para glassmorphism
    function({ addUtilities }) {
      const newUtilities = {
        '.glass': {
          background: 'rgba(25, 25, 25, 0.9)',
          backdropFilter: 'blur(20px)',
          WebkitBackdropFilter: 'blur(20px)',
          border: '1px solid rgba(255, 255, 255, 0.1)',
        },
        '.glass-light': {
          background: 'rgba(35, 35, 35, 0.8)',
          backdropFilter: 'blur(16px)',
          WebkitBackdropFilter: 'blur(16px)',
          border: '1px solid rgba(255, 255, 255, 0.15)',
        },
        '.glass-strong': {
          background: 'rgba(15, 15, 15, 0.95)',
          backdropFilter: 'blur(24px)',
          WebkitBackdropFilter: 'blur(24px)',
          border: '1px solid rgba(255, 255, 255, 0.2)',
        },
        '.text-gradient': {
          background: 'linear-gradient(135deg, #FF6C00 0%, #EA580C 100%)',
          WebkitBackgroundClip: 'text',
          WebkitTextFillColor: 'transparent',
          backgroundClip: 'text',
        },
        '.border-gradient': {
          border: '1px solid transparent',
          background: 'linear-gradient(135deg, rgba(25, 25, 25, 0.9), rgba(25, 25, 25, 0.9)) padding-box, linear-gradient(135deg, #FF6C00, #EA580C) border-box',
        },
      }
      addUtilities(newUtilities)
    },
    
    // Plugin para scrollbar personalizada
    function({ addUtilities }) {
      const scrollbarUtilities = {
        '.scrollbar-thin': {
          '&::-webkit-scrollbar': {
            width: '8px',
            height: '8px',
          },
          '&::-webkit-scrollbar-track': {
            background: '#111111',
          },
          '&::-webkit-scrollbar-thumb': {
            background: 'rgba(255, 255, 255, 0.1)',
            borderRadius: '0.5rem',
          },
          '&::-webkit-scrollbar-thumb:hover': {
            background: 'rgba(255, 255, 255, 0.2)',
          },
        },
      }
      addUtilities(scrollbarUtilities)
    },
  ],
}
