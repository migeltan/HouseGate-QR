// One-off image optimizer. Run once from the project root:
//   npm i --no-save sharp
//   node scripts/optimize-images.mjs
// Safe to re-run: anything already small enough is skipped.
import sharp from "sharp";
import { readFile, writeFile, access } from "node:fs/promises";
import path from "node:path";

const dir = "public/images";

// Logos keep their file names (views and the directory service worker reference them): downscale only.
const LOGOS = {
    "lsb-seal.png": 320,
    "inspire-logo.png": 320,
    "hrep-seal.png": 320,
};

// Building photos become 720px-wide JPEGs (a card shows them at roughly 300px).
const PHOTOS = ["northwing", "southwing", "rvm", "northgate", "main"];

const kb = (n) => Math.round(n / 1024) + " KB";
const exists = async (p) => {
    try {
        await access(p);
        return true;
    } catch {
        return false;
    }
};

for (const [file, size] of Object.entries(LOGOS)) {
    const p = path.join(dir, file);
    if (!(await exists(p))) {
        console.log(`skip ${file} (not found)`);
        continue;
    }
    const input = await readFile(p);
    const meta = await sharp(input).metadata();
    if (Math.max(meta.width, meta.height) <= size) {
        console.log(`skip ${file} (already ${meta.width}x${meta.height})`);
        continue;
    }
    const out = await sharp(input)
        .resize({
            width: size,
            height: size,
            fit: "inside",
            withoutEnlargement: true,
        })
        .png({ compressionLevel: 9, effort: 10 })
        .toBuffer();
    await writeFile(p, out);
    console.log(`${file}: ${kb(input.length)} -> ${kb(out.length)}`);
}

for (const name of PHOTOS) {
    const src = path.join(dir, "buildings", `${name}.png`);
    const dest = path.join(dir, "buildings", `${name}.jpg`);
    if (!(await exists(src))) {
        console.log(`skip ${name} (no .png)`);
        continue;
    }
    const input = await readFile(src);
    const out = await sharp(input)
        .resize({ width: 720, withoutEnlargement: true })
        .jpeg({ quality: 78, mozjpeg: true })
        .toBuffer();
    await writeFile(dest, out);
    console.log(
        `${name}.png -> ${name}.jpg: ${kb(input.length)} -> ${kb(out.length)}`,
    );
}
