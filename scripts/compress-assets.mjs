// `npm run build`-ийн дараа public/build доторх CSS, JS-ийн урьдчилан шахсан (.gz) хувилбарыг үүсгэнэ.
// Apache (XAMPP)-д mod_deflate унтраалттай тул public/.htaccess хөтөч gzip дэмждэг бол .gz файлыг өгнө.
import { readdirSync, readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync, constants } from 'node:zlib';

const dir = fileURLToPath(new URL('../public/build/assets/', import.meta.url));

// 1) Өмнөх build-ийн .gz үлдэгдлийг устгана
for (const name of readdirSync(dir)) {
    if (name.endsWith('.gz')) unlinkSync(join(dir, name));
}

// 2) Шинээр шахна
let before = 0;
let after = 0;
for (const name of readdirSync(dir)) {
    if (!/\.(css|js|svg)$/.test(name)) continue;
    const data = readFileSync(join(dir, name));
    const gz = gzipSync(data, { level: constants.Z_BEST_COMPRESSION });
    writeFileSync(join(dir, name + '.gz'), gz);
    before += data.length;
    after += gz.length;
}

console.log(`gzip: ${(before / 1024).toFixed(1)} KB → ${(after / 1024).toFixed(1)} KB`);
