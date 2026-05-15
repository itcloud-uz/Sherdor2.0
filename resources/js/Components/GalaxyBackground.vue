<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    interactive: {
        type: Boolean,
        default: false
    }
});

const canvasRef = ref(null);
let animationFrameId = null;
let mouseX = 0;
let mouseY = 0;
let targetCenterX = window.innerWidth / 2;
let targetCenterY = window.innerHeight / 2;

onMounted(() => {
    const canvas = canvasRef.value;
    const ctx = canvas.getContext('2d');
    
    let width = window.innerWidth;
    let height = window.innerHeight;
    canvas.width = width;
    canvas.height = height;

    const numStars = 800;
    const stars = [];
    const colors = ['#00F0FF', '#B026FF', '#FFD700', '#FFFFFF']; // Cyan, Purple, Gold, White

    // Initialize stars
    for (let i = 0; i < numStars; i++) {
        stars.push({
            x: Math.random() * width,
            y: Math.random() * height,
            z: Math.random() * width,
            o: Math.random() + 0.2, // brighter
            size: Math.random() * 2.5, // larger
            color: colors[Math.floor(Math.random() * colors.length)]
        });
    }

    let centerX = width / 2;
    let centerY = height / 2;
    mouseX = centerX;
    mouseY = centerY;

    const handleResize = () => {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
        if (!props.interactive) {
            centerX = width / 2;
            centerY = height / 2;
        }
    };

    const handleMouseMove = (e) => {
        if (props.interactive) {
            mouseX = e.clientX;
            mouseY = e.clientY;
        }
    };

    window.addEventListener('resize', handleResize);
    window.addEventListener('mousemove', handleMouseMove);

    const animate = () => {
        // Clear with a very slight trailing effect for motion blur
        ctx.fillStyle = '#05050A'; // Paint deep space abyss color
        ctx.fillRect(0, 0, width, height);

        // Motion blur effect
        ctx.fillStyle = 'rgba(5, 5, 10, 0.4)';
        ctx.fillRect(0, 0, width, height);

        if (props.interactive) {
            // Parallax effect: shift the center point smoothly towards the mouse
            targetCenterX = width / 2 + (mouseX - width / 2) * 0.5;
            targetCenterY = height / 2 + (mouseY - height / 2) * 0.5;
            centerX += (targetCenterX - centerX) * 0.05;
            centerY += (targetCenterY - centerY) * 0.05;
        }

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
                
                ctx.beginPath();
                ctx.arc(px, py, size, 0, Math.PI * 2);
                ctx.fillStyle = star.color;
                ctx.globalAlpha = opacity > 1 ? 1 : opacity;
                ctx.fill();
                
                ctx.globalAlpha = 1.0; // reset
            }
        }

        animationFrameId = requestAnimationFrame(animate);
    };

    animate();

    onUnmounted(() => {
        window.removeEventListener('resize', handleResize);
        window.removeEventListener('mousemove', handleMouseMove);
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
