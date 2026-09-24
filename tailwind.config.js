/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#eef6ff',
                    100: '#d9eaff',
                    200: '#bbdcff',
                    300: '#8cc5ff',
                    400: '#55a4ff',
                    500: '#2b7fff',
                    600: '#0058e6',
                    700: '#0043bd',
                    800: '#03399a',
                    900: '#0a3279',
                    950: '#061d4b',
                },
                accent: {
                    500: '#8b5cf6',
                    600: '#7c3aed',
                }
            },
            backdropBlur: {
                xs: '2px',
            },
            boxShadow: {
                'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.08)',
                'glass-dark': '0 8px 32px 0 rgba(0, 0, 0, 0.37)',
            }
        },
    },
    plugins: [],
};
