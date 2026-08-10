// Renders the user's crop/zoom selection (react-easy-crop's pixel crop
// rectangle) onto a fixed-size canvas and re-encodes it as a JPEG - the
// output is already the exact avatar size, so no separate compression pass
// (see imageCompression.js, used for full-size job/note photos) is needed.
export function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Failed to load image'));
        img.src = src;
    });
}

export async function getCroppedImageFile(imageSrc, pixelCrop, { outputSize = 512, quality = 0.85 } = {}) {
    const img = await loadImage(imageSrc);

    const canvas = document.createElement('canvas');
    canvas.width = outputSize;
    canvas.height = outputSize;
    const ctx = canvas.getContext('2d');

    ctx.drawImage(
        img,
        pixelCrop.x, pixelCrop.y, pixelCrop.width, pixelCrop.height,
        0, 0, outputSize, outputSize
    );

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Crop produced no image data'));
                    return;
                }
                resolve(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
            },
            'image/jpeg',
            quality
        );
    });
}
