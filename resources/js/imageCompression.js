// Resizes and re-encodes an image file in the browser before upload. Phone
// camera photos can exceed AWS Lambda's hard 10MB request payload limit, so
// files must be shrunk client-side — compressing after upload is too late.
export function compressImageFile(file, { maxDimension = 1920, quality = 0.8 } = {}) {
    return new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);

            let { width, height } = img;
            if (width > maxDimension || height > maxDimension) {
                if (width > height) {
                    height = Math.round((height * maxDimension) / width);
                    width = maxDimension;
                } else {
                    width = Math.round((width * maxDimension) / height);
                    height = maxDimension;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        reject(new Error('Image compression produced no data'));
                        return;
                    }
                    resolve(new File(
                        [blob],
                        file.name.replace(/\.\w+$/, '.jpg'),
                        { type: 'image/jpeg' }
                    ));
                },
                'image/jpeg',
                quality
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Failed to load image for compression'));
        };

        img.src = objectUrl;
    });
}

// Compresses each file, falling back to the original file if compression
// fails for a particular image (e.g. an unsupported format).
export async function compressImageFiles(files, options) {
    return Promise.all(
        Array.from(files).map((file) =>
            compressImageFile(file, options).catch(() => file)
        )
    );
}
