/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                abyss: '#05050A',
                glass: 'rgba(255, 255, 255, 0.05)',
                'glass-hover': 'rgba(255, 255, 255, 0.1)',
                'glass-border': 'rgba(255, 255, 255, 0.1)',
                'glass-border-hover': 'rgba(255, 255, 255, 0.2)',
                'neon-primary': 'var(--color-neon-primary)', // Dynamic
                'neon-cyan': '#00F0FF',
                'neon-yellow': '#FCEE09',
                'neon-green': '#39FF14',
                'neon-red': '#FF2A2A',
                'neon-blue': '#0066FF',
                'neon-magenta': '#FF003C',
                'neon-purple': '#B026FF',
                'text-primary': '#FFFFFF',
                'text-secondary': 'rgba(255, 255, 255, 0.7)',
                'text-muted': 'rgba(255, 255, 255, 0.4)',
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
                display: ['Outfit', 'system-ui', 'sans-serif'],
                mono: ['JetBrains Mono', 'monospace'],
            },
            boxShadow: {
                'neon-primary': 'var(--shadow-neon-primary)', // Dynamic
                'neon-cyan': '0 0 15px rgba(0, 240, 255, 0.3)',
                'neon-green': '0 0 15px rgba(57, 255, 20, 0.3)',
                'neon-magenta': '0 0 15px rgba(255, 0, 60, 0.3)',
                'neon-yellow': '0 0 15px rgba(252, 238, 9, 0.3)',
                'depth': '0 10px 30px -10px rgba(0, 0, 0, 0.5)',
            }
        },
    },
    plugins: [require('@tailwindcss/forms')],
};
