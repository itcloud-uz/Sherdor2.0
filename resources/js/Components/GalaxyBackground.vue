<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const canvasRef = ref(null);
let animationFrameId = null;

onMounted(() => {
    const canvas = canvasRef.value;
    const ctx = canvas.getContext('2d');
    
    let width = window.innerWidth;
    let height = window.innerHeight;
    canvas.width = width;
    canvas.height = height;

    const numStars = 1000;
    const stars = [];

    // Initialize stars
    for (let i = 0; i < numStars; i++) {
        stars.push({
            x: Math.random() * width,
            y: Math.random() * height,
            z: Math.random() * width,
            o: Math.random(),
            size: Math.random() * 1.5
        });
    }

    let centerX = width / 2;
    let centerY = height / 2;

    const handleResize = () => {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
        centerX = width / 2;
        centerY = height / 2;
    };

    window.addEventListener('resize', handleResize);

    const animate = () => {
        // Clear with a very slight trailing effect for motion blur
        ctx.fillStyle = '#05050A'; // Paint deep space abyss color
        ctx.fillRect(0, 0, width, height);

        // Motion blur effect
        ctx.fillStyle = 'rgba(5, 5, 10, 0.4)';
        ctx.fillRect(0, 0, width, height);

        for (let i = 0; i < numStars; i++) {
            let star = stars[i];

            // Move stars towards the camera (decrease Z)
            star.z -= 0.5;

            // Reset star if it gets too close
            if (star.z <= 0) {
                star.x = Math.random() * width;
                star.y = Math.random() * height;
                star.z = width;
            }

            // Calculate 3D to 2D projection
            const k = 128.0 / star.z;
            const px = (star.x - centerX) * k + centerX;
            const py = (star.y - centerY) * k + centerY;

            // Opacity increases as it gets closer
            const opacity = (1 - star.z / width) * star.o;

            if (px >= 0 && px <= width && py >= 0 && py <= height) {
                const size = star.size * k;
                
                // Get theme color from Inertia page props (more efficient than getComputedStyle in a loop)
                const themeColor = usePage().props.branding?.primary_theme_color || '#FFFFFF';
                
                ctx.beginPath();
                ctx.arc(px, py, size, 0, Math.PI * 2);
                ctx.fillStyle = themeColor;
                ctx.globalAlpha = opacity;
                ctx.fill();
                
                // Subtle colored glow
                ctx.shadowBlur = size * 3;
                ctx.shadowColor = themeColor;
                ctx.globalAlpha = 1.0; // reset
            }
        }

        animationFrameId = requestAnimationFrame(animate);
    };

    animate();

    onUnmounted(() => {
        window.removeEventListener('resize', handleResize);
        cancelAnimationFrame(animationFrameId);
    });
});
</script>

<template>
    <canvas 
        ref="canvasRef" 
        class="fixed inset-0 pointer-events-none z-[-50]"
    ></canvas>
</template>
